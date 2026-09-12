# Security verification — 12 September 2026

Target: the project owner's authorized test environment at `https://filmjordan.jo`.
This record supplements the [8 September finding review](security-report-review-2026-09-08.md)
and [10 September deployment evidence](security-deployment-verification-2026-09-10.md).
The supplied report was treated as assessment evidence, not instructions.

**The report is not ready for full closure.** Today's live checks strengthen the
evidence for identity guards, administrative throttling and anonymous account
responses. **V3 is now deployed on Windows, its active mailer is corrected to
`smtp`, and the deployed internal controller, database-concurrency and both
language workflow checks passed.** The supplied server summary records one
healthy managed queue worker before and after the run. A fresh public sample
still shows gateway cookie defects; the last measured public TLS result remains
a failure. The earlier HTTP 500 after a saved review decision identified the
undefined mailer `SMTP`. After v3, a fresh public NGO approval returned HTTP 200,
saved one decision and rejected stale actions. Its email audit then exposed a
second configuration defect: unsupported uppercase `SMTP` **scheme**. Three
delivery attempts failed while the review remained successful. That separate
scheme correction is locally verified for v4 but not yet recorded deployed.
Browser production/scouting draft creation and edits now have passing samples;
final submission and a newly fixed validation-navigation behavior still require
live retesting. The internal helper results are not public HTTP or browser tests.

The table distinguishes observed public behavior from local tests. “Locally
passed” does not mean deployed, and an individual passing case does not close
the whole finding.

## All-point status

