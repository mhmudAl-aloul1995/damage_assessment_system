# HR attendance increment: operation and deployment

## Current deliverable

The first attendance increment adds an HR module with explicit employee/user mapping (including staff without login), immutable schedule versions, finite effective-dated shift assignments, append-only event/audit models, daily/monthly read models, an Arabic Blade interface and permission-scoped XLSX exports. Legacy attendance/import rows are read without mutation. The timestamp recorder is a local/test simulator; no capture API, biometric enrollment, correction approval workflow or production gateway is implemented in this increment. Phase 1 remains open. The complete scope is in `hrms-architecture-roadmap.md`.

### Configuration

Set these through deployment secrets/environment and rebuild the normal configuration cache as part of an approved deployment:

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

There are no guessed addresses, ports, CIDRs or router credentials. Device approval is mandatory in configuration; it cannot be disabled by an environment switch. Production capture stays blocked even if `HR_ATTENDANCE_ENABLED=true`. The network services explicitly report unconfigured state and perform no router I/O.

Following the owner's explicit request on 12 October 2026, HR web pages allow HTTP by default (`HR_WEB_REQUIRE_HTTPS=false`). Authentication, authorization and CSRF controls still apply. HTTP does not encrypt sessions or HR data. Set `HR_WEB_REQUIRE_HTTPS=true` to restore HTTPS enforcement outside local/testing once TLS is available. Do not change APP_ENV to bypass transport checks or trust caller-supplied proxy headers. This setting applies only to web pages; it does not enable native biometric attendance, gateway verification or disable certificate verification. After pulling this change, clear or rebuild the deployed configuration cache if it is used.

### Approved deployment sequence (not executed by this change)

1. Back up the database and verify restoration. Review pending migrations with an authorized operator; this repository contains migrations for other modules too.
2. On an isolated staging database, run only the new migration path first and inspect keys/indexes. After approval, the additive HR migration may be applied with `php artisan migrate --path=app/Modules/HR/database/migrations --no-interaction`. No legacy backfill is automatic. Do not use migrate:fresh, reset or rollback against production.
3. Run `php artisan db:seed --class=HrPermissionsSeeder --no-interaction` after approval. The seeder adds four permissions and never assigns roles, removes grants or changes existing users.
4. As explicitly requested by the owner, the existing `Database Officer` role now grants viewing, setup and Excel export for the current attendance module directly, including before the HR permission records are populated. No individual permission assignment or grant command is needed for this role. Other users continue to need `hr.attendance.view` for authorized HR-wide viewing, `hr.attendance.view-own` for self-history, `hr.attendance.setup` for setup and `hr.attendance.export` for exports. Department managers must not receive global viewing while organizational scopes are pending. The role allowance is limited to these four attendance abilities and does not grant future payroll/device approval access.
5. Use the HR attendance navigation. Setup requires exact account IDs from existing user administration; blank means staff without login. Verify account ID/employee identity before saving. Mapping changes need a future audited remapping workflow; do not update identifiers directly.
6. Create schedule versions with explicit working days/timezone and finite assignments. Schedules and assignments cannot be edited/deleted through the application. Changes must use a new version/non-overlapping future period. Open-ended assignment retirement and historical correction workflows remain outstanding.
7. Run staging regression checks and PHC acceptance. Keep capture disabled. No production deployment, migration or router modification was performed during implementation.

### Fixing attendance authorization on the deployed server

After pulling the latest code, accounts with the `Database Officer` role can access the current attendance module directly. Apply the HR migration on the server if its tables are missing; role authorization does not create tables. Refresh the deployed configuration cache if used so the updated sidebar configuration is loaded.

Local permissions are not transferred by Git. For accounts without the `Database Officer` role, the optional command `php artisan hr:grant-attendance-view mhmudaloul@gmail.com --no-interaction` creates the viewing permission if needed, grants only attendance viewing to the named active account, preserves all existing roles/grants, records an audit entry and clears the Spatie permission cache. Re-running it is safe. It refuses to grant access when the HR tables are missing and never runs migrations automatically.

If SSH/terminal access is unavailable, a Database Officer can open **الإدارة المركزية → أوامر Artisan**, find `hr:grant-attendance-view`, enter a non-officer target account's email in the `email` argument, and run it. The existing admin authorization and CSRF protections apply. After a successful run, refresh the attendance page. For non-officer accounts, setup and export continue to require their separate permissions.

### Calculation rules in this increment

