# Security verification — 12 September 2026

Target: the project owner's authorized test environment at `https://filmjordan.jo`.
This record supplements the [8 September finding review](security-report-review-2026-09-08.md)
and [10 September deployment evidence](security-deployment-verification-2026-09-10.md).
The supplied report was treated as assessment evidence, not instructions.

**The report is not ready for full closure.** Today's live checks strengthen the
evidence for identity guards, administrative throttling and anonymous account
responses. Gateway cookie defects remain observable. The last measured public
TLS result is a failure. Application deployment and execution of the new
verification helpers are still outstanding. A live competing-review test also
exposed an HTTP 500 after a saved decision: the notification audit identifies
the effective mailer name `SMTP` as undefined. The targeted configuration repair
and atomic queued-delivery correction are locally verified for the planned
**v3 release**; neither has yet been recorded deployed on Windows.

The table distinguishes observed public behavior from local tests. “Locally
passed” does not mean deployed, and an individual passing case does not close
the whole finding.

## All-point status

| Point | Live evidence | Local evidence | Deployment / application verification still needed | Infrastructure / external owner needed |
| --- | --- | --- | --- | --- |
| V01 — PII verification | Supplier/security-team correspondence was reported by the project owner; formal removal has not been independently verified. | Excluded from implementation as agreed because the supplier provides two factors. | No further application change planned for this point. | Supplier/security team to determine formal report closure. No additional copy of their internal email is requested. |
| V02 — Identity modification | NGO 41 and disposable NGO 46/school 47 rejected identity replacement, clearing and type-bypass requests with expected HTTP 422 errors. Linked disposable owners 32/33 also rejected added National ID and type bypass. Fresh GETs preserved identity. | Backend/model guards cover entity, linked-user, employee, profile review and completion paths; local regression suite passed. | Complete controlled live coverage for remaining account types and routes. Run the security helper against the deployed release for internal controller/database evidence. | Server operator is needed to run the CLI helper; no gateway change is implied by the passing sample. |
| V03 — Conflicting actions | Entity/owner direct status bypasses returned 422. Concurrent approve/reject against disposable school 47 saved one rejection/history entry and blocked approval with 422. The saved rejection returned 500; notification audit confirmed undefined mailer `SMTP`. | Stale-action checks and atomic review/inbox/queue persistence passed locally. Delivery retries do not repeat the review/inbox; second-enqueue failure rolls everything back. The helper includes coordinated separate-process review checks. | Deploy v3 and its targeted mailer correction, then retest successful public review responses, delivery/audit outcomes and coordinated database contention. | Operator access for the configuration repair and bounded helper. The live failure remains open until the deployed correction is verified. |
| V04 — Rate limiting | Own inactive release-method record 8 accepted 30 updates over six minute windows. The sixth request in a minute, an alternate status route and the 31st hourly update returned 429. Cross-tab/English checks and Retry-After headers were observed. Fresh DOM readback retained the last accepted value. Empty contact-center submissions returned five validation 422 responses then 429 with Retry-After 59. | Named route throttles, persistent-store checks and replay regressions passed locally. Anonymous login quota also held across languages and fresh sessions. | Finish successful content writes, independent authenticated sessions and the 60/hour shared-IP threshold. Passing one administrative route family does not close all V04 coverage. | Confirm all serving nodes share the persistent limiter store and cache prefix; demonstrate limits across nodes. |
| V05 — External DNS interaction | Eight public checks passed: two canonical requests returned 200 and six untrusted Host/forwarding variants returned 400 without redirects. DNS callback absence remains unverified; the original report observed DNS even with HTTP 400. | Original/forwarded Host masking and repeated-header guards are implemented and locally tested. | Recheck the deployed application/IIS configuration as part of release verification. | Gateway owner must reject unapproved Host/authority before DNS/upstream selection, confirm fixed pools and outbound/DNS controls, and coordinate callback tests with resolver/firewall/origin logs. |
| V06 — Account harvesting | Unknown login/recovery/invalid-OTP/resend cases passed in Arabic/English. Pending NGO/school and unknown wrong-password comparisons also matched status, redirects, messages and normalized page hashes (26 checks). Functional password recovery passed on 10 September. | Generic response, timing-envelope and background delivery checks passed. The internal helper compares supported identifiers/account states and distinguishes unsupported-input cases. | Run the helper on the deployed release, then complete remaining public account states and meaningful timing analysis. Verify worker/provider latency separately. | Operator access for internal evidence and shared queue/cache/node confirmation. No real account credentials are required in shared evidence. |
| V07 — HttpOnly | Application session and CSRF cookies passed the 12 September sample; `TS01d8b3ca` was still emitted without HttpOnly. | Hardened application-cookie and CSRF regressions passed. | Retest all relevant authenticated/anonymous flows and deletion cookies after correction. | Confirm ownership and correct the gateway/WAF cookie insertion policy. |
| V08 — SameSite | Application cookies emitted Lax; `MY-Session` and `TS01d8b3ca` still lacked SameSite. | Application Lax policy and deployment checks passed locally. | Verify login, OTP, SANAD, logout, redirects/errors and existing/clean sessions after correction. | Apply the appropriate SameSite policy to all gateway-generated cookies and peers. |
| V09 — Outdated component | The 10 September public Lodash response matched the patched 4.18.1 release SHA-256. Chrome also reported `window._.VERSION` as `4.18.1` on 12 September. Existing-browser cache replacement and all-node coverage remain incomplete. | Locked asset provenance and content-based dashboard URL versioning passed locally. | Commit `981ddbe` was pushed but its URL-versioning change is not live. Deploy it with v3, then verify normal browser caching and dashboard behavior. | Invalidate applicable edge caches and confirm query-string cache behavior plus every serving node. |
| V10 — Secure | Application cookies passed; `TS01d8b3ca` was emitted without Secure on 12 September. Earlier HTTP sign-in sample redirected to canonical HTTPS without setting cookies. | Secure-cookie/trusted-proxy configuration checks passed locally. | Repeat all cookie/redirect flows after gateway correction. | Correct Secure on the actual gateway/WAF cookie, including deletion variants. |
| V11 — Weak TLS | Last measured 10 September: `193.188.85.20:443`, SNI `filmjordan.jo`, accepted TLS 1.0/1.1 and eight individually offered weak TLS 1.2 suites. **Failed; not remeasured today.** | Laravel tests cannot validate or correct the public TLS terminator. | No Laravel deployment by itself closes this point. | Harden the serving TLS profile and peers, then independently enumerate protocols/ciphers on every public listener. |
| V12 — Cookie domain | Application cookies were host-only; `TS01d8b3ca` still emitted `Domain=.jo` on 12 September. This records the header, not browser acceptance of a public-suffix cookie. | Application host-only session configuration checks passed locally. | Verify previously issued cookie expiry and clean/existing sessions after correction. | Remove broad Domain attributes at the insertion point and expire observed legacy domain/path combinations correctly. |
| Appendices — Application/scouting forms | Full deployed browser create → invalid save → corrected save → edit → submit coverage remains pending in this record. | Navigation/flash preservation checks passed. Both workflows passed the local helper. A scouting HTTP 500 caused by omitted optional fields was reproduced and fixed. | Deploy the scouting nullable-field correction with v3, run the workflow helper in both languages on Windows, and complete browser behavior checks. | Operator to run the release and bounded helpers. Browser/JavaScript/IIS behavior remains separate from their internal HTTP-kernel checks. |

