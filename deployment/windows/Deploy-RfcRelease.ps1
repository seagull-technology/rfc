param(
    [Parameter(Mandatory = $true)]
    [string] $ArchivePath,

    [string] $SiteName = "RFC",
    [string] $AppPath = "C:\inetpub\rfc",
    [string] $PhpExe = "C:\php\php.exe"
)

$ErrorActionPreference = "Stop"
$timestamp = Get-Date -Format "yyyyMMdd-HHmmss"
$parentPath = Split-Path $AppPath -Parent
$stagePath = Join-Path $parentPath "rfc-stage-$timestamp"
$backupPath = Join-Path $parentPath "rfc-backup-$timestamp"
$failedPath = Join-Path $parentPath "rfc-failed-$timestamp"
$swapped = $false
$maintenanceEnabled = $false
$appPoolName = $null

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

function Stop-RfcSite {
    if ((Get-WebsiteState -Name $SiteName).Value -eq "Started") {
        Stop-Website -Name $SiteName
    }

    if ($appPoolName -and (Get-WebAppPoolState -Name $appPoolName).Value -eq "Started") {
        Stop-WebAppPool -Name $appPoolName
    }

    for ($attempt = 1; $attempt -le 15; $attempt++) {
        $siteStopped = (Get-WebsiteState -Name $SiteName).Value -eq "Stopped"
        $poolStopped = -not $appPoolName -or (Get-WebAppPoolState -Name $appPoolName).Value -eq "Stopped"

        if ($siteStopped -and $poolStopped) {
            return
        }

        Start-Sleep -Seconds 2
    }

    throw "IIS did not fully stop within 30 seconds."
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
            Rename-Item $Source $Destination
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

if (-not (Test-Path $PhpExe)) {
    throw "PHP executable was not found: $PhpExe"
}

Assert-PhpSecurityConfiguration

if (-not (Test-Path $ArchivePath)) {
    throw "Release archive was not found: $ArchivePath"
}

if (-not (Test-Path (Join-Path $AppPath ".env"))) {
    throw "The existing server .env file was not found under $AppPath"
}

Import-Module WebAdministration

if (-not (Test-Path "IIS:\Sites\$SiteName")) {
    throw "IIS website '$SiteName' does not exist."
}

$appPoolName = (Get-Item "IIS:\Sites\$SiteName").applicationPool

try {
    Write-Host "== Extract release =="
    New-Item -ItemType Directory -Force $stagePath | Out-Null
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
    New-Item -ItemType Directory -Force (Join-Path $stagePath "storage") | Out-Null
    & robocopy.exe (Join-Path $AppPath "storage") (Join-Path $stagePath "storage") /E /COPY:DAT /R:2 /W:2 /NFL /NDL /NP

    if ($LASTEXITCODE -gt 7) {
        throw "Storage copy failed with robocopy exit code $LASTEXITCODE."
    }

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
    icacls (Join-Path $stagePath "bootstrap\cache") /grant "IIS_IUSRS:(OI)(CI)M" /T | Out-Null

    Write-Host "== Validate release against server configuration =="
    Invoke-PhpArtisan $stagePath config:clear
    Invoke-PhpArtisan $stagePath view:clear
    Invoke-PhpArtisan $stagePath security:production-check
    Invoke-PhpArtisan $stagePath security:evidence "--label=$timestamp"
    Invoke-PhpArtisan $stagePath migrate:status

    Write-Host "== Enter maintenance mode =="
    Invoke-PhpArtisan $AppPath down --retry=60
    $maintenanceEnabled = $true

    Write-Host "== Upgrade database and reference data =="
    Invoke-PhpArtisan $stagePath migrate --force
    Invoke-PhpArtisan $stagePath db:seed '--class=Database\Seeders\AccessControlSeeder' --force

    Write-Host "== Swap application release =="
    Close-AppExplorerWindows
    Stop-RfcSite
    Rename-DirectoryWithRetry $AppPath $backupPath
    Rename-DirectoryWithRetry $stagePath $AppPath
    $swapped = $true

    Write-Host "== Rebuild production caches =="
    Invoke-PhpArtisan $AppPath optimize:clear
    Invoke-PhpArtisan $AppPath storage:link
    Invoke-PhpArtisan $AppPath config:cache
    # Localized route prefixes are resolved from the request by
    # mcamara/laravel-localization and must remain uncached.
    Invoke-PhpArtisan $AppPath route:clear
    Invoke-PhpArtisan $AppPath view:cache
    Invoke-PhpArtisan $AppPath queue:restart
    Invoke-PhpArtisan $AppPath up
    $maintenanceEnabled = $false

    Start-RfcSite

    Write-Host "== Smoke test =="
    $smokeHost = ([Uri](Get-DotEnvValue (Join-Path $AppPath ".env") "APP_URL")).Host
    $response = Invoke-WebRequest "http://127.0.0.1/ar/sign-in" -Headers @{ Host = $smokeHost } -UseBasicParsing -TimeoutSec 30

    if ($response.StatusCode -ne 200) {
        throw "Smoke test returned HTTP $($response.StatusCode)."
    }

    Write-Host "Deployment completed successfully."
    Write-Host "Application backup: $backupPath"
}
catch {
    Write-Host "Deployment failed: $($_.Exception.Message)" -ForegroundColor Red

    if ($swapped) {
        Write-Host "== Roll back application files =="
        Stop-RfcSite

        if (Test-Path $AppPath) {
            Rename-Item $AppPath $failedPath
        }

        if (Test-Path $backupPath) {
            Rename-Item $backupPath $AppPath
        }

        Invoke-PhpArtisan $AppPath up
        Start-RfcSite
        Write-Host "Previous application files were restored. Failed release: $failedPath"
    }
    elseif ($maintenanceEnabled -and (Test-Path $AppPath)) {
        Invoke-PhpArtisan $AppPath up
        Start-RfcSite
    }

    throw
}
finally {
    if (-not $swapped -and (Test-Path $stagePath)) {
        Write-Host "Staged release retained for inspection: $stagePath"
    }
}
