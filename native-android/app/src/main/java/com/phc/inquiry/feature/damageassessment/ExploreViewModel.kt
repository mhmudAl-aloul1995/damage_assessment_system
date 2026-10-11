package com.phc.inquiry.feature.damageassessment

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.*

data class ExploreState(
    val sectors: List<Sector> = emptyList(), val sector: String = "", val catalogLoading: Boolean = true, val catalogError: String? = null,
    val filters: FilterState = FilterState(), val criteria: SearchCriteria = SearchCriteria("", emptyMap()),
    val map: MapCollection = MapCollection(), val mapLoading: Boolean = false, val mapError: String? = null,
    val statistics: SectorStatistics? = null, val statisticsLoading: Boolean = false, val statisticsError: String? = null,
    val focusRevision: Int = 0, val areaOnly: Boolean = false,
)

@HiltViewModel
class ExploreViewModel @Inject constructor(saved: SavedStateHandle, private val repository: InquiryRepository) : ViewModel() {
    val initialMode: String = saved["mode"] ?: "map"
    private val mutable = MutableStateFlow(ExploreState())
    val state = mutable.asStateFlow()
    private var catalogJob: Job? = null
    private var mapJob: Job? = null
    private var statsJob: Job? = null
    private var filtersJob: Job? = null
    private var mapFilters: Map<String, String> = emptyMap()
    init { catalog() }
    fun catalog() {
        catalogJob?.cancel()
        catalogJob = viewModelScope.launch {
            mutable.update { it.copy(catalogLoading = true, catalogError = null) }
            try {
                val sectors = repository.sectors()
                mutable.update { it.copy(sectors = sectors, catalogLoading = false) }
                sectors.firstOrNull()?.let { selectSector(it.key) }
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (error: InquiryFailure) { mutable.update { it.copy(catalogLoading = false, catalogError = error.message) } }
        }
    }
    fun selectSector(sector: String) {
        if (mutable.value.sectors.none { it.key == sector }) return
        mapJob?.cancel(); statsJob?.cancel(); filtersJob?.cancel()
        mutable.value = ExploreState(sectors = mutable.value.sectors, sector = sector, catalogLoading = false, focusRevision = mutable.value.focusRevision + 1)
        loadFilters(null)
        search("", emptyMap())
    }
    fun search(query: String, filters: Map<String, String>) {
        if (mutable.value.sector.isBlank()) return
        val safeFilters = filters.filter { it.key in setOf("municipality", "neighborhood", "damage_status", "audit_status", "field_completion") && it.value.isNotBlank() }
        mutable.update { it.copy(criteria = SearchCriteria(query.trim().take(150), safeFilters), areaOnly = false) }
        mapFilters = safeFilters + ("search" to mutable.value.criteria.query)
        loadMap(reset = true)
        loadStatistics()
    }
    fun searchArea(bounds: MapBounds) {
        val extent = bounds.filters() ?: return
        mapFilters = mutable.value.criteria.filters + ("search" to mutable.value.criteria.query) + extent
        mutable.update { it.copy(areaOnly = true) }
        loadMap(reset = true, focus = false)
    }
    fun loadMap(reset: Boolean = false, focus: Boolean = reset) {
        if (mutable.value.sector.isBlank() || (!reset && (mutable.value.mapLoading || mutable.value.map.cursor == null))) return
        mapJob?.cancel()
        val sector = mutable.value.sector
        val previous = if (reset) MapCollection() else mutable.value.map
        val after = if (reset) 0L else previous.cursor ?: return
        val filters = mapFilters + ("after_id" to after.toString())
        mapJob = viewModelScope.launch {
            mutable.update { it.copy(map = previous, mapLoading = true, mapError = null) }
            try {
                val page = repository.map(sector, filters)
                val batch = withContext(Dispatchers.Default) { previous.append(page, after) }
                mutable.update { it.copy(map = batch, mapLoading = false, focusRevision = if (focus) it.focusRevision + 1 else it.focusRevision) }
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (error: InquiryFailure) { mutable.update { it.copy(mapLoading = false, mapError = error.message) } }
        }
    }
    fun loadStatistics() {
        if (mutable.value.sector.isBlank()) return
        statsJob?.cancel()
        val sector = mutable.value.sector
        val filters = mutable.value.criteria.filters + ("search" to mutable.value.criteria.query)
        statsJob = viewModelScope.launch {
            mutable.update { it.copy(statistics = null, statisticsLoading = true, statisticsError = null) }
            try { val stats = repository.statistics(sector, filters); mutable.update { it.copy(statistics = stats, statisticsLoading = false) } }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (error: InquiryFailure) { mutable.update { it.copy(statisticsLoading = false, statisticsError = error.message) } }
        }
    }
    fun loadFilters(municipality: String?) {
        filtersJob?.cancel()
        val sector = mutable.value.sector
        filtersJob = viewModelScope.launch {
            mutable.update { it.copy(filters = FilterState(loading = true)) }
            try { val options = repository.filters(sector, municipality); mutable.update { it.copy(filters = FilterState(options = options)) } }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (error: InquiryFailure) { mutable.update { it.copy(filters = FilterState(error = error.message)) } }
        }
    }
}
