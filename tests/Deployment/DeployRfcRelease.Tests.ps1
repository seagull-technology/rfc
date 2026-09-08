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
    "Restore-OriginalRuntimeState", "New-MaintenanceSmokeSession",
    "Resolve-QueueServiceAccountSid", "Grant-QueueWorkerAccess"
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
    $script:lastStorageFlags = $Flags
    if ($Flags -contains "/PURGE") { Get-ChildItem -LiteralPath $Destination -Force | Remove-Item -Recurse -Force }
    Get-ChildItem -LiteralPath $Source -Force | Copy-Item -Destination $Destination -Recurse -Force
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
    Assert-True (($lastStorageFlags -contains "/E") -and ($lastStorageFlags -contains "/PURGE") -and ($lastStorageFlags -notcontains "/MIR")) "Storage mirror must preserve destination ACLs with /E /PURGE."
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
    function Resolve-WindowsAccountSid {
        param([string] $AccountName)
        $script:lastResolvedAccount = $AccountName
        if ($AccountName -eq "NT SERVICE\RFCQueueWorker") { return "S-1-5-80-101-202-303-404-505" }
        if ($AccountName -eq "RFCTEST\queue-user") { return "S-1-5-21-101-202-303-1001" }
        if ($AccountName -eq "DOMAIN\queue-user") { return "S-1-5-21-101-202-304-1002" }
        throw "Account not found"
    }

    foreach ($entry in @(
        @{ Name = "LocalSystem"; Sid = "S-1-5-18" },
        @{ Name = "NT AUTHORITY\SYSTEM"; Sid = "S-1-5-18" },
        @{ Name = "NT AUTHORITY\LocalService"; Sid = "S-1-5-19" },
        @{ Name = "NT AUTHORITY\NETWORK SERVICE"; Sid = "S-1-5-20" },
        @{ Name = "NT SERVICE\RFCQueueWorker"; Sid = "S-1-5-80-101-202-303-404-505" },
        @{ Name = "DOMAIN\queue-user"; Sid = "S-1-5-21-101-202-304-1002" }
    )) {
        $sid = Resolve-QueueServiceAccountSid ([pscustomobject] @{ StartName = $entry.Name })
        Assert-True ($sid -eq $entry.Sid) "The configured service account resolved to an incorrect SID: $($entry.Name)."
    }
    $previousComputerName = $env:COMPUTERNAME
    try {
        $env:COMPUTERNAME = "RFCTEST"
        $sid = Resolve-QueueServiceAccountSid ([pscustomobject] @{ StartName = '.\queue-user' })
        Assert-True ($sid -eq "S-1-5-21-101-202-303-1001") "A local account must resolve on this computer."
        Assert-True ($lastResolvedAccount -eq "RFCTEST\queue-user") "Local account resolution used the wrong machine."
    }
    finally { $env:COMPUTERNAME = $previousComputerName }
    Assert-Throws { Resolve-QueueServiceAccountSid $null } "A missing service identity must fail preflight."
    Assert-Throws { Resolve-QueueServiceAccountSid ([pscustomobject] @{ StartName = " " }) } "A blank service identity must fail preflight."
    Assert-Throws { Resolve-QueueServiceAccountSid ([pscustomobject] @{ StartName = "UNKNOWN\missing" }) } "An unresolvable account must not fall back to a broader principal."
    $checks++

    function icacls.exe {
        param([string] $Path, [Parameter(ValueFromRemainingArguments = $true)] [string[]] $Flags)
        $script:aclCalls += [pscustomobject] @{ Path = $Path; Flags = $Flags }
        $global:LASTEXITCODE = if ($aclCalls.Count -eq $failAclCallNumber) { 5 } else { 0 }
    }
    $aclCalls = @()
    $failAclCallNumber = 0
    $workerSid = "S-1-5-80-101-202-303-404-505"
    Grant-QueueWorkerAccess $stagePath $workerSid
    Assert-True ($aclCalls.Count -eq 3) "Only app read access and the two runtime write paths should be granted."
    Assert-True (($aclCalls[0].Path -eq $stagePath) -and ($aclCalls[0].Flags -contains "*${workerSid}:(OI)(CI)RX")) "The release must grant only RX to the resolved service SID."
    Assert-True (($aclCalls[1].Path -eq (Join-Path $stagePath "storage")) -and ($aclCalls[1].Flags -contains "*${workerSid}:(OI)(CI)M")) "Storage must grant inherited Modify to the same service SID."
    Assert-True (($aclCalls[2].Path -eq (Join-Path $stagePath "bootstrap\cache")) -and ($aclCalls[2].Flags -contains "*${workerSid}:(OI)(CI)M")) "Bootstrap cache must grant inherited Modify to the same service SID."
    Assert-Throws { Grant-QueueWorkerAccess $stagePath "UNKNOWN\missing" } "ACL changes require a resolved SID."
    $aclCalls = @()
    $failAclCallNumber = 2
    Assert-Throws { Grant-QueueWorkerAccess $stagePath $workerSid } "A failed ACL grant must abort deployment."
    Assert-True ($aclCalls.Count -eq 2) "ACL failure must stop subsequent grants."
    $checks++

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
