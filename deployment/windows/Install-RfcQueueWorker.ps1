param(
    [Parameter(Mandatory = $true)]
    [string] $NssmPath,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-fA-F0-9]{64}$')]
    [string] $ExpectedSha256,

    [ValidatePattern('^[A-Za-z][A-Za-z0-9_-]{0,79}$')]
    [string] $ServiceName = "RFCQueueWorker",
    [string] $AppPath = "C:\inetpub\rfc",
    [string] $PhpExe = "C:\php\php.exe",
    [string] $InstallPath = "C:\Program Files\RFC\QueueWorker",
    [string] $LogPath = "C:\ProgramData\RFC\QueueWorker\Logs",
    [switch] $RepairIncomplete
)

# Run in an elevated Windows PowerShell session. This installer deliberately
# leaves the new service Manual and Stopped until the release and .env are ready.
# NSSM must be 2.24-101 or newer; verify ExpectedSha256 against the reviewed binary.
# Official settings: https://nssm.cc/usage and https://nssm.cc/commands
$ErrorActionPreference = "Stop"

function Assert-NssmChecksum {
    param([string] $Path, [string] $Expected)
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) { throw "NSSM binary not found: $Path" }
    if ($Expected -notmatch '^[a-fA-F0-9]{64}$' -or (Get-FileHash -LiteralPath $Path -Algorithm SHA256).Hash -ne $Expected) {
        throw "NSSM SHA256 mismatch. No service has been configured with this binary."
    }
}

function Assert-NssmVersion {
    param([string] $VersionText)
    if ($VersionText -notmatch '(?i)\bNSSM\s+(\d+)\.(\d+)(?:-(\d+))?\b') { throw "Unrecognized NSSM version: $VersionText" }
    $major = [int] $Matches[1]
    $minor = [int] $Matches[2]
    $build = if ($Matches[3]) { [int] $Matches[3] } else { 0 }
    if ($major -lt 2 -or ($major -eq 2 -and ($minor -lt 24 -or ($minor -eq 24 -and $build -lt 101)))) {
        throw "NSSM 2.24-101 or newer is required for virtual accounts and current Windows servers."
    }
}

function Assert-SeparateWorkerPaths {
    param([string[]] $Paths)
    for ($i = 0; $i -lt $Paths.Count; $i++) {
        $left = [IO.Path]::GetFullPath($Paths[$i]).TrimEnd([char[]] '\/')
        if ($left -eq [IO.Path]::GetPathRoot($left).TrimEnd([char[]] '\/')) { throw "A worker directory cannot be a drive root." }
        for ($j = $i + 1; $j -lt $Paths.Count; $j++) {
            $right = [IO.Path]::GetFullPath($Paths[$j]).TrimEnd([char[]] '\/')
            $separator = [IO.Path]::DirectorySeparatorChar
            if ($left.Equals($right, [StringComparison]::OrdinalIgnoreCase) -or
                $left.StartsWith($right + $separator, [StringComparison]::OrdinalIgnoreCase) -or
                $right.StartsWith($left + $separator, [StringComparison]::OrdinalIgnoreCase)) {
                throw "App, PHP, permanent NSSM, and private log directories must be separate: $left / $right"
            }
        }
    }
}

function Assert-NoExistingWorker {
    param([string] $Name)
    if (Get-CimInstance -ClassName Win32_Service -Filter "Name='$Name'") {
        throw "Service $Name already exists. Inspect it separately; this installer will not overwrite it."
    }
}

function Invoke-WorkerNative {
    param([string] $Executable, [string[]] $Arguments)
    # Windows PowerShell 5.1 treats redirected native stderr as ErrorRecords.
    # Capture all diagnostics before deciding success from the native exit code.
    $command = Get-Command -Name $Executable -CommandType Application -ErrorAction Stop
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = "Continue"
        # Native processes update the global automatic variable; a local
        # LASTEXITCODE assignment would mask the returned status in a function.
        $global:LASTEXITCODE = $null
        $output = & $command.Source @Arguments 2>&1
        $exitCode = $global:LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousPreference
    }
    if ($null -eq $exitCode -or $exitCode -ne 0) { throw "Worker setup command failed ($exitCode): $Executable $($Arguments -join ' ') $output" }
    return $output
}