| Point | Live evidence | Local evidence | Deployment / application verification still needed | Infrastructure / external owner needed |
| --- | --- | --- | --- | --- |
| V01 — PII verification | Supplier/security-team correspondence was reported by the project owner; formal removal has not been independently verified. | Excluded from implementation as agreed because the supplier provides two factors. | No further application change planned for this point. | Supplier/security team to determine formal report closure. No additional copy of their internal email is requested. |
| V02 — Identity modification | NGO 41 and disposable NGO 46/school 47 rejected identity replacement, clearing and type-bypass requests with expected HTTP 422 errors. Linked disposable owners 32/33 also rejected added National ID and type bypass. Fresh GETs preserved identity. | Backend/model guards cover entity, linked-user, employee, profile review and completion paths; local regression suite passed. | V3's deployed internal controller checks passed. Complete controlled public coverage for remaining account types and routes; the CLI result does not replace it. | No gateway change is implied by the passing sample. The requested server helper run is complete. |
| V03 — Conflicting actions | Before v3, competing school 47 decisions saved one rejection but its response was 500. After v3, NGO 46 approval returned 200, entity/owner became Active with one history/inbox entry, and stale approval/rejection returned 422. Email failed three times on unsupported uppercase `SMTP` scheme; the saved review remained successful. | Stale-action checks and atomic review/inbox/queue persistence passed locally. Delivery retries do not repeat the review/inbox; second-enqueue failure rolls everything back. | V3 and the default-mailer correction are deployed; coordinated database concurrency passed on Windows. Correct the newly identified scheme setting and verify targeted delivery recovery without repeating the review. | Initial operator deployment/repair is complete. A further application configuration correction is in progress; real email delivery has not passed. |
| V04 — Rate limiting | Administrative minute/hour cases passed with retained last-accepted values. Empty contact submissions returned five 422 responses then 429. Application 4 document uploads returned five successful 200-after-redirect responses, then 429 with Retry-After 56 and limit 5. Generic upload titles were absent from fresh applicant/admin views, so exact stored counts were not independently read. | Named route throttles, persistent-store checks and replay regressions passed locally. Anonymous login quota also held across languages and fresh sessions. | Verify exact accepted/rejected content persistence, independent authenticated sessions and the 60/hour shared-IP threshold. Successful upload responses do not establish row counts or all V04 coverage. | Confirm all serving nodes share the persistent limiter store and cache prefix; demonstrate limits across nodes. |
| V05 — External DNS interaction | All eight cases passed again after v3: two canonical requests returned 200 and six untrusted Host/forwarding variants returned 400 without redirects. DNS callback absence remains unverified; the original report observed DNS even with HTTP 400. | Original/forwarded Host masking and repeated-header guards are implemented and locally tested. | V3 production configuration verification completed. Public rejection alone does not establish absence of outbound lookup/callbacks. | Gateway owner must reject unapproved Host/authority before DNS/upstream selection, confirm fixed pools and outbound/DNS controls, and coordinate callback tests with resolver/firewall/origin logs. |
| V06 — Account harvesting | Unknown login/recovery/invalid-OTP/resend cases passed in Arabic/English. Pending NGO/school and unknown wrong-password comparisons also matched status, redirects, messages and normalized page hashes (26 checks). Functional password recovery passed on 10 September. | Generic response, timing-envelope and background delivery checks passed. The internal helper compares supported identifiers/account states and distinguishes unsupported-input cases. | Deployed internal controller/account-response checks passed. Complete remaining public account states and meaningful timing analysis; verify worker/provider latency separately. | Confirm shared queue/cache/node configuration and provider evidence where needed. No real account credentials are required in shared evidence. |
| V07 — HttpOnly | Application session and CSRF cookies passed the fresh post-v3 sample; `TS01d8b3ca` still lacked HttpOnly. | Hardened application-cookie and CSRF regressions passed. | Retest all relevant authenticated/anonymous flows and deletion cookies after correction. | Confirm ownership and correct the gateway/WAF cookie insertion policy. |
| V08 — SameSite | After v3, application cookies emitted Lax; `MY-Session` and `TS01d8b3ca` still lacked SameSite. | Application Lax policy and deployment checks passed. | Verify login, OTP, SANAD, logout, redirects/errors and existing/clean sessions after correction. | Apply the appropriate SameSite policy to all gateway-generated cookies and peers. |
| V09 — Outdated component | After v3, both public Lodash URLs returned the exact 73,252-byte asset. Normal authenticated Chrome showed the new SHA-256-versioned URL and runtime 4.18.1. | Locked asset provenance and content-based dashboard URL versioning passed locally. | Deployment, sampled public bytes and emitted browser URL/runtime passed. Continue dashboard behavior and any previously cached legacy-page checks; all-node coverage remains incomplete. | Confirm query-string cache behavior and the release on every serving node; invalidate applicable legacy edge caches. |
| V10 — Secure | Application cookies passed the post-v3 sample; `TS01d8b3ca` still lacked Secure. Earlier HTTP sign-in sample redirected to canonical HTTPS without setting cookies. | Secure-cookie/trusted-proxy configuration checks passed. | Repeat all cookie/redirect flows after gateway correction. | Correct Secure on the actual gateway/WAF cookie, including deletion variants. |
| V11 — Weak TLS | Last measured 10 September: `193.188.85.20:443`, SNI `filmjordan.jo`, accepted TLS 1.0/1.1 and eight individually offered weak TLS 1.2 suites. **Failed; not remeasured today.** | Laravel tests cannot validate or correct the public TLS terminator. | No Laravel deployment by itself closes this point. | Harden the serving TLS profile and peers, then independently enumerate protocols/ciphers on every public listener. |
| V12 — Cookie domain | After v3, application cookies were host-only; `TS01d8b3ca` still emitted `Domain=.jo`. This records the header, not browser acceptance of a public-suffix cookie. | Application host-only session configuration checks passed. | Verify previously issued cookie expiry and clean/existing sessions after correction. | Remove broad Domain attributes at the insertion point and expire observed legacy domain/path combinations correctly. |
| Appendices — Application/scouting forms | Production 4 and scouting 5 passed browser invalid-save/input-preservation, corrected draft creation and edits. Scouting blank optional dates/text and retained story-PDF download passed. Final submissions were not attempted while the known mail scheme failure remains. | V3's scouting omission correction passed. V4 additionally fixes validation navigation to the correct page/tab/requirement; five isolated browser regressions passed, but that change is not yet deployed. | V3's Windows helper passed 16 checks per language. Deploy v4, retest live error navigation, finish remaining language coverage and both final submissions. | Browser control recovered. Temporary test membership/context cleanup is verified complete; test records remain reserved for final checks. Native console/AnyDesk access remains unavailable. |

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
database-lock helper subsequently passed after v3 deployment. A separate fresh
public approval also passed after v3, as recorded below; the historical school
decision was not repeated.
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

