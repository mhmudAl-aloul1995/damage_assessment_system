# CODEX_PROGRESS — Native portal
## Latest delivery — 0.6.1-test / code 14 (2026-10-11)
- Owner reports connection failures throughout the app after login; website opens on the same phone/network. Unauthenticated cloud portal/modules and forms probes return 401; this confirms reachability from this computer only. Authenticated/device failure remains unresolved; no claim that this APK fixes the reported connection failure.
- Fixed independently reproduced statistics decoding failure for empty PHP audit_tracks arrays; populated tracks and invalid-data rejection preserved. Added safe network categories NET-TIMEOUT/CONNECT/DNS/TLS/INTERRUPTED/IO, preserved policy errors instead of mislabeling them offline, and retained session/same-draft retry behavior. No raw exceptions, credentials, automatic retries or timeout changes.
- Changed: version, InquiryApi.kt, InquiryRepository.kt, PortalRepository.kt, MapDataTest.kt, InquiryRepositoryTest.kt, PortalRepositoryTest.kt; new AuditTracksSerializer.kt and core/network/ConnectionFailureMessage.kt. No PHP/API endpoint/migration/dependency or server/provider writes.
- Build SUCCESS 1m25s; 77 JVM tests pass, 0 failures/errors; 31 device tests compiled only. Lint 0 errors/34 warnings, existing informational custom-lint limitation retained; diff check passed. Log: tmp/mobile-toolchain/portal-061-native.log. Phone runtime acceptance pending.
- APK: tmp/mobile-delivery/PHC-Native-0.6.1-debug.apk; 168693102 bytes; SHA256 54CF304DF76A151F6A8281635059D5830E42E5DE9394BC61B062079A63FFC9F9. Package com.phc.inquiry.test, code14, min26/target35, four ABIs; v2 signature verified with unchanged certificate 67d381b9e432578f0a732e7e902dc8441d105451c61fee6774749cbafbfd045c. Install as update, preserve data.
- Source: tmp/mobile-delivery/PHC-Native-0.6.1-source.zip, same allowlist as 0.6.0 plus the two new Kotlin files; existing Laravel application required. Prior deliveries preserved.
- Next: owner installs update and provides exact new error/code and affected screen. Diagnose device/authenticated request using that evidence; website opening alone does not verify authenticated API responses. Do not request passwords or claim the known decoder fix resolves connection failures. Broader module parity and owner ArcGIS manual review remain as below.

## Latest native delivery — 0.6.0-test / code 13
Owner approved implementation of the reviewed blue Metronic-inspired workspace/login/statistics designs. Completed native Android changes on 2026-10-11. This section supersedes the preview-only statuses below.

