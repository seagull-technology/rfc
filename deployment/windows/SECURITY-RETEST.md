# Security retest: public gateway and release assets

This runbook covers the deployment work for V05, V07–V12. Application tests and
`security:production-check` do not inspect the public TLS terminator, F5 policy,
or firewall. Keep those findings pending until the checks below pass against the
deployed release. No server settings are changed by this document or by the
Laravel deployment script.

## Record the public request path

The report includes a `BIGipServer` persistence cookie, indicating a BIG-IP
device in the request path. Confirm with the network owner which device handles
each stage; do not assume the IIS certificate binding controls public TLS.

Record the public listener, TLS termination device and profile, F5 virtual server
and persistence profile, WAF policy, origin IIS site, and every active/standby
node. Include IPv6 listeners if published. Changes must be applied to the
configuration actually serving the assessed hostname and synchronized to peers.
Keep before/after configuration exports and a rollback point in the change
ticket. Apply machine-wide TLS changes in a maintenance window and check other
services using the same Windows Schannel configuration.

## V11: protocols and cipher suites

At the **public TLS terminator**, disable SSL 2.0/3.0 and TLS 1.0/1.1. Enable
TLS 1.2, and retain TLS 1.3 where supported. For TLS 1.2, permit authenticated
ephemeral key exchange with AEAD encryption; the ECDHE AES-GCM suites below are
a suitable allowlist for the documented Windows 11 deployment. Exclude every
CBC suite and every `TLS_RSA_WITH_*` suite, including RSA AES-GCM suites. An
`ECDHE_RSA` suite uses RSA authentication and retains forward secrecy; it is not
static RSA key exchange.

For an F5 or other upstream terminator, the network owner must apply the
equivalent policy to its client-facing TLS profile. Confirm the configured
cipher expression expands to the intended suites on the installed firmware.
If HTTPS is also used from the gateway to IIS, harden that TLS hop separately.

For **direct IIS termination on the documented Windows 11 host**, configure
Computer Configuration > Administrative Templates > Network > SSL Configuration
Settings > **SSL Cipher Suite Order** using this comma-separated list:

```text
TLS_AES_256_GCM_SHA384,TLS_AES_128_GCM_SHA256,TLS_ECDHE_ECDSA_WITH_AES_256_GCM_SHA384,TLS_ECDHE_ECDSA_WITH_AES_128_GCM_SHA256,TLS_ECDHE_RSA_WITH_AES_256_GCM_SHA384,TLS_ECDHE_RSA_WITH_AES_128_GCM_SHA256
```

