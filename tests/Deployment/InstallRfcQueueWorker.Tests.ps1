param(
    [string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Install-RfcQueueWorker.ps1")
)

# Portable checks of the real installer helpers. No service, registry, or Windows
# ACL changes occur; the main installer body is parsed but never executed.
# Local validation uses PowerShell 7; Windows PowerShell 5.1 integration requires
# the Windows server and is not claimed by this portable harness.
$ErrorActionPreference = "Stop"
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path $ScriptPath).Path, [ref] $tokens, [ref] $parseErrors)
if ($parseErrors.Count -gt 0) { throw (($parseErrors | ForEach-Object { $_.Message }) -join [Environment]::NewLine) }
foreach ($name in @("Assert-NssmChecksum", "Assert-NssmVersion", "Assert-SeparateWorkerPaths", "Assert-NoExistingWorker", "Invoke-WorkerNative", "Get-WorkerArguments", "Initialize-WorkerRegistration", "Set-WorkerApplicationParameters", "Set-WorkerConfiguration", "Assert-WorkerRegistration", "Assert-RepairableWorker", "Resolve-WorkerVirtualAccountSid", "Assert-WorkerPrivateDirectoryAccess", "Set-WorkerPrivateDirectoryAccess", "Grant-WorkerFileAccess", "Assert-WorkerIsStaged")) {
    $definition = $ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $true) |
        Where-Object { $_.Name -eq $name } | Select-Object -First 1
    if (-not $definition) { throw "Missing installer helper: $name" }
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

