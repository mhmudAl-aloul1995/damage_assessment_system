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
        var onModules: () -> Unit = {}
        var body: ResponseBody = "fixture".toResponseBody()
        private fun check(auth: String) { authorization = auth; failure?.let { throw it } }
        override suspend fun modules(authorization: String): DataResponse<List<PortalModule>> { check(authorization); onModules(); return DataResponse(listOf(PortalModule("heks", "HEKS"), PortalModule("unexpected", "hidden"))) }
        override suspend fun forms(authorization: String): DataResponse<List<PublishedForm>> { check(authorization); return DataResponse(emptyList()) }
        override suspend fun records(authorization: String, module: String, search: String, page: Int) = PortalPage(emptyList())
        override suspend fun record(authorization: String, module: String, id: Long) = DataResponse(PortalRecord(id, module))
        override suspend fun schema(authorization: String, form: String, id: Long?) = DataResponse(FormSchema(form, "heks", "Test", "v", listOf(FormSection("s", emptyList())), false))
        override suspend fun formRecords(authorization: String, form: String, search: String, page: Int) = PortalPage(emptyList())
        override suspend fun submit(authorization: String, form: String, request: SubmissionRequest): SubmissionResponse {
            check(authorization); submitted = request
            return SubmissionResponse(SubmissionReceipt(1, request.uuid, form, "heks", 1, "r", "received", "2026-10-11"), false)
        }
        override suspend fun upload(authorization: String, form: String, uuid: RequestBody, file: MultipartBody.Part) = DataResponse(FormFile(1, "test.jpg", "image/jpeg", 5))
        override suspend fun receipts(authorization: String, page: Int) = ReceiptPage(emptyList())
        override suspend fun receipt(authorization: String, uuid: String) = DataResponse(SubmissionReceipt(1, uuid, "heks-beneficiary", "heks", 1, "r", "received", "2026-10-11"))
        override suspend fun attachment(authorization: String, id: Long): ResponseBody { check(authorization); return body }
        override suspend fun discard(authorization: String, form: String, id: Long, request: DiscardFileRequest): JsonObject { check(authorization); return JsonObject(mapOf("discarded" to JsonPrimitive(true))) }
    }
    private fun state() = SessionState().apply { set(Session("fixture-owner-token", "2099-01-01T00:00:00Z", User(1, "fixture", "fixture@example.test"))) }
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
}

