package com.phc.inquiry.feature.attendance

import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.*
import dagger.hilt.android.lifecycle.HiltViewModel
import java.time.YearMonth
import javax.inject.Inject
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.flow.update
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext

data class AttendanceState(
    val configured: Boolean = false, val signedIn: Boolean = false, val busy: Boolean = false,
    val email: String = "", val name: String = "", val employee: HrEmployee? = null,
    val today: HrAttendanceDay? = null, val history: List<HrAttendanceDay> = emptyList(), val devices: List<HrDevice> = emptyList(),
    val month: String = YearMonth.now().toString(), val currentDevice: HrDevice? = null,
    val selectedShift: Long? = null, val signOperation: AttendanceSignOperation? = null, val retryPending: Boolean = false,
    val message: String? = null,
    val lastReceipt: HrReceipt? = null,
)

@HiltViewModel
class AttendanceViewModel @Inject constructor(private val repository: AttendanceRepository, private val keys: AttendanceSigningKey) : ViewModel() {
    private val mutable = MutableStateFlow(AttendanceState(configured = repository.policy.configured))
    val state = mutable.asStateFlow()
    private var userId = 0L
    private var registration: Pair<HrChallenge, AttendanceKey>? = null
    private var attendance: Pair<HrChallenge, String>? = null
    private var retry: Pair<String, SignedHrAttendance>? = null

    fun bindUser(id: Long, email: String) {
        if (userId == id) return
        userId = id
        mutable.update { it.copy(email = email) }
        if (!repository.policy.configured) return
        run {
            val session = repository.restore(id)
            mutable.update { it.copy(signedIn = session != null, name = session?.employee?.name.orEmpty()) }
            if (session != null) load()
        }
    }

    fun login(password: String) = run {
        val session = repository.login(userId, mutable.value.email, password)
        mutable.update { it.copy(signedIn = true, name = session.employee.name) }
        load()
    }
    fun refresh() = run { load() }
    fun month(value: String) = run {
        val month = runCatching { YearMonth.parse(value).toString() }.getOrElse { throw InquiryFailure("اختر شهراً بصيغة YYYY-MM.") }
        val history = repository.history(month)
        mutable.update { it.copy(month = month, history = history) }
    }
    fun selectShift(id: Long) { mutable.update { it.copy(selectedShift = id) } }

    fun enroll() = run {
        val session = repository.session() ?: throw InquiryFailure("سجّل الدخول إلى الحضور.")
        val challenge = repository.registrationChallenge()
        val payload = AttendanceProtocol.verify(challenge, session.employee.id, "device_registration")
        val key = withContext(Dispatchers.IO) {
            val existing = keys.current(session.employee.id, session.origin)
            val device = existing?.let { local -> mutable.value.devices.firstOrNull { it.uuid == local.uuid } }
            if (device?.status in setOf("pending", "approved")) throw InquiryFailure("الجهاز مسجل؛ انتظر مراجعة HR أو حدّث حالته.")
            if (existing != null && device == null) existing else keys.generate(session.employee.id, session.origin, AttendanceProtocol.nonce(payload))
        }
        registration = challenge to key
        mutable.update { it.copy(signOperation = AttendanceSignOperation(key.alias, payload, "طلب تسجيل الجهاز")) }
    }

    fun prepare(action: String) = run {
        if (action !in setOf("check_in", "check_out")) throw InquiryFailure("عملية غير صالحة.")
        val session = repository.session() ?: throw InquiryFailure("سجّل الدخول إلى الحضور.")
        if (mutable.value.employee?.captureEnabled != true) throw InquiryFailure(mutable.value.employee?.blockers?.firstOrNull() ?: "التسجيل غير متاح حاليًا؛ حدّث الحالة.")
        val device = mutable.value.currentDevice?.takeIf { it.status == "approved" } ?: throw InquiryFailure("يلزم اعتماد الجهاز لدى HR.")
        val key = keys.current(session.employee.id, session.origin) ?: throw InquiryFailure("مفتاح الجهاز غير متاح؛ اطلب الاستبدال لدى HR.")
        val option = mutable.value.today?.captureState?.options?.firstOrNull {
            it.action == action && (action == "check_out" || it.assignmentId == mutable.value.selectedShift)
        } ?: throw InquiryFailure("لا توجد وردية مؤهلة؛ حدّث الحالة أو اطلب تصحيح الحضور لدى HR.")
        val challenge = repository.challenge(HrChallengeRequest(device.credentialId, action, option.assignmentId))
        val bytes = AttendanceProtocol.verify(challenge, session.employee.id, "attendance", device.credentialId, action, assignment = option.assignmentId, workDate = option.workDate)
        attendance = challenge to action
        retry = null
        mutable.update { it.copy(signOperation = AttendanceSignOperation(key.alias, bytes, if (action == "check_in") "الحضور" else "الانصراف"), retryPending = false) }
    }

