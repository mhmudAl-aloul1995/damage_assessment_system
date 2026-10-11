package com.phc.inquiry

import androidx.lifecycle.SavedStateHandle
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.damageassessment.*
import kotlinx.coroutines.*
import kotlinx.coroutines.test.*
import kotlinx.coroutines.flow.first
import kotlinx.serialization.json.*
import org.junit.After
import org.junit.Assert.*
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class ExploreViewModelTest {
    @Before fun setup() { Dispatchers.setMain(UnconfinedTestDispatcher()) }
    @After fun cleanup() { Dispatchers.resetMain() }
    private fun feature(id: Long) = MapFeature(Json.parseToJsonElement("""{"rings":[[[34.4,31.5],[34.41,31.5],[34.41,31.51],[34.4,31.5]]],"spatialReference":{"wkid":4326}}""").jsonObject, InquiryRecord(id))
    private class FakeRepository : InquiryRepository {
        var mapCalls = mutableListOf<Pair<String, Map<String, String>>>()
        var statsCalls = mutableListOf<Pair<String, Map<String, String>>>()
        var mapAction: suspend (String, Map<String, String>) -> MapPage = { _, filters -> MapPage(emptyList(), (filters["after_id"]?.toLong() ?: 0) + 500, 500) }
        override suspend fun sectors() = listOf(Sector("buildings", "المباني"), Sector("housing-units", "الوحدات"))
        override suspend fun map(sector: String, filters: Map<String, String>): MapPage { mapCalls += sector to filters; return mapAction(sector, filters) }
        override suspend fun statistics(sector: String, filters: Map<String, String>): SectorStatistics { statsCalls += sector to filters; return SectorStatistics(summary = mapOf("total" to 2)) }
        override suspend fun filters(sector: String, municipality: String?) = FilterOptions(municipalities = listOf("غزة"))
        override suspend fun restore() {}
        override suspend fun login(email: String, password: String) {}
        override suspend fun logout() {}
        override suspend fun search(sector: String, query: String, page: Int) = RecordPage(emptyList(), 0, 1, 1)
        override suspend fun detail(sector: String, id: Long) = InquiryRecord(id)
        override suspend fun searchFiltered(sector: String, query: String, page: Int, filters: Map<String, String>) = search(sector, query, page)
        override suspend fun history(sector: String, id: Long, track: String, page: Int) = AuditPage(emptyList(), 0, 1, 1)
        override suspend fun attachments(sector: String, id: Long) = emptyList<RecordAttachment>()
        override suspend fun attachment(sector: String, id: Long, attachment: Long) = byteArrayOf()
        override suspend fun citizens(query: String, page: Int) = search("buildings", query, page)
    }
    @Test fun mapContinuationPreservesFiltersAndSearchAndEmptyPagesRemainLoadable() = runTest {
        val repository = FakeRepository()
        val model = ExploreViewModel(SavedStateHandle(mapOf("mode" to "map")), repository)
        model.search(" الأمل ", mapOf("municipality" to "غزة", "token" to "must-drop"))
        model.state.first { !it.mapLoading }
        model.loadMap()
        model.state.first { !it.mapLoading }
        val request = repository.mapCalls.last().second
        assertEquals("الأمل", request["search"]); assertEquals("غزة", request["municipality"])
        assertEquals("polygon", request["geometry"]); assertFalse(repository.statsCalls.last().second.containsKey("geometry"))
        assertEquals("500", request["after_id"]); assertFalse(request.containsKey("token"))
        assertEquals("2", model.state.value.statistics?.summary?.get("total").toString())
    }
    @Test fun areaSearchAffectsOnlyMapAndFullStatisticsKeepOriginalScope() = runTest {
        val repository = FakeRepository()
        val model = ExploreViewModel(SavedStateHandle(), repository)
        model.search("الأمل", mapOf("damage_status" to "fully_damaged"))
        val statsRequests = repository.statsCalls.size
        model.searchArea(MapBounds(34.0, 31.0, 35.0, 32.0))
        assertEquals("34.0", repository.mapCalls.last().second["west"])
        assertEquals(statsRequests, repository.statsCalls.size); assertTrue(model.state.value.areaOnly)
        model.loadStatistics()
        assertFalse(repository.statsCalls.last().second.containsKey("west"))
        assertEquals("الأمل", repository.statsCalls.last().second["search"])
        model.state.first { !it.mapLoading }
    }
    @Test fun changingSectorCancelsOldMapRequestAndClearsOldFiltersAndRecords() = runTest {
        val repository = FakeRepository()
        val pending = CompletableDeferred<MapPage>()
        var cancelled = false
        repository.mapAction = { sector, _ -> if (sector == "buildings") { try { pending.await() } catch (error: CancellationException) { cancelled = true; throw error } } else MapPage(listOf(feature(9)), null, 1) }
        val model = ExploreViewModel(SavedStateHandle(), repository)
        model.selectSector("housing-units")
        model.state.first { !it.mapLoading }
        pending.complete(MapPage(listOf(feature(1)), null, 1))
        assertTrue(cancelled); assertEquals("housing-units", model.state.value.sector)
        assertEquals(listOf(9L), model.state.value.map.features.map { it.attributes.recordId })
        assertTrue(model.state.value.criteria.filters.isEmpty())
        model.selectSector("forbidden")
        assertEquals("housing-units", model.state.value.sector)
    }
    @Test fun errorsAreRecoverableWithoutRepeatedAutomaticRequests() = runTest {
        val repository = FakeRepository()
        repository.mapAction = { _, _ -> throw InquiryFailure("offline") }
        val model = ExploreViewModel(SavedStateHandle(), repository)
        assertEquals("offline", model.state.value.mapError); assertFalse(model.state.value.mapLoading)
        assertEquals(1, repository.mapCalls.size)
        repository.mapAction = { _, _ -> MapPage(listOf(feature(7)), null, 1) }
        model.loadMap(reset = true)
        model.state.first { !it.mapLoading }
        assertNull(model.state.value.mapError); assertEquals(7L, model.state.value.map.features.single().attributes.recordId)
    }
}
