# HR attendance: operation and deployment

## Delivered attendance foundation

The HR module reuses users, authentication, Spatie permissions, Blade/Metronic, queues, notifications and Excel. It implements employee identity mapping, attendance supervisor scope, immutable schedules, fixed/flexible and multiple daily shifts, holidays and cancellation history, temporary calendars, daily/monthly attendance and XLSX, correction requests with independent decisions, persistent reconciliation summaries/exceptions, audit screens and queued decision notifications. Raw HR events and legacy attendance/import records are preserved. Native signed capture supports HTTP(S) with independent device approval. Physical office-network verification remains a separate pending integration. The owner narrowed HR to attendance only on 18 October 2026; see hrms-architecture-roadmap.md for the current scope.

## Configuration

```dotenv
HR_TIMEZONE=Asia/Hebron
HR_WEB_REQUIRE_HTTPS=false
HR_NATIVE_REQUIRE_HTTPS=false
HR_ATTENDANCE_ENABLED=true
HR_ATTENDANCE_NETWORK_MODE=unrestricted
HR_EXPORT_TTL_HOURS=24
HR_ATTENDANCE_GATEWAY_URL=
HR_ALLOWED_NETWORKS=
HR_MIKROTIK_ENABLED=false
HR_MIKROTIK_HOST=
HR_MIKROTIK_PORT=
HR_MIKROTIK_USERNAME=
HR_MIKROTIK_PASSWORD=
```

No addresses, ports, CIDRs or router credentials are guessed. Device approval is mandatory. The default unrestricted mode allows capture from any network reaching the server. Restricted network modes deny capture until actual verification is implemented. No router writes occur.

HR web pages allow HTTP by the owner's explicit instruction. Authentication, permissions and CSRF remain enforced. HR_WEB_REQUIRE_HTTPS=true enables web transport enforcement outside local/testing. The owner's subsequent instruction also permits native attendance over HTTP: HR_NATIVE_REQUIRE_HTTPS=false is independent of the web setting. HTTP does not encrypt credentials or tokens; signing is not transport encryption. Never change APP_ENV to bypass configuration. Existing deployments must update older HR_ATTENDANCE_ENABLED=false / HR_ATTENDANCE_NETWORK_MODE=disabled values and clear the configuration cache to use the new mode.

## Installing on the server

Git transfers code, not database migrations or local grants. Pull the changes, then run:

```shell
php artisan hr:install-attendance --no-interaction
```

The command first backs up the actual HR/user database through the existing backup command, ignoring alternate backup connection overrides for that operation. Failure stops migration. It then migrates only app/Modules/HR/database/migrations, verifies storage, seeds HR permissions and clears route/config caches. It does not run unrelated pending module migrations or legacy backfills. Inspect the existing system operation log for the backup result and file. Verify restoration separately on staging. MySQL backup passwords are passed through the child process environment, never command arguments or ordinary failure logs.

Without SSH, use **الإدارة المركزية → أوامر Artisan → hr:install-attendance** through the existing protected administration screen. Production cannot select --skip-backup. The local/testing-only option was used after verifying the local MySQL phc_new database. No remote migration has been executed by this agent.

Review migrations and staging acceptance before deployment. Never use migrate:fresh, reset or generic rollback against production. Restoring the old per-day unique event index would fail once multiple daily sessions exist; plan a forward repair, not automatic downgrade. Rebuild normal deployment caches after verification.

## Authorization and operation

The additive seeder creates eleven attendance administration/read permissions; it neither grants roles nor removes existing permissions:

- hr.attendance.view: global attendance visibility.
- hr.attendance.view-own: linked employee self history.
- hr.attendance.view-team: staff in the current primary reporting line or an active dated department scope; legacy supervisor mapping applies only before primary organizational history begins.
- hr.attendance.setup and hr.attendance.export.
- hr.attendance.correct: corrections for visible employees.
- hr.attendance.correct-own: self corrections, with self visibility.
- hr.attendance.approve: independent decisions for visible employees.
- hr.attendance.exceptions.manage, hr.attendance.reconcile and hr.attendance.audit.

Phase 2 adds hr.attendance.capture and hr.devices.register for authorized employees, plus separate hr.devices.view, hr.devices.approve and hr.devices.revoke for HR staff. There are 16 active HR permissions, all for attendance or device registration/administration. Retired permissions already stored in the database are preserved but have no HR management endpoints. These five secure-capture/device permissions are not automatically granted to Database Officer. Device approval requires visible employee scope and a different employee identity. Self-service revocation of one's own lost device is allowed; replacement requires revocation before approval of a new key.

