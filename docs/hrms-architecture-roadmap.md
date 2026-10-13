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

The owner explicitly requested HTTP access for HR web pages. `HR_WEB_REQUIRE_HTTPS` defaults to false; true restores HTTPS enforcement outside local/testing. Web authentication, permissions and CSRF remain enforced. The existing `Database Officer` role has nine attendance abilities: view, view-own, setup, export, correct, approve, exceptions.manage, reconcile and audit. It does not gain payroll or device administration permissions. Correction policies still prohibit approving a request submitted by the reviewer or concerning the reviewer's own attendance. Other users require explicit permissions; view-team is limited to mapped supervisor relationships. Employee capture defaults disabled. The gateway boundary denies until a real verified implementation replaces it. Client IP, forwarded headers, SSID, MAC, DHCP or ARP are never proof of physical presence. The web transport exception does not enable biometric attendance or bypass certificate verification.

Future gateway: internal trusted TLS endpoint restricted by firewall to approved office network segments; authenticated gateway-to-backend path (mTLS preferred) and direct public path blocked. Bind short-lived single-use signed challenges to employee, credential, action, nonce and expiry. Consume challenge and append attendance atomically. Device revocation must invalidate pending challenges. Verify actual reverse-proxy trust and VPN/NAT behavior. Never use a shared client-supplied gateway header as authorization.

BiometricPrompt verifies an enrolled device user, not uniquely the PHC employee. Store public keys only; private keys remain in Android Keystore. Do not store biometric templates or promise universal hardware-backed protection. Unsupported hardware requires a separately approved, audited correction workflow, not password-only capture.

## Mandatory phases and database roadmap

| Phase | Deliverable and tables | Exit evidence |
| --- | --- | --- |
| 1 Attendance core | Mapping, schedules/shifts, events, holidays, exceptions, adjustments/decisions, summaries, audits, attendance permissions; daily/monthly UI and Excel; gateway abstractions | Core sequencing, timezone/overnight, absence, conflict, adjustment, permission and legacy regression tests; tested operational setup |
| 2 Secure native Android | hr_employee_devices, hr_device_credentials, hr_device_registration_requests, hr_attendance_challenges; native enrollment, approval/revocation, BiometricPrompt CryptoObject and signed requests | Valid/invalid signature, wrong actor/action, expiration/replay, revoked/unapproved credentials, per-use key and instrumented Android tests |
| 3 MikroTik/network | hr_network_verification_logs; authenticated HTTPS gateway, NetworkEligibilityService, AttendanceGatewayService, MikroTikNetworkService diagnostics | Actual topology approved; TLS, direct-route bypass, forwarded-header spoof, VPN and unapproved segment tests; no router writes without approval |
| 4 Attendance completion | Monitoring, self history, correction workflows, import reconciliation snapshots, queued notifications, advanced/security reports | HR acceptance, import conflict handling, Android integration, real MySQL concurrency and load tests |
| 5 Employee management | hr_departments, organizational units, hr_positions, hr_office_locations, hr_cost_centers, hr_employee_assignments, hr_employment_histories, hr_employee_documents; expand employee profile | Effective-dated history, private downloads, encryption, manager cycle prevention, scoped access, onboarding and organization chart |
| 6 Contracts | hr_contract_types, hr_contracts, hr_contract_amendments, hr_contract_approvals; private documents and renewal notification policies | Configurable types, immutable amendment/approval history, authorized lifecycle and expiry reports |
| 7 Leave and requests | hr_leave_types, hr_leave_policies, hr_leave_balances, hr_leave_transactions, hr_leave_requests, hr_employee_requests, hr_request_approvals, workflow definitions, hr_overtime_requests | HR-approved entitlements, overlap/accrual/carry-over tests, no self approval, leave/short permission/early departure/overtime flows |
| 8 Payroll | hr_salary_grades, hr_employee_compensation, hr_allowance_types, hr_deduction_types, payroll policy versions, hr_payroll_periods, hr_payroll_runs, hr_payroll_items, hr_payslips | Finance-approved policy, decimal monetary calculations, frozen approved runs, separate payroll grants, no automatic bank payments |
| 9 Advanced HR | hr_evaluation_templates, hr_evaluation_periods, hr_employee_evaluations, hr_performance_goals, training courses/enrollments/skills, assets/assignments, disciplinary cases/decisions/appeals, offboarding requests/clearances | Confidential workflows, historical evidence, asset return and access/device revocation, archive rather than delete |
| 10 Complete HRMS | Employee self-service, analytics, report schedules, HR settings and integrations; reuse Laravel notifications | All lifecycle capabilities accepted, restore/retention drills, authorized analytics, queued exports and notifications, bilingual accessibility |

Foreign keys restrict destructive deletion of HR history. Add targeted employee/date, status/date and pending-approval indexes. Soft-delete appropriate catalog entities later. Encryption keys, retention windows, labor/tax/leave rules and payroll deductions require PHC policy decisions; do not hardcode legal assumptions.

## File-by-file outline for the first increment

- `config/modules.php`: register HR beside current modules.
- `app/Modules/HR/Providers/HRServiceProvider.php`: module resources, policies and integration bindings.
- `app/Modules/HR/config/{module,sidebar,hr}.php`: navigation and disabled secure-capture settings.
- `app/Modules/HR/database/migrations/*`: additive employee, schedule, assignment, event and audit tables.
- `app/Modules/HR/Models/*`: typed relationships and immutable ledger models.
- `app/Modules/HR/Services/*`: audited mapping/assignment, capture sequencing, calculations, read-only legacy reconciliation, deny-by-default network boundary.
- `app/Modules/HR/Policies/EmployeePolicy.php`: global and own attendance access initially; organizational scope requires effective-dated organization records later. Do not grant global viewing to department managers as a substitute.
- `app/Modules/HR/Http/{Controllers,Requests}/*`: authorized dashboard, history filters and setup forms.
- `app/Modules/HR/Exports/AttendanceExport.php`: bounded authorized XLSX export with literal string cells.
- `app/Modules/HR/resources/views/*`: Arabic attendance-first dashboard and setup; reuse platform layout.
- `database/seeders/HrPermissionsSeeder.php`: additive permissions only, no automatic role grants.
- `database/factories/Hr*Factory.php`, `tests/Feature/HR/*`: factories and regression/security/business-rule tests.
- `docs/hrms-deployment.md`: operator procedure, blockers and verification evidence.

## Attendance implementation checkpoint

Implemented: fixed/flexible and multiple daily shifts, holiday creation/cancellation history, temporary schedules, leave/excused/off-day calendar exceptions, scoped correction submission and independent approval/rejection, source-change detection, persistent reconciliation summaries and exceptions, queued reconciliation and database decision notifications, audit screens and organization-wide dashboard counts. Legacy imports remain unchanged; unknown imported hours remain null. Private pay/contract data is outside this attendance foundation.

Core sequencing currently runs through an explicitly enabled local/test simulator. Secure employee capture and real Android/gateway acceptance remain prerequisites; Phase 1 is not marked complete while production capture is unavailable. Phase 2 is next. Phase 3 awaits actual PHC topology and verified TLS. Phase 4 still includes advanced/security reports, queued large exports, scheduled attendance alerts, real MySQL concurrency/load checks and Android acceptance. All employee, contract, leave/workflow, payroll, advanced HR and complete-system phases remain in scope.

The scoped installer backs up the actual HR connection, runs only HR migrations and seeds only HR permissions. It refuses skipping backup outside local/testing. This avoids the repository's generic migration route, which may migrate unrelated modules.
