package com.phc.inquiry.feature.attendance

import android.app.Activity
import android.content.Context
import android.content.ContextWrapper
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.selection.selectable
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.CheckCircle
import androidx.compose.material.icons.filled.Refresh
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.semantics.LiveRegionMode
import androidx.compose.ui.semantics.Role
import androidx.compose.ui.semantics.liveRegion
import androidx.compose.ui.semantics.semantics
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.unit.dp
import com.phc.inquiry.core.security.signAttendance
import com.phc.inquiry.core.ui.EmptyState
import com.phc.inquiry.data.HrAttendanceDay
import java.time.YearMonth
import java.time.LocalDate
import java.time.Instant
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale
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
    var selectedTab by rememberSaveable { mutableStateOf(0) }
    var reason by remember { mutableStateOf("") }
    var revokeDevice by remember { mutableStateOf<com.phc.inquiry.data.HrDevice?>(null) }
    val idle = !state.busy && state.signOperation == null
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically) {
            Column(Modifier.weight(1f)) {
                Text("دوامي", style = MaterialTheme.typography.headlineMedium)
                Text(if (state.signedIn) state.name else "حضورك وانصرافك ببصمة هاتفك", color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            if (state.signedIn) IconButton(refresh, enabled = idle) { Icon(Icons.Default.Refresh, "تحديث الحضور") }
        }
        if (!state.configured) {
            EmptyState("خدمة الحضور غير متصلة", "يلزم ضبط عنوان النظام في نسخة التطبيق.")
            return@Column
        }
        if (state.busy) LinearProgressIndicator(Modifier.fillMaxWidth())
        state.message?.let {
            Surface(color = MaterialTheme.colorScheme.secondaryContainer, shape = MaterialTheme.shapes.medium) {
                Text(it, Modifier.fillMaxWidth().padding(16.dp).testTag("attendance-message").semantics { liveRegion = LiveRegionMode.Polite })
            }
        }
        state.lastReceipt?.takeIf { it.confirmed }?.let {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer)) {
                Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Icon(Icons.Default.CheckCircle, null, tint = MaterialTheme.colorScheme.primary)
                    Column {
                        Text(if (it.action == "check_in") "تم تسجيل حضورك" else "تم تسجيل انصرافك", style = MaterialTheme.typography.titleMedium)
                        Text(it.workDate + " · " + attendanceReceiptTime(it.occurredAt, state.today?.timezone ?: "UTC"), style = MaterialTheme.typography.bodySmall)
                    }
                }
            }
        }
        if (!state.signedIn) {
            Card { Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                Text("لنبدأ يوم العمل", style = MaterialTheme.typography.titleLarge)
                Text("أكّد حسابك، ثم استخدم بصمة الهاتف لتسجيل الحضور والانصراف.")
                Text(state.email, style = MaterialTheme.typography.bodyMedium)
                OutlinedTextField(password, { password = it }, label = { Text("كلمة مرور الحساب") }, visualTransformation = PasswordVisualTransformation(),
                    keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password), modifier = Modifier.fillMaxWidth(), singleLine = true, enabled = idle)
                Button(onClick = { val submitted = password; password = ""; login(submitted) }, modifier = Modifier.fillMaxWidth().heightIn(min = 56.dp), enabled = idle && password.isNotBlank()) { Text("متابعة إلى الحضور") }
            } }
            return@Column
        }
        TabRow(selectedTabIndex = selectedTab) {
            listOf("اليوم", "سجلي", "جهازي").forEachIndexed { index, label ->
                Tab(selected = selectedTab == index, onClick = { selectedTab = index }, text = { Text(label) })
            }
        }
        val device = state.currentDevice
        if (selectedTab == 0) {
            if (device?.status != "approved") {
                OutlinedCard { Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("جهّز هاتفك للحضور", style = MaterialTheme.typography.titleMedium)
                    Text(if (device?.status == "pending") "طلبك لدى الموارد البشرية. بعد اعتماد الجهاز يمكنك التسجيل بالبصمة." else "سجّل هذا الهاتف مرة واحدة ليعتمده مسؤول الموارد البشرية.")
                    if (device?.status != "pending") Button(enroll, enabled = idle) { Text("تسجيل الجهاز بالبصمة") }
                    else TextButton(refresh, enabled = idle) { Text("التحقق من الاعتماد") }
                } }
            }
            if (state.employee != null && !state.employee.captureEnabled) {
                Text(state.employee.blockers.firstOrNull() ?: "التسجيل غير متاح حاليًا؛ حدّث الحالة أو راجع الموارد البشرية.", Modifier.testTag("attendance-disabled"), color = MaterialTheme.colorScheme.error)
            }
            state.today?.let { day ->
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer)) {
                    Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text(attendanceDate(day.date), style = MaterialTheme.typography.labelLarge)
                        Text(if (day.captureState.openSession != null) "أنت داخل الدوام" else attendanceStatus(day.status), style = MaterialTheme.typography.headlineSmall)
                        Text(day.schedule ?: "راجع تكليف الوردية لدى الموارد البشرية", style = MaterialTheme.typography.bodyMedium)
                        HorizontalDivider()
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                            AttendanceMetric("الحضور", day.checkIn ?: "—", Modifier.weight(1f))
                            AttendanceMetric("الانصراف", day.checkOut ?: "—", Modifier.weight(1f))
                        }
                        Text("ساعات العمل: " + attendanceHours(day.workedMinutes), style = MaterialTheme.typography.titleMedium)
                    }
                }
                if (day.conflict) Text("يوجد تعارض في السجل؛ راجع الموارد البشرية.", color = MaterialTheme.colorScheme.error)
                day.captureState.options.filter { it.action == "check_in" }.takeIf { it.size > 1 }?.forEach { shift ->
                    Row(Modifier.fillMaxWidth().selectable(state.selectedShift == shift.assignmentId, enabled = idle, role = Role.RadioButton, onClick = { selectShift(shift.assignmentId) }).padding(8.dp), verticalAlignment = Alignment.CenterVertically) {
                        RadioButton(state.selectedShift == shift.assignmentId, null, enabled = idle)
                        Text(shift.schedule + " — " + shift.workDate, Modifier.padding(start = 8.dp))
                    }
                }
                val open = day.captureState.openSession
                open?.let { Text("الوردية المفتوحة: " + it.workDate + " · " + attendanceReceiptTime(it.checkInUtc, day.timezone)) }
                if (open != null && !open.checkoutEligible) Text("تجاوزت الوردية مهلة الانصراف؛ اطلب تصحيح السجل لدى الموارد البشرية.")
                if (day.captureState.options.isEmpty() && open == null) Text("لا توجد عملية متاحة الآن. إذا كانت لديك وردية عمل، راجع تكليفك أو سجل اليوم.")
                val enabled = idle && state.employee?.captureEnabled == true && device?.status == "approved" && !state.retryPending
                Button({ capture("check_in") }, enabled = enabled && open == null && day.captureState.options.any { it.action == "check_in" && it.assignmentId == state.selectedShift }, modifier = Modifier.fillMaxWidth().heightIn(min = 60.dp).testTag("attendance-check-in")) { Text("تسجيل حضور بالبصمة") }
                OutlinedButton({ capture("check_out") }, enabled = enabled && day.captureState.options.any { it.action == "check_out" }, modifier = Modifier.fillMaxWidth().heightIn(min = 56.dp).testTag("attendance-check-out")) { Text("تسجيل انصراف بالبصمة") }
                Text("يظهر التأكيد بعد حفظ العملية على الخادم.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            } ?: EmptyState("لم تُحمّل حالة اليوم", "اضغط تحديث لجلب سجل الحضور من النظام.")
        }
        if (state.retryPending) {
            OutlinedCard { Column(Modifier.fillMaxWidth().padding(16.dp)) {
                Text("نتحقق من تسجيل العملية", style = MaterialTheme.typography.titleMedium)
                Text("لم يصل التأكيد بعد. أعد التحقق لتجنب تكرار التسجيل.")
                OutlinedButton(retry, enabled = idle) { Text("إعادة طلب التأكيد") }
            } }
        }
        if (selectedTab == 1) {
            val displayedMonth = YearMonth.parse(state.month)
            Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.SpaceBetween) {
                TextButton({ month(displayedMonth.minusMonths(1).toString()) }, enabled = idle) { Text("السابق") }
                Text(displayedMonth.format(DateTimeFormatter.ofPattern("MMMM yyyy", Locale.forLanguageTag("ar"))), style = MaterialTheme.typography.titleMedium)
                TextButton({ month(displayedMonth.plusMonths(1).toString()) }, enabled = idle) { Text("التالي") }
            }
            if (state.history.isEmpty()) EmptyState("لا توجد سجلات لهذا الشهر", "اختر شهرًا آخر أو حدّث بياناتك.")
            state.history.sortedByDescending { it.date }.forEach { day -> AttendanceHistoryDay(day) }
        }
        if (selectedTab == 2) {
            Card { Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text("هذا الهاتف", style = MaterialTheme.typography.titleLarge)
                Text("حالة الجهاز: " + attendanceDeviceStatus(device?.status))
                Text("البصمة تؤكد هويتك على الهاتف. لا تُرسل بيانات بصمتك إلى النظام.")
                if (device == null || device.status in setOf("revoked", "rejected")) Button(enroll, enabled = idle) { Text("تسجيل الجهاز بالبصمة") }
            } }
            state.devices.filter { it.status in setOf("pending", "approved") }.forEach { owned ->
                OutlinedCard { Column(Modifier.fillMaxWidth().padding(16.dp)) {
                    Text(owned.label, style = MaterialTheme.typography.titleMedium)
                    Text(attendanceDeviceStatus(owned.status))
                    TextButton({ reason = ""; revokeDevice = owned }, enabled = idle) { Text("إلغاء / فقدان " + owned.label + " — #" + owned.credentialId) }
                } }
            }
            TextButton(logout, enabled = idle) { Text("تسجيل الخروج من الحضور") }
        }
    }
    revokeDevice?.let { target -> AlertDialog(onDismissRequest = { revokeDevice = null }, title = { Text("إلغاء الجهاز #" + target.credentialId) },
        text = { OutlinedTextField(reason, { reason = it }, label = { Text("سبب الفقدان أو الإلغاء") }) },
        confirmButton = { TextButton({ revokeDevice = null; revoke(target.credentialId, reason) }, enabled = reason.trim().length >= 5) { Text("تأكيد الإلغاء") } },
        dismissButton = { TextButton({ revokeDevice = null }) { Text("رجوع") } }) }
}