function Get-WorkerArguments {
    param([string] $ApplicationPath)
    return ('"' + (Join-Path $ApplicationPath "artisan") + '" queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600')
}

function Set-WorkerApplicationParameters {
    param([string] $Name, [string] $Parameters)
    # Write the literal command line to avoid PowerShell 5.1/native executable
    # nested-quote conversion breaking an artisan path containing spaces.
    $key = "HKLM:\SYSTEM\CurrentControlSet\Services\$Name\Parameters"
    if (-not (Test-Path -LiteralPath $key)) { New-Item -Path $key | Out-Null }
    New-ItemProperty -Path $key -Name "AppParameters" -Value $Parameters -PropertyType ExpandString -Force | Out-Null
}

function Initialize-WorkerRegistration {
    param([string] $Name, [string] $ApplicationPath, [string] $PhpPath)
    # New-Service creates the SCM entry only. NSSM's set command first reads
    # Parameters\Application to recognize its service, so bootstrap this before
    # invoking NSSM. Never force-create an existing registry key: that erases it.
    $key = "HKLM:\SYSTEM\CurrentControlSet\Services\$Name\Parameters"
    if (-not (Test-Path -LiteralPath $key)) { New-Item -Path $key | Out-Null }
    New-ItemProperty -Path $key -Name "Application" -Value $PhpPath -PropertyType ExpandString -Force | Out-Null
    New-ItemProperty -Path $key -Name "AppDirectory" -Value $ApplicationPath -PropertyType ExpandString -Force | Out-Null
    Set-WorkerApplicationParameters $Name (Get-WorkerArguments $ApplicationPath)
}

function Assert-RepairableWorker {
    param([string] $Name, [string] $PermanentBinary, [string] $ApplicationPath, [string] $PhpPath, [string] $Logs)
    $service = Get-CimInstance -ClassName Win32_Service -Filter "Name='$Name'"
    if (-not $service -or $service.State -ne "Stopped" -or $service.StartMode -ne "Disabled" -or
        $service.Description -ne "RFC Laravel database queue worker") {
        throw "Repair requires the disabled, stopped service left by this setup. No service will be replaced."
    }
    $imagePath = ([string] $service.PathName).Trim()
    if ($imagePath -ine $PermanentBinary -and $imagePath -ine ('"' + $PermanentBinary + '"')) {
        throw "The disabled service points to an unexpected executable. Repair refused."
    }
    if ($service.StartName -notin @("LocalSystem", "NT AUTHORITY\SYSTEM", "NT SERVICE\$Name")) {
        throw "The disabled service uses an unexpected account. Repair refused."
    }
    $key = "HKLM:\SYSTEM\CurrentControlSet\Services\$Name\Parameters"
    if (Test-Path -LiteralPath $key) {
        $properties = Get-ItemProperty -LiteralPath $key -ErrorAction Stop
        $expected = @{
            Application = $PhpPath; AppDirectory = $ApplicationPath
            AppParameters = (Get-WorkerArguments $ApplicationPath)
            AppStdout = (Join-Path $Logs "worker-out.log")
            AppStderr = (Join-Path $Logs "worker-error.log")
        }
        foreach ($entry in $expected.GetEnumerator()) {
            $existing = $properties.PSObject.Properties[$entry.Key]
            if ($existing -and [string] $existing.Value -ine [string] $entry.Value) {
                throw "Existing $($entry.Key) points to a different worker configuration. Repair refused."
            }
        }
    }
}

