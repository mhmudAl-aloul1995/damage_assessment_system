package com.phc.inquiry

import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.*
import com.phc.inquiry.domain.LoginUseCase
import com.phc.inquiry.feature.damageassessment.InquiryPagingSource
import androidx.paging.PagingSource
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.jsonObject
import kotlinx.serialization.json.jsonPrimitive
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.*
import org.junit.Test
import retrofit2.HttpException
import retrofit2.Response

class InquiryRepositoryTest {
    private class MemoryStore : SessionStore {
        var value: Session? = null
        override fun read() = value
        override fun save(session: Session) { value = session }
        override fun clear() { value = null }
    }
    private class FakeApi : InquiryApi {
        var denied = false
        var loginCalls = 0
        var loginFailure: HttpException? = null
        var loginBody: String? = null
        var requestedFilters: Map<String, String> = emptyMap()
        var attachmentBody: okhttp3.ResponseBody = "fixture".toResponseBody()
        var requestedPage = 0
        val user = User(1, "Test account", "demo@example.test")
        override suspend fun login(request: LoginRequest): LoginResponse { loginFailure?.let { throw it }; loginCalls++; loginBody = Json.encodeToString(request); return LoginResponse("test-token", "2099-01-01T00:00:00Z", user) }
        override suspend fun logout() { if (denied) throw java.io.IOException("offline") }
        override suspend fun me(): DataResponse<User> = DataResponse(user)
        override suspend fun sectors() = DataResponse(listOf(Sector("buildings", "Buildings")))
        override suspend fun search(sector: String, search: String, page: Int): RecordPage {
            if (denied) throw HttpException(Response.error<Any>(401, "{}".toResponseBody()))
            requestedPage = page
            return RecordPage(listOf(InquiryRecord(10)), 21, page, 2)
        }
        override suspend fun detail(sector: String, record: Long) = DataResponse(InquiryRecord(record))
        override suspend fun advancedSearch(sector: String, search: String, page: Int, filters: Map<String, String>): RecordPage { requestedFilters = filters; return search(sector, search, page) }
        override suspend fun filters(sector: String, municipality: String?) = DataResponse(FilterOptions())
        override suspend fun history(sector: String, record: Long, track: String, page: Int) = AuditPage(emptyList(), 0, page, 1)
        override suspend fun attachments(sector: String, record: Long) = DataResponse(emptyList<RecordAttachment>())
        override suspend fun attachment(sector: String, record: Long, attachment: Long) = attachmentBody
        override suspend fun citizens(search: String, page: Int) = search("housing-units", search, page)
    }
    private fun repository(api: FakeApi, store: MemoryStore, state: SessionState) = LaravelInquiryRepository(api, store, state, TransportPolicy("https://example.test/".toHttpUrl(), false))

    @Test fun loginStoresSessionAndUnauthorizedClearsIt() = runTest {
        val api = FakeApi(); val store = MemoryStore(); val state = SessionState()
        val repository = repository(api, store, state)
        repository.login("demo@example.test", "test-password")
        assertEquals("test-token", store.read()?.token)
        api.denied = true
        try { repository.search("buildings", "", 1); fail("401 must fail") } catch (_: InquiryFailure) {}
        assertNull(store.read()); assertNull(state.session.value)
        assertNotNull(state.notice.value)
    }

    @Test fun loginSerializesAllRequiredLaravelFieldsWithDefaultEncoding() = runTest {
        val api = FakeApi()
        repository(api, MemoryStore(), SessionState()).login("demo@example.test", "fixture-password")
        val body = Json.parseToJsonElement(requireNotNull(api.loginBody)).jsonObject
        assertEquals(setOf("email", "password", "device_name"), body.keys)
        assertEquals("PHC Native Android", body.getValue("device_name").jsonPrimitive.content)
        assertEquals("demo@example.test", body.getValue("email").jsonPrimitive.content)
        assertEquals("fixture-password", body.getValue("password").jsonPrimitive.content)
    }

    @Test fun logoutClearsLocalSessionEvenWhenOffline() = runTest {
        val api = FakeApi(); val store = MemoryStore(); val state = SessionState()
        val repository = repository(api, store, state)
        repository.login("demo@example.test", "test-password")
        api.denied = true
        try { repository.logout() } catch (_: InquiryFailure) {}
        assertNull(store.read()); assertNull(state.session.value)
    }

