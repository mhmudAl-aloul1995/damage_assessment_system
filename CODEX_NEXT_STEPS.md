# Exact continuation — Priority 1 acceptance only

Read CODEX_PROGRESS.md. Preserve existing work; do not recreate native-android or start Priority 2/3.

## Current change
The owner accepted HTTP risk and requested real-account login. Version 0.1.1 removes the test-email/confirmation gate. Do not restore it or ask for the password in chat. Native debug HTTP remains restricted to the configured server; release requires encrypted transport.

## Required next actions
1. Build verification is complete: native-account-login-recheck.log, successful in 4m45s; 10 unit tests passed, lint 0 errors/23 warnings. No build is running.
2. Packaging complete: tmp/mobile-delivery/PHC-Native-0.1.1-debug.apk and PHC-Native-Priority1-debug.apk, versionCode 2, v2 signature verified. Source ZIP refreshed. Deliver/install this version over the previous native APK.
3. Deployment availability checked: live buildings and housing-units routes both returned 401 on 2026-10-10 after owner deployment; earlier 404 is resolved. Local /push redirects unauthenticated callers to login. Do not repeat deployment merely for these checks; proceed to authenticated device acceptance. No server migration was run.
4. Install APK on a device, enter account credentials there, verify permissions, building/housing search, pagination, details, empty/error states and logout. No live authenticated test has been performed.
5. Run connectedDebugAndroidTest when an emulator/device is connected. Compilation alone is not a runtime test.

## Build
From D:/myProjects/phc:
    powershell -NoProfile -ExecutionPolicy Bypass -File native-android/build-native.ps1 -Verify -CompileDeviceTests

Portable JDK21, Gradle8.11.1 and Android SDK35 are in tmp/mobile-toolchain. Script configures environment. TestEmail argument was removed. HTTPS endpoint, if provided, can be set with -BaseUrl.

## Files and limitations
- Native source: native-android/app/src/main/java/com/phc/inquiry; Kotlin/Compose/Hilt/Retrofit/Paging3.
- Reuse Laravel API listed in CODEX_PROGRESS.md; details use record_id, not objectid.
- Earlier hybrid android/ios/mobile-shell work is separate and preserved.
- No connected-device runtime or live authenticated acceptance yet. Debug signing only.
- Known prior lint findings: 0 errors/23 warnings including three skipped Navigation custom lint registries. Check latest report after verification.
- Full details, audits, citizen lookup, attachments, GIS, statistics and repair remain deferred.



## Latest priority: accept fixed login on device
Version 0.1.2-test / code 3 built successfully (native-login-payload-fix.log, 1m58s). All 11 unit tests passed including missing-device_name regression; APK v2 signature verified. Latest artifact: tmp/mobile-delivery/PHC-Native-0.1.2-debug.apk, also copied to canonical APK; source ZIP refreshed. No builds running.
Install this version over 0.1.1 and test authenticated login/building/housing search on device. Earlier version 0.1.1 omitted device_name from login JSON and was rejected with 422 before credential verification. Client now supplies a required explicit device_name. No backend change/deployment required for this fix. Never request the account password in chat.