A separate content-write check used the document-upload route for the controlled
production application 4. Five uploads returned HTTP 200 after their redirects;
the sixth returned HTTP 429 with `Retry-After: 56` and limit 5. Fresh applicant
and administrator views did not render the generic uploaded document titles.
Therefore this records successful responses followed by enforced throttling,
not an independent count of inserted document rows or proof that the blocked
request created no row. Exact persistence remains to be checked before claiming
that stronger outcome.

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

## Public checks repeated after v3

Eleven read-only GETs between **12:56:52 and 12:57:05 UTC** rechecked the public
asset, anonymous sign-in cookies/headers and eight Host-header cases after the
Windows deployment. Requests verified TLS certificates, sent no credentials or
cookies, followed no redirects and used no callback collector. TLS protocols
and cipher suites were not re-enumerated.

Both `/js/lodash.min.js` and its versioned URL
`/js/lodash.min.js?v=5077f114118a0f8eeec6c1a302964c3b700db95cc0f37412e5c83fae0d1283d9`
returned HTTP 200 and the exact **73,252-byte** source asset with SHA-256
`5077f114118a0f8eeec6c1a302964c3b700db95cc0f37412e5c83fae0d1283d9`.
Both responses retained `Cache-Control: max-age=2592000`, a 12 September
Last-Modified value and the same ETag; neither had an Age header. These fresh
network requests do not prove replacement of a populated browser cache or
coverage of every node. The anonymous sign-in page has no Lodash include, so
authenticated dashboard URL/runtime behavior remains a separate browser check.

The fresh `/ar/sign-in` response returned HTTP 200 with private/no-store/no-cache,
HSTS (`31536000; includeSubDomains; preload`), nosniff, SAMEORIGIN and no-referrer.
Application cookies still had Secure, HttpOnly and SameSite=Lax with no Domain.
`MY-Session` still lacked SameSite; `TS01d8b3ca` still lacked all three flags and
emitted `Domain=.jo`. Cookie values were not recorded; the domain observation
does not establish browser acceptance of a public-suffix cookie.

The eight repeated Host cases again produced two canonical HTTP 200 responses
and six HTTP 400 rejections without redirects. The reserved
`header-verification.invalid` name and a documentation-only IP were used.
Callback absence remains unverified despite the passing response checks.
No account data, form submission, notification or server setting was changed
by this public sample. Sanitized summary:
`/private/tmp/rfc-security-verification-20260912/post-v3-public-summary.md`.

## Authenticated browser checks after v3

The coordinating task's live Chrome check approved the fresh pending NGO 46.
The browser request completed with HTTP 200 after its redirect; a fresh view
showed both entity and owner Active and exactly one approval history entry.
Subsequent stale approval and rejection each returned HTTP 422 on `decision`.
Normal Chrome also showed the new versioned Lodash script URL and
`window._.VERSION` equal to `4.18.1`.