By the owner's instruction, Database Officer receives the nine administrative attendance abilities directly. It receives no payroll/device grants. Model policies still prohibit approving the reviewer's own attendance or a proposal submitted by that reviewer.

For non-officers, the optional hr:grant-attendance-view email command grants only global attendance viewing, preserves existing roles/grants, audits the change and clears the permission cache. It never migrates tables. Missing workflow tables now produce a useful 503 requiring the HR installer instead of a database exception.

Use **الموارد البشرية → الحضور والانصراف**. Map employees explicitly using existing account IDs; staff without login may leave user_id blank. Check identity before saving. The original supervisor link is a fallback before primary organization assignments exist; current assignments and department scopes then define attendance team visibility. Create immutable schedule versions and finite assignments. Future schedule changes use an audited assignment retirement plus replacement. Historical schedules and raw facts are not edited.

The calendar accepts global or schedule-specific holidays, immutable cancellation records, and employee leave/excused/off-day/temporary-schedule exceptions. A temporary range is limited to 31 days and honors the selected schedule's weekdays; a single-day work_schedule exception can explicitly override an off day. Calendar exceptions must not be used to approve one's own leave. They are attendance administration, not the completed leave entitlement/workflow subsystem.

**ملخص وتقارير Excel** provides scoped monthly counts, known/unknown hours, delay/early-departure minutes and source conflicts. The direct XLSX endpoint remains bounded to 500 employees. For larger reports, request background export: a snapshot of the authorized employee IDs is processed through chained 100-employee workbook jobs and finalized as a private ZIP with per-part generation times, source hashes and file hashes. Parts may reflect sources at different generation times; this is an operational report, not a frozen payroll run. Three pending requests per user limit duplicate workload. Workers recheck the complete scope; download requires the requester, active account, current permissions, unchanged file hash and unexpired request. Temporary files default to 24 hours; configure HR_EXPORT_TTL_HOURS for the approved operational retention window. Request identity/scope/expiry cannot be changed or deleted through Eloquent.

Run normal queue workers with a timeout no greater than the configured retry_after interval. Export jobs use 60-second timeouts, three attempts and overlap locks; the default database retry_after is 90 seconds. A failure stops the chain and removes partial derived files. The daily hr:prune-attendance-exports schedule removes expired files only and retains request history. It is also available as an administrative CLI command. The scheduler and queue worker must be running on the server.

Reconciliation now queues alerts for open absence candidates, missing sessions/times and source conflicts to the currently linked employee and supervisor, subject to active accounts and visible employee scope. A unique exception/recipient/source-fingerprint ledger prevents duplicate delivery. Resolved exceptions and changed recipient scope are rechecked in the job. Alerts and all HR decisions use the existing database notification header with meaningful titles, messages and action links; no external email/push transport is assumed.

**أمن الحضور** displays sanitized API denial records to hr.attendance.audit users. Repeated actor(or anonymous network source)/route/status denials are sampled once per minute to prevent log flooding. Only route/status/sample-window metadata is stored; no password, token, signature, biometric template or raw IP is retained. Audit subject_id now permits null for request-level events and subject_key identifies UUID export requests; existing numeric audit rows remain intact. Downgrading that audit migration is blocked once nonnumeric subjects exist.

Corrections retain the original source fingerprint and optional per-shift intervals. Approving requires a different reviewer, unchanged source data and an authorized employee scope. Imported data changing after approval flags a new conflict; approved facts are not silently rewritten. A final decision is immutable. Unknown times may remain unknown; approval never fabricates hours. Decision notifications use the existing database queue/notification channel.

Reconciliation from the exceptions screen queues a bounded period (at most 31 days) and rechecks the actor's activity and permissions when the job executes. Run the normal queue worker for jobs/notifications. The administrative CLI is:

```shell
php artisan hr:reconcile-attendance --from=2026-10-01 --to=2026-10-12 --no-interaction
```

Optional --employee narrows the command. No automatic recurring reconciliation schedule is configured yet. Persisted exception screens show the last completed reconciliation; queued work is not shown as already finished.

## Calculation rules

