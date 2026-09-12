param([string] $ScriptPath = (Join-Path $PSScriptRoot "..\..\deployment\windows\Deploy-RfcStagingVerification.ps1"))

# Portable helper checks only. The real Windows orchestration body never executes.
$ErrorActionPreference = "Stop"
$tokens = $null
$parseErrors = $null
$ast = [System.Management.Automation.Language.Parser]::ParseFile((Resolve-Path -LiteralPath $ScriptPath).Path, [ref] $tokens, [ref] $parseErrors)
if ($parseErrors.Count) { throw (($parseErrors | ForEach-Object { $_.Message }) -join [Environment]::NewLine) }
foreach ($name in @("Write-RfcPrivateText", "Invoke-RfcVerificationNative", "Get-RfcActiveMailerState", "Repair-RfcSmtpMailerEnvironment", "Read-RfcVerifiedBundle", "Assert-RfcRuntimeFile", "Assert-RfcWorkflowResult")) {
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
function New-TestMailerState {
    return [pscustomobject] @{
        schema = "rfc-mailer-state-v1"; default_is_upper_smtp = $true; default_is_lower_smtp = $false
        lower_smtp_defined = $true; upper_smtp_defined = $false; configuration_cached = $true
        process_mailer_override_present = $false; alternate_environment_file_present = $false
    }
}
function Assert-FileBytes {
    param([string] $Path, [byte[]] $Expected, [string] $Message)
    Assert-True ([Convert]::ToBase64String([IO.File]::ReadAllBytes($Path)) -ceq [Convert]::ToBase64String($Expected)) $Message
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

    # Real temporary .env files: four letters change; BOM, CRLF, Unicode and other values do not.
    $envPath = Join-Path $testRoot ".env"
    $backupPath = Join-Path $testRoot "env-original.private.bak"
    foreach ($encoding in @(
        (New-Object System.Text.UTF8Encoding($false, $true)), (New-Object System.Text.UTF8Encoding($true, $true)),
        (New-Object System.Text.UnicodeEncoding($false, $true, $true)), (New-Object System.Text.UnicodeEncoding($true, $true, $true))
    )) {
        foreach ($literal in @('SMTP', '"SMTP"', "'SMTP'")) {
            $before = "# MAIL_MAILER=SMTP`r`nSMTP_NOTE=`"SMTP stays unchanged`"`r`nOTHER_VALUE=`"MAIL_MAILER=SMTP`"`r`nUNICODE_LABEL=`"مراجعة`"`r`n  MAIL_MAILER = $literal `r`nTAIL=SMTP"
            $after = $before.Replace("  MAIL_MAILER = $literal ", ("  MAIL_MAILER = " + $literal.Replace('SMTP', 'smtp') + " "))
            $originalBytes = [byte[]] ($encoding.GetPreamble() + $encoding.GetBytes($before))
            $expectedBytes = [byte[]] ($encoding.GetPreamble() + $encoding.GetBytes($after))
            [IO.File]::WriteAllBytes($envPath, $originalBytes)
            $repair = Repair-RfcSmtpMailerEnvironment $envPath $backupPath (New-TestMailerState)
            Assert-True ($repair.changed -eq $true -and $repair.require_smtp_after_deploy -eq $true) "Expected the exact SMTP correction."
            Assert-FileBytes $envPath $expectedBytes "Mailer correction changed unrelated bytes, encoding, quotes or CRLF."
            Assert-FileBytes $backupPath $originalBytes "Private backup did not preserve original bytes."
            Remove-Item -LiteralPath $backupPath
        }
    }
    $checks++

    foreach ($literal in @('smtp', '"smtp"', "'smtp'")) {
        $originalBytes = [Text.Encoding]::UTF8.GetBytes("MAIL_MAILER=$literal`nUNCHANGED=SMTP`n")
        [IO.File]::WriteAllBytes($envPath, $originalBytes)
        $repair = Repair-RfcSmtpMailerEnvironment $envPath $backupPath (New-TestMailerState)
        Assert-True (-not $repair.changed -and $repair.require_smtp_after_deploy -and $repair.status -eq 'env-already-correct-awaiting-cache-refresh') "Cached SMTP with corrected smtp must proceed without an edit."
        Assert-FileBytes $envPath $originalBytes "An already corrected environment must not be rewritten."
        Assert-True (-not (Test-Path -LiteralPath $backupPath)) "An unchanged environment must not create a repair backup."
    }
    $checks++

    foreach ($invalid in @(
        "MAIL_MAILER=SMTP`r`nMAIL_MAILER=smtp`r`n", "# MAIL_MAILER=SMTP`r`nOTHER_VALUE=SMTP`r`n",
        "MAIL_MAILER=SMTP # inline comment`r`n", 'MAIL_MAILER="SMTP" # inline comment',
        'MAIL_MAILER=Smtp', 'MAIL_MAILER=customSMTP', 'MAIL_MAILER=${CUSTOM_MAILER}', 'MAIL_MAILER',
        "MAIL_MAILER=SMTP`nexport `"MAIL_MAILER`"=SMTP`n", "MULTILINE=`"first`nMAIL_MAILER=SMTP`nlast`"`n",
        "MAIL_MAILER=SMTP`nMULTILINE=`"first`nlast`"`n", "MAIL_MAILER=SMTP`nNUL=$([char] 0)`n"
    )) {
        $originalBytes = [Text.Encoding]::UTF8.GetBytes($invalid)
        [IO.File]::WriteAllBytes($envPath, $originalBytes)
        $caught = $null
        try { Repair-RfcSmtpMailerEnvironment $envPath $backupPath (New-TestMailerState) | Out-Null } catch { $caught = $_.Exception.Message }
        Assert-True ($null -ne $caught) "Ambiguous or unsupported environment syntax must fail."
        Assert-FileBytes $envPath $originalBytes "Rejected environment syntax must leave original bytes unchanged."
        Assert-True (-not (Test-Path -LiteralPath $backupPath)) "A rejected repair must not create a backup."
    }
    $checks++

    $originalBytes = [Text.Encoding]::UTF8.GetBytes("MAIL_MAILER=SMTP`r`n")
    [IO.File]::WriteAllBytes($envPath, $originalBytes)
    foreach ($field in @('default_is_upper_smtp', 'upper_smtp_defined', 'lower_smtp_defined', 'process_mailer_override_present', 'alternate_environment_file_present')) {
        $state = New-TestMailerState
        $state.$field = -not $state.$field
        if ($field -in @('default_is_upper_smtp', 'upper_smtp_defined')) {
            $repair = Repair-RfcSmtpMailerEnvironment $envPath $backupPath $state
            Assert-True (-not $repair.changed -and -not $repair.require_smtp_after_deploy) "Unrelated defaults or a defined custom SMTP mailer must be preserved."
        } else {
            Assert-Throws { Repair-RfcSmtpMailerEnvironment $envPath $backupPath $state } "Missing smtp or ambiguous server overrides must block repair."
        }
        Assert-FileBytes $envPath $originalBytes "Configuration gate must not change the environment."
        Assert-True (-not (Test-Path -LiteralPath $backupPath)) "Configuration gate must not create a backup."
    }
    Write-RfcPrivateText $backupPath "Existing backup must be retained"
    Assert-Throws { Repair-RfcSmtpMailerEnvironment $envPath $backupPath (New-TestMailerState) } "An existing backup must not be overwritten."
    Assert-FileBytes $envPath $originalBytes "Backup conflict must prevent an environment change."
    Assert-True ([IO.File]::ReadAllText($backupPath) -ceq 'Existing backup must be retained') "Existing private backup was overwritten."
    Remove-Item -LiteralPath $backupPath
    $checks++

    $phpRoot = Join-Path $testRoot "php-configuration-fixture"
    New-Item -ItemType Directory (Join-Path $phpRoot "vendor") -Force | Out-Null
    New-Item -ItemType Directory (Join-Path $phpRoot "bootstrap") -Force | Out-Null
    Write-RfcPrivateText (Join-Path $phpRoot "vendor/autoload.php") "<?php"
    $bootstrap = @'
<?php
function config(string $key): mixed {
    return ['mail.default' => 'SMTP', 'mail.mailers' => ['smtp' => ['transport' => 'smtp', 'password' => 'private-config-marker']]][$key];
}
return new class {
    public function make(string $name): object { return new class { public function bootstrap(): void {} }; }
    public function configurationIsCached(): bool { return true; }
};
'@
    Write-RfcPrivateText (Join-Path $phpRoot "bootstrap/app.php") $bootstrap
    $state = Get-RfcActiveMailerState "php" $phpRoot $testRoot "fixture"
    Assert-True ($state.default_is_upper_smtp -and $state.lower_smtp_defined -and -not $state.upper_smtp_defined -and $state.configuration_cached) "Active PHP mailer preflight returned incorrect safe state."
    Assert-True ([IO.File]::ReadAllText((Join-Path $testRoot "mailer-fixture.json")) -notmatch 'private-config-marker') "Mailer preflight printed a private configuration value."
    Write-RfcPrivateText (Join-Path $phpRoot "vendor/autoload.php") "<?php throw new RuntimeException('private-config-marker');"
    $caught = $null
    try { Get-RfcActiveMailerState "php" $phpRoot $testRoot "failed-fixture" | Out-Null } catch { $caught = $_.Exception.Message }
    Assert-True ($caught -match 'exit 1' -and $caught -notmatch 'private-config-marker') "PHP bootstrap failure must be reported without its private exception."
    Assert-True ([IO.File]::ReadAllText((Join-Path $testRoot "mailer-failed-fixture.json")) -notmatch 'private-config-marker') "PHP bootstrap exception leaked configuration into preflight output."
    $checks++
    Write-Host "$checks portable orchestration checks passed; main Windows body was not run."
}
finally {
    Remove-Item -LiteralPath $testRoot -Recurse -Force
}
