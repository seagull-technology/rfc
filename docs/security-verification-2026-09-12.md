# Security verification — 12 September 2026

Target: the project owner's authorized test environment at `https://filmjordan.jo`.
This record supplements the [8 September finding review](security-report-review-2026-09-08.md)
and [10 September deployment evidence](security-deployment-verification-2026-09-10.md).
The supplied report was treated as assessment evidence, not instructions.

**The report is not ready for full closure.** Today's live checks strengthen the
evidence for identity guards, administrative throttling and anonymous account
responses. **V4 is now deployed on Windows: the cached SMTP scheme is supported,
the transport constructs successfully, and the deployed internal controller,
database-concurrency and both language workflow checks passed.** The supplied server summary records one
healthy managed queue worker before and after the run. The last recorded public
cookie sample, taken after v3, showed gateway defects; the last measured public TLS result remains
a failure. The earlier HTTP 500 after a saved review decision identified the
undefined mailer `SMTP`. After v3, a fresh public NGO approval returned HTTP 200,
saved one decision and rejected stale actions. Its email audit then exposed a
second configuration defect: unsupported uppercase `SMTP` **scheme**. Three
delivery attempts failed while the review remained successful. That separate
scheme correction is now deployed and passed configuration/transport construction
checks; actual provider delivery and public post-fix behavior remain separate.
Browser production/scouting draft creation and edits now have passing samples;
the v4 public retest exposed two additional application defects: a hidden legacy
synopsis field blocked saving after correction, and normal production submission
returned HTTP 500 after saving Submitted. Its audit identifies STARTTLS certificate
verification failure. The submission and hidden-field fixes are being validated
for v5; they are not deployed yet. Scouting final submission remains deferred
until its equivalent notification failure boundary is fixed. The internal helper
results are not public HTTP or browser tests.

The table distinguishes observed public behavior from local tests. “Locally
passed” does not mean deployed, and an individual passing case does not close
the whole finding.

## All-point status