@Composable
private fun AttendanceMetric(label: String, value: String, modifier: Modifier = Modifier) {
    Column(modifier) {
        Text(label, style = MaterialTheme.typography.bodySmall)
        Text(value, style = MaterialTheme.typography.titleLarge)
    }
}

@Composable
private fun AttendanceHistoryDay(day: HrAttendanceDay) {
    OutlinedCard(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(attendanceDate(day.date), style = MaterialTheme.typography.titleMedium)
            Text(attendanceStatus(day.status), color = MaterialTheme.colorScheme.primary)
            Text("الحضور: " + (day.checkIn ?: "—") + "   ·   الانصراف: " + (day.checkOut ?: "—"))
            Text("ساعات العمل: " + attendanceHours(day.workedMinutes))
            if ((day.lateMinutes ?: 0) > 0) Text("التأخير: ${day.lateMinutes} دقيقة")
            if ((day.earlyMinutes ?: 0) > 0) Text("الانصراف المبكر: ${day.earlyMinutes} دقيقة")
        }
    }
}

fun attendanceDate(value: String): String = runCatching {
    LocalDate.parse(value).format(DateTimeFormatter.ofPattern("EEEE، d MMMM yyyy", Locale.forLanguageTag("ar")))
}.getOrDefault(value)

fun attendanceReceiptTime(value: String, timezone: String): String = runCatching {
    val iso = if ('T' in value) value else value.replace(' ', 'T') + "Z"
    Instant.parse(iso).atZone(ZoneId.of(timezone)).format(DateTimeFormatter.ofPattern("HH:mm", Locale.ROOT))
}.getOrDefault(value)

fun attendanceDeviceStatus(status: String?): String = when (status) {
    "approved" -> "معتمد"
    "pending" -> "بانتظار الاعتماد"
    "revoked" -> "ملغى"
    "rejected" -> "مرفوض"
    else -> "غير مسجل"
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
