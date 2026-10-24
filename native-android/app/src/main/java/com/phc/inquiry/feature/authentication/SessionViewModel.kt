package com.phc.inquiry.feature.authentication

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.core.security.TransportPolicy
import com.phc.inquiry.core.security.OfflineAccessController
import com.phc.inquiry.core.security.OfflineAuthRequest
import com.phc.inquiry.core.security.offlineAccessMessage
import com.phc.inquiry.data.*
import com.phc.inquiry.domain.LoginUseCase
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

data class AuthenticationState(val restoring: Boolean = true, val busy: Boolean = false, val message: String? = null,
    val offlineAvailable: Boolean = false, val offlineRequest: OfflineAuthRequest? = null)

@HiltViewModel
class SessionViewModel @Inject constructor(
    private val repository: InquiryRepository,
    private val login: LoginUseCase,
    private val sessions: SessionState,
    val policy: TransportPolicy,
    private val attendance: AttendanceRepository,
    private val offline: OfflineAccessController,
) : ViewModel() {
    val session = sessions.session
    val access = sessions.access
    private val mutable = MutableStateFlow(AuthenticationState())
    val state = mutable.asStateFlow()
    init {
        viewModelScope.launch {
            try { repository.restore() }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (_: Exception) { mutable.update { it.copy(message = "تعذّرت استعادة الجلسة. سجّل الدخول أو استخدم الفتح المحلي المفعّل سابقًا.") } }
            finally { refreshOfflineAvailability(); mutable.update { it.copy(restoring = false) } }
        }
        viewModelScope.launch { sessions.notice.collect { message -> if (message != null) mutable.update { it.copy(message = message) } } }
    }
    private suspend fun refreshOfflineAvailability() {
        val available = withContext(Dispatchers.IO) { runCatching { offline.available() }.getOrDefault(false) }
        mutable.update { it.copy(offlineAvailable = available) }
    }
    fun beginOfflineUnlock() = beginOffline(false)
    fun enableOffline() = beginOffline(true)
    private fun beginOffline(enrolling: Boolean) {
        if (mutable.value.busy) return
        mutable.update { it.copy(busy = true, message = null) }
        viewModelScope.launch {
            try {
                val request = withContext(Dispatchers.IO) { offline.prepare(enrolling) }
                mutable.update { it.copy(offlineRequest = request) }
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (error: Exception) { mutable.update { it.copy(busy = false, message = offlineAccessMessage(error, completing = false)) } }
        }
    }
    fun completeOffline(requestId: String) {
        if (mutable.value.offlineRequest?.id != requestId) return
        mutable.update { it.copy(offlineRequest = null) }
        viewModelScope.launch {
            try {
                val enrolled = withContext(Dispatchers.IO) { offline.complete(requestId) }
                mutable.update { it.copy(message = if (enrolled) "تم تفعيل الفتح المحلي. يمكنك فتح النماذج والمسودات بقفل الجهاز حتى بعد انتهاء جلسة السيرفر." else null) }
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (error: Exception) { mutable.update { it.copy(message = offlineAccessMessage(error, completing = true)) } }
            finally { refreshOfflineAvailability(); mutable.update { it.copy(busy = false) } }
        }
    }
    fun cancelOffline(requestId: String, message: String = "أُلغي التوثيق. لم يُفتح الحساب المحلي.") {
        if (mutable.value.offlineRequest?.id != requestId) return
        offline.cancel()
        mutable.update { it.copy(offlineRequest = null, busy = false, message = message) }
    }
    fun lockOffline() { offline.cancel(); sessions.set(null); mutable.update { it.copy(message = null) } }
    fun forgetOffline() {
        if (mutable.value.busy) return
        mutable.update { it.copy(busy = true) }
        viewModelScope.launch {
            try { withContext(Dispatchers.IO) { offline.forget() }; mutable.update { it.copy(message = "أُلغي الفتح المحلي. المسودات محفوظة للحساب نفسه.") } }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (_: Exception) { mutable.update { it.copy(message = "تعذّر إلغاء الفتح المحلي. أعد المحاولة.") } }
            finally { refreshOfflineAvailability(); mutable.update { it.copy(busy = false) } }
        }
    }

    fun signIn(email: String, password: String) {
        if (mutable.value.busy) return
        viewModelScope.launch {
            mutable.update { it.copy(busy = true, message = null) }
            try { login(email, password) }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: InquiryFailure) { mutable.update { it.copy(message = failure.message) } }
            finally { refreshOfflineAvailability(); mutable.update { it.copy(busy = false) } }
        }
    }

    fun signOut() {
        if (mutable.value.busy) return
        viewModelScope.launch {
            mutable.update { it.copy(busy = true, message = null) }
            try {
                if (sessions.access.value?.localOnly == true) { repository.logout(); return@launch }
                try { attendance.logout() }
                catch (cancelled: CancellationException) { throw cancelled }
                catch (_: Exception) { attendance.clear() }
                repository.logout()
            }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (_: InquiryFailure) { mutable.update { it.copy(message = "تم حذف الجلسة من الجهاز، لكن تعذّر إلغاؤها على السيرفر. تنتهي صلاحية الرمز خلال 24 ساعة من إصداره.") } }
            finally { refreshOfflineAvailability(); mutable.update { it.copy(busy = false) } }
        }
    }
}
