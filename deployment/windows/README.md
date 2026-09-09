# RFC Windows 11 Offline Deployment

This server is expected to have restricted or no public internet. Build the Laravel release outside the VM, copy the archive and offline installers to the VM, then run the server setup locally.

## Required Offline Installers

Copy these installers/packages to the server before starting:

- PHP 8.2 or 8.3 NTS x64 ZIP for Windows.
- Microsoft Visual C++ Redistributable required by the chosen PHP build.
- MySQL 8 or MariaDB installer, unless a database server is already provided.
- IIS URL Rewrite Module installer.
- NSSM (or an equivalent managed Windows service) for the required queue worker.

Composer and Node.js are not required on the server when deploying the prepared release package, because `vendor/` and `public/build/` are included.

## Build The Offline Release

Commit the reviewed application, dependency lockfiles, vendor assets, and release
notes first. From the repository root on the build machine, run:

```sh
python3 scripts/build-offline-release.py \
  --ref HEAD \
  --release-name rfc-offline-release-YYYYMMDD-v1 \
  --notes deployment/windows/RELEASE-NOTES-YYYYMMDD-v1.txt
```

Use the actual release date and committed notes filename. The builder packages
only the selected commit using `git archive`; uncommitted changes are excluded.
It requires Git, Python 3.9+, Composer/PHP, Node/npm, and populated Composer/npm
download caches for the locked packages. It copies those caches into temporary
staging, installs production PHP dependencies there, builds the browser assets,
checks vendor-asset provenance, and compiles Blade views. Network access and
dependency audits are disabled. A missing cached package stops the build.

When an approved earlier release contains the same `composer.json` and
`composer.lock`, it can supply the production vendor files with
`--vendor-archive /path/to/previous/rfc-app.tar.gz --vendor-sha256 APPROVED_SHA256`.
Both options are required together. The builder verifies the approved archive
checksum, matching Composer inputs and exact production package versions/refs,
extracts only vendor files, then regenerates the production autoloader through
Composer in staging. The seed archive hash is recorded in the new manifest.

The output under `deployment/releases` contains the prior ZIP bundle format,
the extracted bundle, an outer ZIP checksum, and additional build/security
evidence. `SOURCE-COMMIT.txt` identifies the source commit;
`BUILD-MANIFEST.json` records runtime file hashes, production dependencies and
tool versions; `SHA256SUMS.txt` covers the bundle files. `DEPLOY-COMMAND.txt`
includes the required `-ExpectedSha256` value for this exact application archive.
Local `.env`, uploads,
logs, caches, tests, `.git`, and `node_modules` are excluded. Existing release
names are never overwritten. Use `--output-dir`, `--composer-cache`, or
`--npm-cache` to select alternative locations.

Before transfer, verify the ZIP checksum. After extraction on Windows, verify
the bundle files against `SHA256SUMS.txt` using `Get-FileHash -Algorithm SHA256`.
This build does not apply server settings or replace the deployment-time
production security check.

## Upgrade The Existing RFC Server

Keep using the offline ZIP transfer to `C:\Deploy`. Complete the pre-deploy
checklist, including a current database backup and the managed queue worker,
then run the bundled script from outside `C:\inetpub\rfc`:

```powershell
Expand-Archive -LiteralPath C:\Deploy\rfc-offline-release-20260909-v1.zip -DestinationPath C:\Deploy
Set-Location C:\inetpub
Set-ExecutionPolicy -Scope Process Bypass
& C:\Deploy\rfc-offline-release-20260909-v1\Deploy-RfcRelease.ps1 `
  -ArchivePath C:\Deploy\rfc-offline-release-20260909-v1\rfc-app.tar.gz `
  -ExpectedSha256 "VERIFIED_64_CHARACTER_APP_ARCHIVE_SHA256"
```

Use the app archive hash from the verified release's `SHA256SUMS.txt`. The script
preserves the current `.env`, uploads, and shared cache; it pauses writers before
the final storage copy and retains the previous release for rollback. A failed
swap restores the previous code while preserving current storage. Database
migrations are not automatically reversed. `-SeedAccessControl` is opt-in and is
not needed for this security release.

