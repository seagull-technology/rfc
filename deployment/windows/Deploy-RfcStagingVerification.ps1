param(
    [Parameter(Mandatory = $true)]
    [string] $ReleaseDirectory,

    [Parameter(Mandatory = $true)]
    [ValidatePattern('^[a-fA-F0-9]{64}$')]
    [string] $ExpectedChecksumsSha256
)

# Windows staging release + bounded internal verification. Run elevated on RFCtgWeb.
# The operator has waived an additional database backup for this staging release.
# The existing deployer still retains the previous application and handles swap failures.
$ErrorActionPreference = "Stop"
$AppPath = "C:\inetpub\rfc"
$PhpExe = "C:\php\php.exe"
$QueueServiceName = "RFCQueueWorker"
$ExpectedAppUrl = "https://filmjordan.jo"
$evidenceDirectory = $null
$exitCode = 1
$summary = [ordered] @{
    started_utc = [DateTime]::UtcNow.ToString("o")
    status = "preflight"
    deployment_completed = $false
    public_security_closure = $false
}

function Write-RfcPrivateText {
    param([string] $Path, [string] $Text)
    [IO.File]::WriteAllText($Path, $Text, (New-Object System.Text.UTF8Encoding($false)))
}

function Invoke-RfcVerificationNative {
    param([string] $Executable, [string[]] $Arguments, [string] $LogPath, [string] $Label)
    $application = Get-Command -Name $Executable -CommandType Application -ErrorAction Stop | Select-Object -First 1
    $previousPreference = $ErrorActionPreference
    $global:LASTEXITCODE = $null
    try {
        $ErrorActionPreference = "Continue"
        $output = & $application.Source @Arguments 2>&1
        $nativeExitCode = $global:LASTEXITCODE
    }
    finally {
        $ErrorActionPreference = $previousPreference
    }
    $text = ($output | ForEach-Object { [string] $_ }) -join [Environment]::NewLine
    Write-RfcPrivateText $LogPath ($text + [Environment]::NewLine)
    if ($null -eq $nativeExitCode -or $nativeExitCode -ne 0) {
        throw "$Label failed (exit $nativeExitCode). Private output: $LogPath"
    }
    return $text
}

function Get-RfcActiveMailerState {
    param([string] $Executable, [string] $Directory, [string] $EvidenceDirectory, [string] $Phase)
    # A file avoids Windows native -r quoting. Only booleans leave this PHP process.
    $scriptPath = Join-Path $EvidenceDirectory "mailer-state.php"
    $php = @'
<?php
declare(strict_types=1);
try {
    $root = rtrim($argv[1], '/\\');
    $processOverride = getenv('MAIL_MAILER') !== false || isset($_ENV['MAIL_MAILER']) || isset($_SERVER['MAIL_MAILER']);
    $externalEnvironment = getenv('APP_ENV');
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $default = config('mail.default');
    $mailers = config('mail.mailers');
    if (!is_string($default) || !is_array($mailers)) {
        throw new RuntimeException('Unexpected mailer configuration shape.');
    }
    $selected = $mailers[$default] ?? null;
    $smtpSelected = is_array($selected) && ($selected['transport'] ?? null) === 'smtp';
    $smtpSchemeSupported = false;
    $smtpConstructible = false;
    if ($smtpSelected) {
        try {
            if (isset($selected['url'])) {
                if (is_string($selected['url']) && class_exists(App\Support\SmtpUrl::class)
                    && App\Support\SmtpUrl::hasAmbiguousScheme($selected['url'])) {
                    throw new RuntimeException('Ambiguous SMTP scheme query options.');
                }
                $selected = (new Illuminate\Support\ConfigurationUrlParser)->parseConfiguration($selected);
                $selected['transport'] = $selected['driver'] ?? null;
            }
            $scheme = $selected['scheme'] ?? null;
            $smtpSchemeSupported = ($selected['transport'] ?? null) === 'smtp'
                && ($scheme === null || $scheme === '' || in_array($scheme, ['smtp', 'smtps'], true));
            if ($smtpSchemeSupported) {
                // Build the transport only; this never opens the SMTP socket or sends mail.
                (new Illuminate\Mail\MailManager($app))->createSymfonyTransport($selected);
                $smtpConstructible = true;
            }
        } catch (Throwable) {
            // Return booleans only, including when a malformed URL contains credentials.
        }
    }
    echo json_encode([
        'schema' => 'rfc-mailer-state-v1',
        'default_is_upper_smtp' => $default === 'SMTP',
        'default_is_lower_smtp' => $default === 'smtp',
        'lower_smtp_defined' => isset($mailers['smtp']) && is_array($mailers['smtp']),
        'upper_smtp_defined' => array_key_exists('SMTP', $mailers),
        'smtp_transport_selected' => $smtpSelected,
        'smtp_scheme_supported' => $smtpSchemeSupported,
        'smtp_transport_constructible' => $smtpConstructible,
        'configuration_cached' => $app->configurationIsCached(),
        'process_mailer_override_present' => $processOverride,
        'alternate_environment_file_present' => is_string($externalEnvironment) && is_file($root.'/.env.'.$externalEnvironment),
    ], JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, "Mailer configuration preflight failed; no configuration values were printed.\n");
    exit(1);
}
'@
    Write-RfcPrivateText $scriptPath $php
    $json = Invoke-RfcVerificationNative $Executable @("-d", "display_errors=0", $scriptPath, $Directory) (Join-Path $EvidenceDirectory "mailer-$Phase.json") "Mailer configuration $Phase"
    try { $state = $json | ConvertFrom-Json } catch { throw "Mailer configuration $Phase did not return the expected safe JSON." }
    if ($state.schema -ne "rfc-mailer-state-v1") { throw "Unexpected mailer configuration result." }
    foreach ($field in @("default_is_upper_smtp", "default_is_lower_smtp", "lower_smtp_defined", "upper_smtp_defined", "smtp_transport_selected", "smtp_scheme_supported", "smtp_transport_constructible", "configuration_cached", "process_mailer_override_present", "alternate_environment_file_present")) {
        if ($state.$field -isnot [bool]) { throw "Incomplete mailer configuration result." }
    }
    return $state
}

