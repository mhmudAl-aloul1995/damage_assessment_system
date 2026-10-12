package com.phc.inquiry

import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.dashboard.WorkspaceStatisticsViewModel
import kotlinx.coroutines.*
import kotlinx.coroutines.test.*
import org.junit.After
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class WorkspaceStatisticsViewModelTest {
    @Before fun setup() { Dispatchers.setMain(UnconfinedTestDispatcher()) }
    @After fun cleanup() { Dispatchers.resetMain() }
    private fun session(id: Long = 1) = SessionState().apply { set(Session("test-token", "2099-01-01T00:00:00Z", User(id, "fixture", "fixture@example.test"))) }
    private class Repository : InquiryRepository {
        var allowed = listOf(Sector("buildings", "المباني"), Sector("housing-units", "الوحدات"))
        val requests = mutableListOf<String>()
        var action: suspend (String) -> SectorStatistics = { SectorStatistics(summary = mapOf("total" to if(it == "buildings") 12L else 47L)) }
        override suspend fun sectors() = allowed
        override suspend fun statistics(sector: String, filters: Map<String, String>): SectorStatistics { assertTrue(filters.isEmpty()); requests += sector; return action(sector) }
        override suspend fun restore() {}
        override suspend fun login(email: String, password: String) {}
        override suspend fun logout() {}
        override suspend fun map(sector: String, filters: Map<String, String>) = MapPage(emptyList(), null, 0)
        override suspend fun search(sector: String, query: String, page: Int) = RecordPage(emptyList(), 0, 1, 1)
        override suspend fun detail(sector: String, id: Long) = InquiryRecord(id)
        override suspend fun searchFiltered(sector: String, query: String, page: Int, filters: Map<String, String>) = search(sector, query, page)
        override suspend fun filters(sector: String, municipality: String?) = FilterOptions()
        override suspend fun history(sector: String, id: Long, track: String, page: Int) = AuditPage(emptyList(), 0, 1, 1)
        override suspend fun attachments(sector: String, id: Long) = emptyList<RecordAttachment>()
        override suspend fun attachment(sector: String, id: Long, attachment: Long) = byteArrayOf()
        override suspend fun citizens(query: String, page: Int) = search("buildings", query, page)
    }
    @Test fun countsRemainSeparateAndNeverRequestUnauthorizedSectors() = runTest {
        val repository = Repository().apply { allowed = listOf(Sector("housing-units", "الوحدات"), Sector("road-facilities", "الطرق")) }
        val model = WorkspaceStatisticsViewModel(repository, session())
        assertEquals(listOf("housing-units"), repository.requests)
        assertEquals(47L, model.state.value.counts.single().total)
        assertFalse(model.state.value.loading)
    }
    @Test fun oneFailedSectorDoesNotBecomeZeroOrHideSuccessfulSectorAndRetryRecovers() = runTest {
        val repository = Repository().apply { action = { if(it == "housing-units") throw InquiryFailure("offline") else SectorStatistics(summary = mapOf("total" to 12L)) } }
        val model = WorkspaceStatisticsViewModel(repository, session())
        assertEquals(12L, model.state.value.counts.first().total)
        assertNull(model.state.value.counts.last().total)
        assertTrue(model.state.value.counts.last().failed)
        repository.action = { SectorStatistics(summary = mapOf("total" to 0L)) }
        model.refresh()
        assertTrue(model.state.value.counts.all { it.total == 0L && !it.failed })
    }
    @Test fun logoutClearsOldTotalsAndCancelsPendingRequests() = runTest {
        val pending = CompletableDeferred<SectorStatistics>()
        var cancelled = false
        val repository = Repository().apply { action = { try { pending.await() } catch (error: CancellationException) { cancelled = true; throw error } } }
        val state = session()
        val model = WorkspaceStatisticsViewModel(repository, state)
        assertTrue(model.state.value.loading)
        state.set(null)
        pending.complete(SectorStatistics(summary = mapOf("total" to 999L)))
        assertTrue(cancelled)
        assertTrue(model.state.value.counts.isEmpty())
        assertFalse(model.state.value.loading)
        model.refresh()
        assertEquals(listOf("buildings"), repository.requests)
    }
    @Test fun missingOrNegativeTotalsRemainUnavailable() = runTest {
        val repository = Repository().apply { action = { if(it == "buildings") SectorStatistics() else SectorStatistics(summary = mapOf("total" to -1L)) } }
        val model = WorkspaceStatisticsViewModel(repository, session())
        assertTrue(model.state.value.counts.all { it.total == null && it.failed })
    }
}