| Point | Live evidence | Local evidence | Deployment / application verification still needed | Infrastructure / external owner needed |
| --- | --- | --- | --- | --- |
| V01 — PII verification | Supplier/security-team correspondence was reported by the project owner; formal removal has not been independently verified. | Excluded from implementation as agreed because the supplier provides two factors. | No further application change planned for this point. | Supplier/security team to determine formal report closure. No additional copy of their internal email is requested. |
| V02 — Identity modification | Earlier NGO/school entity/owner attacks returned 422 with unchanged identities. After v4, 15 English-route attacks against retained student 76/owner 65 and company 77/owner 66 all returned the expected single identity-field 422 error; all four fresh GETs returned 200 with identity unchanged. | Backend/model guards and deployed internal controller checks cover entity, linked-user, employee, profile review and completion paths. | Public student/company/entity/owner samples now passed. Employee 69 additionally passed three public identity attacks with a fresh unchanged read. Profile and completion paths remain internal-only; other language/account-path coverage remains bounded. | No gateway change is implied by the passing sample. Retained fixtures require exact cleanup after public checks. |
| V03 — Conflicting actions | Before v3, competing school 47 decisions saved one rejection but its response was 500. After v3, NGO 46 approval returned 200, entity/owner became Active with one history/inbox entry, and stale approval/rejection returned 422. Its email failed three times on uppercase SMTP scheme; the saved review remained successful. | Stale-action checks and atomic review/inbox/queue persistence passed locally. Delivery retries do not repeat the review/inbox; second-enqueue failure rolls everything back. | V4 is deployed; supported SMTP scheme/transport construction and coordinated database concurrency passed on Windows. Verify intended delivery/audit recovery without repeating the saved review. | Deployment/configuration checks are complete. Transport construction opens no connection, so real email delivery has not passed on that evidence. |
| V04 — Rate limiting | Administrative minute/hour cases passed with retained last-accepted values. Empty contact submissions returned five 422 responses then 429. Application 4 document uploads returned five successful 200-after-redirect responses, then 429 with Retry-After 56 and limit 5. Generic upload titles were absent from fresh applicant/admin views, so exact stored counts were not independently read. | Named route throttles, persistent-store checks and replay regressions passed locally. Anonymous login quota also held across languages and fresh sessions. | Verify exact accepted/rejected content persistence, independent authenticated sessions and the 60/hour shared-IP threshold. Successful upload responses do not establish row counts or all V04 coverage. | Confirm all serving nodes share the persistent limiter store and cache prefix; demonstrate limits across nodes. |
| V05 — External DNS interaction | All eight cases passed again after v3: two canonical requests returned 200 and six untrusted Host/forwarding variants returned 400 without redirects. DNS callback absence remains unverified; the original report observed DNS even with HTTP 400. | Original/forwarded Host masking and repeated-header guards are implemented and locally tested. | V4 production configuration verification completed. Public rejection alone does not establish absence of outbound lookup/callbacks. | Gateway owner must reject unapproved Host/authority before DNS/upstream selection, confirm fixed pools and outbound/DNS controls, and coordinate callback tests with resolver/firewall/origin logs. |
| V06 — Account harvesting | Unknown login/recovery/invalid-OTP/resend cases passed in Arabic/English. Pending NGO/school comparisons passed 26 checks. A later active NGO/rejected school/unknown comparison passed another 26 checks with matching status, redirects, messages and normalized page hashes in both languages. Functional password recovery passed on 10 September. | Generic response, timing-envelope and background delivery checks passed. The internal helper compares supported identifiers/account states and distinguishes unsupported-input cases. | Deployed internal controller/account-response checks passed. A further public matrix passed 180 checks across six states and both languages: 24 wrong-password logins and 12 supported recovery posts. Timing samples remain too small for equivalence; email-login/additional identifier permutations and provider latency are separate. | Confirm shared queue/cache/node configuration and provider evidence where needed. No real account credentials are required in shared evidence. |
| V07 — HttpOnly | Application session and CSRF cookies passed the fresh post-v3 sample; `TS01d8b3ca` still lacked HttpOnly. | Hardened application-cookie and CSRF regressions passed. | Retest all relevant authenticated/anonymous flows and deletion cookies after correction. | Confirm ownership and correct the gateway/WAF cookie insertion policy. |
| V08 — SameSite | After v3, application cookies emitted Lax; `MY-Session` and `TS01d8b3ca` still lacked SameSite. | Application Lax policy and deployment checks passed. | Verify login, OTP, SANAD, logout, redirects/errors and existing/clean sessions after correction. | Apply the appropriate SameSite policy to all gateway-generated cookies and peers. |
| V09 — Outdated component | After v3, both public Lodash URLs returned the exact 73,252-byte asset. Normal authenticated Chrome showed the new SHA-256-versioned URL and runtime 4.18.1. | Locked asset provenance and content-based dashboard URL versioning passed locally. | Deployment, sampled public bytes and emitted browser URL/runtime passed. Continue dashboard behavior and any previously cached legacy-page checks; all-node coverage remains incomplete. | Confirm query-string cache behavior and the release on every serving node; invalidate applicable legacy edge caches. |
| V10 — Secure | Application cookies passed the post-v3 sample; `TS01d8b3ca` still lacked Secure. Earlier HTTP sign-in sample redirected to canonical HTTPS without setting cookies. | Secure-cookie/trusted-proxy configuration checks passed. | Repeat all cookie/redirect flows after gateway correction. | Correct Secure on the actual gateway/WAF cookie, including deletion variants. |
| V11 — Weak TLS | Last measured 10 September: `193.188.85.20:443`, SNI `filmjordan.jo`, accepted TLS 1.0/1.1 and eight individually offered weak TLS 1.2 suites. **Failed; not remeasured today.** | Laravel tests cannot validate or correct the public TLS terminator. | No Laravel deployment by itself closes this point. | Harden the serving TLS profile and peers, then independently enumerate protocols/ciphers on every public listener. |
| V12 — Cookie domain | After v3, application cookies were host-only; `TS01d8b3ca` still emitted `Domain=.jo`. This records the header, not browser acceptance of a public-suffix cookie. | Application host-only session configuration checks passed. | Verify previously issued cookie expiry and clean/existing sessions after correction. | Remove broad Domain attributes at the insertion point and expire observed legacy domain/path combinations correctly. |
| Appendices — Application/scouting forms | Production 4 and scouting 5 passed browser invalid-save/input-preservation, corrected draft creation and edits. Scouting blank optional dates/text and retained story-PDF download passed. V4 correctly opened/highlighted the server-error requirements row, but correcting it exposed a hidden duplicate validation blocker. Normal production 4 submission saved Submitted then returned 500; the audit recorded in-app Sent and email Failed on STARTTLS certificate validation. Scouting 5 remains Draft. | Scouting omission and validation-navigation fixes passed locally, including five isolated browser regressions. | V4 is deployed and its Windows helper passed 16 checks per language. Deploy and publicly retest the additional hidden-field and transactional submission-notification fixes after local validation. Do not replay already-submitted production 4; use a fresh controlled draft. Fix SMTP trust and confirm actual delivery. | RFC browser/DevTools control recovered. Temporary memberships used for v4 tests were also removed from NGO 46 and company 77; original primary membership was unchanged. Test records and the retained CLI run need final cleanup. Direct AnyDesk control remains unavailable. |

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
failure at that point was `UnsupportedSchemeException`: uppercase `SMTP` is not supported
as the scheme for mailer `smtp`; supported schemes are `smtp` and `smtps`.
Thus v3 corrected the default mailer and kept external delivery failure from
turning the saved review into HTTP 500. It did not yet correct this separate
scheme setting or prove email delivery. V4 subsequently corrected the effective
scheme and passed transport construction without connecting. Retain the failed-job/audit evidence and recover the exact
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
navigation to errors; v4's page/tab/requirement correction is now deployed, but
that behavior still needs a live check.

