param([string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Deploy-RfcStagingVerification.ps1"))

# Portable helper checks only. The real Windows orchestration body never executes.
$ErrorActionPreference = "Stop"
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path -LiteralPath $ScriptPath).Path, [ref] $tokens, [ref] $parseErrors)
if ($parseErrors.Count) { throw (($parseErrors | ForEach-Object { $_.Message }) -join [Environment]::NewLine) }
foreach ($name in @("Write-RfcPrivateText", "Invoke-RfcVerificationNative", "Read-RfcVerifiedBundle", "Assert-RfcRuntimeFile", "Assert-RfcWorkflowResult")) {
    $definition = $ast.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] }, $true) |
        Where-Object { $_.Name -eq $name } | Select-Object -First 1
    if (-not $definition) { throw "Missing helper: $name" }
    . ([ScriptBlock]::Create($definition.Extent.Text))
}
function Assert-True { param([bool] $Condition, [string] $Message); if (-not $Condition) { throw $Message } }
function Assert-Throws {
    param([ScriptBlock] $Action, [string] $Message)
    $failed = $false
    try { & $Action | Out-Null } catch { $failed = $true }
    Assert-True $failed $Message
}
$testRoot = Join-Path ([IO.Path]::GetTempPath()) ("rfc-orchestration-test-" + [Guid]::NewGuid().ToString("N"))
New-Item -ItemType Directory $testRoot | Out-Null
$checks = 0
function New-TestBundle {
    $path = Join-Path $testRoot ([Guid]::NewGuid().ToString("N"))
    New-Item -ItemType Directory $path | Out-Null
    foreach ($name in @("rfc-app.tar.gz", "Deploy-RfcRelease.ps1", "Deploy-RfcStagingVerification.ps1", "SOURCE-COMMIT.txt")) {
        Write-RfcPrivateText (Join-Path $path $name) "Reviewed fixture bytes for $name"
    }
    $manifest = @{ schema = "rfc-offline-release-v1"; release = "rfc-offline-release-20260912-v2"; source_commit = ("a" * 40); runtime_files = @{} }
    Write-RfcPrivateText (Join-Path $path "BUILD-MANIFEST.json") ($manifest | ConvertTo-Json -Depth 5)
    $lines = @(Get-ChildItem -LiteralPath $path -File | Sort-Object Name | ForEach-Object {
        (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant() + "  " + $_.Name
    })
    Write-RfcPrivateText (Join-Path $path "SHA256SUMS.txt") (($lines -join "`n") + "`n")
    return $path
}
try {
    $bundlePath = New-TestBundle
    $checksumsHash = (Get-FileHash (Join-Path $bundlePath "SHA256SUMS.txt") -Algorithm SHA256).Hash
    $verified = Read-RfcVerifiedBundle $bundlePath $checksumsHash
    Assert-True ($verified.Manifest.release -eq "rfc-offline-release-20260912-v2") "Verified manifest was not returned."
    Assert-Throws { Read-RfcVerifiedBundle $bundlePath ("0" * 64) } "Untrusted checksum inventory must fail."
    Write-RfcPrivateText (Join-Path $bundlePath "rfc-app.tar.gz") "Tampered archive"
    Assert-Throws { Read-RfcVerifiedBundle $bundlePath $checksumsHash } "Tampered archive must fail."
    $checks++

    $bundlePath = New-TestBundle
    $sumsPath = Join-Path $bundlePath "SHA256SUMS.txt"
    $firstLine = [IO.File]::ReadAllLines($sumsPath)[0]
    [IO.File]::AppendAllText($sumsPath, $firstLine + "`n")
    Assert-Throws { Read-RfcVerifiedBundle $bundlePath (Get-FileHash $sumsPath -Algorithm SHA256).Hash } "Duplicate checksum filenames must fail."
    Write-RfcPrivateText $sumsPath (("a" * 64) + "  ../outside.txt`n")
    Assert-Throws { Read-RfcVerifiedBundle $bundlePath (Get-FileHash $sumsPath -Algorithm SHA256).Hash } "Path traversal in checksum entries must fail."
    $checks++

    $runtimePath = Join-Path $testRoot "runtime"
    New-Item -ItemType Directory (Join-Path $runtimePath "scripts") -Force | Out-Null
    $runtimeFile = Join-Path $runtimePath "scripts/check.php"
    Write-RfcPrivateText $runtimeFile "Reviewed helper bytes"
    $runtimeManifest = @{ runtime_files = @{ 'scripts/check.php' = (Get-FileHash $runtimeFile -Algorithm SHA256).Hash } } | ConvertTo-Json | ConvertFrom-Json
    Assert-RfcRuntimeFile $runtimePath $runtimeManifest "scripts/check.php"
    Write-RfcPrivateText $runtimeFile "Changed helper bytes"
    Assert-Throws { Assert-RfcRuntimeFile $runtimePath $runtimeManifest "scripts/check.php" } "Changed deployed helper must fail."
    Assert-Throws { Assert-RfcRuntimeFile $runtimePath $runtimeManifest "scripts/missing.php" } "Missing manifest entry must fail."
    $checks++

    $workflow = @{ passed = $true; locale = "ar"; database_rolled_back = $true; fixture_records_absent = $true; temporary_files_removed = $true; checks = @(1..16 | ForEach-Object { @{ passed = $true } }) }
    Assert-RfcWorkflowResult ($workflow | ConvertTo-Json -Depth 4) "ar" | Out-Null
    Assert-Throws { Assert-RfcWorkflowResult ($workflow | ConvertTo-Json -Depth 4) "en" } "Wrong locale must fail."
    foreach ($field in @("passed", "database_rolled_back", "fixture_records_absent", "temporary_files_removed")) {
        $workflow[$field] = $false
        Assert-Throws { Assert-RfcWorkflowResult ($workflow | ConvertTo-Json -Depth 4) "ar" } "Failed $field must not become success."
        $workflow[$field] = $true
    }
    $workflow.checks = @(1..15 | ForEach-Object { @{ passed = $true } })
    Assert-Throws { Assert-RfcWorkflowResult ($workflow | ConvertTo-Json -Depth 4) "ar" } "An incomplete check set must fail."
    $checks++

    $pwshExe = (Get-Process -Id $PID).Path
    $originalPreference = $ErrorActionPreference
    $global:LASTEXITCODE = 91
    $text = Invoke-RfcVerificationNative $pwshExe @("-NoProfile", "-NonInteractive", "-Command", "[Console]::Error.WriteLine('private success diagnostic'); exit 0") (Join-Path $testRoot "success.log") "Fixture"
    Assert-True ($text -match "private success diagnostic") "Successful stderr was lost."
    Assert-True ($ErrorActionPreference -eq $originalPreference) "Native invocation changed caller error handling."
    $checks++

    $caught = $null
    try {
        Invoke-RfcVerificationNative $pwshExe @("-NoProfile", "-NonInteractive", "-Command", "[Console]::Error.WriteLine('private failure diagnostic'); exit 17") (Join-Path $testRoot "failure.log") "Fixture" | Out-Null
    } catch { $caught = $_.Exception.Message }
    Assert-True ($caught -match 'exit 17' -and $caught -notmatch 'private failure diagnostic') "Nonzero exit must fail without exposing native diagnostics."
    Assert-True ([IO.File]::ReadAllText((Join-Path $testRoot "failure.log")) -match 'private failure diagnostic') "Private failed output was not saved."
    Assert-True ($ErrorActionPreference -eq $originalPreference) "Native failure changed caller error handling."
    $checks++

    Assert-Throws { Invoke-RfcVerificationNative (Join-Path $testRoot "missing.exe") @() (Join-Path $testRoot "missing.log") "Missing fixture" } "Missing native executable must fail."
    $checks++
    Write-Host "$checks portable orchestration checks passed; main Windows body was not run."
}
finally {
    Remove-Item -LiteralPath $testRoot -Recurse -Force
}
