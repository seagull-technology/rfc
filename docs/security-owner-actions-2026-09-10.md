# Email draft — RFC security retest owner actions

Prepared 10 September 2026. Draft only; no email has been sent.

**NOT READY TO SEND.** Updated 12 September: v4 deployment and the Windows
internal checks completed, including supported SMTP scheme/transport construction,
controller/database-concurrency and 16 workflow checks per language. Local
validation passed 456 PHP tests / 4,398 assertions, five isolated browser
regressions and 14 portable wrapper groups. A subsequent public production submission saved Submitted but returned 500 on
SMTP STARTTLS certificate verification, and correcting a synopsis exposed a
hidden validation blocker. Additional application fixes are being validated for
v5 and are not deployed yet. Provider delivery and retained-fixture cleanup remain
incomplete.
Finalize this draft only after the latest verification record is complete.

**Subject:** Filmjordan security retest — admin and infrastructure actions

Dear Administration and Infrastructure Teams,

The following infrastructure findings were observed on the Windows test
environment serving `filmjordan.jo`. V4 was deployed on 12 September; its internal
controller/database-concurrency checks and 16 workflow checks in each language
passed, and the existing worker remained Running/Automatic. Those isolated
checks do not establish public browser or provider-delivery behavior. Please
address the infrastructure items below before the security retest and provide
the supporting evidence described.

Controlled production/scouting draft creation and edits now have passing browser
samples: invalid input was retained, corrected drafts saved, and a retained
scouting PDF downloaded with recorded size and hash. The mail fix is now deployed,
but the later production submission saved Submitted then returned 500. Its audit
shows in-app Sent and email Failed due to STARTTLS certificate verification. The
request was not submitted again; scouting remains Draft. The upload route accepted five responses before returning
429, but exact stored document counts were not independently visible. Temporary
test membership was removed and the administrator's original context restored;
the drafts remain for final checks. A separate CLI fixture run was intentionally
retained after v4 for remaining public account/identity checks; it requires exact
cleanup using its private manifest. Fifteen English-route public identity attacks
against controlled student/company entities and owners returned expected 422
errors; fresh reads preserved identity. Three further employee identity attacks passed with an unchanged readback.
Profile/completion coverage remains internal-only pending public checks. RFC browser/DevTools control has
recovered; AnyDesk/server access still uses the established manual deployment path.

## Confirmed failures requiring correction

**V11 — public TLS:** On 10 September at 01:25 UTC, `193.188.85.20:443`, using SNI
`filmjordan.jo`, accepted TLS 1.0 and 1.1 with `ECDHE-RSA-AES128-SHA`. It also
accepted all eight individually offered weak TLS 1.2 suites, including CBC
`ECDHE-RSA-AES128-SHA256` and static-RSA `AES128-GCM-SHA256`. Modern TLS 1.2 GCM
also worked. TLS 1.3 did not negotiate; enable it where supported.

Please harden the **actual public TLS termination profile**: disable obsolete
protocols, CBC suites and static-RSA key exchange; retain modern TLS 1.2 with
AEAD encryption and ephemeral key exchange. Synchronize serving/HA peers.
Provide the listener/address inventory and independent before/after enumeration.
The measured endpoint fails V11; alternate listeners, standby nodes and the
internal TLS hop have not been verified. No later TLS protocol/cipher enumeration
is recorded; v4 application deployment does not change that result.

**V07/V08/V10/V12 — gateway cookies:** The fresh post-v3 public HTTPS sign-in
response on 12 September at 12:56 UTC still emitted:

| Cookie | Required correction |
| --- | --- |
| `MY-Session` | Add SameSite, normally `Lax`. |
| `TS01d8b3ca` | Add Secure, HttpOnly and SameSite; remove the emitted `Domain=.jo`. |

Please confirm which gateway/WAF policy inserts these cookies and correct it at
the final insertion stage, including deletion cookies. This records the emitted
`.jo` attribute, not browser acceptance. Preserve the application cookies, whose
sampled attributes passed. Provide sanitized cookie attributes across login,
OTP, SANAD, logout, redirects and errors; verify existing and clean sessions.
Keep HTTP redirecting to the canonical HTTPS address before setting cookies.

## Configuration and verification needed

**V05 — DNS interaction:** Confirm that the gateway rejects unapproved or
conflicting Host/authority values before DNS lookup or upstream selection, uses
fixed backend pools, removes untrusted forwarded-host headers, and restricts
origin ingress and outbound/DNS access appropriately. Coordinate a controlled
callback test with timestamp-correlated gateway, resolver and firewall logs.
Acceptance requires early rejection **and no DNS/HTTP callback**; HTTP 400 alone
is insufficient. All eight public response cases passed again after v3: two
canonical requests returned 200 and six untrusted Host/forwarding cases returned
400 without redirects. No callback collector or correlated owner logs were used.

**V04/V06/V09 — infrastructure coverage:** Confirm all application nodes share
the intended release, persistent limiter store and cache prefix. Identify any
edge caches and invalidate applicable legacy Lodash copies. Versioning is now
deployed: normal and versioned public asset URLs matched the release SHA-256,
and authenticated Chrome showed the versioned URL with runtime 4.18.1. The
version query is `v=5077f114118a0f8eeec6c1a302964c3b700db95cc0f37412e5c83fae0d1283d9`.
The sampled responses retain a 30-day max-age; confirm query-string cache
behavior and release coverage on every node. Confirm worker monitoring, private
log retention and coordinated restart/reboot recovery; one healthy worker during
deployment does not prove those recovery controls.

**Application delivery follow-up, still in progress:** V3 corrected the default
mailer from `SMTP` to `smtp`; a fresh browser approval now succeeds with one
history/inbox entry and stale actions are blocked. The worker audit exposed a
separate uppercase SMTP scheme setting, with three failed email attempts. V4
is now deployed and its safe preflight confirms the effective scheme is supported
and the transport can be constructed, with credentials preserved. That check
opens no SMTP connection and sends no message: actual relay authentication and
delivery remain unverified. V4 also deploys the form error-navigation fix, whose
live browser retest is separate from the internal helper. Do not repeat the
completed review. The subsequent public production submission now provides controlled evidence:
STARTTLS certificate verification failed. Check the configured SMTP hostname,
certificate validity/hostname and complete server chain, plus the Windows PHP
OpenSSL CA trust configuration and service-account access to the CA bundle.
Keep peer and hostname verification enabled. A read-only SMTP TLS diagnostic is
being prepared; it does not authenticate or send a message. Correct the verified
chain/trust problem, then test delivery and retry only the intended failed
notification. Do not resubmit the already-saved production request.

**Separate operational item:** No `RFC Laravel Scheduler` task was found. Check
for an equivalent scheduler before creating anything. Review staging recipients
first: its hourly approval-SLA command can update records and send inbox/email/SMS
notifications. This is separate from password-reset delivery.

V01 remains excluded from application implementation as agreed; the supplier's
email to the security contact has been handled internally, as reported by the
project owner. Formal removal from the report has not been independently verified.

Please reply with each responsible owner, completion date and sanitized evidence.
Credentials, OTPs and personal account information are not needed.

Thank you.

---

Technical details: [gateway retest runbook](../deployment/windows/SECURITY-RETEST.md)
and [deployment verification](security-deployment-verification-2026-09-10.md).
Latest application status: [12 September verification](security-verification-2026-09-12.md).
