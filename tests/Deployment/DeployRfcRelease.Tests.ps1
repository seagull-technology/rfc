param(
    [string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Deploy-RfcRelease.ps1")
)

# Portable regression harness: executes the real deployment helper functions with
# temporary directories and mocked Windows services. It never runs a deployment.
$ErrorActionPreference = "Stop"
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path $ScriptPath).Path, [ref] $tokens, [ref] $parseErrors)

if ($parseErrors.Count -gt 0) {
    throw ($parseErrors | ForEach-Object { $_.Message }) -join [Environment]::NewLine
}

$functionNames = @(
    "Copy-RfcStorage", "Assert-ReleaseArchive", "Move-StagedReleaseIntoPlace",
    "Restore-PreviousReleaseFiles", "Assert-WorkerCommand", "Stop-RfcWorker",
    "Restore-OriginalRuntimeState", "New-MaintenanceSmokeSession"
)

foreach ($name in $functionNames) {
    $definition = $ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $true) |
        Where-Object { $_.Name -eq $name } | Select-Object -First 1

    if (-not $definition) { throw "Missing deployment function: $name" }
    . ([ScriptBlock]::Create($definition.Extent.Text))
}

function Assert-True {
    param([bool] $Condition, [string] $Message)
    if (-not $Condition) { throw $Message }
}

function Assert-Throws {
    param([ScriptBlock] $Action, [string] $Message)
    $threw = $false
    try { & $Action } catch { $threw = $true }
    Assert-True $threw $Message
}