function Set-WorkerConfiguration {
    param([string] $Binary, [string] $Name, [string] $ApplicationPath, [string] $PhpPath, [string] $Logs)
    Initialize-WorkerRegistration $Name $ApplicationPath $PhpPath
    $settings = @(
        @("Application", $PhpPath), @("AppDirectory", $ApplicationPath),
        @("ObjectName", "NT SERVICE\$Name"),
        @("AppExit", "Default", "Restart"), @("AppExit", "0", "Restart"),
        @("AppRestartDelay", "5000"), @("AppThrottle", "1500"),
        @("AppStdout", (Join-Path $Logs "worker-out.log")),
        @("AppStderr", (Join-Path $Logs "worker-error.log")),
        @("AppStdoutCreationDisposition", "4"), @("AppStderrCreationDisposition", "4"),
        @("AppRotateFiles", "1"), @("AppRotateOnline", "1"), @("AppRotateBytes", "10485760"),
        @("AppStopMethodSkip", "0"), @("AppStopMethodConsole", "130000")
    )
    foreach ($setting in $settings) {
        Invoke-WorkerNative $Binary (@("set", $Name) + $setting) | Out-Null
    }
    Set-WorkerApplicationParameters $Name (Get-WorkerArguments $ApplicationPath)
    # Restart the wrapper after a wrapper crash as well as restarting PHP after
    # Laravel queue:restart or --max-time returns exit code zero.
    Invoke-WorkerNative "sc.exe" @("failure", $Name, "reset=", "86400", "actions=", "restart/5000/restart/15000/restart/60000") | Out-Null
    Invoke-WorkerNative "sc.exe" @("failureflag", $Name, "1") | Out-Null
}

function Assert-WorkerRegistration {
    param([string] $Name, [string] $ApplicationPath, [string] $PhpPath, [string] $Logs)
    $key = "HKLM:\SYSTEM\CurrentControlSet\Services\$Name\Parameters"
    $properties = Get-ItemProperty -LiteralPath $key -ErrorAction Stop
    $expected = @{
        Application = $PhpPath; AppDirectory = $ApplicationPath
        AppParameters = (Get-WorkerArguments $ApplicationPath)
        AppStdout = (Join-Path $Logs "worker-out.log")
        AppStderr = (Join-Path $Logs "worker-error.log")
        AppRestartDelay = "5000"
    }
    foreach ($entry in $expected.GetEnumerator()) {
        $actual = $properties.PSObject.Properties[$entry.Key]
        if (-not $actual -or [string] $actual.Value -cne [string] $entry.Value) {
            throw "Worker registration verification failed for $($entry.Key). Do not start it."
        }
    }
}

function Resolve-WorkerVirtualAccountSid {
    param([string] $Name)
    $service = Get-CimInstance -ClassName Win32_Service -Filter "Name='$Name'"
    $expectedAccount = "NT SERVICE\$Name"
    if (-not $service -or $service.StartName -ine $expectedAccount) { throw "Service identity is not $expectedAccount. Do not start it." }
    $account = New-Object System.Security.Principal.NTAccount($expectedAccount)
    $sid = $account.Translate([System.Security.Principal.SecurityIdentifier]).Value
    if ($sid -notmatch '^S-1-5-80-\d+-\d+-\d+-\d+-\d+$') { throw "Could not resolve the exact virtual service SID." }
    return $sid
}

function Assert-WorkerPrivateDirectoryAccess {
    param([string] $Directory, [string] $Sid, [ValidateSet("RX", "M")] [string] $Permission)
    $workerRights = if ($Permission -eq "RX") { [Security.AccessControl.FileSystemRights]::ReadAndExecute } else { [Security.AccessControl.FileSystemRights]::Modify }
    $expected = @{
        "S-1-5-18" = [long] [Security.AccessControl.FileSystemRights]::FullControl
        "S-1-5-32-544" = [long] [Security.AccessControl.FileSystemRights]::FullControl
        $Sid = [long] $workerRights
    }
    $root = Get-Item -LiteralPath $Directory -Force -ErrorAction Stop
    if (-not $root.PSIsContainer) { throw "Expected a private worker directory: $Directory" }
    $items = @($root) + @(Get-ChildItem -LiteralPath $Directory -Force -Recurse -ErrorAction Stop)
    foreach ($item in $items) {
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Unexpected link in private worker directory: $($item.FullName)" }
        $acl = Get-Acl -LiteralPath $item.FullName -ErrorAction Stop
        $isRoot = $item.FullName -eq $root.FullName
        if ($acl.AreAccessRulesProtected -ne $isRoot) { throw "Unexpected worker ACL inheritance: $($item.FullName)" }
        $granted = @{}
        foreach ($rule in $acl.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier])) {
            $identity = $rule.IdentityReference.Value
            if ($rule.AccessControlType -ne [Security.AccessControl.AccessControlType]::Allow -or -not $expected.ContainsKey($identity)) {
                throw "Unexpected permission in private worker directory: $($item.FullName)"
            }
            if ($rule.PropagationFlags -band [Security.AccessControl.PropagationFlags]::InheritOnly) { continue }
            $granted[$identity] = [long] $granted[$identity] -bor [long] $rule.FileSystemRights
            if ($isRoot -and (($rule.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ContainerInherit) -eq 0 -or
                ($rule.InheritanceFlags -band [Security.AccessControl.InheritanceFlags]::ObjectInherit) -eq 0)) {
                throw "Private worker permissions must reach child files and directories: $Directory"
            }
        }
        foreach ($identity in $expected.Keys) {
            if (([long] $granted[$identity] -band $expected[$identity]) -ne $expected[$identity]) {
                throw "Missing required permissions for $identity on $($item.FullName)"
            }
        }
        $allowedWorkerRights = [long] $workerRights -bor [long] [Security.AccessControl.FileSystemRights]::Synchronize
        if (([long] $granted[$Sid] -band (-bnot $allowedWorkerRights)) -ne 0) {
            throw "Worker has excessive permissions on $($item.FullName)"
        }
    }
}

