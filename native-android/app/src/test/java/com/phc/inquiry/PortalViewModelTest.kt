package com.phc.inquiry

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
        override suspend fun modules(authorization: String): DataResponse<List<PortalModule>> { failure?.let { throw it }; return DataResponse(listOf(PortalModule("heks", "HEKS"))) }
        override suspend fun forms(authorization: String) = DataResponse(listOf(PublishedForm("heks-beneficiary", "heks", "HEKS", "v")))
        override suspend fun records(authorization: String, module: String, search: String, page: Int): PortalPage = error("Unexpected records")
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
    private fun repository(api: FakeApi, session: SessionState) = PortalRepository(api, session, object : SessionStore {
        override fun read(): Session? = null
        override fun save(session: Session) {}
        override fun clear() {}
    })
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
