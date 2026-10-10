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

class InquiryPagingSource(private val repository: InquiryRepository, private val sector: String, private val query: String, private val onTotal: (Int) -> Unit) : PagingSource<Int, InquiryRecord>() {
    override fun getRefreshKey(state: PagingState<Int, InquiryRecord>): Int? = state.anchorPosition?.let { position ->
        state.closestPageToPosition(position)?.let { page -> page.prevKey?.plus(1) ?: page.nextKey?.minus(1) }
    }
    override suspend fun load(params: LoadParams<Int>): LoadResult<Int, InquiryRecord> {
        val page = params.key ?: 1
        return try {
            val result = repository.search(sector, query, page)
            onTotal(result.total)
            LoadResult.Page(result.data, if (page > 1) page - 1 else null, if (page < result.lastPage) page + 1 else null)
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (error: Exception) { LoadResult.Error(error) }
    }
}

@OptIn(ExperimentalCoroutinesApi::class)
@HiltViewModel
class SearchViewModel @Inject constructor(savedState: SavedStateHandle, private val repository: InquiryRepository) : ViewModel() {
    val sector: String = checkNotNull(savedState["sector"])
    private val submitted = MutableStateFlow<String?>(null)
    private val count = MutableStateFlow<Int?>(null)
    val total = count.asStateFlow()
    val hasSearched = submitted.map { it != null }.stateIn(viewModelScope, SharingStarted.WhileSubscribed(5000), false)
    val records = submitted.flatMapLatest { query ->
        if (query == null) flowOf(PagingData.empty())
        else Pager(PagingConfig(pageSize = 20, initialLoadSize = 20, enablePlaceholders = false, maxSize = 100)) {
            InquiryPagingSource(repository, sector, query) { count.value = it }
        }.flow
    }.cachedIn(viewModelScope)
    fun search(query: String) {
        val normalized = query.trim().take(150)
        if (submitted.value != normalized) { count.value = null; submitted.value = normalized }
    }
}

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