- Official original council logo, correct Arabic name, blue gradient login, white form surface, retained autofill/auth validation/password handling/HTTP policy.
- Workspace Home: module/search and questionnaire entry points, draft continuation, actual account-scoped building/unit totals; failed/missing totals remain unavailable, not zero. New WorkspaceStatisticsViewModel/Card query only permitted sectors and clear/cancel on account change/logout.
- Five destinations: Home, Modules, My Tasks, Map, Account. My Tasks shows actual unfinished drafts/pending receipt confirmation and routes to the existing submission history; no fictional assignments. Account icon now opens Account rather than immediately opening logout.
- Compact damage workspace with authorized search/map/statistics shortcuts; detail Summary/Full data tabs and existing capability-controlled history/attachments. Statistics highlight sector totals and readable bars; existing filters, unknown categories, separate entity counts and real backend data retained.
- No new API endpoints, migrations, server deployment, provider writes, dependencies, retired audit forms or key/data deletion. Whole-web parity remains incomplete: general administrative CRUD/roles, comprehensive assignment/approval queues, HEKS BOQ/pricing, bank portfolio, native building-child relation browser and generic form designer require further backend/product work.
- Build: SUCCESS 1m53s; 71 JVM tests pass (4 new scope/failure/cancellation/total-integrity cases), 31 device tests compiled only (2 new Tasks/summary UI checks). Lint 0 errors, 34 warnings, 1 information; new warnings are unused resources, existing Navigation custom lint checks remain skipped. git diff --check passed. No on-device UI/cloud acceptance claimed: local emulator ADB reports getprop but closes pm requests.
- APK: tmp/mobile-delivery/PHC-Native-0.6.0-debug.apk; 168552411 bytes; SHA256 DCFB5DF64C682D8628EBC08B7180FC25F060862BA5E07D57FDD962FB689A2385. Package com.phc.inquiry.test, min26/target35, four ABIs. v2 verified with same certificate 67d381b9e432578f0a732e7e902dc8441d105451c61fee6774749cbafbfd045c. Install as update, preserving drafts.
- Source delivery: tmp/mobile-delivery/PHC-Native-0.6.0-source.zip (native project and existing mobile Laravel integration; requires existing Laravel application; excludes private config/keys/build/cache). Older APK/source versions preserved.
- Changed: native MainActivity, PhcComponents/official logo, LoginScreen, DashboardScreen, PortalScreens, InquiryScreens, ExploreScreen, strings, version; added WorkspaceStatisticsViewModel.kt, WorkspaceStatisticsCard.kt, WorkspaceStatisticsViewModelTest.kt, phc_council_logo.jpg; expanded PortalScreenTest. Existing 0.5.5 dirty work retained. Logs: tmp/mobile-toolchain/portal-060-native.log.
- Next: owner phone acceptance of RTL/light/dark/large text/login/search/details/forms/pending submissions/statistics; no new backend deployment for 0.6.0. Prior polygon backend deployment and owner manual ArcGIS incident review remain as previously recorded.

## 2026-10-11 — Comprehensive UX preview (review before native rollout)
- Owner approved the proposed first step: review a Metronic-inspired interactive mobile design for Home → Damage module → Building search → Building detail; whole-system expansion remains incremental.
- Created two interactive directions, «مساحة العمل» and «بوابة الخدمات», in C:/Users/hp/.codex/visualizations/2026/10/09/01a1206a-280c-7881-a162-114e6605eab7/phc-workspace-preview.html. Includes module navigation, task concept, detail tabs, illustrative borrower form steps, light/dark and search state controls. All records are clearly illustrative; no data API requests or real submissions.
- Existing and proposed scope distinguished: drafts/search/forms exist; centralized assignments and wider administrative/BOQ/portfolio workflows require later integration. Damage audit questionnaires stay excluded; exact numeric OBJECTID retained. Map screen explicitly describes the existing real-polygon integration without fabricating map geometry.
- Validation: Playwright using installed headless Edge passed Arabic numeric exact match, partial-ID exclusion, name/filter results, detail tabs, form review/confirmation, both design routes, dark appearance and 320px layout. No JS runtime errors. Visually inspected light and dark screenshots. Verification script: tmp/mobile-toolchain/check-workspace-preview.cjs; standalone review copy and screenshots in tmp/mobile-delivery/phc-workspace-preview-*.
- Modified this phase: preview fragment, preview verification/render artifacts, CODEX_PROGRESS.md and CODEX_NEXT_STEPS.md only. Existing dirty native 0.5.5 changes preserved; no production source, API, database, dependency or server changes in this phase. No new APK/build required; latest installable APK remains tmp/mobile-delivery/PHC-Native-0.5.5-debug.apk.
- Next: obtain feedback on the concrete preview; implement the selected Home/module/search/detail design in Compose, verify affected tests/build and deliver the next APK. Before broader write workflows, map each existing Laravel capability and authorization; do not introduce unsupported actions or restore retired audit forms.

Updated: 2026-10-11, Asia/Hebron. Branch: codex/borrower-floor-demolition-valuation.

