param(
    [Parameter(Mandatory = $true)]
    [string] $ReleaseDirectory,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-fA-F0-9]{64}$')]
    [string] $ExpectedChecksumsSha256
)

# Windows staging release + bounded internal verification. Run elevated on RFCtgWeb.
# The operator has waived an additional database backup for this staging release.
# The existing deployer still retains the previous application and handles swap failures.
$ErrorActionPreference = "Stop"
$AppPath = "C:\inetpub\rfc"
$PhpExe = "C:\php\php.exe"
$QueueServiceName = "RFCQueueWorker"
$ExpectedAppUrl = "https://filmjordan.jo"
$evidenceDirectory = $null
$exitCode = 1
$summary = [ordered] @{
    started_utc = [DateTime]::UtcNow.ToString("o")
    status = "preflight"
    deployment_completed = $false
    public_security_closure = $false
}

function Write-RfcPrivateText {
    param([string] $Path, [string] $Text)
    [IO.File]::WriteAllText($Path, $Text, (New-Object System.Text.UTF8Encoding($false)))
}

function Invoke-RfcVerificationNative {
    param([string] $Executable, [string[]] $Arguments, [string] $LogPath, [string] $Label)
    $application = Get-Command -Name $Executable -CommandType Application -ErrorAction Stop
    $previousPreference = $ErrorActionPreference
    $global:LASTEXITCODE = $null
    try {
        $ErrorActionPreference = "Continue"
        $output = & $application.Source @Arguments 2>&1
        $nativeExitCode = $global:LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousPreference
    }
    $text = ($output | ForEach-Object { [string] $_ }) -join [Environment]::NewLine
    Write-RfcPrivateText $LogPath ($text + [Environment]::NewLine)
    if ($null -eq $nativeExitCode -or $nativeExitCode -ne 0) {
        throw "$Label failed (exit $nativeExitCode). Private output: $LogPath"
    }
    return $text
}

function Read-RfcVerifiedBundle {
    param([string] $Directory, [string] $ChecksumsSha256)
    $checksumsPath = Join-Path $Directory "SHA256SUMS.txt"
    if ((Get-FileHash -LiteralPath $checksumsPath -Algorithm SHA256).Hash -ine $ChecksumsSha256) {
        throw "SHA256SUMS.txt does not match the separately supplied release checksum."
    }
    $hashes = @{}
    foreach ($line in [IO.File]::ReadAllLines($checksumsPath)) {
        if ($line -notmatch '^([a-fA-F0-9]{64})  ([A-Za-z0-9_.-]+)$' -or $Matches[2] -in @('.', '..')) {
            throw "Invalid or unsafe entry in SHA256SUMS.txt."
        }
        $hash = $Matches[1]
        $name = $Matches[2]
        if ($hashes.ContainsKey($name)) { throw "Duplicate bundle checksum entry: $name" }
        $path = Join-Path $Directory $name
        if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ine $hash) {
            throw "Bundle checksum mismatch: $name"
        }
        $hashes[$name] = $hash
    }
    foreach ($required in @("rfc-app.tar.gz", "Deploy-RfcRelease.ps1", "Deploy-RfcStagingVerification.ps1", "BUILD-MANIFEST.json", "SOURCE-COMMIT.txt")) {
        if (-not $hashes.ContainsKey($required)) { throw "Required bundle file is not covered by SHA256SUMS.txt: $required" }
    }
    $manifest = [IO.File]::ReadAllText((Join-Path $Directory "BUILD-MANIFEST.json")) | ConvertFrom-Json
    if ($manifest.schema -ne "rfc-offline-release-v1" -or $manifest.source_commit -notmatch '^[a-f0-9]{40}$' -or
        $manifest.release -notmatch '^rfc-offline-release-[A-Za-z0-9][A-Za-z0-9._-]*$') {
        throw "Unsupported or incomplete build manifest."
    }
    return [pscustomobject] @{ Hashes = $hashes; Manifest = $manifest }
}

function Assert-RfcRuntimeFile {
    param([string] $Directory, [object] $Manifest, [string] $RelativePath)
    $entry = $Manifest.runtime_files.PSObject.Properties[$RelativePath]
    if (-not $entry -or [string] $entry.Value -notmatch '^[a-fA-F0-9]{64}$') {
        throw "Runtime file is missing from the verified manifest: $RelativePath"
    }
    $path = Join-Path $Directory ($RelativePath.Replace('/', [IO.Path]::DirectorySeparatorChar))
    if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ine [string] $entry.Value) {
        throw "Deployed file does not match the verified release: $RelativePath"
    }
}

function Assert-RfcWorkflowResult {
    param([string] $Json, [string] $Locale)
    $result = $Json | ConvertFrom-Json
    if ($result.passed -ne $true -or $result.locale -ne $Locale -or $result.database_rolled_back -ne $true -or
        $result.fixture_records_absent -ne $true -or $result.temporary_files_removed -ne $true -or
        @($result.checks).Count -lt 16 -or @($result.checks | Where-Object { $_.passed -ne $true }).Count -ne 0) {
        throw "Workflow $Locale verification or cleanup is incomplete. Inspect its private JSON output."
    }
    return $result
}

