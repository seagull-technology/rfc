param(
    [Parameter(Mandatory = $true)]
    [string] $ArchivePath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-fA-F0-9]{64}$')]
    [string] $ExpectedSha256,

    [string] $SiteName = "RFC",
    [string] $AppPath = "C:\inetpub\rfc",
    [string] $PhpExe = "C:\php\php.exe",
    [string] $QueueServiceName = "RFCQueueWorker",
    [string] $SchedulerTaskName = "RFC Laravel Scheduler",
    # Existing releases should preserve role/reference records unless the
    # deployment explicitly includes a reviewed access-control seed change.
    [switch] $SeedAccessControl,
    [ValidateRange(30, 900)]
    [int] $WorkerDrainSeconds = 180
)

$ErrorActionPreference = "Stop"
$ArchivePath = (Resolve-Path -LiteralPath $ArchivePath).Path
$timestamp = (Get-Date -Format "yyyyMMdd-HHmmss") + "-" + [Guid]::NewGuid().ToString("N").Substring(0, 8)
$parentPath = Split-Path $AppPath -Parent
$stagePath = Join-Path $parentPath "rfc-stage-$timestamp"
$backupPath = Join-Path $parentPath "rfc-backup-$timestamp"
$failedPath = Join-Path $parentPath "rfc-failed-$timestamp"
$swapped = $false
$oldReleaseMoved = $false
$maintenanceEnabled = $false
$runtimeChanged = $false
$databaseUpgradeStarted = $false
$appPoolName = $null
$schedulerTask = $null
$schedulerWasEnabled = $false
$maintenanceSecret = [Guid]::NewGuid().ToString("N")

# Windows cannot rename an application directory while the shell or an Explorer
# window is positioned inside it. Keep the deployment shell at the parent path.
Set-Location $parentPath

function Invoke-PhpArtisan {
    param(
        [string] $WorkingPath,
        [Parameter(ValueFromRemainingArguments = $true)]
        [string[]] $Arguments
    )

    Push-Location $WorkingPath

    try {
        & $PhpExe artisan @Arguments

        if ($LASTEXITCODE -ne 0) {
            throw "Artisan command failed: php artisan $($Arguments -join ' ')"
        }
    }
    finally {
        Pop-Location
    }
}

function Get-DotEnvValue {
    param(
        [string] $EnvPath,
        [string] $Name
    )

    $contents = [System.IO.File]::ReadAllText($EnvPath)
    $pattern = "(?m)^\s*" + [regex]::Escape($Name) + "\s*=\s*(.*)\s*$"
    $match = [regex]::Match($contents, $pattern)

    if (-not $match.Success) {
        return $null
    }

    return $match.Groups[1].Value.Trim().Trim([char] 34).Trim([char] 39)
}

function Assert-ApplicationIsNotInMaintenance {
    Push-Location $AppPath

    try {
        $output = (& $PhpExe artisan about --only=environment --json) -join [Environment]::NewLine

        if ($LASTEXITCODE -ne 0) {
            throw "Could not read the existing application's maintenance state."
        }

        $maintenanceState = ($output | ConvertFrom-Json).environment.maintenance_mode

        if ($null -eq $maintenanceState) {
            throw "Existing application did not report its maintenance state."
        }

        if ($maintenanceState) {
            throw "The application is already in maintenance mode. Resolve that maintenance window before running this deployment; its existing state will not be overwritten."
        }
    }
    finally {
        Pop-Location
    }
}

function Assert-SessionCookieConfiguration {
    param([string] $EnvPath)

    $appUrl = Get-DotEnvValue $EnvPath "APP_URL"
    $secureCookie = Get-DotEnvValue $EnvPath "SESSION_SECURE_COOKIE"
    $secureEnabled = $secureCookie -and @("1", "true", "yes", "on").Contains($secureCookie.ToLowerInvariant())

    if ($appUrl -and $appUrl.StartsWith("http://", [System.StringComparison]::OrdinalIgnoreCase) -and $secureEnabled) {
        throw "APP_URL uses HTTP while SESSION_SECURE_COOKIE=true. This causes every form submission to fail with HTTP 419. Set SESSION_SECURE_COOKIE=false until HTTPS is enabled."
    }
}

