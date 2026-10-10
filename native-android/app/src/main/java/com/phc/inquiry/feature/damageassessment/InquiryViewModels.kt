package com.phc.inquiry.feature.damageassessment

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import androidx.paging.*
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.launch
import kotlinx.coroutines.Job

class InquiryPagingSource(private val repository: InquiryRepository, private val sector: String, private val query: String, private val filters: Map<String, String> = emptyMap(), private val onTotal: (Int) -> Unit) : PagingSource<Int, InquiryRecord>() {
    override fun getRefreshKey(state: PagingState<Int, InquiryRecord>): Int? = state.anchorPosition?.let { position ->
        state.closestPageToPosition(position)?.let { page -> page.prevKey?.plus(1) ?: page.nextKey?.minus(1) }
    }
    override suspend fun load(params: LoadParams<Int>): LoadResult<Int, InquiryRecord> {
        val page = params.key ?: 1
        return try {
            val result = if (sector == "citizens") repository.citizens(query, page) else if (filters.isEmpty()) repository.search(sector, query, page) else repository.searchFiltered(sector, query, page, filters)
            onTotal(result.total)
            LoadResult.Page(result.data, if (page > 1) page - 1 else null, if (page < result.lastPage) page + 1 else null)
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (error: Exception) { LoadResult.Error(error) }
    }
}

@OptIn(ExperimentalCoroutinesApi::class)
@HiltViewModel
class SearchViewModel @Inject constructor(savedState: SavedStateHandle, private val repository: InquiryRepository) : ViewModel() {
    val sector: String = savedState["sector"] ?: "citizens"
    private val submitted = MutableStateFlow<SearchCriteria?>(null)
    private val filterState = MutableStateFlow(FilterState())
    private var filterJob: Job? = null
    val filterOptions = filterState.asStateFlow()
    private val count = MutableStateFlow<Int?>(null)
    val total = count.asStateFlow()
    val hasSearched = submitted.map { it != null }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), false)
    val records = submitted.flatMapLatest { query ->
        if (query == null) flowOf(PagingData.empty())
        else Pager(PagingConfig(pageSize = 20, initialLoadSize = 20, enablePlaceholders = false, maxSize = 100)) {
            InquiryPagingSource(repository, sector, query.query, query.filters) { count.value = it }
        }.flow
    }.cachedIn(viewModelScope)
    init { if (sector != "citizens") loadFilters() }
    fun search(query: String, filters: Map<String, String> = emptyMap()) {
        val normalized = SearchCriteria(query.trim().take(150), filters.filterValues { it.isNotBlank() })
        if (submitted.value != normalized) { count.value = null; submitted.value = normalized }
    }
    fun loadFilters(municipality: String? = null) {
        filterJob?.cancel()
        filterJob = viewModelScope.launch {
            filterState.value = FilterState(loading = true)
            try { filterState.value = FilterState(options = repository.filters(sector, municipality)) }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (error: InquiryFailure) { filterState.value = FilterState(error = error.message) }
        }
    }
}

data class SearchCriteria(val query: String, val filters: Map<String, String>)
data class FilterState(val loading: Boolean = false, val options: FilterOptions = FilterOptions(), val error: String? = null)

data class DetailState(val loading: Boolean = true, val record: InquiryRecord? = null, val error: String? = null)

@HiltViewModel
class DetailViewModel @Inject constructor(savedState: SavedStateHandle, private val repository: InquiryRepository) : ViewModel() {
    val sector: String = checkNotNull(savedState["sector"])
    private val id: Long = checkNotNull(savedState["record"])
    private val mutable = MutableStateFlow(DetailState())
    val state = mutable.asStateFlow()
    init { refresh() }
    fun refresh() {
        viewModelScope.launch {
            mutable.value = DetailState()
            try { mutable.value = DetailState(loading = false, record = repository.detail(sector, id)) }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (error: InquiryFailure) { mutable.value = DetailState(loading = false, error = error.message) }
        }
    }
}