- UTC event instants; organization/schedule timezone for presentation and work date. Overnight checkout belongs to the check-in work date, even if the assignment expires at midnight.
- One check-in/check-out pair per employee/work date. Local simulation admits check-in from two hours before start until shift end. This fixed simulation window is not an approved PHC production policy.
- Working minutes are elapsed minutes less the configured unpaid break, floored at zero. Breaks are a simple per-shift deduction; timed breaks and partial-shift break rules are pending HR policy.
- Late minutes exclude configured arrival grace. Early departure is measured against scheduled end. Excess minutes are informational, not approved/paid overtime.
- Missing checkout appears only after shift end. Absence is only a candidate after shift end, and is not payroll-ready until leave/holiday/exception reconciliation exists.
- A legacy present/absent day has unknown hours. The UI/export uses null (shown as —), never fabricated timestamps or eight-hour defaults. A legacy absence plus a raw check-in is a conflict. Legacy provenance is labeled `legacy`, not asserted to be an Excel import where row provenance is unavailable.
- Dashboard cards count the current employee page. Global aggregate analytics and materialized summaries are pending. Month exports are limited to 500 employees; larger requests must be narrowed until queued exports are implemented.
- The append-only Eloquent models reject instance and bulk update/delete/upsert. Raw SQL/query-builder access can bypass application protection; production database grants must prohibit UPDATE/DELETE on raw event/audit tables for the runtime account and reserve DDL for a separate deployment account. Backups and audit retention remain mandatory.

## Native Android integration contract to implement in Phase 2

Use the existing Compose navigation/Hilt repository pattern in `native-android`. Add a separately qualified HTTPS-only Retrofit/OkHttp client for HR. Never attach HR credentials/tokens or enrollment payloads to the legacy HTTP origin. Reuse Keystore session patterns only after isolating the secure origin and token audience. Do not widen the existing inquiry interceptor's HTTP write allowlist for attendance.

Planned endpoints under the established `/api/v1/hr` version convention: `me`, `attendance/today`, `attendance/history`, `devices/register`, `devices/status`, `attendance/challenge`, `attendance/check-in`, `attendance/check-out`; separate permissioned administrative device approval/revocation. These endpoints do not exist yet.

Challenge representation must be a documented canonical byte string, returned as base64 with protocol version, challenge ID, employee ID, approved credential ID, action, random nonce and UTC expiry. Android signs the exact returned bytes using an authentication-per-use Keystore key and BiometricPrompt CryptoObject. Server verifies the stored challenge bytes and credential, never reconstructed client claims. Choose and document the signature encoding (e.g. ECDSA P-256 SHA-256 DER) when implementation and interoperability tests are added.

Server transaction must lock credential/challenge/employee in consistent order, reject revoked/unapproved keys, consume one unexpired challenge once, enforce authenticated gateway evidence and append event with server time. Device status and account activity must be rechecked at submission, not only challenge creation. Return success only after commit. Retries need a bounded idempotent receipt tied to the same verified request; reusing a nonce for a different request must fail. Enrollment, rejection, approval, replacement, revocation and recovery need an immutable actor/timestamp history and no self approval.

## Network information and activation checklist

Obtain from PHC network administrators:

- Actual RouterOS version and supported authenticated TLS API; management address/port and least-privilege read-only service account.
- Private server address, office Wi-Fi CIDRs, VLANs, DHCP/NAT/firewall and access point ownership/topology.
- Internal DNS, trusted TLS certificate and renewal method, gateway service identity and reverse-proxy trust list.
- VPN/remote-access routes and whether they can reach the attendance segment; public versus internal ingress separation.

Before activation, verify TLS and hostname validation on real Android devices, internal segment restrictions, direct public-route denial, forged proxy/gateway header denial, VPN/NAT bypass resistance, replay/concurrency, device revocation, wrong employee/action/signature, expired challenges and revoked sessions. DHCP/ARP/SSID are supporting diagnostics only. Never modify MikroTik settings automatically. Production rollout requires explicit approval after documented evidence.

## Validation

Run `php artisan test --compact tests/Feature/HR` against the configured SQLite in-memory test database. Tests exercise UI authorization, self scope, setup validation, legacy import compatibility, calculation/overnight/DST rules, sequencing/idempotency, immutability, XLSX string safety and fail-closed capture/HTTP access. Use the existing attendance, authentication, module and mobile/ArcGIS regression tests as listed in the implementation report. `--no-interaction` is not accepted by this repository's test wrapper; tests run without prompts.

SQLite checks do not prove MySQL row-lock behavior under parallel workers. Real MySQL concurrency, visual browser/device acceptance, restore drills and secure gateway/Android integration remain required before production use.

### Recorded verification — 12 October 2026

- All 32 new HR tests passed, including a real XLSX download.
- Combined targeted run: 119 passed, 2 failed, 549 assertions. Included HR, legacy AttendanceSummary, Authentication, ModuleStructure, ModuleServiceProvider, ModuleSwitcher, MobileApi and DamageInquiryApi tests.
- The two ModuleSwitcher failures concern an existing Borrowers officer also seeing the Damage Assessment building-deletions menu. Both failures were reproduced with the original HEAD versions of `config/modules.php` and `config/sidebar.php`, excluding HR registration/navigation; baseline result: 11 passed, the same 2 failed. HR configurations were restored afterwards. No unrelated navigation change was made.
- `php vendor/bin/pint --dirty --format agent` completed successfully; `git diff --check` found no whitespace errors.
- No live database migrations, permission grants, deployments or Android file edits were performed. Native Android and CODEX progress file changes present before this work were preserved.