function Assert-PhpSecurityConfiguration {
    $exposePhp = [string] (& $PhpExe -r "echo ini_get('expose_php') ? '1' : '0';")

    if ($LASTEXITCODE -ne 0) {
        throw "Could not inspect the PHP security configuration with $PhpExe."
    }

    if ($exposePhp.Trim() -ne "0") {
        throw "PHP expose_php must be Off in php.ini so responses do not disclose the PHP version."
    }
}

function Convert-PhpIniSizeToBytes {
    param([string] $Value)

    $normalized = $Value.Trim()

    if ($normalized -notmatch "^(\d+(?:\.\d+)?)\s*([KMG]?)$") {
        throw "Unsupported PHP size value: $Value"
    }

    $multiplier = switch ($Matches[2].ToUpperInvariant()) {
        "K" { 1KB }
        "M" { 1MB }
        "G" { 1GB }
        default { 1 }
    }

    return [int64] [Math]::Floor(([double] $Matches[1]) * $multiplier)
}

function Assert-PhpUploadConfiguration {
    $settings = [string] (& $PhpExe -r "echo ini_get('upload_max_filesize') . '|' . ini_get('post_max_size');")

    if ($LASTEXITCODE -ne 0) {
        throw "Could not inspect the PHP upload configuration with $PhpExe."
    }

    $values = $settings.Trim().Split("|")

    if ($values.Count -ne 2) {
        throw "Could not parse PHP upload_max_filesize and post_max_size."
    }

    $uploadBytes = Convert-PhpIniSizeToBytes $values[0]
    $postBytes = Convert-PhpIniSizeToBytes $values[1]

    if ($uploadBytes -lt 10MB) {
        throw "PHP upload_max_filesize must be at least 10M so valid registration documents are not rejected."
    }

    if ($postBytes -lt 16MB) {
        throw "PHP post_max_size must be at least 16M so a registration document and optional logo fit in one request."
    }
}

function Show-PhpPerformanceWarning {
    $opcacheEnabled = [string] (& $PhpExe -r "echo (extension_loaded('Zend OPcache') && (bool) ini_get('opcache.enable')) ? '1' : '0';")

    if ($LASTEXITCODE -ne 0 -or $opcacheEnabled.Trim() -ne "1") {
        Write-Warning "PHP OPcache is not enabled. Deployment can continue, but PHP responses will be slower until OPcache is enabled in C:\php\php.ini and the IIS application pool is restarted."
    }
}

function Start-RfcSite {
    $lastError = $null

    for ($attempt = 1; $attempt -le 10; $attempt++) {
        try {
            if ($appPoolName) {
                $poolState = (Get-WebAppPoolState -Name $appPoolName).Value

                if ($poolState -ne "Started") {
                    Start-WebAppPool -Name $appPoolName
                }
            }

            $siteState = (Get-WebsiteState -Name $SiteName).Value

            if ($siteState -ne "Started") {
                Start-Website -Name $SiteName
            }

            return
        }
        catch {
            $lastError = $_
            Start-Sleep -Seconds 2
        }
    }

    throw "IIS could not be started after waiting for service control to settle: $($lastError.Exception.Message)"
}

function New-RfcIisServerManager {
    # WebAdministration is already required for this deployment. Load its IIS
    # management API explicitly so no native command exit code is involved.
    # https://learn.microsoft.com/en-us/dotnet/api/microsoft.web.administration.servermanager.workerprocesses
    Add-Type -Path (Join-Path $env:windir "System32\inetsrv\Microsoft.Web.Administration.dll") -ErrorAction Stop
    return New-Object -TypeName Microsoft.Web.Administration.ServerManager -ErrorAction Stop
}