The default worker is `RFCQueueWorker`; its command must use the absolute
`C:\inetpub\rfc\artisan queue:work` path without `--force`. Pass
`-SchedulerTaskName` if the installed task differs from `RFC Laravel Scheduler`.
The script checks sign-in and worker startup before ending public maintenance.
It queries the IIS management API before maintenance and during shutdown,
requiring both the site and its pool to be Stopped with no remaining workers.
An empty worker collection is valid; a query failure stops deployment with the
underlying error. The sign-in check sends a signed maintenance cookie explicitly
on a loopback request, with redirects and proxy use disabled. It requires HTTP
200 while public maintenance remains active. See `DEPLOY-SMOKE-FIX-20260910.txt`
for the latest standalone helper, which includes both corrections and reuses the
already transferred 2026-09-09 v1 application archive.
After it reports successful deployment, enable automatic startup for a newly
staged queue service, then verify the public site and a controlled recovery request:

```powershell
Set-Service -Name RFCQueueWorker -StartupType Automatic
Get-Service -Name RFCQueueWorker
```

The remaining setup sections are for provisioning a server; an existing
installation should use the upgrade procedure above.

## Recommended Server Layout

```powershell
C:\Deploy
C:\php
C:\inetpub\rfc
```

The IIS website document root must be:

```powershell
C:\inetpub\rfc\public
```

## Install IIS Features

Run PowerShell as Administrator:

```powershell
Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole,IIS-WebServer,IIS-CGI,IIS-DefaultDocument,IIS-StaticContent,IIS-HttpErrors,IIS-HttpLogging,IIS-RequestFiltering,IIS-HttpCompressionStatic,IIS-HttpCompressionDynamic -All
```

Install PHP into `C:\php`, then enable these extensions in `C:\php\php.ini`:

```ini
extension=bcmath
extension=curl
extension=fileinfo
extension=gd
extension=intl
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=tokenizer
extension=xml
extension=zip
date.timezone=Asia/Amman
expose_php=Off
display_errors=Off
log_errors=On
upload_max_filesize=10M
post_max_size=16M
zend_extension=opcache
opcache.enable=1
opcache.memory_consumption=192
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0
realpath_cache_size=4096K
realpath_cache_ttl=600
```

Confirm PHP:

```powershell
C:\php\php.exe -v
C:\php\php.exe -r "echo ini_get('expose_php') ? 'FAIL' : 'OK';"
C:\php\php.exe -r "echo 'upload_max_filesize='.ini_get('upload_max_filesize').PHP_EOL.'post_max_size='.ini_get('post_max_size');"
```

The security command must print `OK`, and the upload command must print at
least `10M` and `16M`. The deployment script rejects a PHP runtime that exposes
its version or cannot accept the application's registration uploads. It warns,
but does not stop deployment, when PHP OPcache is not enabled. Restart the IIS
application pool after changing `php.ini` so FastCGI loads the new settings.

## Extract The Application

Copy the release archive to `C:\Deploy`, then:

```powershell
New-Item -ItemType Directory -Force C:\inetpub\rfc
tar -xzf C:\Deploy\rfc-offline-release.tar.gz -C C:\inetpub\rfc
```

Create the production env file:

```powershell
Copy-Item C:\inetpub\rfc\deployment\windows\.env.production.example C:\inetpub\rfc\.env
notepad C:\inetpub\rfc\.env
```

Fill at minimum:

- `APP_URL`
- database credentials
- `INITIAL_SUPER_ADMIN_PASSWORD`
- `GSB_CLIENT_SECRET`
- `GSB_PSD_BASIC_INFO_BEARER` when enabling the token-protected non-Jordanian lookup
- API product switches and paths listed in `PRE-DEPLOY-CHECKLIST.txt`

Keep `GSB_ENABLED=false` until connectivity is confirmed from the VM.

## Database

Create the database and user:

