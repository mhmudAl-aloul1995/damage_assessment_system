# HR attendance: operation and deployment

## Delivered attendance foundation

The HR module reuses users, authentication, Spatie permissions, Blade/Metronic, queues, notifications and Excel. It implements employee identity mapping, attendance supervisor scope, immutable schedules, fixed/flexible and multiple daily shifts, holidays and cancellation history, temporary calendars, daily/monthly attendance and XLSX, correction requests with independent decisions, persistent reconciliation summaries/exceptions, audit screens and queued decision notifications. Raw HR events and legacy attendance/import records are preserved. Employee capture is currently a local/test simulator; production Android/network capture is not activated. The full ten-phase scope remains in hrms-architecture-roadmap.md.

## Configuration

```dotenv
HR_TIMEZONE=Asia/Hebron
HR_WEB_REQUIRE_HTTPS=false
HR_ATTENDANCE_ENABLED=false
HR_ATTENDANCE_NETWORK_MODE=disabled
HR_ATTENDANCE_GATEWAY_URL=
HR_ALLOWED_NETWORKS=
HR_MIKROTIK_ENABLED=false
HR_MIKROTIK_HOST=
HR_MIKROTIK_PORT=
HR_MIKROTIK_USERNAME=
HR_MIKROTIK_PASSWORD=
```

No addresses, ports, CIDRs or router credentials are guessed. Device approval is mandatory. Gateway/network services currently deny production capture and perform no router writes; flags alone cannot activate it.

HR web pages allow HTTP by the owner's explicit instruction. Authentication, permissions and CSRF remain enforced. HR_WEB_REQUIRE_HTTPS=true enables web transport enforcement outside local/testing. This web setting does not relax native attendance TLS or certificate verification. Never change APP_ENV to bypass security.

## Installing on the server

Git transfers code, not database migrations or local grants. Pull the changes, then run:

```shell
php artisan hr:install-attendance --no-interaction
```

The command first backs up the actual HR/user database through the existing backup command, ignoring alternate backup connection overrides for that operation. Failure stops migration. It then migrates only app/Modules/HR/database/migrations, verifies storage, seeds HR permissions and clears route/config caches. It does not run unrelated pending module migrations or legacy backfills. Inspect the existing system operation log for the backup result and file. Verify restoration separately on staging. MySQL backup passwords are passed through the child process environment, never command arguments or ordinary failure logs.

Without SSH, use **الإدارة المركزية → أوامر Artisan → hr:install-attendance** through the existing protected administration screen. Production cannot select --skip-backup. The local/testing-only option was used after verifying the local MySQL phc_new database. No remote migration has been executed by this agent.

Review migrations and staging acceptance before deployment. Never use migrate:fresh, reset or generic rollback against production. Restoring the old per-day unique event index would fail once multiple daily sessions exist; plan a forward repair, not automatic downgrade. Rebuild normal deployment caches after verification.

## Authorization and operation

The additive seeder creates eleven attendance permissions; it neither grants roles nor removes existing permissions:

- hr.attendance.view: global attendance visibility.
- hr.attendance.view-own: linked employee self history.
- hr.attendance.view-team: employees whose supervisor_user_id is the current user.
- hr.attendance.setup and hr.attendance.export.
- hr.attendance.correct: corrections for visible employees.
- hr.attendance.correct-own: self corrections, with self visibility.
- hr.attendance.approve: independent decisions for visible employees.
- hr.attendance.exceptions.manage, hr.attendance.reconcile and hr.attendance.audit.

By the owner's instruction, Database Officer receives the nine administrative attendance abilities directly. It receives no payroll/device grants. Model policies still prohibit approving the reviewer's own attendance or a proposal submitted by that reviewer.

For non-officers, the optional hr:grant-attendance-view email command grants only global attendance viewing, preserves existing roles/grants, audits the change and clears the permission cache. It never migrates tables. Missing workflow tables now produce a useful 503 requiring the HR installer instead of a database exception.

Use **الموارد البشرية → الحضور والانصراف**. Map employees explicitly using existing account IDs; staff without login may leave user_id blank. Check identity before saving. The supervisor link provides minimum attendance scope; full organization management belongs to Phase 5. Create immutable schedule versions and finite assignments. Future schedule changes use an audited assignment retirement plus replacement. Historical schedules and raw facts are not edited.

The calendar accepts global or schedule-specific holidays, immutable cancellation records, and employee leave/excused/off-day/temporary-schedule exceptions. A temporary range is limited to 31 days and honors the selected schedule's weekdays; a single-day work_schedule exception can explicitly override an off day. Calendar exceptions must not be used to approve one's own leave. They are attendance administration, not the completed leave entitlement/workflow subsystem.

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
- Cards aggregate all authorized matching employees, not just the page of 25. Excel currently caps requests at 500 employees; queued large exports belong to Phase 4.
- Immutable Eloquent models block normal and bulk writes/deletes. Raw SQL can bypass them. Use separate deployment DDL credentials and runtime database grants that prohibit UPDATE/DELETE on HR event/audit/decision ledgers.

## Secure native Android and network prerequisites

Phase 2 extends native-android, not the separate Capacitor shell. It must use a distinct HTTPS-only origin/client/session, Keystore authentication-per-use signing, BiometricPrompt CryptoObject, enrollment approval/revocation, short-lived signed challenges and atomic consumption/event append. Existing HTTP inquiry tokens/client cannot be used as secure HR credentials.

An API contract or disabled network adapter does not establish Android completion. Device attestation where feasible and instrumented biometric/integration checks remain necessary. BiometricPrompt verifies an enrolled device user, not a uniquely identified PHC employee. No biometric templates leave Android.

Obtain actual PHC RouterOS version, management TLS service/port, read-only account, office CIDRs/VLANs/AP topology, internal DNS/certificate, gateway identity, proxy trust and VPN/public ingress separation. No MikroTik settings are guessed or modified. DHCP/ARP/SSID are diagnostics only.

Production activation requires real Android TLS tests, internal network restrictions, direct public-route denial, forged gateway/proxy-header denial, VPN bypass tests, signature/expiry/replay/revocation/concurrency evidence, and explicit PHC acceptance.

## Validation checkpoint

Current attendance/workflow/installer/backup/permission run: **105 tests passed, 453 assertions**. Tests cover global/self/team authorization, immutable source preservation, overnight and DST calculations, flexible and multiple sessions, holidays/retirement history, independent correction decisions, stale sources, idempotency, queues, reconciliation, XLSX string safety and fail-closed capture. Tests use SQLite memory; this does not prove production MySQL concurrency.

Formatter: php vendor/bin/pint --dirty --format agent. Tests run with php artisan test --compact and explicit paths; this repository's test wrapper does not accept --no-interaction.

Real device/browser acceptance, staging restore drills, MySQL parallel-worker behavior, network activation and later HRMS phases remain outstanding. Existing unrelated Damage Assessment and Android changes are preserved.
