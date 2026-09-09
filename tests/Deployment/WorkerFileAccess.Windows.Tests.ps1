param(
    [string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Install-RfcQueueWorker.ps1")
)

# Windows-only integration test of actual NTFS inheritance. It creates no service
# and touches only a uniquely named temporary fixture directory. Run elevated.
$ErrorActionPreference = "Stop"
if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT) {
    Write-Host "SKIP: WorkerFileAccess requires Windows and real NTFS ACLs. No ACL tests ran."
    return
}
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Write-Host "SKIP: WorkerFileAccess requires an elevated Windows PowerShell session. No ACL tests ran."
    return
}
$temporaryParent = [IO.Path]::GetFullPath([IO.Path]::GetTempPath())
$drive = New-Object IO.DriveInfo([IO.Path]::GetPathRoot($temporaryParent))
if ($drive.DriveFormat -ne "NTFS") {
    Write-Host "SKIP: The temporary directory must be on NTFS. No ACL tests ran."
    return
}

$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path -LiteralPath $ScriptPath).Path, [ref] $tokens, [ref] $parseErrors)
if ($parseErrors.Count -gt 0) { throw (($parseErrors | ForEach-Object { $_.Message }) -join [Environment]::NewLine) }
foreach ($name in @("Invoke-WorkerNative", "Set-WorkerPrivateDirectoryAccess", "Assert-WorkerPrivateDirectoryAccess")) {
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
    try { & $Action | Out-Null } catch { $threw = $true }
    Assert-True $threw $Message
}
function Get-NumericAccessRules {
    param([string] $Path)
    return (Get-Acl -LiteralPath $Path).GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])
}
function Assert-ActualWorkerRights {
    param([string] $Path, [string] $Sid, [ValidateSet("RX", "M")] [string] $Permission, [bool] $IsRoot)
    $acl = Get-Acl -LiteralPath $Path
    $rules = @(Get-NumericAccessRules $Path)
    $allowedIdentities = @("S-1-5-18", "S-1-5-32-544", $Sid)
    $workerRights = [long] 0
    foreach ($rule in $rules) {
        Assert-True ($allowedIdentities -contains $rule.IdentityReference.Value) "Unexpected broad or unrelated grant at $Path."
        Assert-True ($rule.AccessControlType -eq [Security.AccessControl.AccessControlType]::Allow) "Unexpected denied rights at $Path."
        if ($rule.IdentityReference.Value -eq $Sid) { $workerRights = $workerRights -bor [long] $rule.FileSystemRights }
        Assert-True ($rule.IsInherited -eq (-not $IsRoot)) "Incorrect explicit/inherited ACE at $Path."
    }
    Assert-True ($acl.AreAccessRulesProtected -eq $IsRoot) "Only the private directory root should block inheritance at $Path."
    $minimum = if ($Permission -eq "RX") { [long] [Security.AccessControl.FileSystemRights]::ReadAndExecute } else { [long] [Security.AccessControl.FileSystemRights]::Modify }
    Assert-True (($workerRights -band $minimum) -eq $minimum) "The worker lacks required $Permission rights at $Path."
    if ($Permission -eq "RX") {
        $writeMask = [long] ([Security.AccessControl.FileSystemRights]::Write -bor
            [Security.AccessControl.FileSystemRights]::Delete -bor
            [Security.AccessControl.FileSystemRights]::DeleteSubdirectoriesAndFiles -bor
            [Security.AccessControl.FileSystemRights]::ChangePermissions -bor
            [Security.AccessControl.FileSystemRights]::TakeOwnership)
        Assert-True (($workerRights -band $writeMask) -eq 0) "The worker must not acquire write/delete/ACL ownership rights to its binary at $Path."
    }
}