```sql
CREATE DATABASE rfc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'rfc_app'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON rfc.* TO 'rfc_app'@'localhost';
FLUSH PRIVILEGES;
```

Then initialize Laravel:

```powershell
cd C:\inetpub\rfc
C:\php\php.exe artisan key:generate --force
C:\php\php.exe artisan migrate --seed --force
C:\php\php.exe artisan storage:link
C:\php\php.exe artisan config:cache
C:\php\php.exe artisan route:clear
C:\php\php.exe artisan view:cache
```

Keep route cache disabled for now. The portal uses localized `/ar` and `/en`
routes through `mcamara/laravel-localization`; caching routes can expose the
non-prefixed route table and make `/ar/sign-in` return 404.

## Permissions

```powershell
icacls C:\inetpub\rfc\storage /grant "IIS_IUSRS:(OI)(CI)M" /T
icacls C:\inetpub\rfc\bootstrap\cache /grant "IIS_IUSRS:(OI)(CI)M" /T
```

## IIS Site

Run PowerShell as Administrator:

```powershell
Import-Module WebAdministration

New-Website -Name "RFC" -PhysicalPath "C:\inetpub\rfc\public" -Port 80 -Force

& $env:windir\system32\inetsrv\appcmd.exe set config /section:system.webServer/fastCgi /+"[fullPath='C:\php\php-cgi.exe']"
& $env:windir\system32\inetsrv\appcmd.exe set config "RFC" /section:system.webServer/handlers /+"[name='PHP_via_FastCGI',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='C:\php\php-cgi.exe',resourceType='Either']"

iisreset
```

`public/web.config` is already included in the release package for Laravel route rewriting.

## Government HTTPS Certificate

The public URL must remain `https://filmjordan.jo`. Do not use
`https://10.0.41.97` in the browser because the certificate is issued for the
domain, not the private IP.

Copy the supplied certificate files to `C:\Deploy\certs`, then run PowerShell as
Administrator:

```powershell
Import-Certificate `
  -FilePath C:\Deploy\certs\DigiCertCA.crt `
  -CertStoreLocation Cert:\LocalMachine\CA

certreq.exe -accept C:\Deploy\certs\filmjordan_jo.crt

Get-ChildItem Cert:\LocalMachine\My |
  Where-Object { $_.Subject -match "filmjordan" } |
  Select-Object Subject, Thumbprint, HasPrivateKey, NotAfter
```

If `HasPrivateKey` is `False`, the certificate cannot be used by IIS for HTTPS.
Ask IT for a password-protected `.pfx` containing the domain certificate,
intermediate chain, and private key, or export that PFX from the machine where
the CSR was generated.

When `HasPrivateKey` is `True`, use IIS Manager:

1. Open **Sites > RFC > Bindings**.
2. Add an `https` binding on port `443`.
3. Use IP address `10.0.41.97` or **All Unassigned**.
4. Set host name to `filmjordan.jo`.
5. Enable SNI when other HTTPS sites share port 443.
6. Select the `filmjordan.jo` certificate.

The network team must forward public `193.188.85.20:443` to
`10.0.41.97:443`. Keep port 80 available until HTTPS is verified, then redirect
HTTP traffic to HTTPS.

Set these values in the real server `.env`:

```env
APP_URL=https://filmjordan.jo
ASSET_URL=https://filmjordan.jo
TRUSTED_PROXIES=
SECURITY_PROFILE_URL_ALLOWED_HOSTS=imdb.com,linkedin.com,filmfreeway.com,vimeo.com,youtube.com
SECURITY_WEBSITE_URL_ALLOWED_HOSTS=filmjordan.jo
SECURITY_OUTBOUND_HTTP_ALLOWED_HOSTS=api-gateway.stg.gsb.gov.jo,bulk-sms.gov.jo,signflow.sanad.gov.jo
SESSION_SECURE_COOKIE=true
SESSION_DOMAIN=null
SANAD_SIGNFLOW_BASE=https://signflow.sanad.gov.jo
SANAD_REDIRECT_URI=https://filmjordan.jo/ar/sign-in/sanad/callback
SANAD_CULTURE=ar
```

