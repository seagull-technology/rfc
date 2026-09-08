param(
    [string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Install-RfcQueueWorker.ps1")
)

# Portable checks of the real installer helpers. No service, registry, or Windows
# ACL changes occur; the main installer body is parsed but never executed.
$ErrorActionPreference = "Stop"
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path $ScriptPath).Path, [ref] $tokens, [ref] $parseErrors)
if ($parseErrors.Count -gt 0) { throw (($parseErrors | ForEach-Object { $_.Message }) -join [Environment]::NewLine) }
foreach ($name in @("Assert-NssmChecksum", "Assert-NssmVersion", "Assert-SeparateWorkerPaths", "Assert-NoExistingWorker", "Invoke-WorkerNative", "Get-WorkerArguments", "Set-WorkerConfiguration", "Resolve-WorkerVirtualAccountSid", "Grant-WorkerFileAccess", "Assert-WorkerIsStaged")) {
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

    function Test-FailingNative { $global:LASTEXITCODE = 5; "native failure" }
    Assert-Throws { Invoke-WorkerNative "Test-FailingNative" @() } "Native setup failures must abort."
    $checks++

    $script:nativeCalls = @()
    $script:failNativeCall = 0
    function Invoke-WorkerNative {
        param([string] $Executable, [string[]] $Arguments)
        $script:nativeCalls += [pscustomobject] @{ Executable = $Executable; Arguments = $Arguments }
        if ($failNativeCall -eq $nativeCalls.Count) { throw "mock native failure" }
    }
    function Set-WorkerApplicationParameters {
        param([string] $Name, [string] $Parameters)
        $script:workerParameters = $Parameters
    }
    Set-WorkerConfiguration $binary "RFCQueueWorker" $app (Join-Path $php "php.exe") $logs
    Assert-True ($workerParameters.StartsWith('"' + (Join-Path $app "artisan") + '" queue:work ')) "Artisan must be absolute and preserve spaces."
    Assert-True ($workerParameters -notmatch '--force\b') "Worker must honor application maintenance mode."
    $configuration = @($nativeCalls | ForEach-Object { $_.Arguments -join "|" })
    foreach ($setting in @("set|RFCQueueWorker|AppExit|Default|Restart", "set|RFCQueueWorker|AppExit|0|Restart", "set|RFCQueueWorker|ObjectName|NT SERVICE\RFCQueueWorker", "set|RFCQueueWorker|AppRotateBytes|10485760")) {
        Assert-True ($configuration -contains $setting) "Missing required worker setting: $setting"
    }
    Assert-True (@($nativeCalls | Where-Object { $_.Executable -eq "sc.exe" }).Count -eq 2) "Wrapper failure recovery must be configured."
    $checks++

    $nativeCalls = @()
    $sid = "S-1-5-80-101-202-303-404-505"
    Grant-WorkerFileAccess $app $php $install $logs $sid
    Assert-True ($nativeCalls.Count -eq 6) "ACL changes must stay within six required scopes."
    Assert-True (($nativeCalls[0].Arguments -contains "*${sid}:(OI)(CI)RX") -and ($nativeCalls[1].Arguments -contains "*${sid}:(OI)(CI)RX")) "App and PHP require read/execute only."
    Assert-True (($nativeCalls[2].Arguments -contains "*${sid}:(OI)(CI)M") -and ($nativeCalls[3].Arguments -contains "*${sid}:(OI)(CI)M")) "Only application runtime directories require Modify."
    Assert-True (($nativeCalls[4].Arguments -contains "/inheritance:r") -and ($nativeCalls[4].Arguments -contains "*${sid}:(OI)(CI)RX")) "Permanent NSSM must have protected read/execute access."
    Assert-True (($nativeCalls[5].Arguments -contains "/inheritance:r") -and ($nativeCalls[5].Arguments -contains "*${sid}:(OI)(CI)M")) "Private logs must have protected worker Modify access."
    Assert-Throws { Grant-WorkerFileAccess $app $php $install $logs "S-1-5-18" } "ACLs must reject broad built-in identities."
    $nativeCalls = @()
    $failNativeCall = 2
    Assert-Throws { Grant-WorkerFileAccess $app $php $install $logs $sid } "ACL failure must stop setup."
    Assert-True ($nativeCalls.Count -eq 2) "ACL changes must stop at the first failure."
    $checks++

    $commands = @($ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.CommandAst] }, $true))
    Assert-True (-not ($commands | Where-Object { $_.GetCommandName() -eq "Start-Service" })) "Installer must never start a worker or consume queued jobs."
    $create = $commands | Where-Object { $_.GetCommandName() -eq "New-Service" }
    Assert-True ($create.Count -eq 1 -and $create.Extent.Text -match '-StartupType Manual') "Service must be created Manual from the outset."
    Assert-True ($ast.Extent.Text -match 'Set-Service -Name \$ServiceName -StartupType Disabled -ErrorAction Continue') "Incomplete setup must disable the new service."
    $checks++
    Write-Host "Install-RfcQueueWorker.ps1: parser passed; $checks portable regression scenarios passed."
}
finally { Remove-Item -LiteralPath $testRoot -Recurse -Force }