function Assert-RfcActiveSmtpTransport {
    param([object] $ActiveState)
    if ($ActiveState.smtp_transport_selected -eq $true -and
        ($ActiveState.smtp_scheme_supported -ne $true -or $ActiveState.smtp_transport_constructible -ne $true)) {
        throw "The deployed SMTP transport is invalid. Review effective MAIL_SCHEME and MAIL_URL overrides; no credentials or mail were sent by this check."
    }
}

function Repair-RfcSmtpMailerEnvironment {
    param([string] $EnvPath, [string] $BackupPath, [object] $ActiveState)
    if ($ActiveState.default_is_upper_smtp -ne $true) {
        return [ordered] @{ status = "not-needed"; changed = $false; require_smtp_after_deploy = $false }
    }
    if ($ActiveState.upper_smtp_defined -eq $true) {
        return [ordered] @{ status = "custom-uppercase-mailer-preserved"; changed = $false; require_smtp_after_deploy = $false }
    }
    if ($ActiveState.lower_smtp_defined -ne $true) { throw "Automatic mailer correction requires an existing lowercase smtp mailer configuration." }
    if ($ActiveState.process_mailer_override_present -eq $true -or $ActiveState.alternate_environment_file_present -eq $true) {
        throw "A process-level mailer override or alternate environment file makes .env correction ambiguous. Review that server configuration; no settings changed."
    }
    if ((Get-Item -LiteralPath $EnvPath -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Automatic mailer correction does not support a linked .env file." }

    $stream = $null
    $writeAttempted = $false
    try {
        # Retain the existing file/ACL and exclude concurrent writers while changing four letters.
        $stream = [IO.File]::Open($EnvPath, [IO.FileMode]::Open, [IO.FileAccess]::ReadWrite, [IO.FileShare]::Read)
        if ($stream.Length -gt 1048576) { throw "Automatic mailer correction does not support an .env file larger than 1 MiB." }
        $original = New-Object byte[] ([int] $stream.Length)
        $read = 0
        while ($read -lt $original.Length) {
            $count = $stream.Read($original, $read, $original.Length - $read)
            if ($count -eq 0) { throw "Could not read the complete .env file; no settings changed." }
            $read += $count
        }
        $offset = 0
        $encoding = New-Object System.Text.UTF8Encoding($false, $true)
        if ($original.Length -ge 4 -and (($original[0] -eq 0xFF -and $original[1] -eq 0xFE -and $original[2] -eq 0 -and $original[3] -eq 0) -or
            ($original[0] -eq 0 -and $original[1] -eq 0 -and $original[2] -eq 0xFE -and $original[3] -eq 0xFF))) {
            throw "Automatic mailer correction does not support UTF-32 .env encoding."
        }
        if ($original.Length -ge 3 -and $original[0] -eq 0xEF -and $original[1] -eq 0xBB -and $original[2] -eq 0xBF) { $offset = 3 }
        elseif ($original.Length -ge 2 -and $original[0] -eq 0xFF -and $original[1] -eq 0xFE) {
            $encoding = New-Object System.Text.UnicodeEncoding($false, $true, $true); $offset = 2
        }
        elseif ($original.Length -ge 2 -and $original[0] -eq 0xFE -and $original[1] -eq 0xFF) {
            $encoding = New-Object System.Text.UnicodeEncoding($true, $true, $true); $offset = 2
        }
        try { $content = $encoding.GetString($original, $offset, $original.Length - $offset) }
        catch { throw "Automatic mailer correction could not decode the .env encoding; no settings changed." }
        if ($content.IndexOf([char] 0) -ge 0) { throw "Automatic mailer correction does not support NUL bytes in .env." }

        $assignments = @()
        foreach ($line in [regex]::Matches($content, '[^\r\n]+')) {
            if ($line.Value -match '^\s*(?:#|$)') { continue }
            $separator = $line.Value.IndexOf('=')
            $rawKey = if ($separator -ge 0) { $line.Value.Substring(0, $separator) } else { $line.Value }
            $key = ($rawKey.Trim() -creplace '^export[ \t]+', '').Trim()
            if ($key.Length -ge 2 -and (($key[0] -eq '"' -and $key[$key.Length - 1] -eq '"') -or
                ($key[0] -eq "'" -and $key[$key.Length - 1] -eq "'"))) { $key = $key.Substring(1, $key.Length - 2) }
            if ($separator -lt 0) {
                if ($key -ceq "MAIL_MAILER") { throw "MAIL_MAILER requires one explicit single-line assignment; no settings changed." }
                continue
            }
            $value = $line.Value.Substring($separator + 1)
            $trimmed = $value.TrimStart([char[]] " `t")
            # Reject multiline quoted values before selecting any matching text within them.
            if ($trimmed.Length -gt 0 -and $trimmed[0] -in @([char] '"', [char] "'")) {
                $quote = $trimmed[0]
                $closed = $false
                for ($i = 1; $i -lt $trimmed.Length; $i++) {
                    if ($quote -eq '"' -and $trimmed[$i] -eq '\') { $i++; continue }
                    if ($trimmed[$i] -eq $quote) { $closed = $true; break }
                }
                if (-not $closed) { throw "Automatic mailer correction requires single-line .env values; no settings changed." }
            }
            if ($key -ceq "MAIL_MAILER") {
                $assignments += [pscustomobject] @{ Value = $value; Start = $line.Index + $separator + 1 }
            }
        }
        if ($assignments.Count -ne 1) { throw "Automatic mailer correction requires exactly one active MAIL_MAILER assignment; no settings changed." }
        $setting = [regex]::Match($assignments[0].Value, '^[ \t]*(?<quote>[''"]?)(?<setting>SMTP|smtp)\k<quote>[ \t]*$')
        if (-not $setting.Success) { throw "MAIL_MAILER must contain only literal SMTP or smtp, optionally quoted. Inline comments and other values require review; no settings changed." }
        if ($setting.Groups['setting'].Value -ceq "smtp") {
            return [ordered] @{ status = "env-already-correct-awaiting-cache-refresh"; changed = $false; require_smtp_after_deploy = $true }
        }
        $characterOffset = $assignments[0].Start + $setting.Groups['setting'].Index
        $byteOffset = $offset + $encoding.GetByteCount($content.Substring(0, $characterOffset))
        $replacement = $encoding.GetBytes("smtp")
        if (Test-Path -LiteralPath $BackupPath) { throw "The private pre-repair environment backup already exists; no settings changed." }
        [IO.File]::WriteAllBytes($BackupPath, $original)
        if ([Convert]::ToBase64String([IO.File]::ReadAllBytes($BackupPath)) -cne [Convert]::ToBase64String($original)) { throw "Private environment backup verification failed; no settings changed." }
        $expected = [byte[]] $original.Clone()
        [Array]::Copy($replacement, 0, $expected, $byteOffset, $replacement.Length)
        $stream.Position = $byteOffset
        $writeAttempted = $true
        $stream.Write($replacement, 0, $replacement.Length)
        $stream.Flush($true)
        $stream.Position = 0
        $verified = New-Object byte[] $original.Length
        $read = 0
        while ($read -lt $verified.Length) {
            $count = $stream.Read($verified, $read, $verified.Length - $read)
            if ($count -eq 0) { throw "Incomplete mailer correction verification." }
            $read += $count
        }
        if ([Convert]::ToBase64String($verified) -cne [Convert]::ToBase64String($expected)) { throw "Mailer correction byte verification failed." }
        return [ordered] @{ status = "corrected-uppercase-smtp"; changed = $true; require_smtp_after_deploy = $true; private_backup = $BackupPath }
    }
    catch {
        if ($writeAttempted) {
            try { $stream.Position = 0; $stream.Write($original, 0, $original.Length); $stream.SetLength($original.Length); $stream.Flush($true) }
            catch { throw "Mailer correction failed and original bytes could not be restored. Use the private backup: $BackupPath" }
            throw "Mailer correction failed; original environment bytes were restored. Private backup: $BackupPath"
        }
        throw
    }
    finally { if ($stream) { $stream.Dispose() } }
}

function Read-RfcVerifiedBundle {
    param([string] $Directory, [string] $ChecksumsSha256)
    $checksumsPath = Join-Path $Directory "SHA256SUMS.txt"
    if ((Get-FileHash -LiteralPath $checksumsPath -Algorithm SHA256).Hash -ine $ChecksumsSha256) {
        throw "SHA256SUMS.txt does not match the separately supplied release checksum."
    }
    $hashes = @{}
    foreach ($line in [IO.File]::ReadAllLines($checksumsPath)) {
        if ($line -notmatch '^([a-fA-F0-9]{64})  ([A-Za-z0-9_.-]+)$' -or $Matches[2] -in @('.', '..')) {
            throw "Invalid or unsafe entry in SHA256SUMS.txt."
        }
        $hash = $Matches[1]
        $name = $Matches[2]
        if ($hashes.ContainsKey($name)) { throw "Duplicate bundle checksum entry: $name" }
        $path = Join-Path $Directory $name
        if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ine $hash) {
            throw "Bundle checksum mismatch: $name"
        }
        $hashes[$name] = $hash
    }
    foreach ($required in @("rfc-app.tar.gz", "Deploy-RfcRelease.ps1", "Deploy-RfcStagingVerification.ps1", "BUILD-MANIFEST.json", "SOURCE-COMMIT.txt")) {
        if (-not $hashes.ContainsKey($required)) { throw "Required bundle file is not covered by SHA256SUMS.txt: $required" }
    }
    $manifest = [IO.File]::ReadAllText((Join-Path $Directory "BUILD-MANIFEST.json")) | ConvertFrom-Json
    if ($manifest.schema -ne "rfc-offline-release-v1" -or $manifest.source_commit -notmatch '^[a-f0-9]{40}$' -or
        $manifest.release -notmatch '^rfc-offline-release-[A-Za-z0-9][A-Za-z0-9._-]*$') {
        throw "Unsupported or incomplete build manifest."
    }
    return [pscustomobject] @{ Hashes = $hashes; Manifest = $manifest }
}

function Assert-RfcRuntimeFile {
    param([string] $Directory, [object] $Manifest, [string] $RelativePath)
    $entry = $Manifest.runtime_files.PSObject.Properties[$RelativePath]
    if (-not $entry -or [string] $entry.Value -notmatch '^[a-fA-F0-9]{64}$') {
        throw "Runtime file is missing from the verified manifest: $RelativePath"
    }
    $path = Join-Path $Directory ($RelativePath.Replace('/', [IO.Path]::DirectorySeparatorChar))
    if ((Get-FileHash -LiteralPath $path -Algorithm SHA256).Hash -ine [string] $entry.Value) {
        throw "Deployed file does not match the verified release: $RelativePath"
    }
}

function Assert-RfcWorkflowResult {
    param([string] $Json, [string] $Locale)
    $result = $Json | ConvertFrom-Json
    if ($result.passed -ne $true -or $result.locale -ne $Locale -or $result.database_rolled_back -ne $true -or
        $result.fixture_records_absent -ne $true -or $result.temporary_files_removed -ne $true -or
        @($result.checks).Count -lt 16 -or @($result.checks | Where-Object { $_.passed -ne $true }).Count -ne 0) {
        throw "Workflow $Locale verification or cleanup is incomplete. Inspect its private JSON output."
    }
    return $result
}

function New-RfcPrivateEvidenceDirectory {
    $parent = "C:\ProgramData\RFC\SecurityVerification"
    $name = (Get-Date -Format "yyyyMMdd-HHmmss") + "-" + [Guid]::NewGuid().ToString("N").Substring(0, 8)
    $path = Join-Path $parent $name
    New-Item -ItemType Directory -Path $path -Force | Out-Null
    $acl = New-Object Security.AccessControl.DirectorySecurity
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($sidText in @("S-1-5-18", "S-1-5-32-544")) {
        $sid = New-Object Security.Principal.SecurityIdentifier($sidText)
        $rule = New-Object Security.AccessControl.FileSystemAccessRule($sid, "FullControl", "ContainerInherit,ObjectInherit", "None", "Allow")
        $acl.AddAccessRule($rule)
    }
    Set-Acl -LiteralPath $path -AclObject $acl
    $actual = Get-Acl -LiteralPath $path
    if (-not $actual.AreAccessRulesProtected) { throw "Evidence directory inheritance could not be restricted." }
    $rules = @($actual.GetAccessRules($true, $true, [Security.Principal.SecurityIdentifier]))
    if ($rules.Count -ne 2 -or @($rules | Where-Object {
        $_.IdentityReference.Value -notin @("S-1-5-18", "S-1-5-32-544") -or
        $_.AccessControlType -ne "Allow" -or $_.FileSystemRights -ne "FullControl"
    }).Count -ne 0) { throw "Evidence directory access differs from the intended administrator/SYSTEM permissions." }
    return $path
}

function Get-RfcHealthyWorker {
    $service = Get-CimInstance Win32_Service -Filter "Name='RFCQueueWorker'"
    if (-not $service -or $service.State -ne "Running" -or $service.StartMode -ne "Auto" -or
        $service.StartName -ine "NT SERVICE\RFCQueueWorker" -or $service.ProcessId -le 0) {
        throw "Expected the existing RFCQueueWorker to be Running/Automatic under its virtual service account. No installation or account change was attempted."
    }
    $workers = @(Get-CimInstance Win32_Process -Filter "ParentProcessId=$($service.ProcessId)" | Where-Object {
        $_.ExecutablePath -ieq $PhpExe -and $_.CommandLine -and
        $_.CommandLine.IndexOf((Join-Path $AppPath "artisan"), [StringComparison]::OrdinalIgnoreCase) -ge 0 -and
        $_.CommandLine -match 'queue:work' -and $_.CommandLine -notmatch '(?:^|\s)"?--force(?:[=\s"]|$)'
    })
    if ($workers.Count -ne 1) { throw "Expected one managed PHP queue worker for this application. Inspect service health before continuing." }
    return [ordered] @{ name = $service.Name; state = $service.State; start_mode = $service.StartMode; managed_php_workers = $workers.Count }
}

try {
    if ([Environment]::OSVersion.Platform -ne [PlatformID]::Win32NT -or [Environment]::MachineName -ine "RFCtgWeb") {
        throw "This orchestration is restricted to the RFCtgWeb Windows staging server."
    }
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        throw "Run this script in Administrator PowerShell."
    }
    $ReleaseDirectory = (Resolve-Path -LiteralPath $ReleaseDirectory).Path
    Set-Location (Split-Path $AppPath -Parent)
    $bundle = Read-RfcVerifiedBundle $ReleaseDirectory $ExpectedChecksumsSha256
    if ((Get-FileHash -LiteralPath $PSCommandPath -Algorithm SHA256).Hash -ine $bundle.Hashes["Deploy-RfcStagingVerification.ps1"]) {
        throw "The running orchestration script differs from the verified release."
    }
    $summary['release'] = $bundle.Manifest.release
    $summary['source_commit'] = $bundle.Manifest.source_commit
    $summary['app_archive_sha256'] = $bundle.Hashes["rfc-app.tar.gz"]
    $summary['worker_before'] = Get-RfcHealthyWorker
    $evidenceDirectory = New-RfcPrivateEvidenceDirectory
    $summary['evidence_directory'] = $evidenceDirectory
    $summary['mailer_before'] = Get-RfcActiveMailerState $PhpExe $AppPath $evidenceDirectory "before"
    $summary['mailer_repair'] = Repair-RfcSmtpMailerEnvironment (Join-Path $AppPath ".env") (Join-Path $evidenceDirectory "env-before-mailer-repair.private.bak") $summary.mailer_before
    Write-Host "Mailer configuration preflight: $($summary.mailer_repair.status)."
    Write-Host "Verified package and existing worker. Deploying $($bundle.Manifest.release)."
    $summary.status = "deployment"
    $powerShellExe = Join-Path $env:windir "System32\WindowsPowerShell\v1.0\powershell.exe"
    Invoke-RfcVerificationNative $powerShellExe @(
        "-NoProfile", "-NonInteractive", "-ExecutionPolicy", "Bypass", "-File", (Join-Path $ReleaseDirectory "Deploy-RfcRelease.ps1"),
        "-ArchivePath", (Join-Path $ReleaseDirectory "rfc-app.tar.gz"), "-ExpectedSha256", $bundle.Hashes["rfc-app.tar.gz"],
        "-SiteName", "RFC", "-AppPath", $AppPath, "-PhpExe", $PhpExe, "-QueueServiceName", $QueueServiceName
    ) (Join-Path $evidenceDirectory "deployment-private.log") "Deployment" | Out-Null
    $summary.deployment_completed = $true
    $summary.status = "verification"
    Write-Host "Deployment completed. Checking the deployed helpers and application configuration."
    foreach ($relative in @(
        "scripts/verify-staging-security.php", "scripts/verify-staging-workflows.php", "app/Http/Controllers/ScoutingRequestController.php",
        "resources/views/layouts/partials/lodash.blade.php", "public/js/lodash.min.js"
    )) { Assert-RfcRuntimeFile $AppPath $bundle.Manifest $relative }
    $summary['worker_after_deploy'] = Get-RfcHealthyWorker
    $summary['mailer_after'] = Get-RfcActiveMailerState $PhpExe $AppPath $evidenceDirectory "after"
    if ($summary.mailer_repair.require_smtp_after_deploy -and ($summary.mailer_after.default_is_lower_smtp -ne $true -or $summary.mailer_after.lower_smtp_defined -ne $true)) {
        throw "Deployment did not activate the corrected smtp configuration. Inspect server-level environment overrides before retrying."
    }
    # Recognized MAIL_SCHEME case is normalized by the new config on cache rebuild.
    # Do not rewrite .env/MAIL_URL or credentials to perform that normalization.
    Assert-RfcActiveSmtpTransport $summary.mailer_after
    Invoke-RfcVerificationNative $PhpExe @((Join-Path $AppPath "artisan"), "security:production-check", "--no-interaction") (Join-Path $evidenceDirectory "configuration-check.log") "Production configuration check" | Out-Null

    Write-Host "Running isolated controller, identity and concurrency checks; response checks cover Arabic and English."
    $controllerText = Invoke-RfcVerificationNative $PhpExe @(
        (Join-Path $AppPath "scripts\verify-staging-security.php"), "--staging-test", "--app-path=$AppPath",
        "--expected-app-url=$ExpectedAppUrl", "--samples=3"
    ) (Join-Path $evidenceDirectory "controller-check.log") "Internal controller verification"
    $runMatches = [regex]::Matches($controllerText, '(?m)^Run (sv-[a-f0-9]{16}):')
    if ($runMatches.Count -ne 1) { throw "Controller verification did not identify exactly one evidence run." }
    $runId = $runMatches[0].Groups[1].Value
    $controllerDirectory = Join-Path $AppPath "storage\app\private\security-verification\$runId"
    $controllerResult = [IO.File]::ReadAllText((Join-Path $controllerDirectory "results.json")) | ConvertFrom-Json
    $cleanup = [IO.File]::ReadAllText((Join-Path $controllerDirectory "cleanup.json")) | ConvertFrom-Json
    if ($controllerResult.run_id -ne $runId -or $controllerResult.internal_checks_passed -ne $true -or
        $cleanup.run_id -ne $runId -or $cleanup.status -ne "completed") {
        throw "Controller verification or exact fixture cleanup is incomplete."
    }
    Copy-Item -LiteralPath (Join-Path $controllerDirectory "results.json") -Destination (Join-Path $evidenceDirectory "controller-results.json")
    Copy-Item -LiteralPath (Join-Path $controllerDirectory "cleanup.json") -Destination (Join-Path $evidenceDirectory "controller-cleanup.json")
    $summary['controller_run_id'] = $runId
    $summary['controller_checks_passed'] = $true
    $summary['concurrent_review'] = $controllerResult.concurrent_review.status

    foreach ($locale in @("ar", "en")) {
        Write-Host "Running $locale form navigation, upload, save, edit and submission checks."
        $workflowText = Invoke-RfcVerificationNative $PhpExe @(
            (Join-Path $AppPath "scripts\verify-staging-workflows.php"), "--app-root=$AppPath", "--confirm-staging", "--locale=$locale"
        ) (Join-Path $evidenceDirectory "workflows-$locale.json") "Workflow $locale verification"
        $workflow = Assert-RfcWorkflowResult $workflowText $locale
        $summary["workflow_${locale}_checks_passed"] = @($workflow.checks).Count
    }
    Invoke-RfcVerificationNative $PhpExe @((Join-Path $AppPath "artisan"), "security:evidence", "--label=$($bundle.Manifest.release)", "--no-interaction") (Join-Path $evidenceDirectory "security-evidence.log") "Security evidence generation" | Out-Null
    $summary['worker_final'] = Get-RfcHealthyWorker
    $summary.status = "deployment-and-internal-checks-completed"
    $exitCode = 0
    if ($summary.concurrent_review -ne "passed") {
        $summary.status = "deployment-completed-concurrency-not-proven"
        $exitCode = 2
        Write-Warning "Deployment and completed checks passed, but database concurrency was not proven. Inspect controller-results.json."
    }
    Write-Host "Application remains live; RFCQueueWorker is Running/Automatic."
    Write-Host "Private evidence: $evidenceDirectory"
    Write-Host "These internal checks do not close public TLS, gateway cookies/DNS, browser behavior or public timing findings."
}
catch {
    $summary.status = "failed"
    $summary['failure'] = $_.Exception.Message
    Write-Warning $_.Exception.Message
    if ($summary.deployment_completed) {
        Write-Warning "Deployment completed before this verification failure. The new release remains deployed; no automatic application rollback or worker reinstall was attempted."
    }
    else {
        Write-Warning "Deployment was not confirmed successful. Check the private deployment log and current IIS/worker state before retrying."
    }
    if ($summary.mailer_repair -and $summary.mailer_repair.changed) {
        Write-Warning "The narrow SMTP-to-smtp environment correction is retained even if deployment failed. The original private backup is recorded in the summary; active cached configuration may still need a successful deployment refresh."
    }
}
finally {
    $summary['finished_utc'] = [DateTime]::UtcNow.ToString("o")
    $summaryJson = $summary | ConvertTo-Json -Depth 8
    Write-Host "Verification summary (private command logs are not printed):"
    Write-Output $summaryJson
    if ($evidenceDirectory -and (Test-Path -LiteralPath $evidenceDirectory)) {
        Write-RfcPrivateText (Join-Path $evidenceDirectory "summary.json") ($summaryJson + [Environment]::NewLine)
        Write-Host "Summary: $(Join-Path $evidenceDirectory 'summary.json')"
    }
}
exit $exitCode
