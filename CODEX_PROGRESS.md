# PHC Native Android — current progress
Updated: 2026-10-10. Owner confirmed completion of Priority 1 after delivery of the native UX/UI redesign.

## Current delivery
- APK: D:/myProjects/phc/tmp/mobile-delivery/PHC-Native-0.2.0-debug.apk
- Canonical APK: tmp/mobile-delivery/PHC-Native-Priority1-debug.apk
- Source archive: tmp/mobile-delivery/PHC-Native-Priority1-source.zip
- Version: 0.2.0-test / code 4, package com.phc.inquiry.test; minimum Android 8/API26, compile/target35.
- APK size: 12,451,448 bytes; SHA256 4F2C21C2EC40E1697CC6AA0F584C36D3952B0EC3C2F3A30D1EA7CB8B164B9829.
- Native Kotlin/Jetpack Compose/Material3; existing hybrid android/ios/mobile-shell work preserved separately.
- Priority 1 accepted as complete by owner on 2026-10-10. Agent has not independently executed the device UI tests or verified live authenticated login/search; this confirmation does not create new test evidence.

## Completed native features
- Arabic RTL login, field validation, keyboard Next/Done, compatible Compose autofill hooks, loading/error states and compact visible HTTP notice.
- Adaptive login for phones/tablets, larger official logo, embedded Noto Sans Arabic with bundled SIL OFL license, green/light palette and explicit dark palette.
- Reusable brand mark, icon tile, status badge, loading/error/empty states; Arabic UI strings in Android resources.
- Dashboard: user welcome, server-authorized building/housing cards, account/logout action.
- Explicit building/housing search, clear query, refresh, server pagination/total, readable record cards, draft/list state retained through navigation state, new search scrolls to first result.
- Basic details grouped by information/location/status; missing values labeled, parent reference when available, copy record number.
- Seven Compose previews in core/ui/DesignPreviews.kt: phone/light/dark/tablet/large text login, dashboard, results, details (annotations share some cases; see source).
- Retrofit/OkHttp/serialization, repository/use case/ViewModels/StateFlow, Navigation/Paging3 retained.
- Keystore AES-GCM token storage, no persistent passwords or network logs, expiry/401, logout cleanup; backups/transfer excluded and production activity FLAG_SECURE retained.
- API-origin/path guard, read-only requests except login/logout, redirects disabled, TLS verification unchanged. Debug HTTP limited to existing server, release prohibits cleartext.
- Owner accepted existing real-account HTTP use; earlier test-email gate removed. Credentials are entered on device only.
- 0.1.2 fixed missing device_name: required non-default LoginRequest field with explicit repository value. Regression test retained.

## Modified/new files for redesign
- app/build.gradle (version only; no dependency changes).
- MainActivity.kt; core/ui/PhcTheme.kt, PhcComponents.kt, DesignPreviews.kt.
- feature/authentication/LoginScreen.kt; feature/dashboard/DashboardScreen.kt; feature/damageassessment/InquiryScreens.kt.
- res/values/strings.xml, res/font/noto_sans_arabic.ttf, assets/NotoSansArabic-OFL.txt.
- app/src/androidTest/.../NativeScreenTest.kt; these two progress documents.
- No Laravel/API/schema changes made in redesign.

## Verification
- Latest log: tmp/mobile-toolchain/native-design-acceptance.log, BUILD SUCCESSFUL in 3m37s, 90 tasks (22 executed/68 up-to-date). No running builds remain.
- 11 native unit tests passed: 8 repository/contract/paging plus 3 transport; 0 failures/errors.
- assembleDebugAndroidTest succeeded with 9 native UI tests compiled. NOT executed on device yet.
- UI tests cover valid login/invalid email/disabled transport, authorized card callbacks, explicit search/internal record ID, saved search draft, detail copy, and five fixture screenshot exports.
- lint: 0 errors/26 warnings. Known Navigation custom lint registries skipped due lint API mismatch; remaining warnings include original launcher resources, IO-thread SharedPreferences commit and unused strings. Do not claim full custom-lint coverage.
- APK v2 signature verified; certificate SHA256 matches previous 0.1.2: 67d381b9e432578f0a732e7e902dc8441d105451c61fee6774749cbafbfd045c. Version/package/minSdk verified with aapt. Installation-over not yet tested.
- Contrast ratios: light button 7.91:1, primary text 14.89:1, secondary text 5.35:1, HTTP notice 6.58:1; dark counterparts 7.97/13.40/9.42:1. Runtime font scaling/TalkBack/autofill still require device review.
- First redesign build needed experimental autofill opt-in; corrected, later builds passed.

## Device visual verification blocker
BlueStacks Pie64 listens at 127.0.0.1:5555 and ADB enumerates API28 device, but shell/install services return error: closed. Configuration showed bst.enable_adb_access="0"; last observation still disabled. Owner replied they will enable Android Debug Bridge. Agent did not change security settings. No real device screenshots were generated; fixture previews are available as Compose source.