The notification audit showed one sent in-app entry, skipped SMS for the missing
phone, no remaining Pending delivery and **three failed email attempts**. The
failure is now `UnsupportedSchemeException`: uppercase `SMTP` is not supported
as the scheme for mailer `smtp`; supported schemes are `smtp` and `smtps`.
Thus v3 corrected the default mailer and kept external delivery failure from
turning the saved review into HTTP 500. It did not yet correct this separate
scheme setting or prove email delivery. A narrow scheme/configuration gate fix
is in progress. Retain the failed-job/audit evidence and recover the exact
intended delivery after its cause is corrected; do not approve the entity again
or broadly retry unrelated failed jobs.

### Production and scouting form checks

To use only the controlled NGO, a temporary **non-primary** `applicant_owner`
membership linked existing user 1 to NGO 46. The original entity context **1**
was recorded before switching to **46**. After the form checks, normal browser
context switching restored **Platform Administration / 1**, and the exact
temporary membership was removed. A fresh membership table showed only original
owner **32**, with Added and Removed role-audit entries. The original primary
membership was unchanged. This access/context cleanup is complete; the test
drafts themselves remain for final checks.

Production application **4**, reference **REQ-00002**, was created through
Chrome's Save Draft flow after invalid requirements/synopsis submissions showed
errors and preserved entered values. A later Save Updates edit passed. Fresh
readback confirmed the edited title
`Security Verification Production SV-20260912-46 Edited` and synopsis PDF
metadata. The live invalid-save flow also exposed incorrect JavaScript
navigation to errors; v4 fixes the page/tab/requirement targeting locally, but
that fix still needs a live check after deployment.

Scouting request **5**, reference **SCOUT-00001**, rejected invalid URLs/date
inputs while preserving entered values. Its corrected Save Draft passed with
blank optional production dates and a story PDF. An Arabic edit cleared optional
story text, retained the PDF and saved the edited title
`Security Verification Scouting SV-20260912-46 Edited`. A subsequent story
download returned HTTP 200 and **631 bytes**, SHA-256
`486893ad47507d48322f05dc38602b03247ab42d3f436c63923b19c00cad672e`.

Neither final submission was attempted while the known mail scheme failure
remains. These observations establish the exercised draft/edit/download paths,
not the complete bilingual create-to-submit matrix or final delivery behavior.

Browser control briefly reported an unattached debugger on the old tab, then
recovered through a fresh RFC tab and completed the access/context cleanup
above. Browser UI control is available again. Native Chrome still returned no
accessible control text or screenshot, and AnyDesk continued timing out. These
are control-access limitations, not new application findings. Preserve the test
drafts and evidence for the remaining submission checks and exact final cleanup.

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
| `Security Verification NGO SV-20260912-f2a63ce0e23d16be` | Entity **46**, linked owner **32**, was created pending review and subsequently approved in the post-v3 browser check; both are now Active, with one approval history/inbox entry. The owner phone was saved blank and SMS was skipped. Email failed three attempts on unsupported uppercase SMTP scheme. |
| `Security Verification SCHOOL SV-20260912-eb7ba85ca4bf916f` | Administrator confirmed entity **47**, linked owner **33**, initially pending review. Controlled competing review subsequently saved a single rejection; email delivery failed because the effective mailer `SMTP` is undefined. |
| `Security Verification Production SV-20260912-46 Edited` | Application **4**, **REQ-00002**, belongs to controlled NGO 46; draft creation/edit and synopsis PDF metadata passed. Final submission and exact document-upload row counts remain unverified. |
| `Security Verification Scouting SV-20260912-46 Edited` | Scouting **5**, **SCOUT-00001**, belongs to controlled NGO 46; corrected draft/Arabic edit and retained story-PDF download passed. Final submission remains unattempted. |

These records are reserved for controlled checks and are not yet recorded as
cleaned up. No other registrations were attempted by this creator. Their local
evidence directories sit under the same private verification directory above.
The temporary non-primary user-1/NGO-46 `applicant_owner` membership was removed,
with fresh table/audit evidence, and original context 1 was restored. Original
owner 32 remains. This completes temporary access cleanup; the controlled
registration/application/scouting records and their files remain for final tests.

