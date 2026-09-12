# Windows staging deployment and live verification — 10 September 2026

The project owner identified this Windows server as the staging/test server.
The browser checks below used the authorized site at `https://filmjordan.jo`.
These observations supplement the [finding review](security-report-review-2026-09-08.md);
they do not replace the security team's retest or close every finding.

Later testing and the next release are tracked in the
[12 September verification record](security-verification-2026-09-12.md).
The table below preserves the state of the 10 September checks.

## Point-by-point status

| Point | Current conclusion | Next action |
| --- | --- | --- |
| V01 — PII verification | Excluded from our implementation; internal supplier email reported. | Security team to determine formal report closure. |
| V02 — Identity modification | Application guards are implemented and locally tested; live tampering not yet verified. | Test altered identity requests against disposable records. |
| V03 — Conflicting approvals | Application fixes are implemented and locally tested; live concurrency not yet verified. | Prepare a pending registration and test competing actions on the deployed database. |
| V04 — Rate limiting | The sampled five-per-minute admin limit passed; overall verification is incomplete. | Check hourly/IP limits, Retry-After, other routes, sessions and nodes. |
| V05 — External DNS interaction | Application Host checks are implemented; gateway behavior is unverified. | Infrastructure team to coordinate callback tests and correlated logs. |
| V06 — Account harvesting | Password recovery and subsequent login passed; enumeration resistance remains unverified live. | Compare account states, identifiers, languages, responses and timing. |
| V07 — HttpOnly | Application cookie sample passed; another emitted cookie lacks HttpOnly. | Confirm and correct the gateway cookie policy. |
| V08 — SameSite | Application cookie sample passed; two other emitted cookies lack SameSite. | Correct the gateway cookie policy and check authentication flows. |
| V09 — Outdated component | Public file matches the patched asset; browser cache coverage is incomplete. | Deploy the tested asset URL-versioning fix and verify caches/nodes. |
| V10 — Secure | Application cookie sample passed; another emitted cookie lacks Secure. | Correct the gateway cookie policy. |
| V11 — Weak TLS | Failed: obsolete protocols and weak suites are still accepted. | Infrastructure owner to harden the public TLS terminator and retest. |
| V12 — Cookie domain | Application cookie sample passed; another response emits Domain=.jo. | Correct the gateway cookie scope and check existing/clean sessions. |
| Appendices — Application/scouting forms | Navigation fix is implemented and locally tested; live workflow retest is pending. | Verify invalid save, corrected save, edit and submit for both forms. |

The report is **not ready for full closure**. Confirmed infrastructure failures
and the outstanding application verification above must be completed first.

## V01 — scope and supplier coordination

The project owner reports that the Gov API supplier has already emailed the
security contact internally. V01 remains excluded from application implementation
as agreed. Formal closure or removal from the report has not been independently
verified; no additional copy of that correspondence is requested in this review.

## Deployment and worker

- User-supplied PowerShell output reports **Deployment completed successfully**
  for deployment ID `20260910-023753-783f322c`, with the application live.
- Application configuration checks passed; there were no pending migrations.
  The exact deployer only reports success after its loopback sign-in check and
  managed worker startup/process checks pass.
- Application archive: `rfc-offline-release-20260909-v1/rfc-app.tar.gz`.
  SHA-256: `4454d3f21e90091b0d9d9c9ab3f8dda678aa9e62e74faa07a6aed4e22b562b1c`.
- Standalone deployer: `rfc-deploy-smoke-fix-20260910/Deploy-RfcRelease.ps1`.
  SHA-256: `de121ec01c661e86d50bc9298ba283c9082180ae3a0aafda901c4288074aa7af`.
- Deployed release fingerprint reported by `security:evidence`:
  `51e299f8bda3071dfd957c888648b162847654fae3b2a78e98f310a39880a8ad`.
- The executable ACL repair was verified on the server: the worker has inherited
  ReadAndExecute on permanent `nssm.exe`, and Administrators/SYSTEM FullControl.
  The reviewed executable checksum and `nssm version` check passed. The private
  log directory grants the worker Modify access.
- After deployment, the user verified **RFCQueueWorker / Running / Automatic**.
  Automatic startup is configured; a reboot recovery test has not been run.

## Controlled password-recovery flow

The project owner authorized one request for an existing company account and
provided its identifier. No account identifier, OTP, password, reset token, or
session-cookie value is retained in this record.