function Get-RfcIisWorkerIds {
    param([string] $PoolName)

    if ([string]::IsNullOrWhiteSpace($PoolName)) { throw "An exact IIS application pool name is required." }
    $manager = $null

    try {
        # A fresh manager avoids using a worker collection cached before Stop.
        $manager = New-RfcIisServerManager
        # Invoke .NET getters explicitly: PowerShell property syntax can turn a
        # getter exception into $null, which must never mean "no workers" here.
        $pools = $manager.get_ApplicationPools()
        if ($null -eq $pools -or $null -eq $pools[$PoolName]) {
            throw "IIS application pool '$PoolName' was not found."
        }

        $workers = $manager.get_WorkerProcesses()
        if ($null -eq $workers) { throw "IIS did not return a worker collection." }
        $workerIds = @(foreach ($worker in $workers) {
            if ([string]::Equals($worker.get_AppPoolName(), $PoolName, [StringComparison]::OrdinalIgnoreCase)) {
                $workerId = [int] $worker.get_ProcessId()
                if ($workerId -le 0) { throw "IIS returned an invalid worker process ID for '$PoolName'." }
                $workerId
            }
        })
        return $workerIds
    }
    catch {
        throw "Could not enumerate IIS workers for '$PoolName': $($_.Exception.Message)"
    }
    finally {
        if ($null -ne $manager) { $manager.Dispose() }
    }
}

function Stop-RfcSite {
    if ((Get-WebsiteState -Name $SiteName).Value -eq "Started") {
        Stop-Website -Name $SiteName
    }

    if ($appPoolName -and (Get-WebAppPoolState -Name $appPoolName).Value -eq "Started") {
        Stop-WebAppPool -Name $appPoolName
    }

    $deadline = (Get-Date).AddSeconds($WorkerDrainSeconds)

    do {
        $siteStopped = (Get-WebsiteState -Name $SiteName).Value -eq "Stopped"
        $poolStopped = -not $appPoolName -or (Get-WebAppPoolState -Name $appPoolName).Value -eq "Stopped"
        $workerIds = @()

        if ($appPoolName) {
            $workerIds = @(Get-RfcIisWorkerIds $appPoolName)
        }

        if ($siteStopped -and $poolStopped -and $workerIds.Count -eq 0) {
            return
        }

        Start-Sleep -Seconds 2
    } while ((Get-Date) -lt $deadline)

    throw "IIS did not fully stop within $WorkerDrainSeconds seconds."
}

function Rename-DirectoryWithRetry {
    param(
        [Parameter(Mandatory = $true)]
        [string] $Source,

        [Parameter(Mandatory = $true)]
        [string] $Destination
    )

    $lastError = $null

    for ($attempt = 1; $attempt -le 15; $attempt++) {
        try {
            Rename-Item -LiteralPath $Source -NewName (Split-Path $Destination -Leaf)
            return
        }
        catch {
            $lastError = $_
            Start-Sleep -Seconds 2
        }
    }

    throw "Could not rename '$Source' after 30 seconds. Close shells, editors, and Explorer windows using this directory. Last error: $($lastError.Exception.Message)"
}

function Close-AppExplorerWindows {
    $shell = New-Object -ComObject Shell.Application

    try {
        foreach ($window in @($shell.Windows())) {
            try {
                $windowPath = $window.Document.Folder.Self.Path

                if ($windowPath -and $windowPath.StartsWith($AppPath, [System.StringComparison]::OrdinalIgnoreCase)) {
                    $window.Quit()
                }
            }
            catch {
                # Ignore non-Explorer shell windows that do not expose a folder path.
            }
        }
    }
    finally {
        [Runtime.InteropServices.Marshal]::FinalReleaseComObject($shell) | Out-Null
    }
}

function Copy-RfcStorage {
    param([string] $SourceApp, [string] $DestinationApp, [switch] $Mirror)

    $source = Join-Path $SourceApp "storage"
    $destination = Join-Path $DestinationApp "storage"

    if (-not (Test-Path -LiteralPath $source -PathType Container)) {
        throw "Storage source does not exist: $source"
    }

    New-Item -ItemType Directory -Force $destination | Out-Null
    # /E /PURGE mirrors deletions like /MIR while preserving the destination
    # directory ACLs granted to IIS and the dedicated queue service identity.
    $copyOptions = @("/E")
    if ($Mirror) { $copyOptions += "/PURGE" }
    & robocopy.exe $source $destination @copyOptions /COPY:DAT /DCOPY:DAT /XJ /R:2 /W:2 /NFL /NDL /NP

    if ($LASTEXITCODE -gt 7) {
        throw "Storage copy failed with robocopy exit code $LASTEXITCODE."
    }
}

