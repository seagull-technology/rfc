# Email draft — RFC security retest owner actions

Prepared 10 September 2026. Draft only; no email has been sent.

**Not ready to send.** Updated 12 September: application fixes and further
verification remain in progress. Finalize this draft after the application
deployment and remaining checks in the latest verification record are complete.

**Subject:** Filmjordan security retest — admin and infrastructure actions

Dear Administration and Infrastructure Teams,

The following infrastructure findings were observed on the Windows test
environment serving `filmjordan.jo`. Please address these items before the
security retest and provide the supporting evidence described below.

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
internal TLS hop have not been verified.

**V07/V08/V10/V12 — gateway cookies:** The public HTTPS sign-in response emitted:

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
is insufficient.

**V04/V06/V09 — infrastructure coverage:** Confirm all application nodes share
the intended release, persistent limiter store and cache prefix. Identify any
edge caches and invalidate legacy Lodash copies when the application team
provides the final asset URL. One public asset sample already matched; application
URL versioning is tested locally and awaits deployment. Confirm worker monitoring, private
log retention and coordinated restart recovery; reboot recovery remains untested.

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