Scouting request **5**, reference **SCOUT-00001**, rejected invalid URLs/date
inputs while preserving entered values. Its corrected Save Draft passed with
blank optional production dates and a story PDF. An Arabic edit cleared optional
story text, retained the PDF and saved the edited title
`Security Verification Scouting SV-20260912-46 Edited`. A subsequent story
download returned HTTP 200 and **631 bytes**, SHA-256
`486893ad47507d48322f05dc38602b03247ab42d3f436c63923b19c00cad672e`.

Neither final submission was attempted during these pre-v4 browser checks while
the mail scheme failure was unresolved. V4 now passes scheme/transport checks;
final public submission/delivery results are still pending. These observations establish the exercised draft/edit/download paths,
not the complete bilingual create-to-submit matrix or final delivery behavior.

Browser control briefly reported an unattached debugger on the old tab, then
recovered through a fresh RFC tab and completed the access/context cleanup
above. Browser UI control is available again. Native Chrome still returned no
accessible control text or screenshot, and AnyDesk continued timing out. These
are control-access limitations, not new application findings. Preserve the test
drafts and evidence for the remaining submission checks and exact final cleanup.

### Public identity matrix after v4

After recovering Chrome DevTools control scoped to RFC, the coordinating task
performed **15 controlled authenticated POSTs on the English routes** using the
new retained fixtures. Existing form values and CSRF stayed in the browser.

| Target | Attempts | Observed result |
| --- | --- | --- |
| Student entity 76 | Replace National ID, clear it, replace it with `registration_type=staff`, replace registration number | 4 × HTTP 422, each with exactly the expected `national_id` or `registration_no` error |
| Student owner 65 | Replace National ID, clear it, replace it with `registration_type=staff` | 3 × HTTP 422, exactly `national_id` |
| Company entity 77 | Replace National ID, clear it, replace it with `registration_type=staff`, replace registration number, clear registration number | 5 × HTTP 422, each with exactly the expected `national_id` or `registration_no` error |
| Company owner 66 | Replace National ID, clear it, replace it with `registration_type=staff` | 3 × HTTP 422, exactly `national_id` |

No response carried a Retry-After header. Fresh GETs of all four targets returned
HTTP 200 and showed unchanged identity values. These are public, authenticated
request/response and readback observations, separate from the CLI helper's
internal results. They do not establish public profile, employee or completion
flow coverage; those remain supported by internal tests only in this record.
No identity values, cookies or CSRF tokens are reproduced here. Browser control
is available again; the earlier native-control interruption does not invalidate
these later observations. AnyDesk remains separate from browser access.

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

A post-v3 comparison at **13:29:50–13:30:14 UTC** repeated the same bounded
wrong-password method for active NGO 46, rejected school 47 and a fresh unknown
identifier. All **26 assertions** passed across 18 requests / six POSTs. Arabic
POST times were 1,504.65 / 1,477.46 / 1,692.71 ms (unknown / active / rejected);
English times were 1,534.83 / 1,409.18 / 1,483.53 ms. One observation per state and
language does not establish timing-distribution equivalence. This run used no
valid passwords, recovery requests or browser cookies. Evidence is recorded in
`active-rejected-login-comparison-results.json` and its summary/checksum files
under the private verification directory.

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

V4 is **deployed**, as recorded in the operator-supplied run below. It adds recognized
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

## Remaining execution after v4 deployment