- Event instants are UTC; work date/presentation use schedule timezone. Overnight checkout belongs to the check-in work date, including assignment expiry.
- One action pair per employee/assignment/work date. Only one open session per employee. Multiple sessions exclude gaps from working hours; submitted correction intervals cannot overlap.
- Local simulation uses a two-hour early arrival window. This is not an approved production policy. No public simulator route exists.
- Each completed session subtracts its configured unpaid break, floored at zero. Timed/partial-shift break policies remain pending PHC decisions.
- Flexible attendance defines an allowed arrival band and preserves the shift's elapsed duration. Late minutes begin after the latest permitted arrival plus grace. Early departure is measured against the employee's arrival-adjusted end.
- Missing sessions/checkout and absence candidates appear after the relevant window expires. Calendar leave/holidays/off days affect expected work. These are not automatic payroll deductions.
- Legacy present/absent flags carry unknown hours. Missing check-in/out also leaves total hours unknown. Raw/legacy/calendar disagreements are flagged; row-level Excel workbook provenance is not claimed when unavailable.
- Cards aggregate all authorized matching employees, not just the page of 25. Immediate Excel caps requests at 500 employees; the private background workflow supports larger authorized rosters with bounded workbook parts.
- Immutable Eloquent models block normal and bulk writes/deletes. Raw SQL can bypass them. Use separate deployment DDL credentials and runtime database grants that prohibit UPDATE/DELETE on HR event/audit/decision ledgers.

## Secure native Android and network prerequisites

Phase 2 extends native-android, not the separate Capacitor shell. The implementation uses a distinct configured HTTP(S) origin/client/session, Keystore authentication-per-use signing, BiometricPrompt CryptoObject, enrollment approval/revocation, short-lived signed challenges and atomic consumption/event append. Existing inquiry tokens, wildcard tokens and mixed HR/inquiry audience tokens are rejected by the HR API. Native HR login issues an eight-hour hr:native token, checks the employee mapping and preserves account separation when switching/logging out.

All native endpoints are under /api/v1/hr. HTTPS enforcement is optional via HR_NATIVE_REQUIRE_HTTPS=true. The default unrestricted network mode does not verify office presence; other modes remain blocked pending an actual network verifier. Available endpoints: auth/login, auth/logout, me, attendance/today, attendance/history, devices/challenge, devices/register, devices/status, devices/{credential}/revoke, attendance/challenge, attendance/check-in and attendance/check-out. The protected HR web devices screen provides scoped approval, rejection, revocation and immutable decision history. No public administrative device API is exposed.

Registration proof expires after five minutes. Attendance challenges expire after 60 seconds and include PHC-HR-1 protocol, purpose, UUID, employee, credential, action, resolved assignment and work date, random 32-byte nonce and Unix UTC expiry. payload is base64 of the exact stored UTF-8 JSON bytes. Android signs those bytes with SHA256withECDSA; signatures are base64 of ASN.1 DER ECDSA P-256 signatures. The server never rebuilds signed client claims. Employee lock precedes credential and challenge locks. Revocation/activity are rechecked, and event append plus challenge consumption commit together. An identical verified request has a ten-minute receipt retry window; other replay/change attempts fail. Client timestamps, IP/SSID assertions and success messages are not trusted.

The HR origin defaults to the application's phcBaseUrl; override phcHrBaseUrl with an HTTP(S) application base ending in /. The build generates Android cleartext exceptions only for configured HTTP hosts. There is no automatic HTTPS downgrade, redirect following, or certificate-validation bypass. Android 28+ is required for native biometric signing; older/unsupported devices use the audited HR correction workflow. The generated P-256 key requires authentication for each use and is invalidated on biometric enrollment changes. Available certificate chains are sent as unverified evidence. Enrollment proves key possession; trusted hardware attestation validation remains outstanding and is not implied by approval. See the Android documentation for [CryptoObject keys](https://developer.android.com/reference/android/hardware/biometrics/BiometricPrompt.CryptoObject) and [server-side attestation verification](https://developer.android.com/privacy-and-security/security-key-attestation).

An API contract or disabled network adapter does not establish Android completion. Device attestation where feasible and instrumented biometric/integration checks remain necessary. BiometricPrompt verifies an enrolled device user, not a uniquely identified PHC employee. No biometric templates leave Android.

Obtain actual PHC RouterOS version, management TLS service/port, read-only account, office CIDRs/VLANs/AP topology, internal DNS/certificate, gateway identity, proxy trust and VPN/public ingress separation. No MikroTik settings are guessed or modified. DHCP/ARP/SSID are diagnostics only.

Production activation requires real Android TLS tests, internal network restrictions, direct public-route denial, forged gateway/proxy-header denial, VPN bypass tests, signature/expiry/replay/revocation/concurrency evidence, and explicit PHC acceptance.

## Validation checkpoint

Attendance-only update (18 October 2026): **206 tests passed, 969 assertions** across `tests/Feature/HR`, role permission grouping, mobile API and the existing Damage Assessment attendance summary tests. This includes retired GET/POST/PUT endpoints, archive/file preservation, installer idempotency, dated attendance scopes, project workers and signed native capture. Formatter and `git diff --check` passed. No Android sources or historical migrations changed. Deployment to the remote server was not performed.

