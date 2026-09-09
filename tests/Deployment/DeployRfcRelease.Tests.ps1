param(
    [string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Deploy-RfcRelease.ps1")
)

# Portable regression harness: executes the real deployment helper functions with
# temporary directories and mocked Windows services. It never runs a deployment.
# Runs locally under PowerShell 7; actual Windows PowerShell 5.1/IIS integration
# requires the Windows server and is not represented by these portable fixtures.
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
    "Restore-OriginalRuntimeState", "New-MaintenanceSmokeCookie",
    "Resolve-QueueServiceAccountSid", "Grant-QueueWorkerAccess",
    "Get-RfcIisWorkerIds", "Stop-RfcSite"
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
    $smokeCookie = New-MaintenanceSmokeCookie
    $payload = [Text.Encoding]::UTF8.GetString([Convert]::FromBase64String($smokeCookie)) | ConvertFrom-Json
    Assert-True ($payload.expires_at -gt [DateTimeOffset]::UtcNow.ToUnixTimeSeconds()) "Smoke maintenance cookie must be unexpired."
    Assert-True ($payload.mac -match '^[a-f0-9]{64}$') "Smoke maintenance cookie must carry an HMAC."
    $checks++

    # Typed fixture makes a worker enumeration failure a real property-getter
    # exception and tracks disposal. No IIS assembly or Windows API is loaded.
    Add-Type -TypeDefinition @"
public sealed class RfcIisWorkerFixture {
    public string PoolNameValue { get; set; }
    public int IdValue { get; set; }
    public bool ThrowOnPoolName { get; set; }
    public bool ThrowOnProcessId { get; set; }
    public RfcIisWorkerFixture(string poolName, int processId) { PoolNameValue = poolName; IdValue = processId; }
    public string AppPoolName {
        get {
            if (ThrowOnPoolName) throw new System.InvalidOperationException("Simulated worker pool-name failure");
            return PoolNameValue;
        }
    }
    public int ProcessId {
        get {
            if (ThrowOnProcessId) throw new System.InvalidOperationException("Simulated worker process-ID failure");
            return IdValue;
        }
    }
}
public sealed class RfcIisManagerFixture : System.IDisposable {
    public System.Collections.IDictionary ApplicationPools { get; set; }
    public object[] Rows { get; set; }
    public bool ThrowOnRead { get; set; }
    public bool Disposed { get; private set; }
    public object[] WorkerProcesses {
        get {
            if (ThrowOnRead) throw new System.InvalidOperationException("Simulated IIS enumeration failure");
            return Rows;
        }
    }
    public void Dispose() { Disposed = true; }
}
"@
    $script:managedRows = @(
        [RfcIisWorkerFixture]::new("RFC", 101),
        [RfcIisWorkerFixture]::new("RFC-extra", 202),
        [RfcIisWorkerFixture]::new("OtherPool", 303),
        [RfcIisWorkerFixture]::new("rfc", 404)
    )
    $script:managerPoolExists = $true
    $script:failWorkerQuery = $false
    $script:createdManagers = @()
    $script:iisPollCount = 0
    $script:useDrainFixture = $false
    $script:workerPollsBeforeDrain = 0
    function New-RfcIisServerManager {
        $script:iisPollCount++
        $manager = New-Object RfcIisManagerFixture
        $manager.ApplicationPools = @{}
        if ($managerPoolExists) { $manager.ApplicationPools["RFC"] = [pscustomobject] @{ Name = "RFC" } }
        $manager.Rows = $managedRows
        if ($useDrainFixture) {
            $manager.Rows = @()
            if ($workerPollsBeforeDrain -lt 0 -or $iisPollCount -le $workerPollsBeforeDrain) {
                $manager.Rows = @([RfcIisWorkerFixture]::new("RFC", 505))
            }
        }
        $manager.ThrowOnRead = $failWorkerQuery
        $script:createdManagers += $manager
        return $manager
    }

    $ids = @(Get-RfcIisWorkerIds "RFC")
    Assert-True (($ids -join ",") -eq "101,404") "Managed enumeration must match the exact pool, retain all its workers, and exclude similarly named pools."
    Assert-True (@($ids | Where-Object { $_ -isnot [int] }).Count -eq 0) "Worker identities must be materialized as integer process IDs."
    Assert-True $createdManagers[-1].Disposed "The IIS manager must be disposed after successful enumeration."
    $managerPoolExists = $false
    Assert-Throws { Get-RfcIisWorkerIds "RFC" } "An absent pool must not look like successful empty worker enumeration."
    Assert-True $createdManagers[-1].Disposed "The IIS manager must be disposed when the requested pool is absent."
    $managerPoolExists = $true
    $failWorkerQuery = $true
    Assert-Throws { Get-RfcIisWorkerIds "RFC" } "An IIS worker-query failure must not become an empty success result."
    Assert-True $createdManagers[-1].Disposed "The IIS manager must be disposed after an enumeration exception."
    $failWorkerQuery = $false
    $savedRows = $managedRows
    $managedRows = $null
    Assert-Throws { Get-RfcIisWorkerIds "RFC" } "A null provider collection must not be accepted as no running workers."
    Assert-True $createdManagers[-1].Disposed "The IIS manager must be disposed after a null provider collection."
    $managedRows = $savedRows
    foreach ($failureFlag in @("ThrowOnPoolName", "ThrowOnProcessId")) {
        $managedRows[0].$failureFlag = $true
        Assert-Throws { Get-RfcIisWorkerIds "RFC" } "A worker $failureFlag exception must fail enumeration instead of silently excluding the worker."
        Assert-True $createdManagers[-1].Disposed "The IIS manager must be disposed after a worker identity getter exception."
        $managedRows[0].$failureFlag = $false
    }
    $managedRows[0].IdValue = 0
    Assert-Throws { Get-RfcIisWorkerIds "RFC" } "A nonpositive process ID must fail enumeration."
    Assert-True $createdManagers[-1].Disposed "The IIS manager must be disposed after an invalid process ID."
    $managedRows[0].IdValue = 101
    $checks++

    $SiteName = "RFC"
    $appPoolName = "RFC"
    $script:iisSiteState = "Started"
    $script:iisPoolState = "Started"
    $script:siteStopCount = 0
    $script:poolStopCount = 0
    $script:iisSleepCount = 0
    function Get-WebsiteState { param([string] $Name); [pscustomobject] @{ Value = $iisSiteState } }
    function Get-WebAppPoolState { param([string] $Name); [pscustomobject] @{ Value = $iisPoolState } }
    function Stop-Website { param([string] $Name); $script:siteStopCount++; $script:iisSiteState = "Stopped" }
    function Stop-WebAppPool { param([string] $Name); $script:poolStopCount++; $script:iisPoolState = "Stopped" }
    function Start-Sleep { param([int] $Seconds); $script:iisSleepCount++ }
    $useDrainFixture = $true
    $workerPollsBeforeDrain = 0
    $iisPollCount = 0
    $WorkerDrainSeconds = 30
    # Reproduce the server's successful empty-worker condition despite AppCmd's
    # exit-code 1. Managed enumeration must not read any stale native status.
    $global:LASTEXITCODE = 1
    Stop-RfcSite
    Assert-True ($siteStopCount -eq 1 -and $poolStopCount -eq 1) "The site and pool must both be stopped before returning."
    Assert-True ($iisPollCount -eq 1 -and $iisSleepCount -eq 0) "Empty managed results must complete shutdown immediately, regardless of native LASTEXITCODE."
    $siteStopCount = 0
    $poolStopCount = 0
    $iisPollCount = 0
    Stop-RfcSite
    Assert-True ($siteStopCount -eq 0 -and $poolStopCount -eq 0 -and $iisPollCount -eq 1) "An already stopped, empty pool must be accepted without repeated stop commands."
    $checks++

    $iisPollCount = 0
    $iisSleepCount = 0
    $workerPollsBeforeDrain = 2
    Stop-RfcSite
    Assert-True ($iisPollCount -eq 3 -and $iisSleepCount -eq 2) "Stopped site/pool state alone must not bypass two remaining worker polls."
    Assert-True (@($createdManagers | Where-Object { -not $_.Disposed }).Count -eq 0) "Every poll must release its own IIS manager."
    $checks++

    $iisPollCount = 0
    $iisSleepCount = 0
    $failWorkerQuery = $true
    Assert-Throws { Stop-RfcSite } "A shutdown worker-query error must fail closed."
    Assert-True ($iisPollCount -eq 1 -and $iisSleepCount -eq 0) "Worker-query failure must abort immediately instead of treating it as a successful drain."
    Assert-True $createdManagers[-1].Disposed "A failed shutdown query must dispose its IIS manager."
    $failWorkerQuery = $false
    $checks++

    $iisPollCount = 0
    $workerPollsBeforeDrain = -1
    $WorkerDrainSeconds = 0
    Assert-Throws { Stop-RfcSite } "A worker that never exits must reach the shutdown timeout instead of permitting a file swap."
    Assert-True ($iisPollCount -eq 1) "The zero-second test deadline should stop after its first worker check."
    $checks++

    Write-Host "$checks deployment regression scenarios passed. No IIS, scheduler, service, or production operations were performed."
}
finally {
    Remove-Item -LiteralPath $testRoot -Recurse -Force
}