$testRoot = Join-Path ([IO.Path]::GetTempPath()) ("rfc-deploy-test-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory $testRoot | Out-Null
$checks = 0

function New-TestLayout {
    $casePath = Join-Path $testRoot ([Guid]::NewGuid().ToString("N"))
    $script:AppPath = Join-Path $casePath "app"
    $script:stagePath = Join-Path $casePath "stage"
    $script:backupPath = Join-Path $casePath "backup"
    $script:failedPath = Join-Path $casePath "failed"
    $script:oldReleaseMoved = $false
    $script:swapped = $false
    $script:renameCount = 0
    $script:failRenameNumber = 0
    $script:failStorageCopy = $false
    New-Item -ItemType Directory (Join-Path $AppPath "storage"), (Join-Path $stagePath "storage") -Force | Out-Null
    Set-Content (Join-Path $AppPath "code.txt") "old-release"
    Set-Content (Join-Path $stagePath "code.txt") "new-release"
    Set-Content (Join-Path $AppPath "storage\old-upload.txt") "original upload"
}

function Rename-DirectoryWithRetry {
    param([string] $Source, [string] $Destination)
    $script:renameCount++
    if ($renameCount -eq $failRenameNumber) { throw "Simulated locked directory" }
    Move-Item -LiteralPath $Source -Destination $Destination
}

function robocopy.exe {
    param([string] $Source, [string] $Destination, [Parameter(ValueFromRemainingArguments = $true)] [string[]] $Flags)
    if ($failStorageCopy) { $global:LASTEXITCODE = 8; return }
    if ($Flags -contains "/MIR") { Remove-Item -LiteralPath $Destination -Recurse -Force }
    Copy-Item -LiteralPath $Source -Destination $Destination -Recurse -Force
    $global:LASTEXITCODE = 1
}

try {
    $archive = Join-Path $testRoot "release.tar.gz"
    Set-Content $archive "test archive bytes"
    Assert-ReleaseArchive $archive (Get-FileHash $archive -Algorithm SHA256).Hash
    Assert-Throws { Assert-ReleaseArchive $archive ("0" * 64) } "An archive checksum mismatch must fail."
    $checks++

    New-TestLayout
    $failRenameNumber = 1
    Assert-Throws { Move-StagedReleaseIntoPlace } "The first rename failure must be reported."
    Restore-PreviousReleaseFiles
    Assert-True ((Get-Content (Join-Path $AppPath "code.txt")) -eq "old-release") "First-rename failure changed the live release."
    Assert-True (-not $oldReleaseMoved) "First-rename failure must not mark the old release as moved."
    $checks++

    New-TestLayout
    $failRenameNumber = 2
    Assert-Throws { Move-StagedReleaseIntoPlace } "The second rename failure must be reported."
    Assert-True $oldReleaseMoved "The successful first rename must be tracked immediately."
    Restore-PreviousReleaseFiles
    Assert-True ((Get-Content (Join-Path $AppPath "code.txt")) -eq "old-release") "Partial swap rollback did not restore AppPath."
    Assert-True (Test-Path $stagePath) "Failed staged release must remain available."
    $checks++

    New-TestLayout
    Set-Content (Join-Path $AppPath "storage\late-upload.txt") "upload after live preflight"
    Copy-RfcStorage $AppPath $stagePath -Mirror
    Move-StagedReleaseIntoPlace
    Assert-True (Test-Path (Join-Path $AppPath "storage\late-upload.txt")) "Final storage sync lost a late upload."
    Set-Content (Join-Path $AppPath "storage\new-upload.txt") "upload from new release"
    Remove-Item (Join-Path $AppPath "storage\old-upload.txt")
    Restore-PreviousReleaseFiles
    Assert-True ((Get-Content (Join-Path $AppPath "code.txt")) -eq "old-release") "Rollback restored the wrong code."
    Assert-True (Test-Path (Join-Path $AppPath "storage\new-upload.txt")) "Rollback lost the newest upload."
    Assert-True (-not (Test-Path (Join-Path $AppPath "storage\old-upload.txt"))) "Rollback resurrected a deleted upload."
    Assert-True (Test-Path $failedPath) "Failed release must be retained for inspection."
    $checks++

    New-TestLayout
    Move-StagedReleaseIntoPlace
    $failStorageCopy = $true
    Assert-Throws { Restore-PreviousReleaseFiles } "Rollback must stop when runtime storage cannot be preserved."
    Assert-True (Test-Path $AppPath) "Storage-copy failure removed the new release."
    Assert-True (Test-Path $backupPath) "Storage-copy failure removed the backup."
    $checks++

    $QueueServiceName = "RFCQueueWorker"
    $validWorker = [pscustomobject] @{ ProcessId = 42; CreationDate = [DateTime] "2026-09-08"; CommandLine = ('php "' + (Join-Path $AppPath "artisan") + '" queue:work --timeout=120') }
    Assert-WorkerCommand @($validWorker)
    Assert-Throws { Assert-WorkerCommand @([pscustomobject] @{ CommandLine = $validWorker.CommandLine + " --force" }) } "A worker that ignores maintenance must be rejected."
    Assert-Throws { Assert-WorkerCommand @([pscustomobject] @{ CommandLine = "php wrong/artisan queue:work" }) } "A worker running another release must be rejected."
    $checks++

    function Get-Service {
        param([string] $Name)
        [pscustomobject] @{ Status = "Running" } | Add-Member -MemberType ScriptMethod -Name WaitForStatus -Value {} -PassThru
    }
    function Get-ServiceWorkerProcesses { $validWorker }
    function Invoke-PhpArtisan { $script:restartSignalled = $true }
    function Get-CimInstance { if (-not $workerFinished) { $validWorker } }
    function Stop-Service { $script:workerStopped = $true }
    function Start-Sleep {}

    $WorkerDrainSeconds = 0
    $workerFinished = $false
    $workerStopped = $false
    $restartSignalled = $false
    Assert-Throws { Stop-RfcWorker } "A busy worker must abort deployment when drain times out."
    Assert-True $restartSignalled "Worker drain must request a graceful restart."
    Assert-True (-not $workerStopped) "A busy worker must not be forcibly stopped."
    $workerFinished = $true
    Stop-RfcWorker
    Assert-True $workerStopped "An idle replacement worker must be stopped before swapping files."
    $checks++

    $script:restoredActions = @()
    function Start-RfcWorker { $script:restoredActions += "start-worker" }
    function Stop-RfcWorker { $script:restoredActions += "stop-worker" }
    function Restore-RfcScheduler { $script:restoredActions += "restore-scheduler" }
    function Get-WebAppPoolState { [pscustomobject] @{ Value = "Started" } }
    function Get-WebsiteState { [pscustomobject] @{ Value = "Started" } }
    function Stop-WebAppPool { $script:restoredActions += "stop-pool" }
    function Stop-Website { $script:restoredActions += "stop-site" }
    $queueWasRunning = $false
    $poolWasStarted = $false
    $siteWasStarted = $false
    Restore-OriginalRuntimeState
    Assert-True ($restoredActions -contains "stop-worker") "Rollback must restore an originally stopped worker."
    Assert-True ($restoredActions -contains "stop-pool") "Rollback must restore an originally stopped pool."
    Assert-True ($restoredActions -contains "stop-site") "Rollback must restore an originally stopped site."
    $checks++

    $maintenanceSecret = "test-maintenance-key"
    $smokeSession = New-MaintenanceSmokeSession
    $cookie = $smokeSession.Cookies.GetCookies([Uri] "http://127.0.0.1/")["laravel_maintenance"]
    $payload = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($cookie.Value)) | ConvertFrom-Json
    Assert-True ($payload.expires_at -gt [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()) "Smoke maintenance cookie must be unexpired."
    Assert-True ($payload.mac -match '^[a-f0-9]{64}$') "Smoke maintenance cookie must carry an HMAC."
    $checks++

    Write-Host "$checks deployment regression scenarios passed. No IIS, scheduler, service, or production operations were performed."
}
finally {
    Remove-Item -LiteralPath $testRoot -Recurse -Force
}
