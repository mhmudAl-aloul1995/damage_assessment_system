package com.phc.inquiry.feature.attendance

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import com.phc.inquiry.core.security.signAttendance
import kotlinx.coroutines.CancellationException

private fun Context.attendanceActivity(): Activity? = when(this) {
    is Activity -> this
    is ContextWrapper -> baseContext.attendanceActivity()
    else -> null
}

@Composable
fun NativeAttendanceScreen(userId: Long, email: String, viewModel: AttendanceViewModel, state: AttendanceState) {
    val context = LocalContext.current
    LaunchedEffect(userId) { viewModel.bindUser(userId, email) }
    LaunchedEffect(state.signOperation) {
        state.signOperation?.let { operation ->
            val activity = context.attendanceActivity()
            if (activity == null) { viewModel.signingFailed("تعذّر فتح شاشة التوثيق."); return@let }
            try { viewModel.signed(signAttendance(activity, operation)) }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (failure: Exception) { viewModel.signingFailed(failure.message ?: "لم يكتمل توثيق التوقيع.") }
        }
    }
    AttendanceScreen(state, viewModel::login, viewModel::refresh, viewModel::enroll, viewModel::prepare,
        viewModel::selectShift, viewModel::month, viewModel::revoke, viewModel::retryConfirmation, viewModel::logout)
}

