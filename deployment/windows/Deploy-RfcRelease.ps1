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

function Start-RfcSite {
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
}

function Stop-RfcSite {
    if ((Get-WebsiteState -Name $SiteName).Value -eq "Started") {
        Stop-Website -Name $SiteName
    }

    if ($appPoolName -and (Get-WebAppPoolState -Name $appPoolName).Value -eq "Started") {
        Stop-WebAppPool -Name $appPoolName
    }
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
    Rename-Item $AppPath $backupPath
    Rename-Item $stagePath $AppPath
    $swapped = $true

    Write-Host "== Rebuild production caches =="
    Invoke-PhpArtisan $AppPath optimize:clear
    Invoke-PhpArtisan $AppPath storage:link
    Invoke-PhpArtisan $AppPath config:cache
    Invoke-PhpArtisan $AppPath view:cache
    Invoke-PhpArtisan $AppPath queue:restart
    Invoke-PhpArtisan $AppPath up
    $maintenanceEnabled = $false

    Start-RfcSite

    Write-Host "== Smoke test =="
    $response = Invoke-WebRequest "http://localhost/ar/login" -UseBasicParsing -TimeoutSec 30

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