    fun signed(signature: String) {
        mutable.update { it.copy(signOperation = null) }
        run {
            val session = repository.session() ?: throw InquiryFailure("انتهت جلسة الحضور.")
            if (session.employee.userId != userId) throw InquiryFailure("تغيّر الحساب أثناء التوثيق.")
            val enrollment = registration
            registration = null
            if (enrollment != null) {
                val (challenge, key) = enrollment
                repository.register(RegisterHrDevice(challenge.id, key.uuid, "جهاز PHC Android", key.publicKey, signature, key.certificates))
                load()
                mutable.update { it.copy(message = "أُرسل طلب الجهاز إلى HR؛ التسجيل يتطلب اعتماده.") }
            } else {
                val (challenge, action) = attendance ?: throw InquiryFailure("لا يوجد تحدٍ معلق.")
                attendance = null
                retry = action to SignedHrAttendance(challenge.id, signature)
                confirmRetry()
            }
        }
    }

    fun signingFailed(message: String) {
        registration = null; attendance = null
        mutable.update { it.copy(signOperation = null, busy = false, message = message) }
    }
    fun retryConfirmation() = run { confirmRetry() }
    private suspend fun confirmRetry() {
        val (action, signed) = retry ?: throw InquiryFailure("لا يوجد طلب معلق للتأكيد.")
        mutable.update { it.copy(retryPending = true) }
        val receipt = repository.submit(action, signed)
        retry = null
        mutable.update { it.copy(retryPending = false, lastReceipt = receipt, message = "أكد الخادم " + (if (receipt.action == "check_in") "الحضور" else "الانصراف") + " بتاريخ " + receipt.workDate) }
        load()
    }
    fun revoke(credentialId: Long, reason: String) = run {
        if (reason.trim().length < 5) throw InquiryFailure("أدخل سبباً واضحاً لإلغاء الجهاز.")
        val device = mutable.value.devices.firstOrNull { it.credentialId == credentialId && it.status in setOf("pending", "approved") } ?: throw InquiryFailure("الجهاز غير قابل للإلغاء.")
        repository.revoke(device.credentialId, reason.trim())
        retry = null; attendance = null
        load()
        mutable.update { it.copy(retryPending = false, message = "أُلغي الجهاز؛ اعتماد البديل يتطلب مراجعة HR.") }
    }
    fun logout() = run {
        try { repository.logout() } finally {
            retry = null; attendance = null; registration = null
            mutable.update { AttendanceState(configured = it.configured, email = it.email) }
        }
    }
    private suspend fun load() {
        val session = repository.session() ?: throw InquiryFailure("سجّل الدخول إلى الحضور.")
        val employee = repository.me()
        val today = repository.today()
        val devices = repository.devices()
        val history = repository.history(mutable.value.month)
        val key = keys.current(session.employee.id, session.origin)
        val current = devices.firstOrNull { it.uuid == key?.uuid }
        val eligible = today.captureState.options.filter { it.action == "check_in" }
        val shift = mutable.value.selectedShift?.takeIf { selected -> eligible.any { it.assignmentId == selected } } ?: eligible.firstOrNull()?.assignmentId
        mutable.update { it.copy(employee = employee, today = today, devices = devices, currentDevice = current, history = history, selectedShift = shift) }
    }
    private fun run(operation: suspend () -> Unit) {
        if (mutable.value.busy || mutable.value.signOperation != null) return
        viewModelScope.launch {
            mutable.update { it.copy(busy = true, message = null) }
            try { operation() }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { mutable.update { it.copy(message = if (failure is InquiryFailure) failure.message else "تعذّر إتمام عملية الحضور.", signedIn = repository.session() != null) } }
            finally { mutable.update { it.copy(busy = false) } }
        }
    }
}
