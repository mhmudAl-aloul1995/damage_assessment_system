# CODEX_PROGRESS — Native portal
Updated: 2026-10-11, Asia/Hebron. Branch: codex/borrower-floor-demolition-valuation.

## Current scope / state
LATEST DELIVERABLE: 0.5.3-test/code10 isolates unreadable/Keystore draft errors from remote module/form loading. Build SUCCESS4m30s;57 JVM tests pass;27 device tests COMPILED ONLY;lint0errors/28warnings+1info. No PHP change in0.5.3;prior74 Laravel tests/644 assertions remain0.5.2 validation. Exact numeric OBJECTID and withdrawal of9audit forms retained;only3borrower/HEKS forms.
APK:tmp/mobile-delivery/PHC-Native-0.5.3-debug.apk (168406059 bytes),SHA2567CE0BC047572A8694FFF5AA92D52D9D537CF301EA292F638182FC009B52404E4;v2 signature verified,same cert as0.5.2. Source:tmp/mobile-delivery/PHC-Native-0.5.3-source.zip. Older artifacts below preserved history. Install update;owner phone verification of restored inquiry and local draft readability required. No key/ciphertext deletion or plaintext fallback;physical key recovery not claimed.
Owner approved unified Native Android portal for ALL four enabled modules, including HEKS (supersedes former Priority3 repair exclusion). Implemented the portal and supported questionnaire workflows below. This is a portal baseline, not full parity with every administrative/BOQ/report screen in the web application. No generic form designer was requested. No iOS binary: owner has no Mac or Apple Developer account.
Final Android0.5.0-test/code7 packaging SUCCESS in2m42s (portal-native-final-package.log);42 JVM tests pass,20 device tests COMPILED ONLY. No physical installation/screenshots/authenticated cloud acceptance claimed. Owner chose phone testing; do not ask for BlueStacks ADB again.
Final APK:tmp/mobile-delivery/PHC-Native-0.5.0-debug.apk,169208473 bytes, SHA256 81BD2AA3E1ACA8C4784936A79A0968DC6949A319653489CCDBB7AAC29F9C42C6. Package com.phc.inquiry.test,code7,min26,target35,four ABIs. v2 signature VERIFIED; same certificate as0.4:67d381b9e432578f0a732e7e902dc8441d105451c61fee6774749cbafbfd045c. Source ZIP:tmp/mobile-delivery/PHC-Native-0.5.0-source.zip. Previous0.4 and internal early APK preserved; use final0.5.

## Completed — UX / inquiry
-100% Native Kotlin/Compose/Material3, Arabic RTL/Noto, light/dark, Arabic calendar/time controls; five destinations:home,inquiry,surveys,map,account.
-Role-aware catalog from Sidebar::forUser:damage_assessment,damage_assessment_borrowers,heks,administration. Disabled/unauthorized modules omitted.
-Borrower name/identity/phone/loan-number search, HEKS name/code/identity/phone search, authorized administration user search/details. Literal SQL wildcard escaping,20-row pagination, bounded queries. Explicit safe fields; no passwords/provider/raw payload dumps.
-Existing five damage sectors/search/filters/basic/full approved details/engineering+legal history/citizen inquiry/files/ArcGIS maps/native statistics retained. New form action on authorized damage details.
-Portal shows available inquiry modules even if a later forms/receipt request fails. Form close refreshes receipts.
-Account changes bind outgoing requests to captured bearer and suppress previous-account responses.

## Completed — published forms / writes
12 supported adapters when their source metadata is available:
borrower-survey;heks-beneficiary;heks-followup;
building-audit;housing-audit;public-building-audit;public-building-unit-audit;
road-audit;road-item-audit;cso-audit;cso-organization-audit;cso-unit-audit.
Borrower supports NEW survey and explicit update; other adapters update existing authorized records/children. No invented new building/HEKS/Kobo creation workflow.
-Reuses StoreBorrowerSurveyRequest, risk analysis, demolition valuation/exchange settings, HEKS existing update/followup rules and engineer resolver, AssessmentEditService and infrastructure edit controllers/history. Unchanged infrastructure answers do not create duplicate edit history.
-Damage phases/audit access/assignment/read-only lawyer and team-leader restrictions enforced; target picker limited to editable assignments/source records. System IDs/phases/assignments/device metadata/geometry excluded from writable questions.
-Only approved scalar/choice/repeat types exposed; raw HEKS Kobo editing remains DISABLED. Not automatic arbitrary XLSForm/form-builder publishing.
-Versioned native schema, required/min/max/length/choice/repeat/conditional validation. Reject unknown/nested mass assignment; exclusive none choices; preserves visit date AND time. JSON answers max2MiB.
-Login grants mobile:submit only if a permitted writable adapter exists. All calls retain active Sanctum bearer/mobile:read; writes also require submit + CURRENT form/module/record authorization.
-Durable per-user UUID/request-hash receipts, user-row/record locks, one transaction for write+receipt+attachment links, replay200/new201; different body/snapshot/schema409. No implicit overwrite by borrower identity.
-Private staged JPEG/PNG/PDF files:15MiB/file,5 files/30MiB/submission,100 pending/user; actual MIME validation/hash dedup, private local disk, safe filenames, actor/form/UUID binding. Scoped receipt/file GET, idempotent staged discard, received files protected from discard.
-Encrypted before/after audit and precise receipt GPS via Laravel encrypted casts. Existing module data/history remains in its existing storage.
-Separate named submit/upload/discard limiters10/min each alongside general60/min; reads do not exhaust write quota.
-Provider audit synchronization now verifies HTTPS certificates.