@Composable
fun AttendanceScreen(state: AttendanceState, login: (String) -> Unit, refresh: () -> Unit, enroll: () -> Unit,
    capture: (String) -> Unit, selectShift: (Long) -> Unit, month: (String) -> Unit, revoke: (Long, String) -> Unit,
    retry: () -> Unit, logout: () -> Unit) {
    var password by remember { mutableStateOf("") }
    var selectedMonth by remember(state.month) { mutableStateOf(state.month) }
    var reason by remember { mutableStateOf("") }
    var revokeDevice by remember { mutableStateOf<com.phc.inquiry.data.HrDevice?>(null) }
    val idle = !state.busy && state.signOperation == null
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        Text("الحضور والانصراف", style = MaterialTheme.typography.headlineSmall)
        if (!state.configured) {
            Text("خدمة الحضور الأصلية تنتظر ضبط رابط البوابة الداخلية المعتمد في نسخة التطبيق. يمكن متابعة صفحات HR على الويب.")
            return@Column
        }
        if (state.busy) LinearProgressIndicator(Modifier.fillMaxWidth())
        state.message?.let { Text(it, Modifier.testTag("attendance-message"), color = MaterialTheme.colorScheme.primary) }
        state.lastReceipt?.takeIf { it.confirmed }?.let { Text("آخر طلب مؤكد: " + (if (it.action == "check_in") "حضور" else "انصراف") + " — " + it.workDate + " — #" + it.eventId) }
        if (!state.signedIn) {
            Text("توثيق جلسة الحضور الآمنة", style = MaterialTheme.typography.titleMedium)
            Text(state.email)
            OutlinedTextField(password, { password = it }, label = { Text("كلمة مرور الحساب") }, visualTransformation = PasswordVisualTransformation(),
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password), modifier = Modifier.fillMaxWidth(), enabled = idle)
            Button(onClick = { val submitted = password; password = ""; login(submitted) }, enabled = idle && password.isNotBlank()) { Text("دخول الحضور") }
            return@Column
        }
        Text(state.name, style = MaterialTheme.typography.titleMedium)
        Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
            OutlinedButton(refresh, enabled = idle) { Text("تحديث") }
            TextButton(logout, enabled = idle) { Text("خروج من الحضور") }
        }
        val device = state.currentDevice
        Text("الجهاز: " + when(device?.status) { "pending" -> "بانتظار HR"; "approved" -> "معتمد"; "revoked" -> "ملغى"; "rejected" -> "مرفوض"; else -> "غير مسجل" })
        if (device == null || device.status in setOf("revoked", "rejected")) Button(enroll, enabled = idle) { Text("طلب تسجيل هذا الجهاز") }
        state.devices.filter { it.status in setOf("pending", "approved") }.forEach { owned ->
            TextButton({ revokeDevice = owned }, enabled = idle) { Text("إلغاء / فقدان " + owned.label + " — #" + owned.credentialId) }
        }
        if (state.employee?.captureEnabled != true) Text("التسجيل الإنتاجي معطل حتى التحقق من البوابة الداخلية.", Modifier.testTag("attendance-disabled"))
        state.today?.let { day ->
            Text(day.date + " — " + attendanceStatus(day.status), style = MaterialTheme.typography.titleMedium)
            Text("الحضور: " + (day.checkIn ?: "—") + " | الانصراف: " + (day.checkOut ?: "—"))
            Text("ساعات العمل: " + attendanceHours(day.workedMinutes))
            if (day.conflict) Text("يوجد تعارض يحتاج مراجعة HR.")
            day.captureState.options.filter { it.action == "check_in" }.forEach { shift ->
                Row { RadioButton(state.selectedShift == shift.assignmentId, { selectShift(shift.assignmentId) }, enabled = idle); Text(shift.schedule + " — " + shift.workDate) }
            }
            val open = day.captureState.openSession
            open?.let { Text("وردية مفتوحة بتاريخ " + it.workDate + " — الحضور UTC: " + it.checkInUtc) }
            if (open != null && !open.checkoutEligible) Text("تجاوزت الوردية مهلة الانصراف؛ اطلب تصحيح السجل لدى HR.")
            val enabled = idle && state.employee?.captureEnabled == true && device?.status == "approved" && !state.retryPending
            Row(horizontalArrangement = Arrangement.spacedBy(10.dp)) {
                Button({ capture("check_in") }, enabled = enabled && open == null && day.captureState.options.any { it.action == "check_in" && it.assignmentId == state.selectedShift }, modifier = Modifier.testTag("attendance-check-in")) { Text("تسجيل حضور") }
                Button({ capture("check_out") }, enabled = enabled && day.captureState.options.any { it.action == "check_out" }, modifier = Modifier.testTag("attendance-check-out")) { Text("تسجيل انصراف") }
            }
        }
        if (state.retryPending) {
            Text("لم يصل تأكيد نهائي. حدّث السجل أو أعد الطلب الموقع نفسه قبل إنشاء عملية جديدة.")
            OutlinedButton(retry, enabled = idle) { Text("إعادة طلب التأكيد") }
        }
        HorizontalDivider()
        Text("سجل الحضور", style = MaterialTheme.typography.titleMedium)
        OutlinedTextField(selectedMonth, { selectedMonth = it }, label = { Text("الشهر YYYY-MM") }, modifier = Modifier.fillMaxWidth(), singleLine = true)
        OutlinedButton({ month(selectedMonth) }, enabled = idle) { Text("عرض الشهر") }
        state.history.forEach { day ->
            OutlinedCard(Modifier.fillMaxWidth()) { Column(Modifier.padding(12.dp)) {
                Text(day.date + " — " + attendanceStatus(day.status))
                Text((day.checkIn ?: "—") + " ← " + (day.checkOut ?: "—"))
                Text("ساعات العمل: " + attendanceHours(day.workedMinutes))
            } }
        }
    }
    revokeDevice?.let { target -> AlertDialog(onDismissRequest = { revokeDevice = null }, title = { Text("إلغاء الجهاز #" + target.credentialId) },
        text = { OutlinedTextField(reason, { reason = it }, label = { Text("سبب الفقدان أو الإلغاء") }) },
        confirmButton = { TextButton({ revokeDevice = null; revoke(target.credentialId, reason) }, enabled = reason.trim().length >= 5) { Text("تأكيد الإلغاء") } },
        dismissButton = { TextButton({ revokeDevice = null }) { Text("رجوع") } }) }
}

fun attendanceHours(minutes: Int?): String = minutes?.let { "%d:%02d".format(it / 60, it % 60) } ?: "غير معروفة"
fun attendanceStatus(status: String): String = when(status) {
    "present", "approved_present", "legacy_present" -> "حاضر"
    "working" -> "داخل الدوام"
    "pending" -> "بانتظار اكتمال اليوم"
    "absence_candidate" -> "غياب يحتاج مراجعة"
    "approved_absent", "legacy_absent" -> "غائب"
    "holiday" -> "عطلة"
    "off_day", "off_day_worked" -> "يوم راحة"
    "leave" -> "إجازة"
    "excused_absence" -> "غياب بعذر"
    "missing_checkout" -> "انصراف مفقود"
    "missing_checkin" -> "حضور مفقود"
    "missing_session" -> "وردية غير مكتملة"
    "conflict" -> "تعارض مصادر"
    "outside_employment" -> "خارج فترة العمل"
    "unscheduled" -> "دون تكليف وردية"
    else -> status
}
