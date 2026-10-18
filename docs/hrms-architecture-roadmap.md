# PHC HRMS architecture and implementation roadmap

## Scope and execution order

The objective is the complete employee lifecycle HRMS. The user's attendance-first order supersedes the ordering in section 32 of the master prompt. Each phase must include functional UI, backend, authorization, tests, operational documentation and evidence before being marked complete. This document is a roadmap, not a completion claim.

## Repository assessment (12 October 2026)

- Laravel 12 modular monolith: `config/modules.php` registers providers through `App\Providers\ModuleServiceProvider`; the reusable module provider loads views, migrations, configuration and web/API routes.
- Existing modules: DamageAssessment, DamageAssessmentBorrowers and Heks. New HR code belongs under `app/Modules/HR`, with HR-prefixed tables. It uses the existing `App\` autoloader, authentication, `users`, Sanctum, Spatie Permission, Blade/Metronic layout and Excel infrastructure. No new framework or dependency is needed for Phase 1.
- `users.id` is unsigned bigint. The table already holds Arabic/English names, national ID, contract type, region and active status. Avoid duplicating sensitive identity fields. An explicit HR employee mapping is required; not every user is an employee. A nullable unique user reference supports staff without login. Do not match accounts by names.
- Legacy `attendances` has unique `(user_id,date)`, boolean status, notes and updated_by. It has no check-in/out times. `AttendanceSheetImport` upserts these rows and can create users and assign roles. Preserve this workflow; do not infer timestamps, import provenance or paid hours from boolean status. Import logs identify workbooks but do not link individual attendance rows to workbooks.
- Legacy attendance controllers have inline validation and broad authenticated access; their current behavior is not a suitable authorization template for confidential HR data. New routes use Form Requests and granular permissions. Legacy access tightening needs a separate compatibility review.
- Native app is `native-android` (the `android` directory is a separate Capacitor shell). Native app uses Kotlin, Jetpack Compose/Navigation, Hilt, Retrofit/OkHttp, coroutines and a Keystore-encrypted session store. `MainActivity.kt`, `NetworkModule.kt`, `InquiryApi.kt`, `InquiryRepository.kt`, `TransportPolicy.kt` are integration points. Existing HTTP allowance and request-method allowlist cannot be reused for secure HR attendance. Several Android files have pre-existing uncommitted changes and are outside this increment.
- Database queues, Laravel notifications and notification storage already exist. Use them for later alerts, large exports and scheduled reports; do not assume push delivery is configured.
- Pest 3 uses SQLite memory databases and RefreshDatabase, including module test directories. User switches away from its hardcoded mysql connection during tests. MySQL lock/concurrency behavior needs additional staging validation.
- Local MySQL schema was inspected with Laravel Boost. HR migrations are additive. Deployment is authorized by the owner, but no remote migration or network configuration has been executed by this agent.

## Attendance data design and invariants

1. `hr_employees`: employee number, nullable unique users.id, display name for staff without login, start/end dates and supervisor_user_id for attendance team scope. Mapping is deliberate and audited; no bulk automatic enrollment and no duplicate user model.
2. `hr_work_schedules`: immutable schedule versions containing timezone, ISO working weekdays, start/end local times, grace and unpaid break minutes. An end time earlier than start means an overnight shift; equal times are invalid. New versions preserve historical calculations.
3. `hr_shift_assignments`: employee/schedule/effective date range. Serialize writes on the employee row and reject overlapping time windows, including adjacent overnight and flexible windows. Multiple daily sessions have separate assignments. `hr_assignment_retirements` shortens future coverage without editing the original assignment. Each assignment is bounded to 366 days.
4. `hr_attendance_events`: immutable raw action/time/source/idempotency key and resolved assignment/work date. Store UTC instants, calculate in schedule timezone, use server time for ordinary attendance. Lock employee before sequencing, reject duplicate actions and checkout without check-in. No public capture endpoint until security phases pass.
5. `hr_audit_logs`: append-only actor/action/subject/time and minimal metadata. No tokens, biometric data, bank details or secrets.
6. Initial read model combines mapped employee, assignment, timestamp events and legacy boolean attendance without writing to `attendances`. Conflicting sources are flagged, legacy-only days have unknown hours, absence is provisional and only evaluated after shift end. Unmapped users remain visible in the existing attendance module.
7. Implemented additive workflow tables: `hr_holidays`, `hr_holiday_cancellations`, `hr_schedule_exceptions`, `hr_attendance_adjustments`, `hr_adjustment_decisions`, `hr_attendance_daily_summaries`, and `hr_attendance_exceptions`. Approved adjustments overlay raw events; never edit raw facts. Persist summary rule versions and source fingerprints. Lock and rebuild affected employee/dates. No payroll assumptions.

## Security boundary

The owner explicitly requested HTTP access for HR web pages, and subsequently for native attendance. `HR_WEB_REQUIRE_HTTPS` and `HR_NATIVE_REQUIRE_HTTPS` default to false. Web authentication, permissions and CSRF remain enforced. The existing `Database Officer` role has nine attendance abilities: view, view-own, setup, export, correct, approve, exceptions.manage, reconcile and audit. It does not gain payroll or device administration permissions. Correction policies still prohibit approving a request submitted by the reviewer or concerning the reviewer's own attendance. Other users require explicit permissions; view-team uses current primary managers and dated department scopes, with legacy supervisor fallback before organization history begins. Signed employee capture now defaults enabled in `unrestricted` network mode; device approval, capture permission, valid shift and signed challenges remain required. This mode makes no claim about physical office presence. All other network modes remain blocked pending a verified implementation. Client IP, forwarded headers, SSID, MAC, DHCP or ARP are never proof of physical presence. HTTP exposes credentials and tokens in transit; signatures do not provide encryption. HTTPS certificate checks are unchanged.

Future gateway: internal trusted TLS endpoint restricted by firewall to approved office network segments; authenticated gateway-to-backend path (mTLS preferred) and direct public path blocked. Bind short-lived single-use signed challenges to employee, credential, action, nonce and expiry. Consume challenge and append attendance atomically. Device revocation must invalidate pending challenges. Verify actual reverse-proxy trust and VPN/NAT behavior. Never use a shared client-supplied gateway header as authorization.

BiometricPrompt verifies an enrolled device user, not uniquely the PHC employee. Store public keys only; private keys remain in Android Keystore. Do not store biometric templates or promise universal hardware-backed protection. Unsupported hardware requires a separately approved, audited correction workflow, not password-only capture.

## Mandatory phases and database roadmap

| Phase | Deliverable and tables | Exit evidence |
| --- | --- | --- |
| 1 Attendance core | Mapping, schedules/shifts, events, holidays, exceptions, adjustments/decisions, summaries, audits, attendance permissions; daily/monthly UI and Excel; gateway abstractions | Core sequencing, timezone/overnight, absence, conflict, adjustment, permission and legacy regression tests; tested operational setup |
| 2 Secure native Android | hr_employee_devices, hr_device_credentials, hr_device_registration_requests, hr_attendance_challenges; native enrollment, approval/revocation, BiometricPrompt CryptoObject and signed requests | Valid/invalid signature, wrong actor/action, expiration/replay, revoked/unapproved credentials, per-use key and instrumented Android tests |
| 3 MikroTik/network | hr_network_verification_logs; authenticated HTTPS gateway, NetworkEligibilityService, AttendanceGatewayService, MikroTikNetworkService diagnostics | Actual topology approved; TLS, direct-route bypass, forwarded-header spoof, VPN and unapproved segment tests; no router writes without approval |
| 4 Attendance completion | Monitoring, self history, correction workflows, import reconciliation snapshots, queued notifications, advanced/security reports | HR acceptance, import conflict handling, Android integration, real MySQL concurrency and load tests |
| 5 Employee management | hr_departments, organizational units, hr_job_titles, hr_office_locations, hr_cost_centers, hr_employee_assignments, hr_employment_histories, hr_employee_documents; expand employee profile | Effective-dated history, private downloads, encryption, manager cycle prevention, scoped access, onboarding and organization chart |
| 6 Contracts | hr_contract_types, hr_contracts, hr_contract_amendments, hr_contract_approvals; private documents and renewal notification policies | Configurable types, immutable amendment/approval history, authorized lifecycle and expiry reports |
| 7 Leave and requests | hr_leave_types, hr_leave_policies, hr_leave_balances, hr_leave_transactions, hr_leave_requests, hr_employee_requests, hr_request_approvals, workflow definitions, hr_overtime_requests | HR-approved entitlements, overlap/accrual/carry-over tests, no self approval, leave/short permission/early departure/overtime flows |
| 8 Payroll | hr_salary_grades, hr_employee_compensation, hr_allowance_types, hr_deduction_types, payroll policy versions, hr_payroll_periods, hr_payroll_runs, hr_payroll_items, hr_payslips | Finance-approved policy, decimal monetary calculations, frozen approved runs, separate payroll grants, no automatic bank payments |
| 9 Advanced HR | hr_evaluation_templates, hr_evaluation_periods, hr_employee_evaluations, hr_performance_goals, training courses/enrollments/skills, assets/assignments, disciplinary cases/decisions/appeals, offboarding requests/clearances | Confidential workflows, historical evidence, asset return and access/device revocation, archive rather than delete |
| 10 Complete HRMS | Employee self-service, analytics, report schedules, HR settings and integrations; reuse Laravel notifications | All lifecycle capabilities accepted, restore/retention drills, authorized analytics, queued exports and notifications, bilingual accessibility |

Foreign keys restrict destructive deletion of HR history. Add targeted employee/date, status/date and pending-approval indexes. Soft-delete appropriate catalog entities later. Encryption keys, retention windows, labor/tax/leave rules and payroll deductions require PHC policy decisions; do not hardcode legal assumptions.

## File-by-file outline for the first increment

- `config/modules.php`: register HR beside current modules.
- `app/Modules/HR/Providers/HRServiceProvider.php`: module resources, policies and integration bindings.
- `app/Modules/HR/config/{module,sidebar,hr}.php`: navigation, optional HTTPS enforcement and attendance/network settings.
- `app/Modules/HR/database/migrations/*`: additive employee, schedule, assignment, event and audit tables.
- `app/Modules/HR/Models/*`: typed relationships and immutable ledger models.
- `app/Modules/HR/Services/*`: audited mapping/assignment, capture sequencing, calculations, read-only legacy reconciliation, deny-by-default network boundary.
- `app/Modules/HR/Policies/EmployeePolicy.php`: global, own and explicit attendance supervisor scope. Full effective-dated organizational scope follows in employee management; do not grant global visibility as a substitute.
- `app/Modules/HR/Http/{Controllers,Requests}/*`: authorized dashboard, history filters and setup forms.
- `app/Modules/HR/Exports/AttendanceExport.php`: bounded authorized XLSX export with literal string cells.
- `app/Modules/HR/resources/views/*`: Arabic attendance-first dashboard and setup; reuse platform layout.
- `database/seeders/HrPermissionsSeeder.php`: additive permissions only, no automatic role grants.
- `database/factories/Hr*Factory.php`, `tests/Feature/HR/*`: factories and regression/security/business-rule tests.
- `docs/hrms-deployment.md`: operator procedure, blockers and verification evidence.

## Attendance implementation checkpoint

Implemented: fixed/flexible and multiple daily shifts, holiday creation/cancellation history, temporary schedules, leave/excused/off-day calendar exceptions, scoped correction submission and independent approval/rejection, source-change detection, persistent reconciliation summaries and exceptions, queued reconciliation and database decision notifications, audit screens and organization-wide dashboard counts. Legacy imports remain unchanged; unknown imported hours remain null. Private pay/contract data is outside this attendance foundation.

Phase 2 now has real device proof enrollment, independent approval/rejection/revocation, one approved device per employee, scoped native tokens, signed attendance challenges, atomic consumption/event append and bounded retry receipts. The native Compose attendance screen uses a separate configured HTTP(S) client/session and Android Keystore authentication-per-use P-256 keys with BiometricPrompt CryptoObject. It includes today/history/device tabs, monthly navigation, registration status, lost-device revocation and confirmed server receipts. Device certificate chains are retained as unverified evidence; trusted-root/attestation-extension/revocation validation is not claimed.

The subsequent owner instruction removes the internal-gateway prerequisite for the default unrestricted mode. Phase 3 office-network verification remains pending actual PHC topology, and hardware biometric/live-server acceptance still needs validation. Enabling unrestricted capture does not complete those items or establish physical office presence.

Phase 4 operational features now include scoped monthly metrics, queued large XLSX exports split into 100-employee workbooks in a private ZIP, source/hash/timestamp manifests, expiring downloads with current permission checks, sampled native API denial reports, queued exception alerts and automatic pruning of expired derived files. Export scope/identity/expiry and alert delivery history are immutable through Eloquent. Reconciliation queues notifications to the current employee and supervisor only when authorized, deduplicated by source fingerprint; resolved/stale scopes are skipped. Notification payloads reuse the platform header's title/message/action_url contract. Audit subjects now support optional UUID keys without changing existing numeric history.

Real MySQL concurrency/load checks, trusted Android attestation validation and gateway/device acceptance still remain. Reconciliation currently runs through the UI queue or administrative command; automated reconciliation timing awaits operational configuration. All employee, contract, leave/workflow, payroll, advanced HR and complete-system phases remain in scope.

The scoped installer backs up the actual HR connection, runs only HR migrations and seeds only HR permissions. It refuses skipping backup outside local/testing. This avoids the repository's generic migration route, which may migrate unrelated modules.

## Employee and organization implementation checkpoint

Phase 5 adds work profiles, departments with parent relationships, configurable job titles, office locations/timezones, cost centers, effective-dated primary/project assignments, immutable assignment endings, dated department scopes, encrypted employment notes and private versioned documents. An expandable organization chart and scoped employee search/history use the existing Arabic Blade/Metronic interface. No real PHC departments or positions are invented by seeders.

Current primary assignments supersede the minimum legacy supervisor link in both employee and attendance scope. Department grants optionally include descendants and expire by date; team viewing still requires the matching permission. Project assignments do not grant authority. Manager cycles are checked at every relevant future assignment boundary; changing one's own organizational authority requires another authorized administrator. Catalog records archive instead of deleting history, and assignment snapshots retain the names effective when recorded.

Database Officer additionally receives `hr.employees.view`, `hr.employees.manage` and `hr.organization.manage`, making twelve explicitly allowed basic attendance/organization capabilities. These do not grant private employee fields, documents, device approvals, contracts or payroll. Ten employee/document permissions expand the additive seeder to 26 total HR permissions. Birth date, emergency contacts, private employment notes and document metadata/content are encrypted; legacy identity fields remain in the existing users table under separate viewing authorization. Documents use private local storage, MIME/extension/size validation, SHA-256 integrity checking, immutable versions and withdrawals, with authorized audited downloads.

An approved attendance correction can administratively resolve a raw open session while preserving the original event. Known corrected checkout times resolve the session; unknown times require an explicit reviewed closure request and remain unknown in reports. Closure is an immutable separate fact, not a fabricated checkout. Native today responses now separately expose raw open-session state and server-eligible actions, including yesterday's overnight checkout. Challenges bind the resolved assignment and work date; submission rechecks eligibility. An ended employee may read their own history and finish a valid previous-work-date session within 24 hours, but cannot begin attendance outside employment.

Remaining Phase 5 acceptance includes PHC organizational data, bilingual UI coverage, multiple employment-period/rehire handling and staging operations. Contracts, leave workflows, payroll and advanced HR remain in the full roadmap and are not represented as implemented placeholders.

### Incremental project attendance foundation — 18 October 2026

Project-based workers use the same employee identity and existing user account. A lightweight immutable `hr_attendance_projects` catalog records the actual code/name and date boundaries; nullable `hr_shift_assignments.project_id` associates each finite attendance assignment with its project. This deliberately reuses the existing day-of-week, fixed/flexible shift, overlap, retirement, capture and absence calculations. Administrative organizational assignments and department access scopes are unchanged. Project membership does not grant team visibility or administrative authority.

Attendance setup now supports optional generated internal numbers, email-based account mapping and explicit audited activation of the three employee self-service attendance/device-registration grants. An Arabic project preparation page joins project creation, employee mapping and schedule assignment. Reports preserve a single employee/day calculation and display project labels; Excel adds a project column. Project cost allocation, payroll, contract classification and real PHC project/worker provisioning remain separate work, and no phase is marked complete by this incremental feature.