Infrastructure requirements and acceptance criteria are specified in the
[gateway retest runbook](../deployment/windows/SECURITY-RETEST.md). No network,
gateway, firewall or TLS configuration was changed during today's verification.

## Live administrative checks today

These observations came from the authenticated administrator's Chrome session.
Requests used the session in its browser; cookies and credentials were not
exported. Sensitive original identity values are not included here.

For NGO entity 41, altered registration identity payloads returned field-specific
HTTP 422 responses. Requests included replacing and clearing registration number,
adding National ID and adding a `registration_type` parameter as a bypass attempt.
An ordinary change to inactive status also returned HTTP 422 on `status`. A fresh
GET confirmed that neither identity nor status changed. This establishes those
specific rejection paths, not the entire identity/workflow matrix.

The same four entity identity attacks and a direct pending-to-active change
were rejected for new disposable NGO 46 and school 47. Their owners 32 and 33
also rejected adding National ID, adding a registration-type bypass parameter,
and activating the pending owner directly. Each response was HTTP 422 on the
intended identity/status field. Fresh entity reads preserved identity/status;
fresh owner reads showed blank National ID and pending-review status.

Two browser requests submitted school 47 approval and rejection concurrently.
Approval returned HTTP 422 on `decision` after 508 ms; rejection returned HTTP
500 after 849 ms. A fresh GET showed **Rejected**, removed the review form and
displayed exactly one rejection history entry. The filtered notification audit
showed an in-app notification sent and email failed with
`InvalidArgumentException: Mailer [SMTP] is not defined.` This proves the saved
state was singular in this sample and identifies the later delivery failure.
The outcome is not a successful end-to-end review pass. The coordinated
database-lock helper and post-fix public review retest remain required.
Subsequent stale approval, stale rejection and a stale ordinary entity edit
each returned HTTP 422 on `decision`, `decision` and `status`, respectively.