function New-RfcPrivateEvidenceDirectory {
    $parent = "C:\ProgramData\RFC\SecurityVerification"
    $name = (Get-Date -Format "yyyyMMdd-HHmmss") + "-" + [Guid]::NewGuid().ToString("N").Substring(0, 8)
    $path = Join-Path $parent $name
    New-Item -ItemType Directory -Path $path -Force | Out-Null
    $acl = New-Object Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($sidText in @("S-1-5-18", "S-1-5-32-544")) {
        $sid = New-Object Security.Principal.SecurityIdentifier($sidText)
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($sid, "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow")
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $path -AclObject $acl
    $actual = Get-Acl -LiteralPath $path
    if (-not $actual.AreAccessRulesProtected) { throw "Evidence directory inheritance could not be restricted." }
    $rules = @($actual.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
    if ($rules.Count -ne 2 -or @($rules | Where-Object {
        $_.IdentityReference.Value -notin @("S-1-5-18", "S-1-5-32-544") -or
        $_.AccessControlType -ne "Allow" -or $_.FileSystemRights -ne "FullControl"
    }).Count -ne 0) { throw "Evidence directory access differs from the intended administrator/SYSTEM permissions." }
    return $path
}

function Get-RfcHealthyWorker {
    $service = Get-CimInstance Win32_Service -Filter "Name='RFCQueueWorker'"
    if (-not $service -or $service.State -ne "Running" -or $service.StartMode -ne "Auto" -or
        $service.StartName -ine "NT SERVICE\RFCQueueWorker" -or $service.ProcessId -le 0) {
        throw "Expected the existing RFCQueueWorker to be Running/Automatic under its virtual service account. No installation or account change was attempted."
    }
    $workers = @(Get-CimInstance Win32_Process -Filter "ParentProcessId=$($service.ProcessId)" | Where-Object {
        $_.ExecutablePath -ieq $PhpExe -and $_.CommandLine -and
        $_.CommandLine.IndexOf((Join-Path $AppPath "artisan"), [StringComparison]::OrdinalIgnoreCase) -ge 0 -and
        $_.CommandLine -match 'queue:work' -and $_.CommandLine -notmatch '(?:^|\s)"?--force(?:[=\s"]|$)'
    })
    if ($workers.Count -ne 1) { throw "Expected one managed PHP queue worker for this application. Inspect service health before continuing." }
    return [ordered] @{ name = $service.Name; state = $service.State; start_mode = $service.StartMode; managed_php_workers = $workers.Count }
}

try {
    if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT -or [Environment]::MachineName -ine "RFCtgWeb") {
        throw "This orchestration is restricted to the RFCtgWeb Windows staging server."
    }
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw "Run this script in Administrator PowerShell."
    }
    $ReleaseDirectory = (Resolve-Path -LiteralPath $ReleaseDirectory).Path
    Set-Location (Split-Path $AppPath -Parent)
    $bundle = Read-RfcVerifiedBundle $ReleaseDirectory $ExpectedChecksumsSha256
    if ((Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash -ine $bundle.Hashes["Deploy-RfcStagingVerification.ps1"]) {
        throw "The running orchestration script differs from the verified release."
    }
    $summary['release'] = $bundle.Manifest.release
    $summary['source_commit'] = $bundle.Manifest.source_commit
    $summary['app_archive_sha256'] = $bundle.Hashes["rfc-app.tar.gz"]
    $summary['worker_before'] = Get-RfcHealthyWorker
    $evidenceDirectory = New-RfcPrivateEvidenceDirectory
    $summary['evidence_directory'] = $evidenceDirectory
    Write-Host "Verified package and existing worker. Deploying $($bundle.Manifest.release)."
    $summary.status = "deployment"
    $powerShellExe = Join-Path $env:windir "System32\WindowsPowerShell\v1.0\powershell.exe"
    Invoke-RfcVerificationNative $powerShellExe @(
        "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", "-File", (Join-Path $ReleaseDirectory "Deploy-RfcRelease.ps1"),
        "-ArchivePath", (Join-Path $ReleaseDirectory "rfc-app.tar.gz"), "-ExpectedSha256", $bundle.Hashes["rfc-app.tar.gz"],
        "-SiteName", "RFC", "-AppPath", $AppPath, "-PhpExe", $PhpExe, "-QueueServiceName", $QueueServiceName
    ) (Join-Path $evidenceDirectory "deployment-private.log") "Deployment" | Out-Null
    $summary.deployment_completed = $true
    $summary.status = "verification"
    Write-Host "Deployment completed. Checking the deployed helpers and application configuration."
    foreach ($relative in @(
        "scripts/verify-staging-security.php", "scripts/verify-staging-workflows.php", "app/Http/Controllers/ScoutingRequestController.php",
        "resources/views/layouts/partials/lodash.blade.php", "public/js/lodash.min.js"
    )) { Assert-RfcRuntimeFile $AppPath $bundle.Manifest $relative }
    $summary['worker_after_deploy'] = Get-RfcHealthyWorker
    Invoke-RfcVerificationNative $PhpExe @((Join-Path $AppPath "artisan"), "security:production-check", "--no-interaction") (Join-Path $evidenceDirectory "configuration-check.log") "Production configuration check" | Out-Null

    Write-Host "Running isolated controller, identity and concurrency checks; response checks cover Arabic and English."
    $controllerText = Invoke-RfcVerificationNative $PhpExe @(
        (Join-Path $AppPath "scripts\verify-staging-security.php"), "--staging-test", "--app-path=$AppPath",
        "--expected-app-url=$ExpectedAppUrl", "--samples=3"
    ) (Join-Path $evidenceDirectory "controller-check.log") "Internal controller verification"
    $runMatches = [regex]::Matches($controllerText, '(?m)^Run (sv-[a-f0-9]{16}):')
    if ($runMatches.Count -ne 1) { throw "Controller verification did not identify exactly one evidence run." }
    $runId = $runMatches[0].Groups[1].Value
    $controllerDirectory = Join-Path $AppPath "storage\app\private\security-verification\$runId"
    $controllerResult = [IO.File]::ReadAllText((Join-Path $controllerDirectory "results.json")) | ConvertFrom-Json
    $cleanup = [IO.File]::ReadAllText((Join-Path $controllerDirectory "cleanup.json")) | ConvertFrom-Json
    if ($controllerResult.run_id -ne $runId -or $controllerResult.internal_checks_passed -ne $true -or
        $cleanup.run_id -ne $runId -or $cleanup.status -ne "completed") {
        throw "Controller verification or exact fixture cleanup is incomplete."
    }
    Copy-Item -LiteralPath (Join-Path $controllerDirectory "results.json") -Destination (Join-Path $evidenceDirectory "controller-results.json")
    Copy-Item -LiteralPath (Join-Path $controllerDirectory "cleanup.json") -Destination (Join-Path $evidenceDirectory "controller-cleanup.json")
    $summary['controller_run_id'] = $runId
    $summary['controller_checks_passed'] = $true
    $summary['concurrent_review'] = $controllerResult.concurrent_review.status

    foreach ($locale in @("ar", "en")) {
        Write-Host "Running $locale form navigation, upload, save, edit and submission checks."
        $workflowText = Invoke-RfcVerificationNative $PhpExe @(
            (Join-Path $AppPath "scripts\verify-staging-workflows.php"), "--app-root=$AppPath", "--confirm-staging", "--locale=$locale"
        ) (Join-Path $evidenceDirectory "workflows-$locale.json") "Workflow $locale verification"
        $workflow = Assert-RfcWorkflowResult $workflowText $locale
        $summary["workflow_${locale}_checks_passed"] = @($workflow.checks).Count
    }
    Invoke-RfcVerificationNative $PhpExe @((Join-Path $AppPath "artisan"), "security:evidence", "--label=$($bundle.Manifest.release)", "--no-interaction") (Join-Path $evidenceDirectory "security-evidence.log") "Security evidence generation" | Out-Null
    $summary['worker_final'] = Get-RfcHealthyWorker
    $summary.status = "deployment-and-internal-checks-completed"
    $exitCode = 0
    if ($summary.concurrent_review -ne "passed") {
        $summary.status = "deployment-completed-concurrency-not-proven"
        $exitCode = 2
        Write-Warning "Deployment and completed checks passed, but database concurrency was not proven. Inspect controller-results.json."
    }
    Write-Host "Application remains live; RFCQueueWorker is Running/Automatic."
    Write-Host "Private evidence: $evidenceDirectory"
    Write-Host "These internal checks do not close public TLS, gateway cookies/DNS, browser behavior or public timing findings."
}
catch {
    $summary.status = "failed"
    $summary['failure'] = $_.Exception.Message
    Write-Warning $_.Exception.Message
    if ($summary.deployment_completed) {
        Write-Warning "Deployment completed before this verification failure. The new release remains deployed; no automatic application rollback or worker reinstall was attempted."
    }
    else {
        Write-Warning "Deployment was not confirmed successful. Check the private deployment log and current IIS/worker state before retrying."
    }
}
finally {
    $summary['finished_utc'] = [DateTime]::UtcNow.ToString("o")
    $summaryJson = $summary | ConvertTo-Json -Depth 8
    Write-Host "Verification summary (private command logs are not printed):"
    Write-Output $summaryJson
    if ($evidenceDirectory -and (Test-Path -LiteralPath $evidenceDirectory)) {
        Write-RfcPrivateText (Join-Path $evidenceDirectory "summary.json") ($summaryJson + [Environment]::NewLine)
        Write-Host "Summary: $(Join-Path $evidenceDirectory 'summary.json')"
    }
}
exit $exitCode