$testRoot = Join-Path ([IO.Path]::GetTempPath()) ("rfc-worker-test-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory $testRoot | Out-Null
$checks = 0
try {
    $binary = Join-Path $testRoot "nssm.exe"
    Set-Content $binary "reviewed binary fixture"
    Assert-NssmChecksum $binary (Get-FileHash $binary -Algorithm SHA256).Hash
    Assert-Throws { Assert-NssmChecksum $binary ("0" * 64) } "Unverified NSSM must fail before use."
    Assert-Throws { Assert-NssmChecksum (Join-Path $testRoot "missing.exe") ("0" * 64) } "Missing NSSM must fail."
    $checks++

    Assert-NssmVersion "NSSM 2.24-101-g897c7ad 64-bit"
    Assert-NssmVersion "NSSM 2.25 64-bit"
    Assert-Throws { Assert-NssmVersion "NSSM 2.24 64-bit" } "Stable 2.24 lacks required fixes."
    Assert-Throws { Assert-NssmVersion "unknown binary" } "Unknown version must fail."
    $checks++

    $app = Join-Path $testRoot "app with spaces"
    $php = Join-Path $testRoot "php"
    $install = Join-Path $testRoot "worker"
    $logs = Join-Path $testRoot "logs"
    Assert-SeparateWorkerPaths @($app, $php, $install, $logs)
    Assert-Throws { Assert-SeparateWorkerPaths @($app, (Join-Path $app "worker")) } "Permanent NSSM must remain outside the replaced application."
    Assert-Throws { Assert-SeparateWorkerPaths @($logs, $logs) } "Private logs cannot share the binary directory."
    $checks++

    $script:mockService = $null
    function Get-CimInstance { param($ClassName, $Filter); return $script:mockService }
    Assert-NoExistingWorker "RFCQueueWorker"
    $mockService = [pscustomobject] @{ State = "Stopped"; StartMode = "Manual"; StartName = "NT SERVICE\RFCQueueWorker" }
    Assert-Throws { Assert-NoExistingWorker "RFCQueueWorker" } "Existing services must never be overwritten."
    Assert-WorkerIsStaged "RFCQueueWorker"
    foreach ($property in @("State", "StartMode", "StartName")) {
        $original = $mockService.$property
        $mockService.$property = "unexpected"
        Assert-Throws { Assert-WorkerIsStaged "RFCQueueWorker" } "Incorrect $property must fail staging verification."
        $mockService.$property = $original
    }
    $mockService.StartName = "LocalSystem"
    Assert-Throws { Resolve-WorkerVirtualAccountSid "RFCQueueWorker" } "Broad-account fallback must never be accepted."
    $checks++

    # Exercise the actual native wrapper against a child process. Stderr alone
    # must not hide its exit status; the caller's error preference must survive.
    $pwshExe = (Get-Process -Id $PID).Path
    $originalPreference = $ErrorActionPreference
    $diagnostic = Invoke-WorkerNative $pwshExe @("-NoLogo", "-NoProfile", "-NonInteractive", "-Command", "[Console]::Error.WriteLine('benign diagnostic'); exit 0")
    Assert-True (($diagnostic -join " ") -match "benign diagnostic") "Successful native stderr must be captured."
    Assert-True ($ErrorActionPreference -eq $originalPreference) "Native success must restore the caller's error preference."
    Assert-Throws { Invoke-WorkerNative $pwshExe @("-NoLogo", "-NoProfile", "-NonInteractive", "-Command", "[Console]::Error.WriteLine('failure diagnostic'); exit 17") } "A real nonzero native exit must abort setup."
    Assert-True ($ErrorActionPreference -eq $originalPreference) "Native failure must restore the caller's error preference."
    Assert-Throws { Invoke-WorkerNative (Join-Path $testRoot "missing-native.exe") @() } "An unresolved native command must fail before execution."
    $checks++

    # Model the Registry provider's important difference from the filesystem:
    # New-Item -Force on an existing registry key resets its values. NSSM only
    # recognizes a service after Parameters/Application has been initialized.
    $script:registry = @{}
    $script:registryEvents = @()
    $script:nativeCalls = @()
    $script:failNativeCall = 0
    $registryKey = "HKLM:\SYSTEM\CurrentControlSet\Services\RFCQueueWorker\Parameters"
    function Test-Path {
        param([Alias("LiteralPath")] [string] $Path, [string] $PathType)
        if ($Path.StartsWith("HKLM:")) { return $script:registry.ContainsKey($Path) }
        if ($PathType) { return Microsoft.PowerShell.Management\Test-Path -LiteralPath $Path -PathType $PathType }
        return Microsoft.PowerShell.Management\Test-Path -LiteralPath $Path
    }
    function New-Item {
        param([string] $Path, [switch] $Force)
        if (-not $Path.StartsWith("HKLM:")) { throw "Unexpected non-registry mutation in helper tests: $Path" }
        if ($registry.ContainsKey($Path) -and -not $Force) { throw "Registry key already exists." }
        $script:registry[$Path] = @{}
        $script:registryEvents += "create:$Path"
    }
    function New-ItemProperty {
        param([string] $Path, [string] $Name, $Value, [string] $PropertyType, [switch] $Force)
        if (-not $registry.ContainsKey($Path)) { throw "Registry key must exist before its values are written." }
        $script:registry[$Path][$Name] = $Value
        $script:registryEvents += "value:$Name"
    }
    function Get-ItemProperty {
        param([Alias("LiteralPath")] [string] $Path, [string] $Name, [string] $ErrorAction)
        if (-not $registry.ContainsKey($Path)) { return $null }
        return [pscustomobject] $registry[$Path]
    }
    function Invoke-WorkerNative {
        param([string] $Executable, [string[]] $Arguments)
        $script:nativeCalls += [pscustomobject] @{ Executable = $Executable; Arguments = $Arguments }
        if ($failNativeCall -eq $nativeCalls.Count) { throw "mock native failure" }
        if ($Executable -eq $binary) {
            $key = "HKLM:\SYSTEM\CurrentControlSet\Services\$($Arguments[1])\Parameters"
            if (-not $registry.ContainsKey($key) -or -not $registry[$key]["Application"]) {
                throw "Simulated NSSM: not a valid NSSM service (Parameters/Application absent)."
            }
            $setting = $Arguments[2]
            if ($setting -eq "ObjectName") { $script:mockService.StartName = $Arguments[3] }
            elseif ($setting -eq "AppExit") { $script:registry[$key]["AppExit:" + $Arguments[3]] = $Arguments[4] }
            else { $script:registry[$key][$setting] = $Arguments[3] }
            $script:registryEvents += "nssm:$setting"
        }
    }

    Assert-Throws { Invoke-WorkerNative $binary @("set", "RFCQueueWorker", "Application", (Join-Path $php "php.exe")) } "The stateful mock must reproduce NSSM rejecting an uninitialized native service."
    $nativeCalls = @()
    Initialize-WorkerRegistration "RFCQueueWorker" $app (Join-Path $php "php.exe")
    Assert-True ($registry[$registryKey]["Application"] -eq (Join-Path $php "php.exe")) "Initialization must write Application before NSSM is called."
    Assert-True ($nativeCalls.Count -eq 0) "Registration initialization must not call NSSM before bootstrapping its registry values."
    $registry = @{} # The public configuration entry point must also bootstrap a fresh service itself.
    Set-WorkerConfiguration $binary "RFCQueueWorker" $app (Join-Path $php "php.exe") $logs
    $workerParameters = $registry[$registryKey]["AppParameters"]
    Assert-True ($workerParameters.StartsWith('"' + (Join-Path $app "artisan") + '" queue:work ')) "Artisan must be absolute and preserve spaces."
    Assert-True ($workerParameters -notmatch '--force\b') "Worker must honor application maintenance mode."
    $expectedValues = @{
        Application = (Join-Path $php "php.exe"); AppDirectory = $app
        AppStdout = (Join-Path $logs "worker-out.log"); AppStderr = (Join-Path $logs "worker-error.log")
        AppRotateBytes = "10485760"; "AppExit:Default" = "Restart"; "AppExit:0" = "Restart"
    }
    foreach ($entry in $expectedValues.GetEnumerator()) {
        Assert-True ($registry[$registryKey][$entry.Key] -eq $entry.Value) "Configuration lost registry value $($entry.Key); existing keys must not be recreated with -Force."
    }
    Assert-True ($mockService.StartName -eq "NT SERVICE\RFCQueueWorker") "Worker configuration must select the exact virtual account."
    Assert-True (@($nativeCalls | Where-Object { $_.Executable -eq "sc.exe" }).Count -eq 2) "Wrapper failure recovery must be configured."
    $checks++

    Assert-WorkerRegistration "RFCQueueWorker" $app (Join-Path $php "php.exe") $logs
    foreach ($name in @("Application", "AppDirectory", "AppParameters", "AppStdout", "AppStderr", "AppRestartDelay")) {
        $original = $registry[$registryKey][$name]
        $registry[$registryKey][$name] = "wrong configured value"
        Assert-Throws { Assert-WorkerRegistration "RFCQueueWorker" $app (Join-Path $php "php.exe") $logs } "Registration readback must reject an incorrect $name."
        $registry[$registryKey].Remove($name)
        Assert-Throws { Assert-WorkerRegistration "RFCQueueWorker" $app (Join-Path $php "php.exe") $logs } "Registration readback must reject a missing $name."
        $registry[$registryKey][$name] = $original
    }
    Assert-WorkerRegistration "RFCQueueWorker" $app (Join-Path $php "php.exe") $logs
    $checks++

    # Both helpers may be reused in repair. Neither may erase previously applied
    # restart/log values while preserving or rewriting the application arguments.
    Initialize-WorkerRegistration "RFCQueueWorker" $app (Join-Path $php "php.exe")
    Set-WorkerApplicationParameters "RFCQueueWorker" (Get-WorkerArguments $app)
    foreach ($entry in $expectedValues.GetEnumerator()) {
        Assert-True ($registry[$registryKey][$entry.Key] -eq $entry.Value) "Repair initialization/arguments erased $($entry.Key)."
    }
    New-Item -Path $registryKey -Force
    Assert-True ($registry[$registryKey].Count -eq 0) "Registry mock must reproduce the destructive New-Item -Force behavior."
    $checks++

    $script:ExpectedSha256 = (Get-FileHash $binary -Algorithm SHA256).Hash
    $mockService = [pscustomobject] @{
        State = "Stopped"; StartMode = "Disabled"; StartName = "LocalSystem"
        PathName = ('"' + $binary + '"'); Description = "RFC Laravel database queue worker"
    }
    Assert-RepairableWorker "RFCQueueWorker" $binary $app (Join-Path $php "php.exe") $logs
    $mockService.StartName = "NT SERVICE\RFCQueueWorker"
    Assert-RepairableWorker "RFCQueueWorker" $binary $app (Join-Path $php "php.exe") $logs
    foreach ($entry in @(
        @{ Key = "State"; Value = "Running" }, @{ Key = "StartMode"; Value = "Manual" },
        @{ Key = "PathName"; Value = ('"' + $binary + '" extra-argument') },
        @{ Key = "Description"; Value = "Some other service" }, @{ Key = "StartName"; Value = "NT AUTHORITY\NetworkService" }
    )) {
        $original = $mockService.($entry.Key)
        $mockService.($entry.Key) = $entry.Value
        Assert-Throws { Assert-RepairableWorker "RFCQueueWorker" $binary $app (Join-Path $php "php.exe") $logs } "Repair must reject a mismatched $($entry.Key)."
        $mockService.($entry.Key) = $original
    }
    $checks++

    $repairPaths = @{
        Application = (Join-Path $php "php.exe"); AppDirectory = $app
        AppParameters = (Get-WorkerArguments $app)
        AppStdout = (Join-Path $logs "worker-out.log"); AppStderr = (Join-Path $logs "worker-error.log")
    }
    foreach ($entry in $repairPaths.GetEnumerator()) { $registry[$registryKey][$entry.Key] = $entry.Value }
    Assert-RepairableWorker "RFCQueueWorker" $binary $app (Join-Path $php "php.exe") $logs
    foreach ($entry in $repairPaths.GetEnumerator()) {
        $registry[$registryKey][$entry.Key] = "unrelated value"
        Assert-Throws { Assert-RepairableWorker "RFCQueueWorker" $binary $app (Join-Path $php "php.exe") $logs } "Repair must reject an unrelated registry $($entry.Key)."
        $registry[$registryKey][$entry.Key] = $entry.Value
    }
    $script:ExpectedSha256 = "0" * 64
    Assert-Throws { Assert-NssmChecksum $binary $ExpectedSha256 } "Repair preflight must reject an unverified permanent NSSM binary."
    $script:ExpectedSha256 = (Get-FileHash $binary -Algorithm SHA256).Hash
    $checks++

    $nativeCalls = @()
    $sid = "S-1-5-80-101-202-303-404-505"
    Microsoft.PowerShell.Management\New-Item -ItemType Directory -Path $install, $logs | Out-Null
    $installedBinary = Join-Path $install "nssm.exe"
    $existingLog = Join-Path $logs "worker-out.log"
    Set-Content -LiteralPath $installedBinary -Value "fixture"
    Set-Content -LiteralPath $existingLog -Value "fixture"
    $script:privateAcls = @{}
    foreach ($path in @($install, $logs, $installedBinary, $existingLog)) {
        $isRoot = $path -in @($install, $logs)
        $workerRights = if ($path -in @($install, $installedBinary)) { "ReadAndExecute" } else { "Modify" }
        $rules = @()
        foreach ($identity in @("S-1-5-18", "S-1-5-32-544", $sid)) {
            $rights = if ($identity -eq $sid) { $workerRights } else { "FullControl" }
            $rules += [pscustomobject] @{
                IdentityReference = [pscustomobject] @{ Value = $identity }
                FileSystemRights = [Security.AccessControl.FileSystemRights] $rights
                AccessControlType = [Security.AccessControl.AccessControlType]::Allow
                PropagationFlags = [Security.AccessControl.PropagationFlags]::None
                InheritanceFlags = [Security.AccessControl.InheritanceFlags] "ContainerInherit, ObjectInherit"
            }
        }
        $acl = [pscustomobject] @{ AreAccessRulesProtected = $isRoot; Rules = $rules }
        $acl | Add-Member ScriptMethod GetAccessRules { param($Explicit, $Inherited, $TargetType); return $this.Rules }
        $privateAcls[$path] = $acl
    }
    function Get-Acl {
        param([string] $LiteralPath, [string] $ErrorAction)
        if (-not $privateAcls.ContainsKey($LiteralPath)) { throw "Unexpected ACL read: $LiteralPath" }
        return $privateAcls[$LiteralPath]
    }
    Grant-WorkerFileAccess $app $php $install $logs $sid
    Assert-True ($nativeCalls.Count -eq 8) "Private root grants and inheritance changes must use separate calls."
    Assert-True (($nativeCalls[0].Arguments -contains "*${sid}:(OI)(CI)RX") -and ($nativeCalls[1].Arguments -contains "*${sid}:(OI)(CI)RX")) "App and PHP require read/execute only."
    Assert-True (($nativeCalls[2].Arguments -contains "*${sid}:(OI)(CI)M") -and ($nativeCalls[3].Arguments -contains "*${sid}:(OI)(CI)M")) "Only application runtime directories require Modify."
    foreach ($index in @(4, 6)) {
        Assert-True (($nativeCalls[$index].Arguments -contains "/grant:r") -and ($nativeCalls[$index + 1].Arguments -contains "/inheritance:r")) "Grant access before removing inherited root permissions."
        Assert-True (-not ($nativeCalls[$index].Arguments -contains "/T") -and -not ($nativeCalls[$index + 1].Arguments -contains "/T")) "Private ACL setup must preserve child inheritance."
    }
    Assert-True ($nativeCalls[4].Arguments -contains "*${sid}:(OI)(CI)RX") "Permanent NSSM must have read/execute access."
    Assert-True ($nativeCalls[6].Arguments -contains "*${sid}:(OI)(CI)M") "Private logs must have worker Modify access."
    Assert-Throws { Grant-WorkerFileAccess $app $php $install $logs "S-1-5-18" } "ACLs must reject broad built-in identities."
    $nativeCalls = @()
    $failNativeCall = 2
    Assert-Throws { Grant-WorkerFileAccess $app $php $install $logs $sid } "ACL failure must stop setup."
    Assert-True ($nativeCalls.Count -eq 2) "ACL changes must stop at the first failure."
    $checks++

    # Native exit zero is insufficient: validate the actual child ACL, including
    # an empty DACL and disabled inheritance as seen on the staging server.
    $goodRules = $privateAcls[$installedBinary].Rules
    $privateAcls[$installedBinary].Rules = @()
    Assert-Throws { Assert-WorkerPrivateDirectoryAccess $install $sid "RX" } "Empty executable ACL must fail even if icacls succeeded."
    $privateAcls[$installedBinary].Rules = $goodRules
    $privateAcls[$installedBinary].AreAccessRulesProtected = $true
    Assert-Throws { Assert-WorkerPrivateDirectoryAccess $install $sid "RX" } "Protected child must not silently miss future inherited permissions."
    $privateAcls[$installedBinary].AreAccessRulesProtected = $false
    $privateAcls[$installedBinary].Rules[2].FileSystemRights = [Security.AccessControl.FileSystemRights]::Modify
    Assert-Throws { Assert-WorkerPrivateDirectoryAccess $install $sid "RX" } "Worker must not be able to rewrite its service executable."
    $privateAcls[$installedBinary].Rules[2].FileSystemRights = [Security.AccessControl.FileSystemRights]::ReadAndExecute
    $privateAcls[$installedBinary].Rules[2].AccessControlType = [Security.AccessControl.AccessControlType]::Deny
    Assert-Throws { Assert-WorkerPrivateDirectoryAccess $install $sid "RX" } "Deny entries must not count as granted access."
    $privateAcls[$installedBinary].Rules[2].AccessControlType = [Security.AccessControl.AccessControlType]::Allow
    $privateAcls[$existingLog].Rules[2].FileSystemRights = [Security.AccessControl.FileSystemRights]::ReadAndExecute
    Assert-Throws { Assert-WorkerPrivateDirectoryAccess $logs $sid "M" } "Existing log files must actually be writable by the worker."
    $privateAcls[$existingLog].Rules[2].FileSystemRights = [Security.AccessControl.FileSystemRights]::Modify
    Assert-WorkerPrivateDirectoryAccess $install $sid "RX"
    Assert-WorkerPrivateDirectoryAccess $logs $sid "M"
    $checks++

    Assert-True ($ast.Extent.Text -match 'Assert-NssmChecksum \$permanentBinary \$ExpectedSha256[\s\S]*Assert-RepairableWorker') "Permanent binary checksum verification must precede partial-repair validation."
    $commands = @($ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.CommandAst] }, $true))
    Assert-True (-not ($commands | Where-Object { $_.GetCommandName() -eq "Start-Service" })) "Installer must never start a worker or consume queued jobs."
    $create = $commands | Where-Object { $_.GetCommandName() -eq "New-Service" }
    Assert-True ($create.Count -eq 1 -and $create.Extent.Text -match '-StartupType Manual') "Service must be created Manual from the outset."
    Assert-True ($ast.Extent.Text -match 'Set-Service -Name \$ServiceName -StartupType Disabled -ErrorAction Continue') "Incomplete setup must disable the new service."
    $checks++
    Write-Host "Install-RfcQueueWorker.ps1: parser passed; $checks portable regression scenarios passed."
}
finally { Remove-Item -LiteralPath $testRoot -Recurse -Force }
