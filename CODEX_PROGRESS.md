# PHC Native Android — Priority 1 progress

Updated: 2026-10-10 (Asia/Hebron).

## Delivery status
- Separate, genuinely native Android project: native-android/. Kotlin + Jetpack Compose + Material Design 3. No WebView, Capacitor, Cordova or HTML screens in this project.
- Existing android/, ios/ and mobile-shell/ were preserved as received at the start of the native request. Earlier hybrid/API changes remain in the working tree; they are not the native deliverable.
- Priority 1 implementation and APK build completed. Anonymous live route availability is verified; authenticated end-to-end acceptance is pending a device/account test. Priority 2/3 were not started.
- Latest native APK: D:/myProjects/phc/tmp/mobile-delivery/PHC-Native-Priority1-debug.apk
- APK size: 11433120 bytes. SHA-256: 0B8F52C95BDA1818F2DA110EAD59044F6A4041AFA5598CB4B49205FE61549252.
- Android Studio source archive: tmp/mobile-delivery/PHC-Native-Priority1-source.zip (created at final packaging).
- App: PHC Inquiry. Package: com.phc.inquiry.test (debug); com.phc.inquiry (release).
- Version: 0.1.2-test, versionCode 3. Minimum Android 8/API 26, target/compile Android 15/API 35.

## Completed features
- [x] Inspected actual Laravel/Sanctum auth, sector permissions, source models and API response contract.
- [x] Independent native project and Gradle wrapper; Kotlin 2.0.21, Compose compiler, Material3, Hilt/KSP.
- [x] Arabic RTL native login with logo, password visibility, validation, loading and safe errors.
- [x] Native dashboard with user name and server-authorized building/housing sectors only.
- [x] Native building search by name/number and housing search by available name/number/parent building name.
- [x] Paging3/server pagination (20 records/page, bounded in-memory cache), results, empty state, retry and refresh.
- [x] Separate basic detail navigation for buildings and housing, using record_id rather than objectid.
- [x] Basic details: record/name/building, municipality/neighborhood, damage, field completion, audit status, parent reference when returned.
- [x] Retrofit/OkHttp + kotlinx serialization; repository/use case/ViewModels/StateFlow; Navigation Compose.
- [x] Keystore-backed AES-GCM session storage. No stored passwords, token logging, HTTP logging interceptor or local record database.
- [x] Expiry/401 handling, logout/revocation, local session removal on offline logout; cancellation preserved.
- [x] System light/dark mode, responsive scrolling, native logo/launcher resources.
- [x] Read-only network guard; bearer restricted to the configured API; redirects disabled; normal certificate validation retained.
- [x] Backups and device transfer excluded; screenshots/recents protected with FLAG_SECURE.
- [x] Native debug APK assembled and signature verified.

## Security / HTTP limitation
Current backend is public HTTP. No server, certificate, Nginx, database or production configuration was changed.

The owner explicitly requested login with their existing real account and accepted HTTP risk, then instructed us to continue. This supersedes the earlier test-account-only requirement. Version 0.1.1 removes the build-time email allowlist and test-data confirmation; login still displays an unencrypted-transport warning. No actual credentials were requested or transmitted during development. The owner enters credentials only on their device.

HTTP remains limited to the existing IP/port in debug builds. Release builds prohibit cleartext; API origin/path restrictions, read-only guard, server permissions, normal TLS validation and encrypted local token storage are retained. No server configuration was changed.
## Laravel API reused (already implemented locally before native phase)
- POST api/v1/auth/login: email/password/device_name -> access_token, expires_at, user.
- GET api/v1/me; GET api/v1/modules; POST api/v1/auth/logout.
- GET api/v1/damage-assessment/sectors: authorized sector catalog.
- GET api/v1/damage-assessment/{buildings|housing-units}?search=...&page=...
- GET api/v1/damage-assessment/{sector}/{record_id}: limited record details.
- API returns data, total, current_page, last_page for lists; details return data.
- Server enforcement: Sanctum, active account, mobile:read token, rate limit, SectorOverviewRequest authorization, explicit account phase restrictions.
- Sources: audited_buildings; audited_housing_units when available, otherwise housing_units, with parent building relation. Reuses SectorOverviewService. No duplicate database/models.
- Latest live inquiry checks (2026-10-10 after owner used deployment flow): buildings and housing-units both return HTTP 401 to anonymous requests, replacing earlier 404. Protected routes are now reachable. No authenticated server request was made.
- API search currently reuses existing LIKE search. Large-dataset index/performance work is deferred, not claimed complete.