## Current scope / state
LATEST DELIVERABLE:0.5.5-test/code12 brings Native UI closer to existing Metronic blue/navy/cool-gray design,white compact cards/header/footer andHome inquiry+survey shortcuts. BuildSUCCESS4m29s;67JVMtests pass(includes2light/darkcontrast cases);29device tests COMPILED ONLY;lint0errors/28warnings+1info;diffcheckpass. No PHP changes in055;054's31PHP cases retained as previous validation. Polygon map,parent damage,exactOBJECTID,3forms andKeystore isolation retained.
APK:tmp/mobile-delivery/PHC-Native-0.5.5-debug.apk,168424587bytes,SHA25674E8848890E077F8F958241948555093E3B0C21AE4EF6AA98E89F5D597A7F26F;v2verified,samecert as054. Source:tmp/mobile-delivery/PHC-Native-0.5.5-source.zip. Older artifacts retained. NEXT:install055 update andphone visual acceptance(light/dark/RTL,long names,navigation/form readability). No additional PHP deployment for055,but054 polygon backend still requires deployment if not already raised. No key/data deletion/provider writes.
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
# Active 0.5.4 — building-level map symbology (2026-10-11)
Owner requests symbology at building level. Laravel mobile map now supplies safe building identity/OBJECTID/name/normalized parent damage (eager loaded, same authorized phase/assignment query). Native groups by building identity, never shared coordinates for building sectors; housing colors use the parent's recorded damage, not the worst loaded unit. Missing/conflicting parent classification is neutral; old servers remain readable but require deployment for parent colors. Unit record ids remain for authorized detail navigation only. Map counts/legend/selection explain loaded units and parent classification. Roads retain sector symbology. No provider writes, migrations, dependency changes or deployments.
Modified: SectorOverviewService.php, InquiryApi.kt, MapData.kt, ArcGISCanvas.kt, ExploreScreen.kt, strings.xml, app/build.gradle (0.5.4/code11), MobileDamageMapStatisticsTest.php, MapDataTest.kt, MapStatisticsScreenTest.kt. Added regressions for parent-vs-unit damage, identity grouping, old backend, mixed/conflicting metadata, filters/cursors/phases and parent summary UI. Build/tests pending; latest verified APK remains 0.5.3. Next: targeted PHP tests/Pint and Native Verify+CompileDeviceTests, then package only on success. Pending manual ArcGIS review unchanged.

## 0.5.4 polygon clarification — 2026-10-11
Owner confirmed actual building Polygon footprints. Preserved existing uncommitted building-level grouping/parent-damage changes; old unfinished054 native run has no active Gradle process and delivered APK remained053.
- Verified READ-ONLY TLS authenticated ArcGIS metadata:configured source and target residential building layers are esriGeometryPolygon. Local source/audited DB has lat/lon and area/length,NO geometry/location column;source boundaries must be read from provider. No migration or fake footprints.
- Added MobileBuildingFootprintService:trusted HTTPS arcgis.com configured layers only;UUID globalids from already authorized bounded rows (max500),one bulk query,verified token kept server-side,identity whitelist,cached geometry15min;local recorded damage stays current. No provider edit calls.
- Optional validated geometry=polygon on existing map route;housing uses parent polygons. Missing/unavailable/invalid boundaries increment omitted,retain pagination,never fabricate shapes/convert points. Legacy default API geometry and roads remain supported. Ring closure/vertex/coordinate limits apply.
- Native building maps request polygon mode,discard old-server points/open rings,preserve cursor/error notices,draw translucent damage fills with matching boundary outline,identify same building to existing summary/record choices. Grouping/classification remains by building,not co-located unit damage.
- Completed:30 targeted PHP tests306assertions +7footprint tests48assertions rerun after cache-budget guard (31unique cases total).65JVM tests pass;28device tests COMPILED ONLY. Native Verify+CompileDeviceTests SUCCESS3m12s (90tasks35executed55cached),Pint pass,lint0errors/28warnings+1info,diffcheckpass. Logs portal-054-polygons-native.log,portal-054-polygons-backend.log,portal-054-polygons-final-test.log,portal-054-polygons-pint-final.log.
- READ-ONLY real provider verification:source/target layer metadata bothPolygon;new service successfully fetched one real boundary for previously known source globalid (one ring,coordinates/token not printed). No geometry/business/provider edit. Local phase1 DB probe has no record;authenticated Laravel CLOUD/physical-map acceptance not claimed.
- New files:app/services/MobileBuildingFootprintService.php,tests/Feature/MobileBuildingPolygonMapTest.php. Updates:DamageInquiryRequest.php,SectorOverviewService.php,MapData.kt,ExploreViewModel.kt,ArcGISCanvas.kt,strings.xml,MapDataTest.kt,ExploreViewModelTest.kt plus preserved earlier054 files. No new dependencies/migrations/server deployment;pending ArcGIS manual review unchanged.
- Packaged054 APK/source with same signing cert;168418955bytes,SHA25600253B77D17C0A20D75511A13FF672A07559ECC71FA173836F86EF9F8E32DAEA. Source includes2newPHP files. Restored Pest temporary results from entry snapshot (already dirty),preserving preexisting building-map work. NEXT:owner deploys PHP/new service,installs054 update and accepts polygon map/search/color/selection on phone. No new migration. Pending ArcGIS correction still owner manual review.