Leave `TRUSTED_PROXIES` empty for direct IIS/NAT traffic. If IT confirms that
another server terminates HTTPS and forwards requests to IIS, set its exact
internal IP or CIDR. The checked-in IIS gateway policy accepts only
`filmjordan.jo` in `Host`, `X-Forwarded-Host`, `X-Original-Host`, and `X-Host`,
and rejects the generic `Forwarded` header. If the approved proxy uses
`Forwarded`, have it strip that header or update the IIS rule to validate only
the final public host before enabling the proxy.

The two `SECURITY_*_ALLOWED_HOSTS` values are explicit URL-domain allowlists.
Review them with the RFC business owner and add only required profile or company
domains; subdomains of an approved hostname are accepted automatically.
Outbound government HTTP clients and the validated SANAD browser redirect target
use the separate `SECURITY_OUTBOUND_HTTP_ALLOWED_HOSTS` list. Server-side HTTP
clients do not follow redirects. Coordinate changes to that list with MODEE and
the server egress/firewall policy.

Each deployment also runs `php artisan security:evidence`. The resulting
versioned JSON manifest is stored privately under
`storage\app\private\security-evidence` and records the exact lockfile hashes,
dependency inventory, route controls, security configuration, and runtime-CDN
template scan for the tested release.

Before the security retest, complete [the public gateway and asset checks](SECURITY-RETEST.md).
They cover TLS protocols/ciphers, BIG-IP cookie attributes, Host rejection before
DNS/routing, outbound firewall rules, and replacement of cached JavaScript assets.
The Laravel production check does not verify those infrastructure controls.

After editing `.env`:

```powershell
Set-Location C:\inetpub\rfc
C:\php\php.exe artisan optimize:clear
C:\php\php.exe artisan config:cache
iisreset
```

## Scheduler

Create a Windows scheduled task that runs every minute:

```powershell
$action = New-ScheduledTaskAction -Execute "C:\php\php.exe" -Argument "C:\inetpub\rfc\artisan schedule:run"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName "RFC Laravel Scheduler" -Action $action -Trigger $trigger -RunLevel Highest -User "SYSTEM"
```

## Queue Worker

A continuously running worker is required for production password-reset OTP
messages. Recovery requests queue delivery so SMS provider latency cannot reveal
whether an account exists on IIS. Set `QUEUE_CONNECTION=database` and
`DB_QUEUE_RETRY_AFTER=180` in production `.env` (the reservation must outlast the
configured 120-second worker timeout), then rebuild the configuration cache. The standard
migrations include the jobs and failed-jobs tables. Use a separate shared durable
queue only if its worker and reservation/visibility timeout are configured too.

Native Windows PHP generally lacks PCNTL, so Laravel's `--timeout` cannot enforce
a hard process timeout there. Keep `GOV_SMS_CONNECT_TIMEOUT=5` and
`GOV_SMS_TIMEOUT=15`, and start with one worker. The SMS client's bounded HTTP
calls must finish well inside the queue's reservation interval. Review those
bounds before adding other long-running jobs or more workers.