## Completed — drafts / file UX
-Android Keystore AES-GCM, account+API-origin isolation, authenticated filename/scope binding, atomic private/noBackup files. No saved passwords/plaintext photo cache.
-Cached schema, autosave coalescing, step/repeat/choice wizard, inline errors/review/explicit send. Local save and server receipt clearly distinguished.
-Frozen durable pending request before sending; ambiguous retries preserve UUID/payload. Reauthentication does not delete drafts. An unexpired previously authenticated account can open its own drafts offline; expired/revoked/inactive sessions cannot restore.
-Conflict refresh preserves answers, displays current-server versus draft changes, requires explicit review. Bounded lightweight draft list summaries.
-PNG/JPEG/PDF picker; streaming encryption during import, private viewers reuse isolated PDF renderer and bounded image decode. Optional foreground GPS with runtime consent/timeout; no background location, GPS hardware optional.
-Safe local deletion/retry-save/failure states; unresolved submissions cannot be deleted as ordinary drafts.

## API additions
GET api/v1/portal/modules
GET api/v1/portal/{damage_assessment_borrowers|heks|administration}/records and /{record}
GET api/v1/forms; forms/{form}/schema?record=; forms/{form}/records
POST api/v1/forms/{form}/submissions; /attachments; /attachments/{attachment}/discard
GET api/v1/submissions; submissions/{uuid}; form-attachments/{attachment}
Existing damage/auth/modules endpoints retained. Exact native mutation allowlist; no arbitrary server commands.

## Modified files
New:MobilePortalController; requests MobilePortalQueryRequest/MobileFormSubmitRequest/MobileFormUploadRequest/MobileFormDiscardRequest;
models/factories MobileFormSubmission/MobileFormAttachment;
services MobilePortalService/MobileFormDefinitionService/MobileFormService/MobileDamageFormService;
two migrations listed below; four portal/form Pest suites.
Updated:MobileAccessController,AppServiceProvider,routes/api.php,SectorOverviewService,ArcgisAuditedUploadService.
Native new:PortalApi/PortalRepository; feature/portal DraftVault,FormRules,PortalViewModels,PortalScreens,NativeLocation,PortalFileViewModel,PortalPreviews; FormRulesTest/PortalRepositoryTest; DraftVaultTest/PortalScreenTest.
Native updated:MainActivity,NetworkModule,TransportPolicy,InquiryRepository,strings,manifest,Gradle,existing repository/transport tests. Prior Priority3 dirty files preserved; no commit/push/deployment performed.

## Verification / deployment
-59 backend tests/412 assertions pass in portal-backend-combined.log; mocked existing provider regression1 test/2 assertions PASS.60 unique affected backend tests. Later affected private-file test1/19 and portal-label tests5/28 PASS. Scoped inquiry/map regressions included.
-42 JVM tests pass;20 device tests compiled only. Lint0errors/27warnings+1info (Navigation custom lint registries skipped; dynamic locale AppBundle warning remains in lint model despite disabled language splitting). No APK compiler/signature failure.
-Pint dirty --format agent checked. No installed pest-testing skill; Boost12.x docs used. Boost Artisan/URL/schema tools used; actual users.id verified bigint unsigned matches receipt FK.
-New migrations NOT executed against real local/cloud DB:
database/migrations/2026_10_11_112238_create_mobile_form_submissions_table.php
database/migrations/2026_10_11_112239_create_mobile_form_attachments_table.php
-Owner deployment required:verified maintenance page http://localhost/phc/push does git push/pull, NOT migration. Run ONLY the two listed migrations on deployed Laravel, clear route/config caches as appropriate; never invoke all-pending run-migrations blindly.
-Owner real-account HTTP acceptance persists; debug HTTP restricted to existing IP/port, release blocks cleartext. HTTPS/release signing/ArcGIS Lite deployment licensing still production prerequisites; no fake SDK key.
-Source archive requires the existing Laravel repository; not a standalone complete backend.

