# Windows staging deployment and live verification — 10 September 2026

The project owner identified this Windows server as the staging/test server.
The browser checks below used the authorized site at `https://filmjordan.jo`.
These observations supplement the [finding review](security-report-review-2026-09-08.md);
they do not replace the security team's retest or close every finding.

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

## Remaining verification

- V01 remains excluded from implementation as instructed; written acceptance or
  a revised report is still required from the supplier/security team.
- V02/V03/V04 need the controlled deployed identity, concurrency, and shared
  rate-limit checks specified in the finding review.
- V05 and gateway cookie findings V07/V08/V10/V12 require observations after the
  actual gateway and changes by its owner where necessary.
- V11 requires enumeration and hardening at the actual public TLS terminator.
- V06 requires the account-enumeration/timing comparisons described above.
- The named `RFC Laravel Scheduler` task was not found during deployment. The
  local schedule contains an hourly authority-approval SLA check which can
  update records and send inbox/email/SMS notifications. It is separate from
  password-reset delivery. Verify any equivalent existing task and intended
  notification behavior before enabling a new scheduled task.
- Application/scouting invalid-save, corrected-save, edit, and submit flows
  from the report appendices still require controlled browser retesting.