Obtain NSSM from its [official download page](https://nssm.cc/download). Use the
recommended 2.24-101 build or a reviewed newer build for modern Windows. Verify
the transferred executable's SHA-256 against the recorded trusted download
before running it. The setup script copies it to the permanent location
`C:\Program Files\RFC\QueueWorker\nssm.exe`, outside the application directory.
Leave that runtime copy in place; source downloads can remain under `C:\Deploy`.

The bundled setup script creates a dedicated passwordless Windows virtual
account, limits application writes to runtime directories, and configures NSSM
to restart a worker after normal or failed exits. It creates the service with
Manual startup and leaves it Stopped so setup does not consume queued jobs.
The deployment script preserves this account's permissions in each new release.

Run the setup from an elevated PowerShell window before the first deployment
that requires the worker (replace the checksum with the verified executable hash):

```powershell
& C:\Deploy\rfc-offline-release-20260909-v1\Install-RfcQueueWorker.ps1 `
  -NssmPath C:\Deploy\nssm-2.24-101-g897c7ad\win64\nssm.exe `
  -ExpectedSha256 "VERIFIED_64_CHARACTER_NSSM_EXE_SHA256"
```

The setup refuses to overwrite an existing service by default. If the initial
setup reported `not a valid NSSM service`, use the corrected setup script with
`-RepairIncomplete`. That mode only accepts this installer's exact disabled,
stopped service, expected executable/account/description, and matching existing
application/log settings. It verifies the permanent NSSM checksum again and
repairs the registration without deleting the service. The corrected script
initializes `Parameters\Application` before calling NSSM, preserves existing
registry values, and verifies the final application/log configuration.

For that specific incomplete installation, run the corrected script from an
elevated PowerShell window (substitute its actual extracted path):

```powershell
& C:\Deploy\rfc-queue-worker-repair-20260908\Install-RfcQueueWorker.ps1 `
  -NssmPath "C:\Program Files\RFC\QueueWorker\nssm.exe" `
  -ExpectedSha256 "eee9c44c29c2be011f1f1e43bb8c3fca888cb81053022ec5a0060035de16d848" `
  -RepairIncomplete
```

Successful setup or repair leaves the service Manual and Stopped. A failure
leaves it Disabled for inspection. The deployment starts and checks the worker
while maintenance still pauses jobs. Set startup to Automatic only after the
deployment succeeds, using the upgrade commands above.

The earlier September 8/9 installer artifacts also applied inheritance removal
recursively to the private NSSM/log directories. Staging subsequently reported
SCM event 7000 (`Access is denied`): the parent folder had the intended grants,
but `nssm.exe` displayed no permission entries. The current installer grants the
three intended identities on each private root first, removes only that root's
inherited permissions in a separate command, and verifies the actual root and
descendant ACLs before reporting success. Existing protected descendants fail
verification; they are not silently broadened or reset.

For that inspected staging configuration, run
[`Repair-RfcWorkerBinaryAccess.ps1`](Repair-RfcWorkerBinaryAccess.ps1) from an
elevated PowerShell session. It checks the stopped service's identity/path,
restores inheritance only on its permanent executable, checks the reviewed
SHA-256 before invoking `nssm version`, and displays the executable/log ACLs.
Review the output before retrying deployment. It does not start the worker.
Use the corrected installer from this revision for new installations; the
immutable earlier release/repair ZIPs still contain their original scripts.

ACL validation includes portable regression checks and an additional
`tests/Deployment/WorkerFileAccess.Windows.Tests.ps1` test that exercises real
NTFS inheritance in disposable directories on elevated Windows. A skipped
Windows test on another operating system is not Windows integration validation.

Temporary alternative while testing:

```powershell
cd C:\inetpub\rfc
C:\php\php.exe artisan queue:work --sleep=3 --tries=3 --timeout=120
```

After every deployment, confirm `RFCQueueWorker` is running with the deployed
application path and can access the same database/configuration. The deployment
script runs `queue:restart`; the service manager must restart the exited worker.
Check `php artisan queue:failed` and worker logs. In an approved test account,
request and receive a password-reset OTP, complete the reset, and ensure the
queue drains. Also compare a nonexistent identifier: the HTTP acknowledgement
must be identical and must not wait for SMS delivery. Never switch production to
`sync` to work around a missing worker; restore the worker instead.

## GSB Connectivity Check

Before enabling live API calls:

```powershell
cd C:\inetpub\rfc
PowerShell -ExecutionPolicy Bypass -File .\deployment\windows\check-gsb-connectivity.ps1
```

If DNS fails but the IP works, set `GSB_FORCE_IP` in `.env` and keep the official host in `GSB_BASE_URL`.

After connectivity and credentials are confirmed:

```powershell
notepad C:\inetpub\rfc\.env
C:\php\php.exe artisan config:clear
C:\php\php.exe artisan config:cache
```

Then open:

```text
/ar/control-panel/integrations
```

Use that page to test configured integrations safely.
