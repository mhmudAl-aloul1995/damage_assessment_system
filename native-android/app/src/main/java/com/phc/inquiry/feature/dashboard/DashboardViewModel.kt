package com.phc.inquiry.feature.dashboard

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch

data class DashboardState(val loading: Boolean = true, val sectors: List<Sector> = emptyList(), val error: String? = null)

@HiltViewModel
class DashboardViewModel @Inject constructor(private val repository: InquiryRepository) : ViewModel() {
    private val mutable = MutableStateFlow(DashboardState())
    val state = mutable.asStateFlow()
    init { refresh() }
    fun refresh() {
        viewModelScope.launch {
            mutable.value = DashboardState()
            try { mutable.value = DashboardState(loading = false, sectors = repository.sectors()) }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: InquiryFailure) { mutable.value = DashboardState(loading = false, error = failure.message) }
        }
    }
}