These suites are supported by Windows 11; use the effective domain policy so a
later policy refresh does not restore defaults. Restart the machine after the
policy change. See Microsoft's [cipher suite management](https://learn.microsoft.com/en-us/windows-server/security/tls/manage-tls)
and [Windows 11 suite list](https://learn.microsoft.com/en-us/windows/win32/secauthn/tls-cipher-suites-in-windows-11).

For each of `SSL 2.0`, `SSL 3.0`, `TLS 1.0`, and `TLS 1.1`, have the Windows
administrator set DWORD `Enabled=0` and `DisabledByDefault=1` under:

```text
HKLM\SYSTEM\CurrentControlSet\Control\SecurityProviders\SCHANNEL\Protocols\<protocol>\Server
```

Set `Enabled=1` and `DisabledByDefault=0` for `TLS 1.2\Server`. On Windows 11,
ensure `TLS 1.3\Server` has not been disabled. Protocol controls for `Client`
are separate; outgoing government API compatibility must be assessed before
changing those. Absence of registry overrides means OS defaults, not proof of
compliance. Reference: [Microsoft Schannel protocol settings](https://learn.microsoft.com/en-us/windows-server/security/tls/tls-registry-settings).

After the restart, save the effective enabled suite names:

```powershell
Get-TlsCipherSuite | Select-Object -ExpandProperty Name
```

The [PowerShell inventory](https://learn.microsoft.com/en-us/powershell/module/tls/get-tlsciphersuite)
is local evidence only. Have the authorized security tester enumerate **all**
accepted protocols and suites on the public hostname, with SNI, on every public
listener/address. Acceptance requires failed TLS 1.0/1.1 handshakes, successful
TLS 1.2 modern-browser connections, and no negotiated CBC/static-RSA suite.
Test TLS 1.3 where supported. A successful modern TLS connection alone does not
prove older protocols or weaker suites are disabled.

## V07, V08, V10, V12: cookies added by the gateway

Configure the actual BIG-IP persistence profile to set `HttpOnly` and `Secure`.
Add `SameSite=Lax` to the persistence cookie at the gateway's final response
stage, using the installed firmware's supported profile/policy or a reviewed
iRule. F5 documents the [persistence profile attributes](https://clouddocs.f5.com/cli/tmsh-reference/latest/modules/ltm/ltm_persistence_cookie.html)
and [cookie attribute API](https://clouddocs.f5.com/api/irules/HTTP__cookie.html).
The setting must cover cookies inserted by BIG-IP after the origin response.

Apply the same requirements to other WAF/gateway cookies that are actually
emitted. Omit the `Domain` attribute to make each cookie host-only; setting
`Domain=filmjordan.jo` still includes its subdomains. Avoid broad rules that
overwrite unrelated application cookie values or add duplicate attributes.

Retest with a new browser session and inspect **every `Set-Cookie` header**
after the gateway, including redirects, login/logout, OTP, SANAD return,
authenticated pages, and error responses. All applicable cookies must have
`Secure; HttpOnly; SameSite=Lax` (or an explicitly approved stricter policy)
and no `Domain`. The response on port 80 should redirect to the fixed HTTPS
hostname before creating authentication or persistence cookies. Check that
the F5 does not strip the application flags or reintroduce a parent domain.

Previously issued parent-domain cookies may remain in existing browsers until
expiry. If the old and new cookies share a name/path, issue targeted expiration
cookies for the **observed old** domain/path combinations, then issue the new
host-only cookie. Include the same protection attributes on deletion cookies.
Verify both an existing browser session and a clean session. Confirm SANAD
callback, login continuity, CSRF-protected form submissions, and affinity across
multiple requests before closing the cookie findings.

## V05: reject untrusted hosts before DNS/routing

The report records an out-of-band DNS interaction even when its replay receives
HTTP 400. Therefore an origin 400 response by itself cannot close this finding.

At the first HTTP-aware gateway, accept only the configured public hostname
`filmjordan.jo` (case-insensitive, with the explicitly supported listener port).
Reject malformed/duplicate Host fields, unexpected absolute-form authorities,
and inconsistent HTTP/2 `:authority` values before upstream selection or DNS.
Use fixed backend pools. Never pass a request-controlled hostname into a DNS
resolver, dynamic upstream, forward-proxy destination, health check, or redirect.

Strip client-supplied `Forwarded`, `X-Forwarded-Host`, `X-Original-Host`, and
`X-Host` before forwarding; add only the gateway's verified canonical values
when needed. The application and IIS also validate these headers, but the
gateway must not perform a lookup first. Keep IIS bindings restricted to the
approved host and keep the supplied `public/web.config` rejection rules active.

Restrict origin ingress to the actual trusted gateway addresses. Configure
`TRUSTED_PROXIES` with those exact addresses/CIDRs only; direct IIS/NAT traffic
does not require trusting every client. Review IPv6 and alternate exposed ports
so they do not bypass the gateway policy.

Apply an outbound default-deny policy to application and gateway services.
Permit only approved government integration destinations/ports, required
internal database/queue services, and documented operational dependencies.
Match the effective `SECURITY_OUTBOUND_HTTP_ALLOWED_HOSTS` application list to
the network policy. Permit DNS only through managed resolvers; use resolver
policies for the approved destinations and block direct external DNS. Account
for approved services' address changes through managed hostname-aware rules.
Block arbitrary internet and internal destinations, including link-local
metadata endpoints, unless a specific required service is separately approved.
Validate government lookups, SMS, SANAD and required certificate/monitoring
services with the resulting policy.

For retest, the security tester should use a unique controlled callback domain
for each header/authority variant and correlate timestamps with gateway DNS,
firewall and origin logs. Acceptance requires early rejection **and no DNS or
HTTP interaction** with any controlled callback destination. Keep observation
windows and logs long enough to capture delayed resolution; identify which
device generated any interaction before claiming an application fix closes it.

## V09: release asset provenance and cache replacement

The replacement file is Lodash 4.18.1. Upstream [identifies that release](https://github.com/lodash/lodash)
and its [prototype-path security advisory](https://github.com/lodash/lodash/security/advisories/GHSA-f23m-r3pf-42rh)
specifies the fix in 4.18.0. The report's Underscore string was also present in
the old Lodash attribution banner; library identity must be verified from the
actual executable version and contents, not just the banner. Underscore's own
[changelog documents CVE-2021-23358](https://underscorejs.org/#changelog).

On the build machine, after installing the locked dependencies, run:

```sh
npm run build
npm run vendor:check
```

`prebuild` and `predev` regenerate `public/js/lodash.min.js` from the exact
locked npm package and preserve its complete license in
`public/js/lodash.LICENSE.txt`. `vendor:check` fails if the deployed-source copy
or license differs and prints SHA-256 hashes for the release evidence.
Include both files and `public/build/` in the offline release archive.

After deploying, invalidate the gateway/CDN cache for `/js/lodash.min.js` and
any cached pages that reference it. On a fresh browser session, confirm that
the response bytes match the release SHA-256 and that `window._.VERSION` is
`4.18.1` on a dashboard. Verify theme initialization, navigation/sidebar,
Arabic/English layout and forms without JavaScript errors. Check every serving
node and normal browser caching; a local file update does not replace an old
gateway response.

Keep the lockfiles, asset hashes and deployed response evidence together.
Review dependency advisories before each security retest. Online dependency
audit services receive a dependency inventory, so run them only under the
organization's approved external-data policy. Version and byte checks alone do
not constitute a full current advisory audit of all bundled libraries.