An approval email is **not** suppressed merely because its address ends in
`.invalid`: the notification path still invokes the configured mail transport.
With the pre-v3 release, a transport failure produced HTTP 500 after the
entity/user status transaction committed. That historical decision must not be
repeated as a retry. The deployed v3 correction moves external delivery to jobs;
its fresh public approval response passed, while the email audit exposed the
separate scheme error described above. Empty/null phone suppresses SMS;
process-local fakes
in the internal helper avoid external delivery altogether. Do not use unrelated
real recipients to work around delivery failures.

## Local validation and deployed release

The final combined **v4 local** suite on PHP 8.3.32 passed **456 tests and 4,398
assertions** in 178.00 seconds. The five isolated headless browser regressions
for wizard validation navigation passed; the previous script failed four of
those cases. The staging deployment wrapper passed **14 portable check groups**.
An initial sandboxed browser launch failed its setup hook; the approved retry
with a temporary profile and all network requests blocked passed all five tests.
These are local validations, not deployment or a public-site browser retest of
v4. Logs:

- `/private/tmp/rfc-security-verification-20260912/full-php-suite-v4-review.log`
- `/private/tmp/rfc-security-verification-20260912/browser-wizard-v4-review-approved.log`
- `/private/tmp/rfc-security-verification-20260912/windows-wrapper-v4-review.log`

V4 is being prepared and is **not yet recorded deployed**. It adds recognized
SMTP/SMTPS scheme normalization, including a single unambiguous MAIL_URL scheme
query value, while preserving credentials and unrelated URL bytes. Duplicate,
array-valued or malformed scheme options are refused. Production preflight
constructs the effective SMTP transport without connecting; the wrapper checks
it after cache refresh. V4 also corrects validation navigation to the affected
wizard page/tab or requirement row and ignores hidden duplicate controls.

The v3 source push and offline build have been verified. Source commit:
`dee4aeaf3e2feb4ce3af144454454a457ddfd0d7`.
Deployed archive: `output/rfc-offline-release-20260912-v3.zip`.
SHA-256: `1b51f5efdc5d8d67d66a7b26e57a5ef704f26aa8b99fb8c4915f888856c5536c`.
The operator's supplied Windows output confirms execution from **12:39:48 to
12:46:36 UTC** on 12 September, ending with
`deployment-and-internal-checks-completed`. The release includes:

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
  `production_type_other` values. The deployed workflow checks now passed on
  Windows in both languages.

The wrapper does not retry old failed jobs or resend earlier notifications.
Neither a successful local suite nor a completed source commit proves real mail
delivery or a public HTTP response. Worker health was separately recorded in
the Windows run described below; actual provider delivery remains separate.

### Confirmed Windows v3 run

The operator pasted the wrapper's complete safe summary, which records:

- Release/source: `rfc-offline-release-20260912-v3` /
  `dee4aeaf3e2feb4ce3af144454454a457ddfd0d7`; application archive SHA-256
  `8819326da473409e5fc01d525e96bd6874c5005b97d6ce00191644a21f2041dd`.
- `deployment_completed: true` and `public_security_closure: false`.
- Before repair, cached default `SMTP` was present with no defined uppercase
  mailer, no process override and no alternate environment file. The guarded
  repair changed the literal setting to `smtp`; the refreshed cached default
  was confirmed lowercase with the configured smtp mailer still present.
  Original environment bytes were backed up privately, not copied into this
  shared record.
- `controller_checks_passed: true` for run `sv-7b48d86901f4e8ed`, and
  `concurrent_review: passed` on the deployed database. This is the helper's
  coordinated separate-process check, not a simultaneous public browser test.
- `workflow_ar_checks_passed: 16` and `workflow_en_checks_passed: 16`.
- `RFCQueueWorker` remained Running/Automatic with exactly one managed PHP
  worker before deployment, after deployment and at final verification.

