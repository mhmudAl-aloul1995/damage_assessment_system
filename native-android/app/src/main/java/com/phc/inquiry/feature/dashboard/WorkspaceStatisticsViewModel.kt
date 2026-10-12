package com.phc.inquiry.feature.dashboard

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.InquiryRepository
import com.phc.inquiry.data.SessionState
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Job
import kotlinx.coroutines.launch
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.distinctUntilChanged
import kotlinx.coroutines.flow.map
import kotlinx.coroutines.flow.update

data class WorkspaceCount(val sector: String, val total: Long? = null, val failed: Boolean = false)
data class WorkspaceStatisticsState(val loading: Boolean = true, val counts: List<WorkspaceCount> = emptyList(), val error: String? = null)

@HiltViewModel
class WorkspaceStatisticsViewModel @Inject constructor(private val repository: InquiryRepository, private val session: SessionState) : ViewModel() {
    private val mutable = MutableStateFlow(WorkspaceStatisticsState())
    val state = mutable.asStateFlow()
    private var job: Job? = null
    init {
        viewModelScope.launch {
            session.session.map { it?.user?.id }.distinctUntilChanged().collect { user ->
                job?.cancel()
                mutable.value = WorkspaceStatisticsState(loading = user != null)
                if (user != null) refresh()
            }
        }
    }
    fun refresh() {
        val owner = session.session.value?.user?.id ?: return
        job?.cancel()
        job = viewModelScope.launch {
            mutable.value = WorkspaceStatisticsState()
            try {
                val sectors = repository.sectors().map { it.key }.filter { it in setOf("buildings", "housing-units") }.distinct()
                if (session.session.value?.user?.id != owner) return@launch
                mutable.update { it.copy(counts = sectors.map(::WorkspaceCount)) }
                for (sector in sectors) {
                    val count = try {
                        val total = repository.statistics(sector, emptyMap()).summary["total"]
                        if (total == null || total < 0) throw InquiryFailure("تعذّرت قراءة إجمالي السجلات.")
                        WorkspaceCount(sector, total)
                    } catch (cancelled: CancellationException) { throw cancelled }
                    catch (_: Exception) { WorkspaceCount(sector, failed = true) }
                    if (session.session.value?.user?.id != owner) return@launch
                    mutable.update { it.copy(counts = it.counts.map { previous -> if (previous.sector == sector) count else previous }) }
                }
                mutable.update { it.copy(loading = false) }
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (_: Exception) {
                if (session.session.value?.user?.id == owner) mutable.value = WorkspaceStatisticsState(loading = false, error = "تعذّر تحميل ملخص الإحصائيات.")
            }
        }
    }
}