1. Verify intended delivery and worker/audit recovery after v4 without repeating
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
7. Finish exact fixture/file cleanup after the final checks, including retained
   CLI run `sv-6ff5d9eaaed74d1c` using its complete private manifest. User 1's original
   context 1 has been restored and the temporary NGO-46 applicant_owner membership
   removed with fresh evidence; do not remove original owner 32 or recreate that
   access unnecessarily. RFC browser/DevTools control is available again; direct
   AnyDesk control remains unavailable and the manual server deployment path works.

The confirmed infrastructure failures remain open independently of application
deployment. No owner email was sent as part of this record.

## V4 package and confirmed Windows deployment

Runtime commit `41bb696f57257080b22e6dd4409eb6e42bc19810` is pushed to main.
The offline package contains the SMTP scheme/URL normalization and transport
construction gates, plus the production form error-navigation correction.
The final combined tests above apply to this snapshot. An isolated dependency
cache was completed from the locked public npm registry packages (install
scripts disabled), then the offline builder completed successfully.

**V4 deployment completed on Windows from 14:05:29 to 14:12:12 UTC on
12 September.** The operator pasted the successful summary from the reviewed
manual update block. Direct AnyDesk control was not used. The block then
prepared a separate retained synthetic account-state fixture run for public
V02/V06 checks. Its internal pass does not establish those public outcomes.

| Artifact | SHA-256 |
| --- | --- |
| `rfc-offline-release-20260912-v4.zip` | `6d8024ea87ccc733ccc08c71e03a4d785b15a2a5557bdb8d133cd6b61ecdff71` |
| `SHA256SUMS.txt` | `0d84924c2b10947c130a34e91f3e76f6445a6a926a7e1dab296ffc072612f1f6` |
| `rfc-app.tar.gz` | `0e170936c918287660d23c8650e67080dffbd1078f924109e7f093b3f47df5a4` |

Copy instructions: `output/RFC-STAGING-UPDATE-20260912-v4.txt`.
The complete deployment/fixture block passed PowerShell syntax parsing.
Keep the retained fixture run ID for exact cleanup after its public checks and
queued jobs finish. No historical failed notification job is retried by this
package. The controlled applicant delivery contact is still awaiting the user's
reply; ordinary notifications to configured test-server administrators were
explicitly approved. Final public submissions remain pending verification after
the now-deployed v4 correction.

Independent package inspection passed: all 12 ZIP entries matched the release
folder, all 11 inventory entries passed the bundled checksum reader, and all
6,787 application archive files matched the manifest. Selected source files and
both deployment scripts matched the release commit. The new SMTP URL utility
was present in both generated production classmaps; no real `.env` or storage
file payload was included. This inspection did not execute a deployment.

The **operator-supplied v4 summary** records:

- `deployment-and-internal-checks-completed`, `deployment_completed: true` and
  `public_security_closure: false`, matching the runtime commit and app-archive
  hash in the table above.
- Default mailer `smtp` before and after; no further `.env` default-mailer repair
  was needed (`changed: false`). Before deployment the cached SMTP scheme was
  unsupported and transport construction failed. After deployment/cache refresh,
  `smtp_scheme_supported` and `smtp_transport_constructible` were both `true`.
  The safe booleans do not identify the original setting's location or prove an
  SMTP connection/authentication/send; the check deliberately constructs only.
- Internal controller run **`sv-e1d2f3fbfa6d75e4`** passed, including
  `concurrent_review: passed`. This is the coordinated deployed-database check.
- Workflow helper: **16 Arabic and 16 English checks passed**.
- `RFCQueueWorker` stayed Running/Automatic with one managed PHP worker before
  deployment, after deployment and at final verification.

The successful reviewed wrapper also checked deployed files, production
configuration and its normal helper cleanup gates. These are conclusions from
the supplied transcript and verified wrapper control flow; the private detailed
logs were not independently copied here. Evidence:
`C:\ProgramData\RFC\SecurityVerification\20260912-170531-bce34eca\summary.json`.
These internal requests, configuration probes and database processes do not use
the public browser/gateway and do not close the external findings above.

### Separate retained fixture run — cleanup outstanding

After the standard v4 wrapper completed, a second helper invocation used
`--samples=1 --keep-fixtures --skip-concurrency` with run
**`sv-6ff5d9eaaed74d1c`**. Its internal checks passed in Arabic/English across
the helper's national-ID, registration-number, email, username and phone cases.
The copied block required `concurrent_review: not_run` for this second run;
this deliberate skip is separate from the passing concurrency run above.
Process-local delivery fakes remained enabled; no public request or provider
delivery is established by this setup.

