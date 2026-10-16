package com.phc.inquiry

import com.phc.inquiry.core.security.SessionStore
import com.phc.inquiry.data.*
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.*
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.*
import org.junit.Test
import retrofit2.HttpException
import retrofit2.Response

class PortalRepositoryTest {
    private class MemoryStore: SessionStore {
        var current: Session? = null
        override fun read() = current
        override fun save(session: Session) { current = session }
        override fun clear() { current = null }
    }
    private class FakeApi: PortalApi {
        var failure: Exception? = null
        var authorization: String? = null
        var submitted: SubmissionRequest? = null
        var publishedForms: List<PublishedForm> = emptyList()
        var received = ReceiptPage(emptyList())
        var onModules: () -> Unit = {}
        var body: ResponseBody = "fixture".toResponseBody()
        var permittedModules = listOf(PortalModule("heks", "HEKS"), PortalModule("unexpected", "hidden"))
        private fun check(auth: String) { authorization = auth; failure?.let { throw it } }
        override suspend fun modules(authorization: String): DataResponse<List<PortalModule>> { check(authorization); onModules(); return DataResponse(permittedModules) }
        override suspend fun forms(authorization: String): DataResponse<List<PublishedForm>> { check(authorization); return DataResponse(publishedForms) }
        override suspend fun records(authorization: String, module: String, search: String, page: Int) = PortalPage(emptyList())
        override suspend fun record(authorization: String, module: String, id: Long) = DataResponse(PortalRecord(id, module))
        override suspend fun schema(authorization: String, form: String, id: Long?): DataResponse<FormSchema> { check(authorization); return DataResponse(FormSchema(form, "heks", "Test", "v", listOf(FormSection("s", emptyList())), false)) }
        override suspend fun formRecords(authorization: String, form: String, search: String, page: Int): PortalPage { check(authorization); return PortalPage(emptyList()) }
        override suspend fun submit(authorization: String, form: String, request: SubmissionRequest): SubmissionResponse {
            check(authorization); submitted = request
            return SubmissionResponse(SubmissionReceipt(1, request.uuid, form, "heks", 1, "r", "received", "2026-10-11"), false)
        }
        override suspend fun upload(authorization: String, form: String, uuid: RequestBody, file: MultipartBody.Part): DataResponse<FormFile> { check(authorization); return DataResponse(FormFile(1, "test.jpg", "image/jpeg", 5)) }
        override suspend fun receipts(authorization: String, page: Int): ReceiptPage { check(authorization); return received }
        override suspend fun receipt(authorization: String, uuid: String) = DataResponse(SubmissionReceipt(1, uuid, "heks-beneficiary", "heks", 1, "r", "received", "2026-10-11"))
        override suspend fun attachment(authorization: String, id: Long): ResponseBody { check(authorization); return body }
        override suspend fun discard(authorization: String, form: String, id: Long, request: DiscardFileRequest): JsonObject { check(authorization); return JsonObject(mapOf("discarded" to JsonPrimitive(true))) }
    }
    private fun state() = SessionState().apply { set(Session("fixture-owner-token", "2099-01-01T00:00:00Z", User(1, "fixture", "fixture@example.test"))) }
    @Test fun attendanceModuleSurvivesCatalogFilteringOnlyWhenReturnedByTheServer() = runTest {
        val api = FakeApi()
        api.permittedModules = listOf(PortalModule("hr", "الموارد البشرية", recordSearch = false), PortalModule("heks", "HEKS"), PortalModule("unexpected", "hidden"))
        val repository = PortalRepository(api, state(), MemoryStore())
        assertEquals(listOf("hr", "heks"), repository.modules().map { it.key })
        assertFalse(repository.modules().first().recordSearch)
        api.permittedModules = listOf(PortalModule("heks", "HEKS"))
        assertEquals(listOf("heks"), repository.modules().map { it.key })
    }
    @Test fun connectionFailuresIdentifyTheCategoryWithoutLeakingDetailsOrLosingSession() = runTest {
        val api = FakeApi(); val state = state(); val store = MemoryStore()
        store.save(state.session.value!!)
        val repository = PortalRepository(api, state, store)
        val cases = listOf(
            java.net.SocketTimeoutException("private") to "NET-TIMEOUT",
            java.io.InterruptedIOException("private") to "NET-TIMEOUT",
            java.net.ConnectException("private") to "NET-CONNECT",
            java.net.NoRouteToHostException("private") to "NET-CONNECT",
            java.net.UnknownHostException("private") to "NET-DNS",
            javax.net.ssl.SSLException("private") to "NET-TLS",
            java.io.EOFException("private") to "NET-INTERRUPTED",
            java.net.SocketException("private") to "NET-INTERRUPTED",
            java.io.IOException("private") to "NET-IO",
        )
        for ((failure, code) in cases) {
            api.failure = failure
            try { repository.modules(); fail("Expected failure") } catch (error: FormFailure) {
                assertTrue(error.message!!.contains(code)); assertFalse(error.message!!.contains("private"))
                assertFalse(error.definitelyRejected)
            }
            assertNotNull(state.session.value); assertNotNull(store.read())
        }
        api.failure = null
        assertEquals(listOf("heks"), repository.modules().map { it.key })
    }
    @Test fun timedOutSubmissionKeepsAmbiguousReceiptAndTransportPolicyIsNotReportedAsOffline() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        val request = SubmissionRequest("same-uuid", "version", answers = JsonObject(emptyMap()))
        api.failure = java.net.SocketTimeoutException("private")
        try { repository.submit("heks-beneficiary", request); fail("Expected timeout") } catch (error: FormFailure) {
            assertTrue(error.message!!.contains("NET-TIMEOUT")); assertTrue(error.message!!.contains("نفس المسودة"))
            assertFalse(error.definitelyRejected)
        }
        api.failure = com.phc.inquiry.core.security.InquiryFailure("اتصال HTTP غير مسموح في هذه النسخة أو لهذا السيرفر.")
        try { repository.modules(); fail("Expected policy failure") } catch (error: FormFailure) {
            assertTrue(error.message!!.contains("HTTP")); assertFalse(error.message!!.contains("NET-"))
        }
    }
    @Test fun catalogIsBoundToCapturedAccountAndFiltersUnknownModules() = runTest {
        val api = FakeApi(); val state = state()
        val repository = PortalRepository(api, state, MemoryStore())
        assertEquals(listOf("heks"), repository.modules().map { it.key })
        assertEquals("Bearer fixture-owner-token", api.authorization)
        api.onModules = { state.set(Session("other-token", "2099-01-01T00:00:00Z", User(2, "other", "other@example.test"))) }
        try { repository.modules(); fail("Cross-account response accepted") } catch(error: FormFailure) { assertNull(error.code) }
    }
    @Test fun retriesPreserveSubmissionIdentityAndFrozenAnswers() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        val request = SubmissionRequest("fixture-uuid", "form-version", answers = JsonObject(mapOf("name" to JsonPrimitive("same"))))
        api.failure = java.io.IOException("offline")
        try { repository.submit("heks-beneficiary", request); fail("Expected network failure") } catch(error: FormFailure) { assertFalse(error.definitelyRejected) }
        api.failure = null
        assertEquals(request.uuid, repository.submit("heks-beneficiary", request).uuid)
        assertEquals(request, api.submitted)
    }
    @Test fun serverValidationIsStructuredAndRevokedSessionIsCleared() = runTest {
        val api = FakeApi(); val state = state(); val store = MemoryStore()
        store.save(state.session.value!!)
        val repository = PortalRepository(api, state, store)
        api.failure = HttpException(Response.error<Any>(422, """{"errors":{"answers.name":["fixture validation"]},"trace":"private"}""".toResponseBody()))
        try { repository.modules(); fail("Expected validation") } catch(error: FormFailure) {
            assertEquals(listOf("fixture validation"), error.errors["answers.name"]); assertFalse(error.message!!.contains("private"))
        }
        api.failure = HttpException(Response.error<Any>(401, "{}".toResponseBody()))
        try { repository.modules(); fail("Expected unauthorized") } catch(error: FormFailure) { assertEquals(401, error.code) }
        assertNull(state.session.value); assertNull(store.read())
    }
    @Test fun boundsReceiptAttachmentDownloads() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        api.body = ByteArray(15 * 1024 * 1024 + 1).toResponseBody()
        try { repository.attachment(1); fail("Oversized file accepted") } catch(error: FormFailure) { assertEquals(413, error.code) }
    }
    @Test fun failedReceiptReadsDoNotSuggestThatASurveyWasSent() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        for (failure in listOf(HttpException(Response.error<Any>(500, "{}".toResponseBody())), java.io.IOException("offline"))) {
            api.failure = failure
            try { repository.receipts(); fail("Expected read failure") } catch(error: FormFailure) {
                assertFalse(error.message!!.contains("استلام")); assertFalse(error.message!!.contains("المسودة"))
            }
            api.failure = null
            assertEquals(listOf("heks"), repository.modules().map { it.key })
        }
    }
    @Test fun ambiguousWriteFailuresStillRequireRetryingTheSameDraft() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        api.failure = HttpException(Response.error<Any>(500, "{}".toResponseBody()))
        val request = SubmissionRequest("same-uuid", "version", answers = JsonObject(mapOf("name" to JsonPrimitive("same"))))
        try { repository.submit("heks-beneficiary", request); fail("Expected write failure") } catch(error: FormFailure) {
            assertFalse(error.definitelyRejected); assertTrue(error.message!!.contains("نفس المسودة"))
        }
    }
    @Test fun uninitializedStorageHasAnActionableMessageWithoutExposingServerDetails() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        api.failure = HttpException(Response.error<Any>(503, """{"code":"mobile_form_storage_not_ready","message":"SQL private details","trace":"private"}""".toResponseBody()))
        try { repository.receipts(); fail("Expected readiness failure") } catch(error: FormFailure) {
            assertEquals(503, error.code); assertTrue(error.message!!.contains("مسؤول السيرفر")); assertFalse(error.message!!.contains("private"))
        }
    }
    @Test fun ignoresAuditFormsEvenWhenAnOlderServerStillPublishesThem() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        api.publishedForms = listOf(PublishedForm("borrower-survey", "damage_assessment_borrowers", "Borrower", "v"), PublishedForm("building-audit", "damage_assessment", "Audit", "v"))
        assertEquals(listOf("borrower-survey"), repository.forms().map { it.key })
    }
    @Test fun blocksAllRetiredAuditRequestsBeforeTheyReachTheServer() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        for (form in listOf("building-audit", "housing-audit", "public-building-audit", "public-building-unit-audit", "road-audit", "road-item-audit", "cso-audit", "cso-organization-audit", "cso-unit-audit")) {
            val request = SubmissionRequest("old-uuid", "v", answers = JsonObject(mapOf("name" to JsonPrimitive("test"))))
            val actions: List<suspend () -> Any> = listOf(
                { repository.schema(form, 1) }, { repository.formRecords(form, "", 1) }, { repository.submit(form, request) },
                { repository.upload(form, "old-uuid", "test.jpg", "image/jpeg", byteArrayOf()) }, { repository.discard(form, "old-uuid", 1) },
            )
            for (action in actions) {
                try { action(); fail("Retired audit form accepted") } catch(error: FormFailure) { assertEquals(404, error.code) }
            }
        }
        assertNull(api.authorization); assertNull(api.submitted)
    }
    @Test fun hidesRetiredAuditReceiptsWithoutChangingServerPagination() = runTest {
        val api = FakeApi(); val repository = PortalRepository(api, state(), MemoryStore())
        api.received = ReceiptPage(listOf(SubmissionReceipt(1, "uuid", "building-audit", "damage_assessment", 1, "v", "received", "2026-10-11")), 2, 3)
        val page = repository.receipts(2)
        assertTrue(page.data.isEmpty()); assertEquals(2, page.currentPage); assertEquals(3, page.lastPage)
    }
}

