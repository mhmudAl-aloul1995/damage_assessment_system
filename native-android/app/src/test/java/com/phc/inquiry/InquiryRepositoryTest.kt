package com.phc.inquiry

import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.*
import com.phc.inquiry.domain.LoginUseCase
import com.phc.inquiry.feature.damageassessment.InquiryPagingSource
import androidx.paging.PagingSource
import kotlinx.coroutines.test.runTest
import kotlinx.serialization.json.Json
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
        var requestedPage = 0
        val user = User(1, "Test account", "demo@example.test")
        override suspend fun login(request: LoginRequest): LoginResponse { loginCalls++; return LoginResponse("test-token", "2099-01-01T00:00:00Z", user) }
        override suspend fun logout() { if (denied) throw java.io.IOException("offline") }
        override suspend fun me(): DataResponse<User> = DataResponse(user)
        override suspend fun sectors() = DataResponse(listOf(Sector("buildings", "Buildings")))
        override suspend fun search(sector: String, search: String, page: Int): RecordPage {
            if (denied) throw HttpException(Response.error<Any>(401, "{}".toResponseBody()))
            requestedPage = page
            return RecordPage(listOf(InquiryRecord(10)), 21, page, 2)
        }
        override suspend fun detail(sector: String, record: Long) = DataResponse(InquiryRecord(record))
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
}