## 0.5.5 native Metronic visual alignment — 2026-10-11
Owner asked to bring mobile design close to existing Metronic dashboard. Read existing assets/css/style.bundle.rtl.css and layouts/module-switcher CSS:primary#3E97FF,text#071437,gray#78829D/sidebar#111827. Adapted native theme to same blue/navy/cool-gray family,using darker accessible action blue#2563EB and legible secondary text. Existing Arabic Noto retained for mobile readability.
- Shared light/dark Material3 palettes,white/neutral surfaces,no purple navigation tint;compact12dp cards,small soft-blue icon tiles;login card consistent.
- Home white service panel with direct unified inquiry +survey shortcuts,compact metric cards,module list. Header/footer neutral surfaces and blue selected navigation.
- Renamed map paging to display more buildings for building sectors,more locations elsewhere,responding to earlier confusing label. No search/map/provider/auth/storage/permissions behavior changed beyond inquiry shortcut navigation.
- Completed:Build055/code12 SUCCESS4m29s(90tasks40executed50cached),67JVM tests pass includingtext/actions contrast>=4.5 inlight/dark;29device tests COMPILED ONLY;lint0errors/28warnings+1info;diffcheckpass. Log portal-055-native.log. Physical visual screenshots/acceptance not performed.
- Packaged055 APK168424587bytes,SHA25674E8848890E077F8F958241948555093E3B0C21AE4EF6AA98E89F5D597A7F26F,v2verified,same cert as054. SourceZIP includes new MetronicThemeTest.kt. Older054 retained. Native edits only,no new dependencies/PHP migrations/deployment/provider writes.
- Changed PhcTheme.kt,PhcComponents.kt,PortalScreens.kt,MainActivity.kt,LoginScreen.kt,ExploreScreen.kt,strings.xml,app/build.gradle,PortalScreenTest.kt;new MetronicThemeTest.kt. NEXT:owner installs055 as update andreviews actual phone light/dark Arabic layouts/inquiry shortcuts/forms/map-pagination labels. Prior054 Laravel polygon updates must still deploy if not already done. Do not rerun successful checks without new changes/failure. Pending ArcGIS manual review unchanged.

## 2026-10-11 — Official council logo in UX preview
Owner supplied codex-clipboard-26d41b37-5223-4633-9c3f-7c51140c78ff.jpg as the council logo. Original bytes preserved at C:/Users/hp/.codex/visualizations/2026/10/09/01a1206a-280c-7881-a162-114e6605eab7/phc-council-logo.jpg and embedded in the preview; no cropping, recoloring or image editing. Replaced PHC placeholder with aspect-preserving logo on white, and corrected preview institution name to المجلس الفلسطيني للإسكان. Playwright image decode/alt check and existing interactive/layout suite passed; inspected dark 320px screenshot. Preview only; current APK remains 0.5.5. Carry this exact supplied logo/name into the approved native redesign. Current interaction selection is مساحة العمل; no new approval to implement all proposed workflows inferred from logo delivery alone.

## 2026-10-11 — Login design preview
Created separate phc-login-preview.html in the thread visualization directory at owner request. Official logo preserved, Arabic Metronic-inspired login, light/dark, fixed demo-only email/password, local visibility toggle and explicit non-network login feedback. Headless Edge checks passed logo decode, password toggle, readonly inputs, local feedback, 320px dark overflow and no runtime errors. Light screenshot visually reviewed. No APK, auth or backend changes.