## Real test-write incident — owner chose manual review
One initial test omitted Queue::fake; existing sync queue executed TWO actual ArcGIS edits before isolation:
building source801 -> target15413 building_type=edited; housing source901 -> target48702 housing_unit_type=new.
Verified HTTPS read confirmed matching source links. Current source values are building and mezzanine respectively. Local audited rows801/901 absent; PRE-TEST audited values are UNKNOWN (source values are not proven backups).
Owner was informed, then explicitly chose “سأراجع السجلين قبل التصحيح”. NO correction authorized/performed; do not auto-restore. Keep this fact in final report.
New damage tests now Queue::fake +Http::preventStrayRequests and verify queued jobs/SSL; subsequent tests use only fixtures/mocks. No additional real provider writes identified. No production SQL migrations or Laravel deployment performed.

## Exact next step
Final package/version/signature/hash checked and copied. Source archive prepared with native project/mobile integration files; Pest temporary results restored. Next is owner deployment of PHP plus ONLY two migrations, re-login and physical phone acceptance. No data correction without later owner direction.
Future feature parity not implemented:full HEKS BOQ/pricing/scores/labels/native administration CRUD/bank-portfolio screens/new module-specific creation workflows/arbitrary form designer/XLSForm logic. Do not claim these are complete.

Build: powershell -NoProfile -ExecutionPolicy Bypass -File native-android/build-native.ps1 -Verify -CompileDeviceTests
Existing JDK21/Gradle8.11.1/SDK35/Esri200.6 caches in tmp/mobile-toolchain; do not redownload or restart analysis.

## 0.5.1 screenshot failure investigation — 2026-10-11
- Screenshot shows modules/forms loaded, followed by failure while loading receipt history. Confirmed code defect: refresh fetched receipts on HOME and reused write-ambiguity messages for GET failures.
- Fixed native read/write error messages; receipt history now loads only in Sent tab, with independent loading/error/pagination and retry. HOME/inquiry/forms stay usable on receipt failure; Sent does not falsely show an empty history on failure.
- Added Laravel authenticated storage readiness checks before receipt/private-file/submission operations; absent table(s) yield safe JSON503 code mobile_form_storage_not_ready. No automatic migrations and no new endpoint/dependency.
- Boost read-only LOCAL schema check returned neither mobile_form_submissions nor mobile_form_attachments. CLOUD missing-table cause remains unverified: no authenticated cloud error/log evidence available. Asked owner whether targeted migrations were run.
- Modified native PortalRepository.kt, PortalViewModels.kt, PortalScreens.kt, MainActivity.kt, app/build.gradle (0.5.1/code8), PortalRepositoryTest.kt, PortalScreenTest.kt; Laravel MobilePortalController.php and new MobileFormStorageReadinessTest.php.
- Completed:21 targeted PHP tests/181 assertions;45 JVM tests,22 instrumentation tests COMPILED ONLY;Pint;lint0errors/27warnings+1info. Build SUCCESS3m34s (90 tasks,35 executed). Logs portal-051-backend.log, portal-051-pint.log, portal-051-native.log in tmp/mobile-toolchain. Older0.5.0 APK preserved.
- No real DB migrations/deployment/provider writes. ArcGIS incident remains owner review before any correction.
- Owner says BOTH tables prepared, then says uploaded them just now. Do NOT assume missing CLOUD tables. No cloud Laravel error was supplied; root cause of earlier receipt failure remains unverified pending retry after deployment. Browser inventory has no authenticated cloud tab. SystemLogController shows operation logs, NOT Laravel exception logs; do not direct owner there for this exception.
- Packaged verified0.5.1-test/code8 APK with same signing cert;168395379bytes,SHA25625DFAA0A369C2CDDC082D950EB5F29D57FDC3A63B9267312065CBCD1831C4EA3. Source bundle includes new readiness test. Next:owner installs as update, retries Sent tab after fresh server deployment. If failure persists, obtain ONLY relevant api/v1/submissions exception from CLOUD storage/logs/laravel.log; no credentials. Do not rerun completed checks or run real migrations without a new need.

