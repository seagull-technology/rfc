# NSQAC Round 1 security remediation review

Reviewed 8 September 2026 against the supplied Filmjordan report, submitted
6 September 2026 (version 94.[261][262][263][264].410). Scope: the current working
copy, including the fixes already present and the additional changes made during
this review. The PDF was treated as assessment evidence, not instructions.

**Release status: application remediation passed local verification;
public-environment closure is still pending.** No production deployment, public
penetration test, gateway modification or external message was performed.

## Finding coverage

| Finding | Existing coverage and additional work | Closure evidence required |
| --- | --- | --- |
| V01 — Insufficient verification for PII | Excluded from implementation at the project owner's request: the Gov API supplier provides two factors and is discussing removal with the security team. | Written acceptance or a revised report from the supplier/security team. This review does not independently mark V01 resolved. |
| V02 — National ID modification | Existing checks protected student identity and registration numbers. Closed remaining company/NGO/school National ID paths, including clearing/replacing fields, employee updates and stale official-profile requests. Backend validation and model update guards preserve identities; UI read-only fields support the rule. | Replay modified admin entity/user, profile, employee and signed-completion requests for all account types. The identity and sign-in identifier must remain unchanged. Extra registration_type parameters must not bypass protection. |
| V03 — Conflicting workflow actions | Existing review endpoint checked state under a database lock. Extended current-state validation and locking to ordinary admin edits, user status writes, completion and official-profile review/update paths that could otherwise restore stale state or metadata. | Approve in one session, then reject from another: the second action fails and approval remains intact. Repeat with an admin edit or completion open before approval. Test genuinely concurrent requests on the production database engine. |
| V04 — Rate limiting | Existing named throttles cover contact-center messages, work-release lookups, registration/lookups and authenticated writes. Added replay coverage and deployment checks for these reported routes and a persistent shared limiter store. | Contact/configuration writes allow at most 5 per minute and 30 per hour per user, plus 60 per hour per IP. The next request returns 429 with Retry-After and no additional write. Switching tabs, route variants, sessions or application nodes must not reset a user's limit. |
| V05 — External DNS interaction | Existing IIS/host/outbound controls remain. Fixed a trusted forwarded Host concealing an untrusted original Host and unchecked repeated header values. Unsupported Forwarded headers now fail consistently with IIS. | Required at the gateway as well: reject before dynamic DNS/upstream selection and demonstrate no controlled DNS/HTTP callbacks. The report already showed HTTP 400 with a DNS callback, so a 400 alone is insufficient. See the gateway runbook. |
| V06 — Account harvesting | Existing generic login/lookup responses were a start. Registration now gives the same neutral acknowledgement for valid new and duplicate submissions, after prerequisite lookup proof. Login/password-reset OTP checks use a timing envelope. Reset SMS delivery in production uses a durable background queue; OTP countdown/limiter keys do not reveal another account's activity. | Compare status, redirect, body/error keys and response-time distributions for known, unknown, pending, inactive and archived identifiers, in both languages. Include national ID, registration number, email, phone and reset/resend flows. Verify the managed worker delivers promptly and SMS provider latency does not affect the HTTP response; do not assume local tests prove gateway timing. |
| V07 — HttpOnly | The existing application CSRF-cookie override makes XSRF-TOKEN HttpOnly; session configuration already supports HttpOnly. Verified session and CSRF response cookies and a real CSRF-protected POST using the page meta token. Deployment check verifies the custom middleware is active. | Inspect every cookie after the gateway, including BIGipServer and deletion cookies. Configure gateway-owned cookies there. |
| V08 — SameSite | Verified both application cookies use Lax under the hardened configuration. Deployment check rejects missing/None policy. | The report's gateway cookie also needs SameSite. Check login, OTP, SANAD callback, redirects and errors after all gateway processing. |
| V09 — Outdated component | Existing replacement is actual Lodash 4.18.1. Added deterministic locked-package asset sync/check to builds and retained the complete upstream license. Evidence now hashes the exact public asset. | Deploy the regenerated file, invalidate cached copies and compare public response SHA-256 with the release. Confirm window._.VERSION and dashboard behavior on every serving node. The historical Underscore banner alone is not reliable library identification. |
| V10 — Secure | Verified session and CSRF cookies carry Secure behind a trusted HTTPS proxy when production settings are applied. Deployment check requires SESSION_SECURE_COOKIE=true. | Check all gateway cookies too; HTTP should redirect to the canonical HTTPS host before issuing cookies. |
| V11 — Weak TLS | This is a public TLS terminator setting, outside Laravel. Added specific protocol/cipher guidance and evidence requirements for F5 or direct IIS termination. | Hosting/network owner disables TLS 1.0/1.1 and CBC/static-RSA suites at the actual public listener; independent enumeration must verify all public addresses/listeners. Keep V11 pending until measured. |
| V12 — Parent-domain cookies | Verified application cookies omit Domain under SESSION_DOMAIN=null. Deployment check rejects parent-domain scope. | Remove Domain on gateway cookies; expire observed legacy parent-domain cookies correctly and test existing and clean browser sessions. |