function Set-WorkerPrivateDirectoryAccess {
    param([string] $Directory, [string] $Sid, [ValidateSet("RX", "M")] [string] $Permission)
    if ($Sid -notmatch '^S-1-5-80-\d+-\d+-\d+-\d+-\d+$') { throw "An exact virtual service SID is required for worker ACLs." }
    # Set the root grants before removing inherited access. Never combine these
    # operations or remove inheritance recursively: that left nssm.exe with an
    # empty DACL on Windows even though icacls reported success. Descendants must
    # continue inheriting the restricted root's grants.
    Invoke-WorkerNative "icacls.exe" @($Directory, "/grant:r", "*S-1-5-18:(OI)(CI)F", "*S-1-5-32-544:(OI)(CI)F", "*${Sid}:(OI)(CI)$Permission", "/Q") | Out-Null
    Invoke-WorkerNative "icacls.exe" @($Directory, "/inheritance:r", "/Q") | Out-Null
    Assert-WorkerPrivateDirectoryAccess $Directory $Sid $Permission
}

function Grant-WorkerFileAccess {
    param([string] $ApplicationPath, [string] $PhpDirectory, [string] $BinaryDirectory, [string] $Logs, [string] $Sid)
    if ($Sid -notmatch '^S-1-5-80-\d+-\d+-\d+-\d+-\d+$') { throw "An exact virtual service SID is required for worker ACLs." }
    foreach ($path in @($ApplicationPath, $PhpDirectory)) {
        Invoke-WorkerNative "icacls.exe" @($path, "/grant", "*${Sid}:(OI)(CI)RX", "/T", "/Q") | Out-Null
    }
    foreach ($path in @((Join-Path $ApplicationPath "storage"), (Join-Path $ApplicationPath "bootstrap\cache"))) {
        Invoke-WorkerNative "icacls.exe" @($path, "/grant", "*${Sid}:(OI)(CI)M", "/T", "/Q") | Out-Null
    }
    # These two directories are newly created by this installer. Keep service
    # output private and the permanent service binary unwritable by the worker.
    Set-WorkerPrivateDirectoryAccess $BinaryDirectory $Sid "RX"
    Set-WorkerPrivateDirectoryAccess $Logs $Sid "M"
}

function Assert-WorkerIsStaged {
    param([string] $Name)
    $service = Get-CimInstance -ClassName Win32_Service -Filter "Name='$Name'"
    if (-not $service -or $service.State -ne "Stopped" -or $service.StartMode -ne "Manual" -or $service.StartName -ine "NT SERVICE\$Name") {
        throw "Worker staging verification failed: expected Manual, Stopped, and the exact virtual service account."
    }
}

if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT) { throw "Run this installer on the Windows application server." }
$principal = New-Object Security.Principal.WindowsPrincipal([Security.Principal.WindowsIdentity]::GetCurrent())
if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) { throw "Run Windows PowerShell as Administrator." }

