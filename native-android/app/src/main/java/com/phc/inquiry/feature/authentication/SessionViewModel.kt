package com.phc.inquiry.feature.authentication

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.core.security.TransportPolicy
import com.phc.inquiry.data.*
import com.phc.inquiry.domain.LoginUseCase
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch

data class AuthenticationState(val restoring: Boolean = true, val busy: Boolean = false, val message: String? = null)

@HiltViewModel
class SessionViewModel @Inject constructor(
    private val repository: InquiryRepository,
    private val login: LoginUseCase,
    sessions: SessionState,
    val policy: TransportPolicy,
) : ViewModel() {
    val session = sessions.session
    private val mutable = MutableStateFlow(AuthenticationState())
    val state = mutable.asStateFlow()
    init {
        viewModelScope.launch { try { repository.restore() } finally { mutable.update { it.copy(restoring = false) } } }
        viewModelScope.launch { sessions.notice.collect { message -> if (message != null) mutable.update { it.copy(message = message) } } }
    }

    fun signIn(email: String, password: String) {
        if (mutable.value.busy) return
        viewModelScope.launch {
            mutable.update { it.copy(busy = true, message = null) }
            try { login(email, password) }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: InquiryFailure) { mutable.update { it.copy(message = failure.message) } }
            finally { mutable.update { it.copy(busy = false) } }
        }
    }

    fun signOut() {
        if (mutable.value.busy) return
        viewModelScope.launch {
            mutable.update { it.copy(busy = true, message = null) }
            try { repository.logout() }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (_: InquiryFailure) { mutable.update { it.copy(message = "تم حذف الجلسة من الجهاز، لكن تعذّر إلغاؤها على السيرفر. تنتهي صلاحية الرمز خلال 24 ساعة من إصداره.") } }
            finally { mutable.update { it.copy(busy = false) } }
        }
    }
}