## Files
- Native build: settings.gradle, build.gradle, app/build.gradle, gradle.properties, wrapper and build-native.ps1 under native-android/.
- UI/navigation: MainActivity.kt, core/ui/PhcTheme.kt, feature/authentication, feature/dashboard, feature/damageassessment.
- API/architecture: data/InquiryApi.kt, data/InquiryRepository.kt, domain/LoginUseCase.kt, core/network/NetworkModule.kt.
- Security: core/security/TransportPolicy.kt, core/security/SessionStore.kt, main/debug network security XML, no-backup XML and manifest.
- Tests: app/src/test/.../TransportPolicyTest.kt, InquiryRepositoryTest.kt; app/src/androidTest/.../NativeScreenTest.kt.
- Backend local files to deploy: routes/api.php; app/Http/Controllers/Api/V1/DamageInquiryController.php; app/Http/Requests/Api/DamageInquiryRequest.php; app/Modules/DamageAssessment/app/Services/SectorOverviewService.php. Existing mobile auth files/migration must already be present.
- Progress: CODEX_PROGRESS.md and CODEX_NEXT_STEPS.md.

## Verification
- Early build attempted immediately after project/navigation creation. First failure: Maven TLS download handshake; recovered 19 official JARs using verified HTTPS and official SHA-1 checksums, without disabling certificate validation.
- First full verification build succeeded in 5m50s. Final incremental build after backup/launcher changes: BUILD SUCCESSFUL in 2m29s, 90 tasks (32 executed, 58 up-to-date).
- 10 native JUnit tests passed (7 repository/paging/serialization/expiry + 3 transport/read-only boundary tests), 0 failures.
- assembleDebugAndroidTest succeeded. Compose instrumentation test is COMPILED ONLY; no device/emulator connected (adb devices empty).
- lintDebug: 0 errors, 23 warnings. Three Navigation 2.8.5 custom lint registries were skipped automatically because of their lint API compatibility issue; do not describe this as full custom-lint coverage. Other warnings concern launcher resource cosmetics/redundancy and synchronous SharedPreferences commit, which runs on Dispatchers.IO.
- APK verified with apksigner (v2); aapt confirmed package, launcher activity, minSdk and targetSdk. Installation/runtime on a device remains unverified.
- Earlier Laravel targeted tests: 69 passed / 547 assertions. No Laravel changes were made during native phase, so those suites were not redundantly rerun.
- Logs: tmp/mobile-toolchain/native-early-build.log, native-verify-build.log, native-final-build.log.
- Reports: native-android/app/build/reports/tests/testDebugUnitTest/index.html; native-android/app/build/reports/lint-results-debug.html.

## Build / resume
From repository root:

    powershell -NoProfile -ExecutionPolicy Bypass -File native-android/build-native.ps1 -Verify -CompileDeviceTests

For the confirmed test account only:

    powershell -NoProfile -ExecutionPolicy Bypass -File native-android/build-native.ps1 -TestEmail 'APPROVED_TEST_EMAIL' -Verify

The script uses existing tmp/mobile-toolchain JDK21 / Gradle8.11.1 / AndroidSDK35, or configured JAVA_HOME/ANDROID_HOME and the wrapper. Android Studio can open native-android/ directly. No passwords, signing keys or machine-specific SDK paths are committed.