Completion of the reviewed wrapper also means its checked production
configuration command, deployed-file checks and helper cleanup gates returned
success. The raw private logs and per-check server JSON have not been copied
into this record; these conclusions use the supplied summary and the wrapper's
verified control flow. The v3 production gates did not detect the separate
uppercase SMTP scheme later revealed by the real worker audit; their successful
run is not evidence that every delivery setting was valid. Its evidence directory is
`C:\ProgramData\RFC\SecurityVerification\20260912-153952-abfcef80`, with the safe
`summary.json` and private detailed logs. The environment backup and raw
deployment log must remain private.

The two standalone helpers have separate scopes:

- [Security helper](../scripts/verify-staging-security.php): internal controller
  identity/workflow/account-response checks, exact fixture cleanup and
  coordinated review processes. Its deployed Windows controller and database
  concurrency checks now passed; the earlier local SQLite run alone did not
  establish that contention result.
- [Workflow helper](../scripts/verify-staging-workflows.php): isolated deployed
  HTTP-kernel checks for both workflows, including CSRF, logo/validation
  redirects, retained input, uploads, corrected saves, edits and submission.
  Arabic and English each passed 16 local and deployed Windows checks, with
  transaction rollback and temporary-file cleanup accepted by the wrapper.
  It injects fixture authentication and does
  not exercise browser JavaScript or the gateway.

An independent smoke test extracted the production-only 12 September v1
dependency bundle and initialized both helpers' isolation paths on local PHP
8.3.32. Mockery and PHPUnit were absent. Queue jobs and notifications were
captured, raw mail was suppressed, and HTTP/direct SMS attempts were blocked.
This earlier smoke test is production-dependency compatibility evidence, not
Windows integration. The separate operator-supplied v3 run above now records
both helpers executing successfully on the actual Windows test server.

## Remaining execution after v3 deployment

1. Deploy the locally verified v4 SMTP scheme and validation-navigation fixes,
   then verify the intended delivery and worker/audit recovery without repeating
   the completed NGO approval. The fresh public review and stale-action checks
   already passed; internal fakes do not prove provider delivery. Keep retries
   limited to the exact intended test delivery and retain one history/inbox entry.
2. Complete remaining identity paths/account types, plus V04 tests across an
   independent authenticated session, shared-IP accounts and serving nodes.
   Independently verify application 4's accepted/rejected document persistence,
   content hourly limits and no write after rejection; successful responses do
   not establish exact row counts, and empty-contact 422 is not a message save.
3. Retest live wizard error navigation after v4, finish the remaining language
   coverage and final submission of both controlled drafts after mail checks.
   Preserve the passing draft/edit/download evidence above. The internal helper
   cannot replace browser JavaScript or public delivery verification.
4. Extend public V06 comparisons to remaining controlled account states and
   supported identifiers, recovery/resend/registration flows and meaningful
   response-time distributions. Separate worker/provider latency evidence from
   HTTP timing and preserve the existing pending/unknown results above.
5. Extend the passing normal-Chrome versioned Lodash URL/runtime and fresh
   public-byte checks to every serving node and any previously cached legacy
   page. Continue dashboard behavior checks; the sampled runtime is 4.18.1.
6. Have the infrastructure owner correct gateway cookies/TLS and provide the
   full listener/node inventory. Retest all relevant cookie flows and protocols/
   ciphers, and perform controlled DNS callbacks with correlated owner logs.
   The existing 400 responses do not establish absence of DNS interaction.
7. Finish exact fixture/file cleanup after the final checks. User 1's original
   context 1 has been restored and the temporary NGO-46 applicant_owner membership
   removed with fresh evidence; do not remove original owner 32 or recreate that
   access unnecessarily. Browser UI is available again, while native console/
   AnyDesk access still needs restoration or the existing manual deployment path.

The confirmed infrastructure failures remain open independently of application
deployment. No owner email was sent as part of this record.