## 0.5.2 OBJECTID inquiry and audit questionnaire withdrawal — 2026-10-11
Owner requested numeric search by exact OBJECTID instead of database record id, and removal of audit questionnaires.
- Mobile damage inquiry numeric inputs now use qualified objectid equality, excluding numeric name/globalid/partial-ID collisions; Arabic/Persian digits normalize to ASCII. Name search preserved; unified citizen identity search unchanged. Same scoped query feeds lists/maps/stats and retains phase/permission restrictions.
- Native labels/cards/details/copy use OBJECTID; removed database-id fallback and internal reference from visible basic details. Internal row keys remain for safe detail navigation only.
- MobileFormService publishes only borrower-survey/heks-beneficiary/heks-followup. Nine damage audit form schemas/records/submissions/uploads/discard now404. Existing provider/web audit services/histories and saved business data untouched.
- Native allowlist independently excludes disabled forms/receipts even against older server. Old audit drafts are hidden and opening/resending blocked, but encrypted files are NOT deleted automatically.
- Updated existing MobileDamageFormApiTest (same12 cases) for withdrawn API behavior; retained TLS regression. Added five-sector exact-number/search-map-stat tests, read/write blocking tests, OBJECTID display/copy UI checks. New damage tests fake queues and block stray provider requests.
- Completed:initial native verificationSUCCESS3m12s;final resource-label packageSUCCESS1m54s;48 JVM tests pass,24 instrumentation tests COMPILED ONLY.74 PHP tests/644 assertions pass (60/578 +auth/throttle14/66). Pint pass;lint0errors/28warnings+1info. Logs portal-052-native.log,portal-052-package.log,portal-052-backend.log,portal-052-auth.log,portal-052-pint.log. Earlier fixture-only failures corrected (refresh persisted attributes/id and housing parent geometry); final checks pass.
- Delivered APK0.5.2-test/code9:168899301bytes,SHA256CC20445E77042ED850434F42B50FE893EBA64F3FECE224DAF3A29F5192F2B07F,v2 verified,same cert as0.5.1. Source ZIP0.5.2 includes native+mobile Laravel integration;older0.5.1 preserved. Pest temporary results restored;unrelated changes preserved. No migrations/deployment/provider writes; pending ArcGIS owner review unchanged.
- Next:owner raises PHP via verified http://localhost/phc/push (changed SectorOverviewService.php +MobileFormService.php), installs0.5.2 as update and tests exact OBJECTID on physical phone. No new migrations for this change. Existing read-only audit history remains. CLOUD acceptance and actual device tests not performed.
- Modified:SectorOverviewService.php,MobileFormService.php,native app/build.gradle,PortalRepository.kt,PortalViewModels.kt,InquiryScreens.kt,PhcComponents.kt,strings.xml,PortalRepositoryTest.kt,NativeScreenTest.kt,DamageInquiryApiTest.php,MobileDamageFormApiTest.php.

## 0.5.3 device Keystore failure isolation — 2026-10-11
- Owner phone screenshot:HOME shows raw Keystore operation failed,zero modules/forms. Confirmed defect:PortalViewModel refreshed local drafts BEFORE fetching modules/forms; one unreadable ciphertext/key failure aborted whole refresh. Hardware/key/ciphertext cause on owner device is unknown.
- Native fix:remote catalog/forms load independently;local draft errors use dedicated draftsError state/local retry,do not overwrite remote errors or show false zero/empty drafts. Partial listing retains readable drafts and counts unavailable files; files/keys are never deleted automatically.
- Decryption never generates/replaces missing key;only Android33+ failures classified transient retry ONCE with fresh Cipher and same ciphertext/AAD. Permanent failures retained. Encryption setup/write errors shown in Arabic;no plaintext fallback. Official Android KeyStoreException docs consulted (developer.android.com/reference/android/security/KeyStoreException).
- Introduced small DraftSummaryStore binding for lifecycle regression tests;added batch/retry and PortalViewModel JVM tests +corruption preservation/local warning device tests. Physical device/actual hardware recovery not yet verified.
- Modified DraftVault.kt,DraftSummaryStore.kt,NetworkModule.kt,PortalViewModels.kt,PortalScreens.kt,MainActivity.kt,app/build.gradle (0.5.3/code10),new DraftSummaryStoreTest.kt/PortalViewModelTest.kt,device DraftVaultTest.kt/PortalScreenTest.kt.
- Completed:build-native.ps1 -Verify -CompileDeviceTests SUCCESS4m30s,90tasks37executed53cached (portal-053-native.log);57 JVM tests (including9new regression cases) pass;27 device tests COMPILED ONLY. Lint0errors/28warnings+1info;gitdiffcheckpass. No unchanged PHP tests rerun.
- Delivered0.5.3-test/code10 APK168406059bytes,SHA2567CE0BC047572A8694FFF5AA92D52D9D537CF301EA292F638182FC009B52404E4,v2signature verified,same cert as0.5.2. Source bundle includes3new native files.0.5.2 preserved. No new dependencies,PHP changes,migrations,deployment,real provider writes.
- Next:owner installs update without clearing app data,opensHOME/inquiry (modules/forms independent of draft storage),triesDrafts/local retry and confirms on phone. Device Keystore root failure/key recovery remains unknown;do not claim inaccessible files decrypted. Existing ciphertext/key alias preserved;missing decryption key never regenerated during reads. No automatic reset/delete;no plaintext storage or submission before durable encrypted save. Prior ArcGIS incident stays owner manual review.