Historical HR/workflow/device/operations/installer/backup/permission/mobile/legacy-attendance run: **172 tests passed, 814 assertions**. This was a historical validation checkpoint before attendance-only simplification. Current regression tests verify retired endpoints, archive/file preservation and attendance team scope; the employee directory and organization chart are no longer delivered features. Overnight native checkout and stale-session refusal are verified. Operations tests include real private XLSX/ZIP files, formula string safety, unknown hours, owner/expiry/revoked-scope checks, hash tampering, immutable requests, bounded chains, scoped/deduplicated exception notifications and sanitized security reports. Secure tests verify actual OpenSSL P-256 signatures, expiry, replay, changed action, deactivation/revocation, wrong token audience, independent approval, replacement, own history and rollback on invalid sequence. Core tests cover scopes, source preservation, overnight/DST, schedules, histories, corrections, queues and fail-closed capture. An earlier targeted legacy/module/auth/mobile regression run passed 76 tests (397 assertions). SQLite tests do not prove production MySQL concurrency.

native-android/build-native.ps1 -Verify -CompileDeviceTests completed: debug APK, JVM unit tests, lintDebug and instrumented-test APK compilation. Three new AttendanceSecurityTest JVM cases passed. Five Compose attendance cases were compiled but not run: both attached ADB aliases returned error: closed. Actual biometric signing, Keystore invalidation/recovery, gateway TLS and device acceptance remain outstanding. Building an APK is not production Android validation.

Formatter: php vendor/bin/pint --dirty --format agent. Tests run with php artisan test --compact and explicit paths; this repository's test wrapper does not accept --no-interaction.

Real device/browser acceptance, staging restore drills, MySQL parallel-worker behavior, and network activation remain outstanding. Other HRMS phases are outside the current scope. Existing unrelated Damage Assessment and Android changes are preserved.

## Attendance-only scope — 18 October 2026

The HR workspace now contains attendance and its supporting identity/project/schedule/device/correction/reporting workflows only. Contract, employee directory/profile/document/history and organization management routes, controllers, forms, views, services and contract expiry scheduling have been removed. Database Officer has only the original nine attendance allowances. Old direct grants do not restore removed endpoints; their URLs return 404.

Historical migrations remain unchanged. No DROP, DELETE, file cleanup or account changes are part of this update. Existing contract/profile/document/history records, encrypted files, legacy attendance and Excel imports remain stored. Keep the existing APP_KEY to retain access to encrypted archives during a separately authorized recovery. Minimal organizational relationships and dated scopes remain solely because attendance authorization and exception notification recipients depend on them; management screens and writes are removed.

After deploying the code, run `php artisan hr:install-attendance --no-interaction`. The installer backs up first, applies HR migrations, seeds only attendance/device permissions and clears route, configuration and compiled-view caches. It no longer seeds contract types. Restart long-running queue workers with the existing deployment procedure so they load the new code. No Android rebuild is required for this server-only scope reduction.

## Project attendance preparation — 18 October 2026

From the HR home page, attendance administrators can open **تجهيز حضور موظفي المشاريع**. First create a real project and its dates, then reuse or create the employee attendance identity, and finally select the project, work schedule and finite assignment dates. Employees retain one identity/account across projects; ending a project assignment does not end their employment or deactivate the account. Assignments cannot extend beyond the project or employment interval, overlap existing working shifts, or exceed the existing one-year assignment limit. Absence candidates are calculated only for scheduled working days within the effective assignment; imported source facts and unknown times remain unchanged.

The additive migration creates `hr_attendance_projects` and adds nullable `project_id` to `hr_shift_assignments`. Existing assignments keep a null project. Run the existing protected `hr:install-attendance --no-interaction` on a deployed server; it backs up before applying HR migrations. Projects and assignments preserve their historical definitions. New project setup does not create demonstration employees, projects, schedules, or dates in production. Project names appear in daily/monthly attendance and an additional Excel column; daily totals still combine all the employee's shifts rather than allocate salaries or costs between projects.

An official employee number is optional in attendance setup; a unique internal PHC code is generated when absent. Enter the employee's existing account email to link it. Selecting **تفعيل الحضور عبر التطبيق** explicitly grants only own attendance viewing, native capture and device registration. No permissions are granted by default. The same option is available when assigning an already mapped employee to a project. It does not approve the device, grant payroll or HR administration, change roles, or bypass independent device approval. The employee must still request device registration from Android. No Android update is needed for these project assignments: the native API already captures against the selected immutable shift assignment.
