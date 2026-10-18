# PHC HR attendance architecture and roadmap

## Current scope — 18 October 2026

The owner explicitly narrowed the HR module to attendance and check-out, including only their required supporting capabilities. This supersedes the original full HRMS scope. Employee administration, contracts, general leave/request workflows, payroll, performance, training, assets, discipline and offboarding are outside the current scope. No later HRMS phases should be started automatically.

The existing HR module key, Laravel authentication, users, Spatie permissions, Sanctum, Blade/Metronic and native PHC Android entry remain. Damage Assessment, ArcGIS and other workspaces are unaffected.

## Active capabilities

- Minimal employee attendance identity: existing account mapping, generated internal or official number, display name, actual start/end dates and attendance supervisor.
- Project workers: dated projects and finite schedule assignments, one identity across multiple projects and absence only on assigned workdays.
- Immutable fixed/flexible schedules, multiple daily shifts, overnight and DST handling, effective periods and audited retirement.
- Check-in/check-out events, late/early minutes, working hours, missing sessions and absence candidates.
- Attendance holidays/calendar exceptions, corrections with independent decisions, reconciliation and append-only audit history.
- Daily/monthly dashboards, employee history, scoped immediate/queued Excel reports and security/exception reports.
- Native Android attendance in the existing PHC app: approved device enrollment, Android Keystore, BiometricPrompt CryptoObject, signed challenges, replay protection, revocation and bounded receipts.
- Attendance/device permissions, scoped notifications and expiring private export downloads.

## Data and preservation

`hr_employees` reuses the existing users relationship. Attendance schedules, dated assignments, projects, raw UTC events, decisions, closures, daily summaries, exceptions, challenges, credentials, device decisions, exports, alerts and audit logs remain active. Legacy `attendances` and Excel imports remain unchanged. Boolean imported attendance does not establish check-in/out timestamps or hours; unknown hours stay null.

Historical migrations are retained unchanged. Existing employee profiles, contracts, financial terms, employment histories, documents and encrypted files are preserved, with no destructive migration or file removal. Their management routes, views, controllers, requests, services, models, catalogs and contract expiry scheduling have been removed. Retired permission records and role assignments are not deleted; they cannot restore removed routes.

A minimal organizational read layer remains for attendance authorization: current primary reporting lines, dated department scopes/descendants and immutable endings. These prevent accidentally expanding team visibility or reverting to superseded supervisors. They do not expose employee/organization administration. Project membership itself never grants administrative or team-view authority.

## Authorization and native transport

Database Officer retains nine explicit attendance abilities: view, view-own, setup, export, correct, approve, exceptions.manage, reconcile and audit. The seeder creates 16 attendance/device permissions only. Device administration and employee capture still require explicit grants. Explicit employee activation grants only view-own, capture and devices.register, while independent device approval remains separate.

Native tokens have only the hr:native audience. Capture requires a linked active employee account, capture permission, an approved device, valid assignment and a fresh signed challenge. Corrections and device decisions retain independent approval rules.

By the owner's explicit instruction, web and native HTTP(S) are supported with independent configuration flags. Native capture defaults enabled in unrestricted network mode. Signing does not encrypt HTTP traffic. The Android client enforces the configured origin, rejects redirects and never bypasses certificate checks. Network modes requiring office verification continue to deny capture until implemented and verified. No router settings, addresses or credentials are guessed.

## Remaining attendance work

1. Configure actual PHC projects, employee accounts, supervisors, workdays and schedules through attendance setup; do not invent organizational or project data.
2. Validate real Android biometric capture, recovery and revocation on hardware. Instrumented tests previously compiled but could not run because ADB transfers failed; compilation is not hardware acceptance.
3. Validate MySQL concurrent capture/approval/export workers and backup restoration in staging. SQLite regression tests do not establish MySQL concurrency behavior.
4. Complete internal-network/MikroTik eligibility only after actual network details and an approved policy are supplied. Unrestricted mode does not prove physical office presence.
5. Trusted attestation root/extension/revocation verification remains unimplemented; retained certificate chains are unverified evidence.

No phase is declared complete solely because its page exists. See hrms-deployment.md for installation, operation, calculation rules and validation limits.