The V04 target was the previously created, inactive release-method record 8.
Thirty accepted updates were spread over six separate minute windows. The
cross-tab English sixth request returned HTTP 429. An alternate status route
returned HTTP 429 with `Retry-After: 21`. The 31st hourly update returned:

```text
HTTP 429
Retry-After: 2285
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 0
```

A fresh DOM readback showed sort value `912030` and inactive status. This confirms
the rejected request did not overwrite the last accepted update. Shared-IP,
independent-session, content/contact and multi-node checks are still separate.

The contact-center submission endpoint received six deliberately empty requests,
containing only the browser's CSRF field. The first five returned HTTP 422;
observed validation errors required title, message type, message and recipient
scope. The sixth returned HTTP 429, `Retry-After: 59`, limit 5. No recipient or
message was submitted. This demonstrates that the content-submission limiter
runs for that route; it does not test successful message creation or its hourly
and shared-IP thresholds.

## Public Host-header checks

Eight credential-free GETs used the real TLS SNI/certificate identity for
`filmjordan.jo`, with reserved `.invalid` names for untrusted HTTP headers.
Canonical Host and canonical forwarded Host returned 200. Foreign Host,
forwarded-Host concealment, foreign/mixed forwarded Host, `Forwarded` and
`X-Original-Host` variants returned 400. None emitted a Location header; no
redirect was followed. Cookies and bodies were not saved. See
`public-host-header-results.json` in the private evidence directory.
There was no callback collector or correlated server DNS log, so callback
absence and all gateway nodes remain unverified.

## Anonymous HTTP checks today

The reusable [anonymous harness](../scripts/security_retest_http.py) performed
33 requests, including 13 POSTs, between **11:25:46 and 11:26:31 UTC**. All
40 bounded assertions passed. It used fresh in-memory cookie jars, fresh CSRF
tokens, synthetic unknown identifiers and random wrong passwords. It imported
no browser cookies and submitted no valid password or known-account recovery.

- Five unknown login attempts alternating Arabic/English returned the same
  localized invalid-credentials outcome; the sixth returned 429 with
  `Retry-After: 881`. A fresh anonymous session with the same identifier also
  returned 429, with `Retry-After: 880`.
- Both languages returned the neutral OTP-page acknowledgement for unknown
  recovery, without a development OTP.
- Invalid OTP submissions remained on the OTP page with the localized neutral
  error. Immediate resends displayed the cooldown message.
- The reviewed unknown-user delivery job exits without an existing user.
  Absence of real SMS in this run is inferred from that code path, not verified
  from provider/queue logs.

This small sample is not a timing-distribution comparison. Non-throttled unknown
login POSTs took 1,423.07–1,598.15 ms. Unknown recovery took 1,012.11 and 650.75 ms.
Invalid OTP took 7,427.69 ms in Arabic and 1,590.55 ms in English. The outlier is
retained without claiming a cause or equivalence from two samples.

A separate comparison used the two newly confirmed pending fixtures and a fresh
unknown identifier: 18 requests, six wrong-password POSTs, **26 checks passed**.
Each fixture identifier was submitted only once per language. Within each
language all three cases matched HTTP 302 sign-in redirects, POST body hashes,
followed HTTP 200 generic messages and normalized full sign-in page hashes.
POST timings were 1,453.58–1,530.74 ms. One sample per state/language is not a
timing-distribution comparison; active, inactive and archived public states
remain separate. No valid passwords or known-account recovery were submitted.
Evidence: `pending-login-comparison-results.json` and
`pending-login-comparison-summary.md` in the same private directory below.