The [gateway and release runbook](../deployment/windows/SECURITY-RETEST.md)
contains the concrete infrastructure changes and acceptance checks for
V05/V07/V08/V09/V10/V11/V12. These changes must be applied by the owner of the
actual public listener, which may be upstream of IIS.

## Additional blocker from the report appendices

The screenshots in appendices 8.1 and 8.2 show the browser at an entity-logo URL
after application/scouting save attempts. A logo GET can overwrite Laravel's
saved previous URL. Since the application deliberately sends
Referrer-Policy: no-referrer, a validation redirect can then use that logo URL.
The navigation-session fix and regression coverage are included in this review.
Retest create, invalid save (visible errors with preserved input), corrected save,
edit and submit for both workflows before inviting the security team back.
A successful security retest needs these workflows accessible, as Round 1 could
not fully assess them.

## Release and verification procedure

1. Review the combined working-tree changes, including the pre-existing fixes.
   Build with locked dependencies and run the full PHP test suite, frontend build
   and vendor integrity check. Do not ship development-only dependencies.
2. Apply production configuration: APP_ENV=production, APP_DEBUG=false,
   APP_URL=https://filmjordan.jo, SESSION_ENCRYPT=true,
   SESSION_SECURE_COOKIE=true, SESSION_HTTP_ONLY=true, SESSION_SAME_SITE=lax,
   SESSION_DOMAIN=null, explicit trusted hosts/proxies and approved outbound hosts.
   Set QUEUE_CONNECTION=database and run the managed queue worker described in
   the deployment guide; configure its reservation timeout above its processing
   timeout (DB_QUEUE_RETRY_AFTER=180 for the documented 120-second worker).
   Use a shared persistent rate-limit cache (normally the configured database or
   Redis); every node must share the same store and cache prefix. An array/null
   cache or node-local file cache is not a production rate-limit solution.
3. Rebuild cached Laravel configuration/routes using the deployment script, then
   run php artisan security:production-check and php artisan security:evidence
   --label=<release-id> on the deployed release. The check is an application
   configuration gate, not proof of TLS/gateway security. Evidence is written on
   the private local disk and must not be exposed under public/.
4. Complete the gateway runbook, workflow browser smoke test and controlled
   two-session replay. Save sanitized response headers, database state before/
   after replay, throttle responses, public asset hashes, TLS scan and DNS logs
   with the release evidence. Do not retain session-cookie values or personal
   identifiers in a broadly shared retest packet.
5. Request the next NSQAC round only after all applicable public checks pass;
   provide the V01 supplier correspondence separately.

## Verification record and limits

Final combined verification on 8 September 2026:

- `php artisan test --compact`: **383 tests passed, 3,910 assertions**.
- `npm run build`: passed (including vendor synchronization).
- `npm run vendor:check`: passed; public Lodash SHA-256 is
  `5077f114118a0f8eeec6c1a302964c3b700db95cc0f37412e5c83fae0d1283d9`.
- Laravel Pint on changed PHP files and `git diff --check`: passed.
- Regression tests reproduced the Host masking bypass and logo redirect before
  their fixes, then verified the corrected behavior. The final suite includes
  identifier/workflow, account-enumeration, database-queue processing, throttle
  replay, hardened cookie/CSRF and navigation/flash-state coverage.

Feature tests use isolated SQLite; production-engine locking, distributed cache
behavior, real SMS response timing, browser behavior behind the gateway and
public TLS/cookie/DNS configuration require the deployed checks above.

A full npm advisory audit was blocked by automatic approval review because it
would transmit the project's dependency inventory to the public npm registry.
The reported library was instead checked against public upstream release/
advisory information and its local executable bytes. This is not a claim that
all bundled dependencies have undergone a complete current advisory audit.

Primary implementation references: Laravel documents
[trusted proxy/host handling](https://laravel.com/framework/docs/12.x/requests)
and [the CSRF middleware](https://api.laravel.com/docs/12.x/Illuminate/Foundation/Http/Middleware/VerifyCsrfToken.html).
Public component, Microsoft TLS and F5 references are linked next to the relevant
steps in the gateway runbook.