## Remaining / next step
1. Install version 0.1.2 and complete device acceptance; fixes omitted device_name in login JSON.
2. Live buildings/housing routes now return 401 as expected; test authorized login/search/details on the device.
3. Connect an Android device/emulator, install native APK, run Compose instrumentation and login/building/housing/detail/logout smoke tests.
4. Optional Priority 1 maintenance: align Navigation custom lint version and simplify redundant copied launcher resources. Current build has no lint errors.
5. Await explicit instruction before Priority 2/3. Full details, audit histories, citizen lookup, attachments, GIS, charts, repair, English localization and large-scale performance remain outside this delivered slice.

## Account-login update — 2026-10-10
- Removed test-email/checkbox gate from LoginScreen, TransportPolicy, repository, LoginUseCase, SessionViewModel and NetworkModule.
- Updated app/build.gradle to versionCode 2 / 0.1.1-test, ALLOW_HTTP debug flag; removed obsolete TestEmail build script argument.
- Updated transport/repository unit tests and native login instrumentation tests. Corrected a stale expected API call count in the updated unit test; all 10 unit tests passed on rerun.
- Anonymous live building endpoint check still returned HTTP 404 today. Mobile login availability does not establish that building/housing search is deployed.
- Final verification log: tmp/mobile-toolchain/native-account-login-recheck.log. BUILD SUCCESSFUL in 4m45s; 90 tasks (16 executed, 74 up-to-date). 10 unit tests passed. lintDebug: 0 errors/23 warnings as previously documented. Both native UI tests compiled; no connected device. APK packaged under canonical and versioned names; source ZIP refreshed. No builds remain running.

- Versioned APK: D:/myProjects/phc/tmp/mobile-delivery/PHC-Native-0.1.1-debug.apk; v2 signature verified, versionCode 2, Android 8 minimum. Latest unit tests: 10 passed, 0 failures/errors. Device tests still compile-only.



## Deployment follow-up — 2026-10-10
- Inspected routes/web.php: /push stages/commits changes, runs git push, then redirects to configured server_pull_url; /pull executes git pull. Both require maintenance role/permission within authenticated routes.
- Working tree was clean and branch matched origin at commit 048995da1 (Auto-update).
- Anonymous request to localhost/phc/push redirected to login; this agent did not run the authenticated deployment action.
- Live buildings and housing-units API requests now both return 401, confirming protected routes are available. Earlier 404 observations are historical. This does not verify authenticated search, database queries, or account permissions.
- Next step: install native 0.1.1 APK and test with account credentials entered only on device. No new APK is needed for this server deployment.

## Login payload correction — 2026-10-10
- User screenshot showed the native generic HTTP 422 validation error after login.
- Root cause found in client contract: LoginRequest.deviceName had a Kotlin default value while network Json uses default encodeDefaults=false, so device_name was omitted. Laravel MobileLoginRequest requires device_name before credential verification. This defect alone prevents login regardless of valid account credentials.
- Fixed deviceName to be a required constructor value; repository explicitly supplies PHC Native Android. No backend validation/authentication was weakened or changed.
- Added regression test through repository login and actual kotlinx serialization checking all three required wire keys and values using fixture credentials only.
- Bumped Android version to 0.1.2-test / versionCode 3. Build and unit/lint verification running: tmp/mobile-toolchain/native-login-payload-fix.log.
- Pending: verify build/test results, package APK/source and update canonical artifact/hash. No real credentials used; device login acceptance still pending.

- Login payload fix verification complete: BUILD SUCCESSFUL in 1m58s; 11 unit tests passed (8 repository/contract + 3 transport), 0 failures. Lint completed; signature v2 verified; versionCode 3 confirmed. No builds running. APK: D:/myProjects/phc/tmp/mobile-delivery/PHC-Native-0.1.2-debug.apk. SHA-256: 0B8F52C95BDA1818F2DA110EAD59044F6A4041AFA5598CB4B49205FE61549252. Canonical APK and source archive refreshed. Live authenticated login remains unverified; user must install new APK and retry on device.