The observed public cookie attributes were:

| Cookie | Secure | HttpOnly | SameSite | Domain emitted |
| --- | --- | --- | --- | --- |
| `XSRF-TOKEN` | Yes | Yes | Lax | None |
| `rfc-e-permit-session` | Yes | Yes | Lax | None |
| `MY-Session` | Yes | Yes | Missing | None |
| `TS01d8b3ca` | Missing | Missing | Missing | `.jo` |

Cookie values, CSRF tokens, passwords, account identifiers and response bodies
are excluded from the shared results. Sanitized evidence is retained locally at:

- `/private/tmp/rfc-security-verification-20260912/anonymous-http-results.json`
- `/private/tmp/rfc-security-verification-20260912/anonymous-http-summary.md`
- `/private/tmp/rfc-security-verification-20260912/HTTP-SHA256SUMS.txt`

The 10 September TLS evidence and its bounded coverage limits remain in the
earlier deployment record. No successful infrastructure correction/retest has
been supplied since those observations.

## Disposable live fixtures

The [fixture creator](../scripts/security_create_registration_fixture.py) sends
one NGO or school registration per invocation, with its own anonymous CSRF
session. It uses a marked dummy PDF, unique `.invalid` email and an all-zero
phone that the reviewed normalizer converts to empty. Registration itself does
not dispatch notifications or perform a government lookup for these two types.
No terms/consent checkbox was present or submitted. Generated passwords are
confined to private local files and are not included in this document.

| Fixture | Current evidence |
| --- | --- |
| `Security Verification NGO SV-20260912-f2a63ce0e23d16be` | Administrator confirmed new entity **46**, linked owner **32**, pending review. The owner phone was subsequently saved blank through the normal admin form; the reviewed controller stores that value as `NULL`. |
| `Security Verification SCHOOL SV-20260912-eb7ba85ca4bf916f` | Administrator confirmed entity **47**, linked owner **33**, initially pending review. Controlled competing review subsequently saved a single rejection; email delivery failed because the effective mailer `SMTP` is undefined. |

These records are reserved for controlled checks and are not yet recorded as
cleaned up. No other registrations were attempted by this creator. Their local
evidence directories sit under the same private verification directory above.

An approval email is **not** suppressed merely because its address ends in
`.invalid`: the notification path still invokes the configured mail transport.
On the currently deployed pre-v3 code, a transport failure can produce HTTP 500
after the entity/user status transaction has committed. Check fresh state before
any retry. The locally verified v3 correction moves external delivery to jobs;
it is not yet a live pass. Empty/null phone suppresses SMS; process-local fakes
in the internal helper avoid external delivery altogether. Do not use unrelated
real recipients to work around delivery failures.

## Local validation and deployment boundary

The final combined local suite reported by the coordinating task on 12 September
passed **426 tests and 4,268 assertions**. The staging deployment wrapper passed
**12 portable check groups**; these are local PowerShell checks, not Windows
PowerShell 5.1/IIS execution of the new wrapper.

The v3 source push and offline build have been verified. Source commit:
`dee4aeaf3e2feb4ce3af144454454a457ddfd0d7`.
Ready archive: `output/rfc-offline-release-20260912-v3.zip`.
SHA-256: `1b51f5efdc5d8d67d66a7b26e57a5ef704f26aa8b99fb8c4915f888856c5536c`.
**Windows deployment has not occurred.** Manual transfer/execution is pending
after the remote-control connection timed out. The release includes:

- Atomic registration decisions: status/history, one inbox notification and two
  independent database delivery jobs share the application transaction. A queue
  insert failure rolls them back. Email/SMS failures retry through the worker
  without changing the saved decision or repeating the inbox entry. Three
  attempts use 15-second backoff. Delivery is at-least-once; an ambiguous provider
  response can still lead to a duplicate external message.
- Small immutable notification snapshots containing only entity ID, names and
  original decision status. The entity snapshot excludes loaded users, password
  hashes, remember tokens, National IDs and unrelated metadata. The
  original locale and generated action/signed URLs are retained across retries.
