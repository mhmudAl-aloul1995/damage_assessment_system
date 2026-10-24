package com.phc.inquiry

import androidx.lifecycle.SavedStateHandle
import com.phc.inquiry.core.security.SessionStore
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.test.*
import kotlinx.serialization.json.JsonObject
import okhttp3.*
import org.junit.After
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PortalViewModelTest {
    @Before fun setup() { Dispatchers.setMain(UnconfinedTestDispatcher()) }
    @After fun cleanup() { Dispatchers.resetMain() }
    private class FakeApi : PortalApi {
        var failure: Exception? = null
        var moduleCalls = 0
        var modulesGate: CompletableDeferred<Unit>? = null
        var recordsHandler: suspend (String, Int) -> PortalPage = { _, _ -> error("Unexpected records") }
        override suspend fun modules(authorization: String): DataResponse<List<PortalModule>> { moduleCalls++; modulesGate?.await(); failure?.let { throw it }; return DataResponse(listOf(PortalModule("heks", "HEKS"))) }
        override suspend fun forms(authorization: String) = DataResponse(listOf(PublishedForm("heks-beneficiary", "heks", "HEKS", "v")))
        override suspend fun records(authorization: String, module: String, search: String, page: Int): PortalPage = recordsHandler(search, page)
        override suspend fun record(authorization: String, module: String, id: Long): DataResponse<PortalRecord> = error("Unexpected record")
        override suspend fun schema(authorization: String, form: String, id: Long?): DataResponse<FormSchema> = error("Unexpected schema")
        override suspend fun formRecords(authorization: String, form: String, search: String, page: Int): PortalPage = error("Unexpected forms")
        override suspend fun submit(authorization: String, form: String, request: SubmissionRequest): SubmissionResponse = error("Unexpected submission")
        override suspend fun upload(authorization: String, form: String, uuid: RequestBody, file: MultipartBody.Part): DataResponse<FormFile> = error("Unexpected upload")
        override suspend fun receipts(authorization: String, page: Int): ReceiptPage = error("Home must not request receipts")
        override suspend fun receipt(authorization: String, uuid: String): DataResponse<SubmissionReceipt> = error("Unexpected receipt")
        override suspend fun attachment(authorization: String, id: Long): ResponseBody = error("Unexpected file")
        override suspend fun discard(authorization: String, form: String, id: Long, request: DiscardFileRequest): JsonObject = error("Unexpected discard")
    }
    private class FakeDrafts : DraftSummaryStore {
        @Volatile var fail = false
        var result = DraftListing(emptyList())
        override fun listing(owner: Long): DraftListing { if(fail) throw java.security.ProviderException("Keystore operation failed"); return result }
        override fun delete(owner: Long, uuid: String) = error("Unexpected deletion")
    }
    private fun session() = SessionState().apply { set(Session("fixture-token", "2099-01-01T00:00:00Z", User(1, "Fixture", "fixture@example.test"))) }
    @Test fun newSearchFailureNeverDisplaysResultsFromThePreviousQuery() = runTest {
        val sessions = session()
        val api = FakeApi().apply { recordsHandler = { query, _ ->
            if (query.isNotEmpty()) throw java.io.IOException("Offline")
            PortalPage(listOf(PortalRecord(1, SHELTER_WORKSPACE)), total = 1)
        } }
        val model = PortalRecordsViewModel(SavedStateHandle(mapOf("module" to SHELTER_WORKSPACE)), repository(api, sessions))
        assertEquals(1, model.state.first { !it.loading }.rows.size)
        model.search("مستفيد جديد")
        val failed = model.state.first { !it.loading }
        assertTrue(failed.rows.isEmpty()); assertFalse(failed.searched)
        assertEquals("مستفيد جديد", failed.query); assertNotNull(failed.error)
        sessions.expire()
    }

    @Test fun failedNextPageRetriesThatPageAndPreservesAlreadyLoadedRecords() = runTest {
        val sessions = session()
        val pages = mutableListOf<Int>()
        var failNextPage = true
        val api = FakeApi().apply { recordsHandler = { _, page ->
            pages += page
            if (page == 2 && failNextPage) throw java.io.IOException("Offline")
            PortalPage(listOf(PortalRecord(page.toLong(), SHELTER_WORKSPACE)), total = 2, currentPage = page, lastPage = 2)
        } }
        val model = PortalRecordsViewModel(SavedStateHandle(mapOf("module" to SHELTER_WORKSPACE)), repository(api, sessions))
        model.state.first { !it.loading }; model.more()
        val failed = model.state.first { !it.loading }
        assertTrue(failed.appendFailed); assertEquals(listOf(1L), failed.rows.map { it.id })
        failNextPage = false; model.retry()
        val ready = model.state.first { !it.loading }
        assertEquals(listOf(1, 2, 2), pages); assertEquals(listOf(1L, 2L), ready.rows.map { it.id })
        assertNull(ready.error); assertFalse(ready.appendFailed)
        sessions.expire()
    }
    private fun repository(api: FakeApi, session: SessionState, offline: OfflineFormStore = MemoryOfflineForms()) = PortalRepository(api, session, object : SessionStore {
        override fun read(): Session? = null
        override fun save(session: Session) {}
        override fun clear() {}
    }, offline)
    @Test fun deviceUnlockedWorkspaceDoesNotAttemptAnyServerCatalogRefresh() = runTest {
        val sessions = session(); val offline = MemoryOfflineForms()
        val schema = FormSchema("heks-phase-three", "heks-shelter", "الثالثة", "v", listOf(FormSection("المستفيد", listOf(FormField("name", "الاسم")))), false)
        offline.saveCatalog(1, OfflineCatalog(forms = listOf(PublishedForm(schema.key, schema.module, schema.title, schema.version, canSubmit = true)), schemas = mapOf(schema.key to schema)))
        sessions.enterOffline(sessions.session.value!!.user)
        val api = FakeApi()
        val model = PortalViewModel(repository(api, sessions, offline), FakeDrafts(), sessions)
        val state = model.state.first { !it.loading }
        assertTrue(state.localOnly); assertNull(state.error); assertEquals(1, state.forms.size)
        assertEquals(0, api.moduleCalls); assertTrue(state.usingSavedCatalog)
        model.downloadOffline()
        model.state.first { !it.loading }
        assertEquals(0, api.moduleCalls)
        sessions.expire()
    }
    @Test fun cachedCatalogIsVisibleBeforeTheUnavailableServerRespondsAndAfterRestart() = runTest {
        val session = session(); val offline = MemoryOfflineForms()
        val schema = FormSchema("heks-phase-three", "heks-shelter", "الثالثة", "v", listOf(FormSection("المستفيد", listOf(FormField("name", "الاسم")))), false)
        offline.saveCatalog(1, OfflineCatalog(listOf(PortalModule("heks-shelter", "HEKS")), listOf(PublishedForm(schema.key, schema.module, schema.title, schema.version, canSubmit = true)), mapOf(schema.key to schema), "2026-10-23T07:00:00Z"))
        val gate = CompletableDeferred<Unit>()
        val api = FakeApi().apply { failure = java.net.ConnectException("offline"); modulesGate = gate }
        val model = PortalViewModel(repository(api, session, offline), FakeDrafts(), session)
        val initial = model.state.first { it.forms.isNotEmpty() }
        assertFalse(initial.loading); assertNull(initial.error); assertTrue(initial.usingSavedCatalog)
        gate.complete(Unit)
        val ready = model.state.first { it.error != null }
        assertEquals(setOf("heks-phase-three"), ready.offlineReadyKeys); assertTrue(ready.usingSavedCatalog)
        assertFalse(ready.loading); assertEquals(schema.title, ready.forms.single().title)
        val reopened = PortalViewModel(repository(api, session, offline), FakeDrafts(), session)
        assertEquals(ready.offlineReadyKeys, reopened.state.first { it.error != null }.offlineReadyKeys)
        session.expire()
    }
    @Test fun keystoreFailureNeverBlocksRemoteModulesOrFormsAndRetryClearsOnlyDraftWarning() = runTest {
        val session = session(); val drafts = FakeDrafts().apply { fail = true }
        val model = PortalViewModel(repository(FakeApi(), session), drafts, session)
        val failed = model.state.first { !it.loading && it.draftsError != null }
        assertEquals(listOf("heks"), failed.modules.map { it.key }); assertEquals(1, failed.forms.size)
        assertNull(failed.error); assertFalse(failed.draftsError!!.contains("Keystore"))
        drafts.fail = false; model.localDrafts()
        val recovered = model.state.first { it.draftsError == null }
        assertEquals(1, recovered.modules.size); assertNull(recovered.error)
        session.expire()
    }
    @Test fun failedOfflineTemplateStorageDoesNotBlockOnlineFormsOrClaimFieldReadiness() = runTest {
        val session = session()
        val failedStorage = object : OfflineFormStore {
            override fun readCatalog(owner: Long): OfflineCatalog? = throw java.security.ProviderException("private keystore error")
            override fun saveCatalog(owner: Long, catalog: OfflineCatalog) { throw java.security.ProviderException("private keystore error") }
            override fun clearCatalog(owner: Long) {}
        }
        val model = PortalViewModel(repository(FakeApi(), session, failedStorage), FakeDrafts(), session)
        val state = model.state.first { !it.loading && it.forms.isNotEmpty() }
        assertNull(state.error); assertTrue(state.offlineReadyKeys.isEmpty()); assertFalse(state.preparingOffline)
        assertNotNull(state.offlineNotice); assertFalse(state.offlineNotice!!.contains("private"))
        session.expire()
    }
    @Test fun partialDraftFailureKeepsReadableAllowedFormsWithoutRevivingAuditQuestionnaires() = runTest {
        val session = session()
        val drafts = FakeDrafts().apply { result = DraftListing(listOf(
            DraftSummary("1", "heks-beneficiary", "HEKS", "heks", 1, "now", false, null),
            DraftSummary("2", "building-audit", "Audit", "damage_assessment", 1, "now", false, null),
        ), 1) }
        val model = PortalViewModel(repository(FakeApi(), session), drafts, session)
        val state = model.state.first { !it.loading && it.draftsError != null }
        assertEquals(listOf("1"), state.drafts.map { it.uuid }); assertNull(state.error)
        session.expire()
    }
    @Test fun localDraftFailureDoesNotHideRemoteErrorsOrMixTheirRetryState() = runTest {
        val session = session(); val drafts = FakeDrafts().apply { fail = true }
        val api = FakeApi().apply { failure = FormFailure(503, message = "server unavailable") }
        val model = PortalViewModel(repository(api, session), drafts, session)
        val state = model.state.first { !it.loading && it.draftsError != null }
        assertEquals("server unavailable", state.error)
        drafts.fail = false; model.localDrafts()
        assertEquals("server unavailable", model.state.first { it.draftsError == null }.error)
        session.expire()
    }
}
