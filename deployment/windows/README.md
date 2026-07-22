# RFC Windows 11 Offline Deployment

This server is expected to have restricted or no public internet. Build the Laravel release outside the VM, copy the archive and offline installers to the VM, then run the server setup locally.

## Required Offline Installers

Copy these installers/packages to the server before starting:

- PHP 8.2 or 8.3 NTS x64 ZIP for Windows.
- Microsoft Visual C++ Redistributable required by the chosen PHP build.
- MySQL 8 or MariaDB installer, unless a database server is already provided.
- IIS URL Rewrite Module installer.
- Optional: NSSM, if you want the queue worker as a Windows service.

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
Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole,IIS-WebServer,IIS-CGI,IIS-DefaultDocument,IIS-StaticContent,IIS-HttpErrors,IIS-HttpLogging,IIS-RequestFiltering -All
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
```

Confirm PHP:

```powershell
C:\php\php.exe -v
```

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
non-prefixed route table and make `/ar/login` return 404.

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

## Scheduler

Create a Windows scheduled task that runs every minute:

```powershell
$action = New-ScheduledTaskAction -Execute "C:\php\php.exe" -Argument "C:\inetpub\rfc\artisan schedule:run"
$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 1)
Register-ScheduledTask -TaskName "RFC Laravel Scheduler" -Action $action -Trigger $trigger -RunLevel Highest -User "SYSTEM"
```

## Queue Worker

Preferred: install NSSM offline and create a service:

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
/ar/admin/integrations
```

Use that page to test configured integrations safely.