    @Test fun invalidLoginDoesNotCallNetwork() = runTest {
        val api = FakeApi()
        try { LoginUseCase(repository(api, MemoryStore(), SessionState()))("invalid", ""); fail("Must validate") } catch (_: InquiryFailure) {}
        assertEquals(0, api.loginCalls)
    }

    @Test fun enabledHttpAcceptsAccountWithoutAnEmailAllowlist() = runTest {
        val api = FakeApi()
        val repository = LaravelInquiryRepository(api, MemoryStore(), SessionState(), TransportPolicy("http://213.6.135.115/damage_assessment_system/".toHttpUrl(), true))
        repository.login("account@example.test", "fixture-password")
        assertEquals(1, api.loginCalls)
    }

    @Test fun expiredStoredSessionIsDiscarded() = runTest {
        val api = FakeApi(); val store = MemoryStore(); val state = SessionState()
        store.save(Session("expired", "2000-01-01T00:00:00Z", api.user))
        repository(api, store, state).restore()
        assertNull(store.read()); assertNull(state.session.value)
    }

    @Test fun pagingUsesServerPagesAndKeepsInternalRecordId() = runTest {
        val api = FakeApi(); var total = 0
        val source = InquiryPagingSource(repository(api, MemoryStore(), SessionState()), "housing-units", "test") { total = it }
        val result = source.load(PagingSource.LoadParams.Refresh(null, 20, false)) as PagingSource.LoadResult.Page<Int, InquiryRecord>
        assertEquals(1, api.requestedPage); assertEquals(21, total); assertEquals(2, result.nextKey); assertEquals(10L, result.data.single().recordId)
    }

    @Test fun actualLaravelContractAcceptsNumericOrStringObjectIdsAndNulls() {
        val json = Json { ignoreUnknownKeys = true }
        for (objectId in listOf("123", "\"123\"", "null")) {
            val record = json.decodeFromString<InquiryRecord>("""{"record_id":17,"objectid":$objectId,"name":null,"field_completed":false,"geometry":null}""")
            assertEquals(17L, record.recordId)
            assertFalse(record.displayName.isBlank())
        }
    }

    @Test fun advancedPagingKeepsFiltersAcrossPages() = runTest {
        val api = FakeApi()
        val filters = mapOf("municipality" to "غزة", "damage_status" to "fully_damaged")
        val source = InquiryPagingSource(repository(api, MemoryStore(), SessionState()), "buildings", "", filters) {}
        source.load(PagingSource.LoadParams.Append(2, 20, false))
        assertEquals(2, api.requestedPage); assertEquals(filters, api.requestedFilters)
    }

    @Test fun attachmentReaderRejectsOversizedUnknownLengthStreams() = runTest {
        val api = FakeApi()
        val stream = okio.Buffer().write(ByteArray(15 * 1024 * 1024 + 1))
        api.attachmentBody = object : okhttp3.ResponseBody() {
            override fun contentType(): okhttp3.MediaType? = null
            override fun contentLength(): Long = -1L
            override fun source(): okio.BufferedSource = stream
        }
        try { repository(api, MemoryStore(), SessionState()).attachment("buildings", 1, 9); fail("Must reject oversized file") }
        catch (_: InquiryFailure) {}
    }

    @Test fun phaseTwoDetailContractRetainsCapabilityDenialsAndNullableValues() {
        val record = Json.decodeFromString<InquiryRecord>("""{"record_id":17,"sector":"housing-units","details":[{"key":"floor_number","label":"الطابق","value":null}],"capabilities":{"audit_history":false,"attachments":false,"full_details":true}}""")
        assertEquals("housing-units", record.sector); assertNull(record.details.single().value)
        assertFalse(record.capabilities.attachments); assertFalse(record.capabilities.auditHistory)
        assertTrue(record.capabilities.fullDetails)
    }

    @Test fun validationErrorsAreShownWithoutLeakingRawServerResponse() = runTest {
        val api = FakeApi()
        api.loginFailure = HttpException(Response.error<Any>(422, """{"errors":{"email":["fixture validation"]},"trace":"private-trace"}""".toResponseBody()))
        try { repository(api, MemoryStore(), SessionState()).login("demo@example.test", "fixture-password"); fail("422 must fail") }
        catch (error: InquiryFailure) { assertEquals("fixture validation", error.message) }
    }
}