- A narrowly guarded staging-wrapper repair of the confirmed `SMTP` → `smtp`
  configuration error, with private byte-for-byte backup and preservation of
  unrelated settings. Defined custom mailer names are not normalized. Ambiguous
  assignments/overrides stop the automatic repair.
- Production gates for an exact configured mailer name and a finite positive
  SMTP socket timeout no greater than 30 seconds, including mail-URL overrides.
  The bundled timeout defaults to 30 seconds without a new required environment
  value. It bounds individual socket waits, not total job duration; the nominal
  90-second job timeout is not a PCNTL-enforced deadline on Windows.
- A database-queue production gate: its driver must be `database`, and
  `DB_QUEUE_CONNECTION` must be null/empty or exactly the default application
  connection name. Another alias is rejected even if its settings match. The
  notification path also checks the actual shared connection before dispatch.
- Lodash dashboard URL versioning and the scouting correction for omitted
  `production_start_date`, `production_end_date`, `story_text` and
  `production_type_other` values. These changes are not yet verified on Windows.

The wrapper does not retry old failed jobs or resend earlier notifications.
Neither a successful local suite nor a completed source commit proves real mail
delivery, queue health or a deployed HTTP response.

The two standalone helpers have separate scopes:

- [Security helper](../scripts/verify-staging-security.php): internal controller
  identity/workflow/account-response checks, exact fixture cleanup and
  coordinated review processes. Its local SQLite execution passed; SQLite
  explicitly does not prove the production database's row-lock contention.
- [Workflow helper](../scripts/verify-staging-workflows.php): isolated deployed
  HTTP-kernel checks for both workflows, including CSRF, logo/validation
  redirects, retained input, uploads, corrected saves, edits and submission.
  Arabic and English each passed 16 local checks, with transaction rollback and
  temporary-file cleanup verified. It injects fixture authentication and does
  not exercise browser JavaScript or the gateway.

An independent smoke test extracted the production-only 12 September v1
dependency bundle and initialized both helpers' isolation paths on local PHP
8.3.32. Mockery and PHPUnit were absent. Queue jobs and notifications were
captured, raw mail was suppressed, and HTTP/direct SMS attempts were blocked.
This is production-dependency compatibility evidence, **not Windows integration**.
Neither helper has yet been recorded running on the actual Windows test server.

## Remaining execution after v3 deployment

1. Run the verified v3 staging wrapper and retain the deployed source/archive
   evidence, production-check result and active mailer/worker status. Run the
   security helper with database concurrency enabled and the workflow helper in
   both languages; require explicit results and cleanup evidence. A skipped or
   unproved concurrency result is not a pass.
2. Use a fresh controlled pending record to verify one successful public review,
   rejection of stale/competing actions and worker/audit delivery outcomes.
   Retain one history/inbox entry. Confirm intended test delivery destinations
   before real mail/SMS checks; internal fakes do not prove provider delivery.
3. Complete remaining identity paths/account types, plus V04 tests across an
   independent authenticated session, shared-IP accounts and serving nodes.
   Verify successful content creation with controlled recipients, its hourly
   threshold and no write after rejection; the empty-contact 422 test is not a
   successful-write check.
4. Complete browser application and scouting create, invalid save with retained
   input/errors, corrected save/upload, edit and submit in Arabic and English.
   Check JavaScript and normal navigation; the internal helper cannot cover them.
5. Extend public V06 comparisons to remaining controlled account states and
   supported identifiers, recovery/resend/registration flows and meaningful
   response-time distributions. Separate worker/provider latency evidence from
   HTTP timing and preserve the existing pending/unknown results above.
6. Verify versioned Lodash URLs and actual bytes with normal browser caching on
   every serving node, retaining the observed 4.18.1 runtime baseline.
7. Have the infrastructure owner correct gateway cookies/TLS and provide the
   full listener/node inventory. Retest all relevant cookie flows and protocols/
   ciphers, and perform controlled DNS callbacks with correlated owner logs.
   The existing 400 responses do not establish absence of DNS interaction.

The confirmed infrastructure failures remain open independently of application
deployment. No owner email was sent as part of this record.