## 2026-10-11 — Alternative login concept
At owner request, created phc-login-alternative.html alongside the prior preview: compact horizontal welcome/logo, unboxed fields on white, council-green actions, shorter layout and dark theme. Original login preview preserved. Verified logo load, visibility toggle, demo feedback, 320px dark layout and no runtime errors with headless Edge; light image reviewed. Design review only; no native/APK/backend changes.

## 2026-10-11 — Blue login selected; background variation
Owner chose the original blue login over the green alternate. At follow-up request created phc-login-blue-background.html with soft blue radial/linear background, white logo/card surfaces and subtle shadows; original previews retained. Headless Edge verified gradient/image rendering, dark 320px no overflow and zero runtime errors; light screenshot inspected. Background variation awaits design feedback. No native/APK changes.

## 2026-10-11 — Statistics in comprehensive UX preview
Owner requested statistics in new design and approved proceeding. Updated phc-workspace-preview.html with Home statistics summary, damage-workspace entry and statistics screen; phc-workspace-statistics.html opens directly to statistics for review. Separate illustrative building/unit datasets, region and damage filters, recalculated counts/percentages, unclassified included and reset. No server/PII reads or native changes. Headless Edge targeted check tmp/mobile-toolchain/check-statistics-preview.cjs passed aggregate sums, distinct entity totals, filters/denominators, both entry routes, dark 320px layout and zero runtime errors. Reviewed light/dark screenshots. Latest APK remains 0.5.5.

## Active 0.6.0/code13 — approved native workspace redesign
Owner authorized implementation of all reviewed UI. Native changes: original council logo resource/aspect ratio/name, blue gradient login retaining actual auth/HTTP behavior, workspace Home/search/forms/tasks, five navigation destinations, real per-sector Home stats via new session-aware WorkspaceStatisticsViewModel/Card, tasks from real drafts/receipts, compact damage dashboard, summary/detail record tabs, statistics totals and bar charts. New four JVM statistics regressions and two device UI tests. No demo figures copied; no provider/DB/API writes. Building next with Verify/CompileDeviceTests; latest verified APK still 0.5.5 until success. Broader web parity/admin/BOQ/assignments and building child linkage remain separate backend capabilities, not fabricated in UI.

0.6.0 source archive verified: 136 allowlisted source entries, 893969 bytes, SHA256 2E6649B52F18AAAA57296FF6A39BD182812006C2A832316D16B59C6312C16DA2. Original supplied logo SHA256 equals native resource. No unchanged checks repeated; no deployment/commit performed.

## Active 0.6.1 — update-error investigation
Owner reports an unspecified error requesting update; screen/exact text clarification is pending. Unauthenticated read-only cloud probes to portal/modules, forms and buildings/stats each returned401application/json (routes reachable, no authenticated payload inspected). Independently reproduced a confirmed decoder bug: SectorOverviewService::auditTracks returns engineering/legal [] for public-buildings, road-facilities and cso-surveys, whereas Kotlin expected nested objects. New MapDataTest failed with JsonDecodingException before fix (portal-061-reproduction.log). Added field-scoped AuditTracksSerializer accepting only EMPTY PHP arrays; populated objects retained and malformed nonempty arrays/counts rejected. No blanket coercion/zero substitution. Three new regression cases. Version0.6.1/code14; verification pending. This is a confirmed bug but not yet proven to be the owner's specific error. No PHP/server/data changes.

0.6.1 follow-up: owner identifies connection error in all areas after login, while website opens on the same phone/network. Root cause remains unverified without device exception/authenticated response. Initial native verification passed; added category-specific safe connection messages (NET-TIMEOUT/CONNECT/DNS/TLS/INTERRUPTED/IO), retained transport-policy messages, same-draft ambiguous write handling and session preservation. Three repository regression tests added; rerunning native verification. No retry/timeouts/auth behavior changes, no credential logging or cloud writes.

0.6.1 source archive verified: 138 entries, 898406 bytes, SHA256 EEF2388B4426BBBEE9326853489C1EB6AB6F4270037BA578396AF8FBBED6DA98. Source snapshot includes latest continuation instructions; this hash recorded after archive creation.