$AppPath = (Resolve-Path -LiteralPath $AppPath).Path
$PhpExe = (Resolve-Path -LiteralPath $PhpExe).Path
$NssmPath = (Resolve-Path -LiteralPath $NssmPath).Path
$InstallPath = [IO.Path]::GetFullPath($InstallPath)
$LogPath = [IO.Path]::GetFullPath($LogPath)
$phpDirectory = Split-Path $PhpExe -Parent
Assert-SeparateWorkerPaths @($AppPath, $phpDirectory, $InstallPath, $LogPath)
foreach ($path in @((Join-Path $AppPath "artisan"), (Join-Path $AppPath ".env"), $PhpExe)) {
    if (-not (Test-Path -LiteralPath $path -PathType Leaf)) { throw "Required application file is missing: $path" }
}
foreach ($path in @((Join-Path $AppPath "storage"), (Join-Path $AppPath "bootstrap\cache"))) {
    if (-not (Test-Path -LiteralPath $path -PathType Container)) { throw "Required runtime directory is missing: $path" }
}
Assert-NssmChecksum $NssmPath $ExpectedSha256
Assert-NssmVersion ((Invoke-WorkerNative $NssmPath @("version")) -join " ")
$permanentBinary = Join-Path $InstallPath "nssm.exe"
if ($RepairIncomplete) {
    Assert-NssmChecksum $permanentBinary $ExpectedSha256
    foreach ($path in @($InstallPath, $LogPath)) {
        if (-not (Test-Path -LiteralPath $path -PathType Container)) { throw "Expected setup directory is missing: $path" }
    }
    Assert-RepairableWorker $ServiceName $permanentBinary $AppPath $PhpExe $LogPath
}
else {
    Assert-NoExistingWorker $ServiceName
    foreach ($path in @($InstallPath, $LogPath)) {
        if (Test-Path -LiteralPath $path) { throw "Dedicated worker directory already exists: $path. Inspect it before setup; no files will be overwritten." }
    }
}

$createdService = [bool] $RepairIncomplete
try {
    if (-not $RepairIncomplete) {
        New-Item -ItemType Directory -Path $InstallPath, $LogPath -Force | Out-Null
        Copy-Item -LiteralPath $NssmPath -Destination $permanentBinary
        Assert-NssmChecksum $permanentBinary $ExpectedSha256
        # Native creation keeps the service stopped; initialize NSSM's registry
        # identity before its first set command. Failed setup stays disabled.
        New-Service -Name $ServiceName -BinaryPathName ('"' + $permanentBinary + '"') -StartupType Manual -DisplayName "RFC Queue Worker" -Description "RFC Laravel database queue worker" | Out-Null
        $createdService = $true
    }
    Set-WorkerConfiguration $permanentBinary $ServiceName $AppPath $PhpExe $LogPath
    Assert-WorkerRegistration $ServiceName $AppPath $PhpExe $LogPath
    $workerSid = Resolve-WorkerVirtualAccountSid $ServiceName
    Grant-WorkerFileAccess $AppPath $phpDirectory $InstallPath $LogPath $workerSid
    Assert-NssmChecksum $permanentBinary $ExpectedSha256
    Set-Service -Name $ServiceName -StartupType Manual
    Assert-WorkerIsStaged $ServiceName
}
catch {
    if ($createdService) {
        # Retain the stopped service and files for inspection, but prevent a
        # partially configured worker from starting after a reboot.
        Set-Service -Name $ServiceName -StartupType Disabled -ErrorAction Continue
        Write-Warning "Setup did not complete. $ServiceName has been disabled; inspect the error before repairing or removing this newly created service."
    }
    throw
}

Write-Host "Worker staged: $ServiceName / NT SERVICE\$ServiceName / Manual / Stopped"
Write-Host "Permanent NSSM: $permanentBinary (do not move or delete it)"
Write-Host "Private logs: $LogPath (10 MiB rotation; arrange retention separately)"
Write-Host "Complete the release deployment and .env checks before starting the worker."
Write-Host "After successful deployment and worker verification: Set-Service -Name $ServiceName -StartupType Automatic"
Write-Host "Then verify: Get-Service -Name $ServiceName | Select-Object Name, Status, StartType"
