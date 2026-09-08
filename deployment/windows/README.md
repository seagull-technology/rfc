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
120-second worker timeout), then rebuild the configuration cache. The standard
migrations include the jobs and failed-jobs tables. Use a separate shared durable
queue only if its worker and reservation/visibility timeout are configured too.

Install NSSM offline and create a service:

```powershell
nssm install RFCQueueWorker C:\php\php.exe "C:\inetpub\rfc\artisan queue:work --sleep=3 --tries=3 --timeout=120"
nssm set RFCQueueWorker AppDirectory C:\inetpub\rfc
nssm start RFCQueueWorker
```

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