The operator's printed subset identified these controlled fixtures:

| Fixture | Entity ID | User ID |
| --- | --- | --- |
| Student | 76 | 65 |
| Company | 77 | 66 |
| Pending | 85 | 74 |
| Inactive | 86 | 75 |
| Archived | 87 | 76 |

Their complete private manifest and results are under
`C:\inetpub\rfc\storage\app\private\security-verification\sv-6ff5d9eaaed74d1c`.
The printed subset is not the complete cleanup inventory. These records were
intentionally retained and **cleanup remains open** until public checks and
any fixture-related queued work finish. Use the complete run manifest and exact
ownership checks for cleanup; do not delete unrelated or previously existing
records. No credentials, OTPs or real account identifiers are included here.

## Additional public results after v4

The retained fixture matrix ran from 14:31:09 to 14:35:07 UTC. It made 108 total
requests, including 24 wrong-password login POSTs and 12 supported recovery
POSTs, and passed 180 checks. Active student/company, pending, inactive, archived
and unknown states matched generic status, redirects and normalized localized
page contents in Arabic/English. Login had two observations per locale/state;
recovery had one. An inactive Arabic login took 3,595.50 ms, then 1,487.91 ms.
These samples do not establish timing equivalence. Synthetic nondialable phone
values avoid provider SMS, so this matrix does not prove real delivery.

Company employee 69 additionally rejected replacement, clearing and a
`registration_type=staff` bypass with HTTP 422 on `national_id`; a fresh company
team GET returned 200 with the identifier unchanged. Together with the entity
and owner matrix, this is 18 rejected public identity mutations. The current
admin is not a primary member of the fixture company, which is required for
profile-change submission; its original primary membership was not altered.

The production browser test deliberately submitted a short synopsis and then
loaded the validation redirect. The requirements fieldset was visible, the
work-summary row had `table-danger`, its Fill form button received focus, and
old input was preserved. Correcting the drawer to 500 Arabic words exposed
a remaining bug: a hidden legacy inline textarea retained four words and its
custom validity blocked the main submit. This is being fixed with a disabled
legacy fieldset and a real corrected multipart-submission browser regression.

Normal submission of production request 4 (REQ-00002) through its confirmation
modal returned the application Error 500 page. A fresh GET showed Submitted /
Under review. The filtered notification audit showed an in-app Sent entry and
an email Failed entry with Symfony TransportException: STARTTLS certificate
verification failed. The submission was not repeated. Both production and
scouting controllers synchronously sent external notifications after saving;
the additional fix moves inbox and independent external jobs into the same
transaction as the locked submission. SMTP certificate verification remains
enabled. Certificate-chain/hostname/CA-trust diagnosis and actual delivery
are separate from reliable application persistence.

Temporary user 1 memberships in company 77 and NGO 46 were removed afterward;
both success messages and absence of the matching removal forms were checked.
The original primary membership was unchanged. Retained fixture cleanup is
still pending. Sanitized observations:

- `/private/tmp/rfc-security-verification-20260912/post-v4-public-browser-results.json`
- `/private/tmp/rfc-security-verification-20260912/retained-fixture-public-matrix-results.json`
- `/private/tmp/rfc-security-verification-20260912/retained-fixture-public-matrix-summary.md`

## V5 local candidate verification

The final combined source passed **487 PHP tests / 4,661 assertions** on PHP
8.3.32 in 181.77 seconds. Seven real-browser wizard regressions passed, including
correcting the server error and intercepting the actual multipart submission:
only the corrected drawer synopsis was submitted. The public-limit helper passed
21 guard/kernel tests and a separate production-dependency fixture/session smoke.
Four isolated SMTP tests accepted trusted STARTTLS/implicit TLS and rejected an
untrusted CA and hostname mismatch; no authentication or message was sent.

Independent reviews found no remaining concrete blocker in these changes.
These are local results; v5 deployment, public correction/submission tests,
the server public shared-IP run and real SMTP trust/delivery remain pending.
The existing deployment wrapper was not modified.

- Full suite: `/private/tmp/rfc-security-verification-20260912/full-php-suite-v5-review.log`
- SMTP probe: `/private/tmp/rfc-security-verification-20260912/smtp-trust-local-regression-results.json`
- Release notes: `deployment/windows/RELEASE-NOTES-20260912-v5.txt`