## API contract / backend status
- POST api/v1/auth/login: email,password,device_name -> access_token,expires_at,user.
- GET api/v1/me; GET api/v1/modules; POST api/v1/auth/logout.
- GET api/v1/damage-assessment/sectors.
- GET api/v1/damage-assessment/{buildings|housing-units}?search=...&page=...
- GET api/v1/damage-assessment/{sector}/{record_id}; internal record_id, not objectid.
- Lists: data,total,current_page,last_page; details: data.
- Existing Sanctum/active-account/mobile:read token/rate limit/sector permissions/phase restrictions retained.
- Existing API files: routes/api.php; Api/V1/DamageInquiryController.php; Requests/Api/DamageInquiryRequest.php; Modules/DamageAssessment/.../SectorOverviewService.php. Reuses audited buildings and audited housing/base housing models.
- Owner deployment made anonymous live buildings/housing routes return 401 rather than earlier 404. Local /push stages/commits/pushes then redirects to configured /pull; it does not run migrations. Agent did not run authenticated deploy.
- Latest actual owner login screenshot after 0.1.2 showed generic server failure. Exact HTTP status/production exception not available. Empty live JSON login test returned proper 422 validation errors. Missing personal_access_tokens table is only a hypothesis. Need production exception/authorized terminal access; never run all pending migrations blindly.
- Earlier Laravel targeted tests: 69 passed/547 assertions. No PHP changes in native redesign.

## Next steps
1. Owner enables BlueStacks ADB; confirm shell/install works. Run connectedDebugAndroidTest with existing portable toolchain.
2. Extract five fixture PNGs and review actual phone/large/dark layouts; add/fix any necessary tests or layout changes based on evidence. No account needed for fixtures.
3. Install 0.2.0 over existing app and verify native navigation/back/keyboard/font scale and clipboard.
4. Separately obtain production login exception and resolve verified cause; test authorized search/details on device without sharing credentials in chat.
5. Refresh APK/source/progress after any fixes. Do not start Priority2/3 (GIS, audits, attachments, citizen, stats, repair).

## Build
From repository root:
    powershell -NoProfile -ExecutionPolicy Bypass -File native-android/build-native.ps1 -Verify -CompileDeviceTests
Portable JDK21/Gradle8.11.1/SDK35 in tmp/mobile-toolchain. TestEmail argument no longer exists. BaseUrl argument can configure a supplied encrypted endpoint; no server SSL changes performed.

## Owner acceptance — 2026-10-10
Owner message: "تم إكتمال المرحلة الأولى". Record Priority 1 as accepted by the owner. No additional APK, code, server changes or tests were performed for this acceptance update. Prior device-test and production-log observations are historical investigation context; do not infer whether they were resolved or invent results. Priority 2 has not started and requires a new instruction to implement it.
Next planned scope: advanced API-supported search filters, fuller building/housing details, authorized engineering/legal audit history, unified citizen inquiry and authorized attachment viewer. GIS/statistics remain Priority 3. Inspect available contracts and permissions incrementally when Priority 2 is requested.

## Priority 2 — active implementation (owner authorized)
- Local Laravel Boost tools used via php artisan boost:mcp stdio: search-docs for Laravel12/Pest3 validation/auth/file testing, list-artisan-commands, database-schema (metadata only, audited tables). No configured remote Boost tool was exposed. pest-testing skill not found in installed skill locations; version-specific Boost test docs used.
- Existing route/service permission logic and audited models inspected; no DB migrations/dependency changes.
- Implemented locally: sector-scoped filters endpoint, full detail fields from approved Assessment labels/known fields with technical fields excluded and audit visibility required, paginated engineering/legal history with source fallback, combined citizen inquiry restricted to authorized audited sectors/phases, attachment list/content proxy (PNG/JPEG/PDF <=15MB, no provider token/URL exposed).
- Files: SectorOverviewService (citizen query + scoped inquiryModel), DamageInquiryController, DamageInquiryRequest, MobileCitizenInquiryRequest, services/MobileDamageDetailService, routes/api.php, MobileDamageAdvancedApiTest.
- Previous API tests passed 9/59 assertions. New suite initial failures were test fixture required status metadata and guard caching when switching token in one test; corrections applied. Latest suite rerun still needed after stage/order fix.
- Native DTO/API/repository methods and bounded attachment reader added; search ViewModel/PagingSource being extended for filters/citizens. Native UI/history/viewer navigation and FakeApi regression implementations still pending. No Phase2 APK built yet.

## Priority 2 verification checkpoint
- Full backend suite passed 23 tests / 156 assertions (phase2-backend-final.log), including baseline routes, phase filtering/housing/citizen name and exact identity checks, audit authorization, bounded history, attachment ownership/MIME/size and verified TLS options plus existing Arcgis attachment behavior. Pint dirty completed.
- Mobile provider requests explicitly verify TLS using optional existing ArcgisService parameters and separate verified token cache; legacy service defaults preserved. Unknown attachment size is refused before byte download.
- PDF rendering now uses a non-exported isolated Android service receiving a temporary read-only descriptor; no token/account data is sent to it. Temporary private PDF removed after rendering; PNG/JPEG decoded with dimension sampling, attachment reader bounded at 15MB including unknown-length streams.
- Native build assembled APK and 15 unit tests passed. Latest native-final failed only compiling new Android test because PdfDocument is not Closeable; test changed to try/finally close. Rebuild required. UI suite now includes Phase2 callbacks/filter application and isolated PDF rendering tests; device tests remain unexecuted (BlueStacks ADB still disabled).
- Remaining immediate work: finish small UI usability changes/full empty-field toggle; build verify/compile device tests, package 0.3.0 code5 APK and updated source, record hashes/signature/results. Backend changes remain local and must be deployed with owner's existing authenticated /push->/pull workflow before Phase2 endpoints work live. No server migration/deploy occurred.