# Numeric virtual-service SID requires no account registration or service. This
# fixture tests the ACLs themselves; it never impersonates or starts the worker.
$workerSid = "S-1-5-80-101-202-303-404-505"
$fixtureRoot = Join-Path $temporaryParent ("rfc-worker-acl-test-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory -Path $fixtureRoot | Out-Null
$checks = 0
try {
    $binaryDirectory = Join-Path $fixtureRoot "binary"
    New-Item -ItemType Directory -Path $binaryDirectory | Out-Null
    $binaryFile = Join-Path $binaryDirectory "nssm-fixture.bin"
    [IO.File]::WriteAllText($binaryFile, "Inert fixture created before ACL setup; never executed.")
    Set-WorkerPrivateDirectoryAccess -Directory $binaryDirectory -Sid $workerSid -Permission RX
    Assert-WorkerPrivateDirectoryAccess -Directory $binaryDirectory -Sid $workerSid -Permission RX
    Assert-ActualWorkerRights $binaryDirectory $workerSid RX $true
    Assert-ActualWorkerRights $binaryFile $workerSid RX $false
    $checks++

    $logDirectory = Join-Path $fixtureRoot "logs"
    New-Item -ItemType Directory -Path $logDirectory | Out-Null
    Set-WorkerPrivateDirectoryAccess -Directory $logDirectory -Sid $workerSid -Permission M
    $nestedLogs = Join-Path $logDirectory "rotated"
    New-Item -ItemType Directory -Path $nestedLogs | Out-Null
    $logFile = Join-Path $nestedLogs "worker-fixture.log"
    [IO.File]::WriteAllText($logFile, "Log created after ACL setup.")
    Assert-WorkerPrivateDirectoryAccess -Directory $logDirectory -Sid $workerSid -Permission M
    Assert-ActualWorkerRights $logDirectory $workerSid M $true
    Assert-ActualWorkerRights $nestedLogs $workerSid M $false
    Assert-ActualWorkerRights $logFile $workerSid M $false
    $checks++

    # Verify the real validator catches excessive worker rights, then restore the
    # intended root ACL through the real setup helper before the next scenario.
    Invoke-WorkerNative "icacls.exe" @($binaryDirectory, "/grant:r", "*${workerSid}:(OI)(CI)M", "/Q") | Out-Null
    Assert-Throws { Assert-WorkerPrivateDirectoryAccess $binaryDirectory $workerSid RX } "RX validation must reject an actual worker Modify grant."
    Set-WorkerPrivateDirectoryAccess $binaryDirectory $workerSid RX
    Assert-WorkerPrivateDirectoryAccess $binaryDirectory $workerSid RX
    $checks++

    # Give an actual child file an empty, protected DACL. Owner WRITE_DAC permits
    # the fixture cleanup to restore access; no machine or service ACL is touched.
    $emptyChild = Join-Path $binaryDirectory "empty-dacl-fixture.bin"
    [IO.File]::WriteAllText($emptyChild, "Deliberate invalid child ACL fixture.")
    $emptyAcl = Get-Acl -LiteralPath $emptyChild
    $emptyAcl.SetSecurityDescriptorSddlForm('D:P', [Security.AccessControl.AccessControlSections]::Access)
    Set-Acl -LiteralPath $emptyChild -AclObject $emptyAcl
    $observedAcl = Get-Acl -LiteralPath $emptyChild
    $rawDescriptor = [Security.AccessControl.RawSecurityDescriptor]::new($observedAcl.GetSecurityDescriptorBinaryForm(), 0)
    Assert-True ($null -ne $rawDescriptor.DiscretionaryAcl -and $rawDescriptor.DiscretionaryAcl.Count -eq 0) "The invalid-child fixture must have an empty DACL, not a null DACL that permits access."
    Assert-Throws { Assert-WorkerPrivateDirectoryAccess $binaryDirectory $workerSid RX } "A correct directory ACL must not hide an empty child-file ACL."
    $checks++

    # Observe the old combined recursive command on this Windows version. The
    # original empty-child behavior is not assumed to reproduce on every OS.
    $legacyDirectory = Join-Path $fixtureRoot "legacy"
    New-Item -ItemType Directory -Path $legacyDirectory | Out-Null
    $legacyFile = Join-Path $legacyDirectory "legacy-fixture.bin"
    [IO.File]::WriteAllText($legacyFile, "Inert fixture for the previous ACL command.")
    Invoke-WorkerNative "icacls.exe" @($legacyDirectory, "/inheritance:r", "/grant:r", "*S-1-5-18:(OI)(CI)F", "*S-1-5-32-544:(OI)(CI)F", "*${workerSid}:(OI)(CI)RX", "/T", "/Q") | Out-Null
    if (@(Get-NumericAccessRules $legacyFile).Count -eq 0) {
        Assert-Throws { Assert-WorkerPrivateDirectoryAccess $legacyDirectory $workerSid RX } "The validator must reject the old command's reproduced empty-child ACL."
        Write-Host "Observed and rejected the old combined command's empty child DACL."
    }
    else {
        Write-Host "The old command did not produce an empty child DACL on this OS; deliberate empty-DACL detection was tested separately."
    }
    $checks++

    Write-Host "$checks Windows NTFS ACL scenarios passed. No services were created or started."
}
finally {
    # This path was generated by this process below the temporary directory.
    # Restore Administrators only inside that exact disposable subtree, including
    # the intentionally protected child, before deleting the test fixtures.
    if (Test-Path -LiteralPath $fixtureRoot) {
        & icacls.exe $fixtureRoot /grant:r '*S-1-5-32-544:(OI)(CI)F' /T /C /Q | Out-Null
        if ($LASTEXITCODE -eq 0) {
            Remove-Item -LiteralPath $fixtureRoot -Recurse -Force
        }
        else {
            Write-Warning "Could not fully restore fixture cleanup access. Retained temporary test directory: $fixtureRoot"
        }
    }
}