function Assert-ReleaseArchive {
    param([string] $Path, [string] $Expected)

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Release archive was not found: $Path"
    }

    $actual = (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash

    if (-not $actual.Equals($Expected, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Release archive SHA-256 does not match the expected checksum. No release files were extracted."
    }
}

function Move-StagedReleaseIntoPlace {
    Rename-DirectoryWithRetry $AppPath $backupPath
    $script:oldReleaseMoved = $true
    Rename-DirectoryWithRetry $stagePath $AppPath
    $script:swapped = $true
}

function Restore-PreviousReleaseFiles {
    if (-not $oldReleaseMoved) {
        return
    }

    if (-not (Test-Path -LiteralPath $backupPath -PathType Container)) {
        throw "Rollback backup is missing: $backupPath"
    }

    if (Test-Path -LiteralPath $AppPath -PathType Container) {
        # Carry forward uploads, sessions, logs and other runtime files even when
        # the new release is rolled back. Never restore an older storage snapshot.
        Copy-RfcStorage $AppPath $backupPath -Mirror
        Rename-DirectoryWithRetry $AppPath $failedPath
    }

    Rename-DirectoryWithRetry $backupPath $AppPath
    $script:oldReleaseMoved = $false
    $script:swapped = $false
}

function Get-ServiceWorkerProcesses {
    $service = Get-CimInstance Win32_Service -Filter ("Name='" + $QueueServiceName.Replace("'", "''") + "'")

    if (-not $service -or $service.ProcessId -eq 0) {
        return
    }

    $allProcesses = @(Get-CimInstance Win32_Process)
    $processIds = @([uint32] $service.ProcessId)
    $visitedIds = $processIds
    $descendants = @()

    while ($processIds.Count -gt 0) {
        $children = @($allProcesses | Where-Object { $processIds -contains $_.ParentProcessId -and $visitedIds -notcontains $_.ProcessId })
        $descendants += $children
        $processIds = @($children | ForEach-Object { [uint32] $_.ProcessId })
        $visitedIds += $processIds
    }

    @($allProcesses | Where-Object { $_.ProcessId -eq $service.ProcessId }) + $descendants |
        Where-Object { $_.CommandLine -match 'queue:work(?:\s|"|$)' }
}

function Resolve-WindowsAccountSid {
    param([string] $AccountName)

    $account = New-Object System.Security.Principal.NTAccount($AccountName)
    $account.Translate([System.Security.Principal.SecurityIdentifier]).Value
}

function Resolve-QueueServiceAccountSid {
    param([object] $Service)

    if (-not $Service -or [string]::IsNullOrWhiteSpace($Service.StartName)) {
        throw "Cannot determine the logon account for queue service '$QueueServiceName'."
    }

    $accountName = $Service.StartName.Trim()

    # Win32_Service uses aliases for built-in accounts. Resolve those without
    # depending on the server's localized display names.
    switch -Regex ($accountName) {
        '^(LocalSystem|NT AUTHORITY\\SYSTEM)$' { return "S-1-5-18" }
        '^(LocalService|NT AUTHORITY\\(LocalService|LOCAL SERVICE))$' { return "S-1-5-19" }
        '^(NetworkService|NT AUTHORITY\\(NetworkService|NETWORK SERVICE))$' { return "S-1-5-20" }
    }

    if ($accountName.StartsWith('.\')) {
        if ([string]::IsNullOrWhiteSpace($env:COMPUTERNAME)) {
            throw "Cannot resolve the machine name for queue account '$accountName'."
        }

        $accountName = $env:COMPUTERNAME + $accountName.Substring(1)
    }

    try {
        # This also resolves passwordless NT SERVICE\<service> virtual accounts.
        # Never substitute a broad group if a configured account cannot resolve.
        $sid = Resolve-WindowsAccountSid $accountName
    }
    catch {
        throw "Cannot resolve queue service account '$accountName' to a Windows SID. Verify the service logon account before deployment."
    }

    if ($sid -notmatch '^S-1-\d+(?:-\d+)+$') {
        throw "Queue service account '$accountName' did not resolve to a valid Windows SID."
    }

    return $sid
}

function Grant-QueueWorkerAccess {
    param([string] $ReleasePath, [string] $AccountSid)

    if ($AccountSid -notmatch '^S-1-\d+(?:-\d+)+$') {
        throw "Cannot grant release access without a resolved queue service SID."
    }

    # Releases receive fresh ACLs: /COPY:DAT deliberately does not copy the old
    # storage ACLs. Reapply only this service principal on every staged release.
    $grants = @(
        @{ Path = $ReleasePath; Rights = "RX" },
        @{ Path = (Join-Path $ReleasePath "storage"); Rights = "M" },
        @{ Path = (Join-Path $ReleasePath "bootstrap\cache"); Rights = "M" }
    )

    foreach ($grant in $grants) {
        & icacls.exe $grant.Path /grant ("*${AccountSid}:(OI)(CI)" + $grant.Rights) /T | Out-Null

        if ($LASTEXITCODE -ne 0) {
            throw "Could not grant queue service '$QueueServiceName' $($grant.Rights) access to '$($grant.Path)'."
        }
    }
}

function Assert-WorkerCommand {
    param([object[]] $Workers)

    if ($Workers.Count -eq 0) {
        throw "Service '$QueueServiceName' has no running Laravel queue:work process."
    }

    $artisanPath = Join-Path $AppPath "artisan"

    foreach ($worker in $Workers) {
        if ($worker.CommandLine.IndexOf($artisanPath, [System.StringComparison]::OrdinalIgnoreCase) -lt 0 -or
            $worker.CommandLine -match '(?:^|\s)"?--force"?(?:\s|$)') {
            throw "Service '$QueueServiceName' must run '$artisanPath queue:work' without --force so maintenance pauses new jobs."
        }
    }
}

function Stop-RfcWorker {
    if ((Get-Service -Name $QueueServiceName).Status -eq "Stopped") {
        return
    }

    $workers = @(Get-ServiceWorkerProcesses)
    Assert-WorkerCommand $workers
    # The service manager may restart a worker after queue:restart. Maintenance
    # mode keeps that replacement idle while we wait for the old job to finish.
    Invoke-PhpArtisan $AppPath queue:restart
    $deadline = (Get-Date).AddSeconds($WorkerDrainSeconds)

    do {
        $remaining = @($workers | Where-Object {
            $current = Get-CimInstance Win32_Process -Filter "ProcessId=$($_.ProcessId)"
            $current -and $current.CreationDate -eq $_.CreationDate
        })

        if ($remaining.Count -eq 0) {
            Stop-Service -Name $QueueServiceName
            (Get-Service -Name $QueueServiceName).WaitForStatus("Stopped", [TimeSpan]::FromSeconds(30))
            return
        }

        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)

    throw "Queue worker did not finish its current job within $WorkerDrainSeconds seconds. Deployment will not force-terminate it."
}

function Start-RfcWorker {
    if ((Get-Service -Name $QueueServiceName).Status -ne "Running") {
        Start-Service -Name $QueueServiceName
    }

    (Get-Service -Name $QueueServiceName).WaitForStatus("Running", [TimeSpan]::FromSeconds(30))
    $deadline = (Get-Date).AddSeconds(30)

    do {
        $workers = @(Get-ServiceWorkerProcesses)

        if ($workers.Count -gt 0) {
            Assert-WorkerCommand $workers
            Start-Sleep -Seconds 2
            $stable = @(Get-ServiceWorkerProcesses | Where-Object { $workers.ProcessId -contains $_.ProcessId })

            if ($stable.Count -gt 0) {
                return
            }
        }

        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)

    throw "Service '$QueueServiceName' did not start a stable worker for the deployed application."
}

function New-MaintenanceSmokeSession {
    # AppServiceProvider forces HTTPS redirects, so following the secret URL
    # would leave loopback. Build Laravel's signed maintenance cookie locally.
    $expiresAt = [DateTimeOffset]::UtcNow.AddMinutes(10).ToUnixTimeSeconds()
    $hmac = New-Object System.Security.Cryptography.HMACSHA256

    try {
        $hmac.Key = [System.Text.Encoding]::UTF8.GetBytes($maintenanceSecret)
        $digest = $hmac.ComputeHash([System.Text.Encoding]::UTF8.GetBytes($expiresAt.ToString([Globalization.CultureInfo]::InvariantCulture)))
        $mac = ([BitConverter]::ToString($digest)).Replace("-", "").ToLowerInvariant()
    }
    finally {
        $hmac.Dispose()
    }

    $payload = @{ expires_at = $expiresAt; mac = $mac } | ConvertTo-Json -Compress
    $value = [Convert]::ToBase64String([System.Text.Encoding]::UTF8.GetBytes($payload))
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $session.Cookies.Add([Uri] "http://127.0.0.1", [System.Net.Cookie]::new("laravel_maintenance", $value, "/"))

    return $session
}

function Suspend-RfcScheduler {
    if (-not $schedulerTask) {
        return
    }

    Disable-ScheduledTask -TaskName $schedulerTask.TaskName -TaskPath $schedulerTask.TaskPath | Out-Null
    $deadline = (Get-Date).AddSeconds($WorkerDrainSeconds)

    do {
        if ((Get-ScheduledTask -TaskName $schedulerTask.TaskName -TaskPath $schedulerTask.TaskPath).State -ne "Running") {
            return
        }

        Start-Sleep -Seconds 1
    } while ((Get-Date) -lt $deadline)

    throw "Scheduled task '$SchedulerTaskName' did not finish; deployment will not terminate an active scheduler."
}

function Restore-RfcScheduler {
    if ($schedulerTask -and $schedulerWasEnabled) {
        Enable-ScheduledTask -TaskName $schedulerTask.TaskName -TaskPath $schedulerTask.TaskPath | Out-Null
    }
}

function Restore-OriginalRuntimeState {
    if ($queueWasRunning) {
        Start-RfcWorker
    }
    elseif ((Get-Service -Name $QueueServiceName).Status -ne "Stopped") {
        Stop-RfcWorker
    }

    Restore-RfcScheduler

    if ($poolWasStarted -and (Get-WebAppPoolState -Name $appPoolName).Value -ne "Started") {
        Start-WebAppPool -Name $appPoolName
    }
    elseif (-not $poolWasStarted -and (Get-WebAppPoolState -Name $appPoolName).Value -ne "Stopped") {
        Stop-WebAppPool -Name $appPoolName
    }

    if ($siteWasStarted -and (Get-WebsiteState -Name $SiteName).Value -ne "Started") {
        Start-Website -Name $SiteName
    }
    elseif (-not $siteWasStarted -and (Get-WebsiteState -Name $SiteName).Value -ne "Stopped") {
        Stop-Website -Name $SiteName
    }
}

if (-not (Test-Path $PhpExe)) {
    throw "PHP executable was not found: $PhpExe"
}

Assert-PhpSecurityConfiguration
Assert-PhpUploadConfiguration
Show-PhpPerformanceWarning

Assert-ReleaseArchive $ArchivePath $ExpectedSha256

if (-not (Test-Path (Join-Path $AppPath ".env"))) {
    throw "The existing server .env file was not found under $AppPath"
}

Import-Module WebAdministration

if (-not (Test-Path "IIS:\Sites\$SiteName")) {
    throw "IIS website '$SiteName' does not exist."
}

$site = Get-Item "IIS:\Sites\$SiteName"
$configuredPublicPath = [IO.Path]::GetFullPath([Environment]::ExpandEnvironmentVariables($site.physicalPath)).TrimEnd([char] '\', [char] '/')
$expectedPublicPath = [IO.Path]::GetFullPath((Join-Path $AppPath "public")).TrimEnd([char] '\', [char] '/')

if (-not $configuredPublicPath.Equals($expectedPublicPath, [StringComparison]::OrdinalIgnoreCase)) {
    throw "IIS site '$SiteName' serves '$configuredPublicPath', not the requested application '$expectedPublicPath'."
}

$appPoolName = $site.applicationPool
$otherPoolSites = @(Get-Website | Where-Object { $_.Name -ne $SiteName -and $_.applicationPool -eq $appPoolName -and $_.State -eq "Started" })

if ($otherPoolSites.Count -gt 0) {
    throw "Application pool '$appPoolName' also serves another running website. Give RFC a dedicated pool before deploying."
}

# Prove that worker enumeration is available before taking the site offline.
# AppCmd can return exit 1 for a valid empty list; the managed API represents
# that state as an empty collection and preserves real enumeration failures.
$preflightIisWorkers = @(Get-RfcIisWorkerIds $appPoolName)
Write-Host "IIS worker preflight passed: $appPoolName / $($preflightIisWorkers.Count) worker(s)"

Assert-ApplicationIsNotInMaintenance
$siteWasStarted = (Get-WebsiteState -Name $SiteName).Value -eq "Started"
$poolWasStarted = (Get-WebAppPoolState -Name $appPoolName).Value -eq "Started"
$queueService = Get-Service -Name $QueueServiceName -ErrorAction SilentlyContinue

if (-not $queueService) {
    throw "Required queue service '$QueueServiceName' is missing. Install the managed worker before deployment."
}

if ($queueService.Status -notin @("Running", "Stopped")) {
    throw "Queue service '$QueueServiceName' must be Running or Stopped before deployment."
}

$queueWasRunning = $queueService.Status -eq "Running"
$queueServiceConfiguration = Get-CimInstance Win32_Service -Filter ("Name='" + $QueueServiceName.Replace("'", "''") + "'")
$queueServiceAccountSid = Resolve-QueueServiceAccountSid $queueServiceConfiguration

if ($queueWasRunning) {
    Assert-WorkerCommand @(Get-ServiceWorkerProcesses)
}

if ($SchedulerTaskName) {
    $schedulerTasks = @(Get-ScheduledTask -TaskName $SchedulerTaskName -ErrorAction SilentlyContinue)

    if ($schedulerTasks.Count -gt 1) {
        throw "More than one scheduled task is named '$SchedulerTaskName'; use a unique task name."
    }

    if ($schedulerTasks.Count -eq 1) {
        $schedulerTask = $schedulerTasks[0]
        $schedulerWasEnabled = $schedulerTask.Settings.Enabled
    }
    else {
        Write-Warning "Scheduled task '$SchedulerTaskName' was not found. Confirm no external scheduler writes to this application during deployment."
    }
}

try {
    Write-Host "== Extract release =="
    New-Item -ItemType Directory $stagePath | Out-Null
    & tar.exe -xzf $ArchivePath -C $stagePath

    if ($LASTEXITCODE -ne 0 -or -not (Test-Path (Join-Path $stagePath "artisan"))) {
        throw "The release archive could not be extracted correctly."
    }

    # Bootstrap caches are environment-specific and may reference development-only
    # service providers that are intentionally absent from the production vendor set.
    Get-ChildItem (Join-Path $stagePath "bootstrap\cache") -File -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -ne ".gitignore" } |
        Remove-Item -Force

    Write-Host "== Preserve environment and storage =="
    Copy-Item (Join-Path $AppPath ".env") (Join-Path $stagePath ".env") -Force
    Assert-SessionCookieConfiguration (Join-Path $stagePath ".env")
    Copy-RfcStorage $AppPath $stagePath

    @(
        "storage\app\public",
        "storage\app\private",
        "storage\framework\cache\data",
        "storage\framework\sessions",
        "storage\framework\views",
        "storage\logs",
        "bootstrap\cache"
    ) | ForEach-Object {
        New-Item -ItemType Directory -Force (Join-Path $stagePath $_) | Out-Null
    }

    icacls (Join-Path $stagePath "storage") /grant "IIS_IUSRS:(OI)(CI)M" /T | Out-Null

    if ($LASTEXITCODE -ne 0) {
        throw "Could not grant IIS write access to staged storage."
    }

    icacls (Join-Path $stagePath "bootstrap\cache") /grant "IIS_IUSRS:(OI)(CI)M" /T | Out-Null

    if ($LASTEXITCODE -ne 0) {
        throw "Could not grant IIS write access to staged bootstrap cache."
    }

    Grant-QueueWorkerAccess $stagePath $queueServiceAccountSid

    Write-Host "== Validate release against server configuration =="
    Invoke-PhpArtisan $stagePath config:clear
    Invoke-PhpArtisan $stagePath view:clear
    Invoke-PhpArtisan $stagePath security:production-check
    Invoke-PhpArtisan $stagePath migrate:status

    Write-Host "== Quiesce application writers =="
    $runtimeChanged = $true
    Suspend-RfcScheduler
    $maintenanceEnabled = $true
    Invoke-PhpArtisan $AppPath down --retry=60 "--secret=$maintenanceSecret"
    Stop-RfcSite
    Stop-RfcWorker

    Write-Host "== Synchronize final runtime storage =="
    # The live preflight copy is insufficient: users may have uploaded or deleted
    # files since it ran. Mirror only after web, worker and scheduler are quiet.
    Copy-RfcStorage $AppPath $stagePath -Mirror

    Write-Host "== Upgrade database and reference data =="
    $databaseUpgradeStarted = $true
    Invoke-PhpArtisan $stagePath migrate --force

    if ($SeedAccessControl) {
        Invoke-PhpArtisan $stagePath db:seed '--class=Database\Seeders\AccessControlSeeder' --force
    }

    Write-Host "== Swap application release =="
    Close-AppExplorerWindows
    Move-StagedReleaseIntoPlace

    Write-Host "== Rebuild production caches =="
    Invoke-PhpArtisan $AppPath optimize:clear --except=cache
    Invoke-PhpArtisan $AppPath storage:link
    Invoke-PhpArtisan $AppPath config:cache
    # Localized route prefixes are resolved from the request by
    # mcamara/laravel-localization and must remain uncached.
    Invoke-PhpArtisan $AppPath route:clear
    Invoke-PhpArtisan $AppPath view:cache
    Invoke-PhpArtisan $AppPath security:evidence "--label=$timestamp"

    Start-RfcSite

    Write-Host "== Smoke test while public maintenance remains enabled =="
    $smokeHost = ([Uri](Get-DotEnvValue (Join-Path $AppPath ".env") "APP_URL")).Host
    $smokeSession = New-MaintenanceSmokeSession
    $response = Invoke-WebRequest "http://127.0.0.1/ar/sign-in" -Headers @{ Host = $smokeHost } -WebSession $smokeSession -UseBasicParsing -TimeoutSec 30

    if ($response.StatusCode -ne 200) {
        throw "Smoke test returned HTTP $($response.StatusCode)."
    }

    # Validate worker startup while maintenance still prevents it consuming jobs.
    # Reopening public traffic is the final deployment step after rollback checks.
    Start-RfcWorker
    Restore-RfcScheduler
    Invoke-PhpArtisan $AppPath up
    $maintenanceEnabled = $false

    Write-Host "Deployment completed successfully."
    Write-Host "Application backup: $backupPath"
}
catch {
    $deploymentError = $_
    Write-Host "Deployment failed: $($deploymentError.Exception.Message)" -ForegroundColor Red

    try {
        if ($oldReleaseMoved) {
            Write-Host "== Roll back application files =="
            Stop-RfcSite

            if (Test-Path -LiteralPath $AppPath) {
                Invoke-PhpArtisan $AppPath down --retry=60
                Stop-RfcWorker
            }

            Restore-PreviousReleaseFiles
            Invoke-PhpArtisan $AppPath optimize:clear --except=cache
            Write-Host "Previous application files were restored. Failed release retained at: $failedPath"
        }

        if ($runtimeChanged) {
            # Restart the original services while maintenance still pauses jobs.
            Restore-OriginalRuntimeState

            if ($maintenanceEnabled -and (Test-Path -LiteralPath $AppPath)) {
                Invoke-PhpArtisan $AppPath up
                $maintenanceEnabled = $false
            }
        }
    }
    catch {
        Write-Host "Rollback needs operator attention: $($_.Exception.Message). Backup: $backupPath; staged release: $stagePath; failed release: $failedPath" -ForegroundColor Red
    }

    if ($databaseUpgradeStarted) {
        Write-Warning "Application rollback does not reverse migrations or reference-data changes. Verify compatibility or restore the separately prepared database backup."
    }

    throw $deploymentError
}
finally {
    if (-not $swapped -and (Test-Path $stagePath)) {
        Write-Host "Staged release retained for inspection: $stagePath"
    }
}