1. Submitted the password-reset request once through the user's Chrome tab.
2. Observed the neutral acknowledgement and OTP verification page.
3. The user entered the OTP in Chrome; the server accepted it and opened the
   new-password form.
4. The user completed the password change. The success message was observed on
   the sign-in page.
5. The user signed in with the new password, and the authenticated company
   dashboard at `/ar/dashboard` was observed.

**Result: the functional password-recovery regression passed for this account.**
The live flow exercised queued OTP delivery, verification, password replacement,
and subsequent sign-in. No provider-latency measurement or queue-depth capture
was taken. This result does **not** close V06 account harvesting: controlled
comparisons of responses and timing across account states, identifier types,
languages, and reset/resend/registration flows remain outstanding.

The browser's text/DOM inspection omitted the first OTP box even though both
the user's screenshot and a live screenshot showed five filled boxes. Only that
box uses `autocomplete="one-time-code"`. A verification click was initially
blocked by automatic approval review; after the visual confirmation and field
metadata check, verification succeeded. This was not established as an
application input defect, and no OTP UI change was made.

## V02/V03 — administrative fixture availability

A read-only check of the admin entity listing used `status=pending_review`, all
registration types and all records. The listing showed zero pending registrations
for students, companies, NGOs and schools. No identity or workflow edits were
submitted. V02/V03 therefore still require controlled fixtures and replay of
identity changes, stale actions and concurrent decisions. This listing check is
not a pass for either finding or proof about records in other statuses.

## V04 — bounded live administrative rate-limit test

The project owner authorized disposable test records in this environment. A
release-method record was created for the test with ID `8` and neutral code
`security_retest_20260910_01`; it remains inactive.

- Five updates to this record were accepted within a window of less than
  33 seconds.
- The sixth update displayed the Too Many Requests page with explicit error code
  429; response headers were not captured.
- A fresh GET after the rejection showed the saved sort value `990005`, rather
  than the blocked request's proposed value `990006`. This is server readback
  evidence that the rejected update did not overwrite the last accepted value.
- The initial record creation was outside the measured limiter window and is
  excluded from the five accepted requests.

**Result: the five-per-minute limit and rejection without a further write passed
for this administrative update path and session.** No Retry-After header was
captured. This bounded test does not close V04: the hourly limit, shared-IP limit,
cross-session behavior, multiple nodes and the other reported routes remain
untested on the deployed environment. The inactive test record was retained so
its final state can be inspected.

## V09 — public component replacement

The authenticated dashboard references
`https://filmjordan.jo/js/lodash.min.js`. An independent HTTPS GET to that normal
URL, without credentials, cookies, or a cache-busting query, returned:

| Observation | Result |
| --- | --- |
| HTTP status | 200; no redirect followed |
| Content-Type | `application/javascript` |
| Response size | 73,252 bytes |
| Component banner | Lodash 4.18.1 |
| Response SHA-256 | `5077f114118a0f8eeec6c1a302964c3b700db95cc0f37412e5c83fae0d1283d9` |
| Release asset SHA-256 | Exact match with the response |
| Response Date | Thu, 10 Sep 2026 00:06:28 GMT |
| Cache-Control | `max-age=2592000` |
| Last-Modified | Wed, 09 Sep 2026 06:45:15 GMT |

**Result: the public asset replacement check passed for the endpoint sampled.**
This was not a capture of bytes already held in the browser cache, a runtime
`window._.VERSION` measurement, or enumeration of all serving nodes. Existing
cached sessions, alternate nodes, and broader dashboard behavior still need the
checks in the [retest runbook](../deployment/windows/SECURITY-RETEST.md).

The response's 30-day cache lifetime combined with the unversioned asset URL can
leave older bytes in an existing browser cache even when a fresh network response
matches the release. A shared dashboard partial now appends the asset's SHA-256
as a query parameter for the admin, authority and portal layouts. The local
regression check passed with changed file contents and an unchanged modification
time, confirming that the rendered URL changes with the bytes. This additional
cache-versioning change is **pending deployment**. After deployment, verify the
rendered URL and actual bytes with normal browser caching and every serving node;
confirm that any edge cache distinguishes the query parameter.

## Public sign-in cookies and HTTP redirect

A subsequent credential-free GET to `https://filmjordan.jo/ar/sign-in` returned
HTTP 200 and the following cookie attributes. Cookie values were not recorded.

| Cookie | Secure | HttpOnly | SameSite | Domain attribute |
| --- | --- | --- | --- | --- |
| `XSRF-TOKEN` | Yes | Yes | Lax | Absent |
| `rfc-e-permit-session` | Yes | Yes | Lax | Absent |
| `MY-Session` | Yes | Yes | Absent | Absent |
| `TS01d8b3ca` | No | No | Absent | `.jo` |

The HTTP sign-in URL returned 302 to the exact HTTPS sign-in URL and emitted no
cookies. These are sampled response-header observations, not proof across every
flow/node or a determination that a browser accepts the emitted `.jo` Domain.

The application cookie attributes passed this sample. The other two cookies
appear gateway-generated and still require owner confirmation/correction for
V07/V08/V10/V12. No gateway settings were changed. Login/logout, OTP, SANAD,
redirect/error variants and existing-browser cookie behavior remain to be checked.

## V11 — measured public TLS failure

**Result: V11 failed on the measured public listener.** Between
`2026-09-10 01:25:43 UTC` and `01:25:50 UTC`, twelve sequential TLS handshakes
tested TCP `193.188.85.20:443` with SNI `filmjordan.jo`, using OpenSSL 3.6.3.
The current resolver returned that single A record and no AAAA answers. The test
sent no HTTP requests, credentials or application cookies and changed no settings.

| Offered protocol | Measured result |
| --- | --- |
| TLS 1.0 | Accepted `ECDHE-RSA-AES128-SHA` |
| TLS 1.1 | Accepted `ECDHE-RSA-AES128-SHA` |
| TLS 1.2 | Accepted modern `ECDHE-RSA-AES128-GCM-SHA256` |
| TLS 1.3 | Did not negotiate; server alert 40, handshake failure; reason not investigated |

All eight weak TLS 1.2 suites offered individually were also accepted:

| Accepted suite, OpenSSL name | Relevant issue |
| --- | --- |
| `ECDHE-RSA-AES128-SHA256` | CBC |
| `ECDHE-RSA-AES256-SHA384` | CBC |
| `ECDHE-RSA-AES128-SHA` | CBC |
| `ECDHE-RSA-AES256-SHA` | CBC |
| `AES128-GCM-SHA256` | Static RSA key exchange |
| `AES256-GCM-SHA384` | Static RSA key exchange |
| `AES128-SHA256` | Static RSA key exchange and CBC |
| `AES256-SHA256` | Static RSA key exchange and CBC |

The client permitted legacy algorithms to offer these tests; this did not change
the server. Accepted handshakes reported certificate and peer-name verification
OK. The successful modern TLS 1.2 handshake does not negate the weak options
accepted separately. The TLS 1.3 rejection alone does not establish the platform's
capabilities.

The hosting/network owner must harden the actual public TLS terminator, disable
TLS 1.0/1.1 and CBC/static-RSA suites, retain modern TLS 1.2 with authenticated
ephemeral key exchange and AEAD, and retain/enable TLS 1.3 where supported.
Synchronize serving and HA peers, then independently enumerate all protocols and
suites on every public address/listener with SNI.

This was a bounded failing check, not exhaustive enumeration. It did not test
SSL 2/3, every cipher suite, unknown/standby addresses, alternate ports or the
gateway-to-IIS TLS hop. The evidence does not establish which device terminates
public TLS; changing IIS alone may not affect the measured listener. Full
inventory and retest evidence remain required from the infrastructure owner.

## Remaining verification

- V01 remains excluded; supplier/security-team coordination is being handled
  internally, and formal report closure has not been independently verified.
- V02/V03 need the controlled deployed identity and concurrency checks specified
  in the finding review.
- V04 passed the bounded administrative update test above. Retry-After, hourly,
  shared-IP, cross-session, multi-node and other-route verification remain open.
- V05 and gateway cookie findings V07/V08/V10/V12 require observations after the
  actual gateway and changes by its owner where necessary.
- V09 requires deployment of the additional URL-versioning change, then normal
  browser-cache, runtime/dashboard and all-node checks.
- V11 failed the measured public listener; the owner must correct its TLS policy
  and provide complete public listener coverage and a successful retest.
- V06 requires the account-enumeration/timing comparisons described above.
- The named `RFC Laravel Scheduler` task was not found during deployment. The
  local schedule contains an hourly authority-approval SLA check which can
  update records and send inbox/email/SMS notifications. It is separate from
  password-reset delivery. Verify any equivalent existing task and intended
  notification behavior before enabling a new scheduled task.
- Application/scouting invalid-save, corrected-save, edit, and submit flows
  from the report appendices still require controlled browser retesting.
