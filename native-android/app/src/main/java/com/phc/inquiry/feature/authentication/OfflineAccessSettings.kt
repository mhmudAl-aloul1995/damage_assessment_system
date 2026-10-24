package com.phc.inquiry.feature.authentication

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.unit.dp

@Composable fun OfflineAccessSettings(state: AuthenticationState, enable: () -> Unit, forget: () -> Unit) {
    var confirmForget by remember { mutableStateOf(false) }
    OutlinedCard(Modifier.fillMaxWidth()) { Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
        Text("فتح التطبيق دون إنترنت", style = MaterialTheme.typography.titleMedium)
        Text("بعد تنزيل النماذج، فعّل الفتح بقفل الجهاز. يتيح النماذج والمسودات حتى بعد انتهاء الجلسة أو تسجيل الخروج؛ ولا يتيح الإرسال للسيرفر.")
        if (state.offlineAvailable) Text("يوجد حساب مجهّز على هذا الجهاز. إعادة التفعيل تربطه بالحساب الحالي.", style = MaterialTheme.typography.bodySmall)
        Button(onClick = enable, enabled = !state.busy, modifier = Modifier.fillMaxWidth().testTag("enable-offline-access")) { Text(if (state.offlineAvailable) "تحديث الفتح المحلي لهذا الحساب" else "تفعيل الفتح بقفل الجهاز") }
        if (state.offlineAvailable) TextButton(onClick = { confirmForget = true }, enabled = !state.busy) { Text("إلغاء تفعيل الفتح المحلي") }
        state.message?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
    } }
    if (confirmForget) AlertDialog(onDismissRequest = { confirmForget = false }, title = { Text("إلغاء الفتح المحلي؟") }, text = { Text("لن تُحذف المسودات. ستحتاج تسجيل الدخول مع وجود نت لإعادة تفعيل الفتح بقفل الجهاز.") },
        confirmButton = { TextButton(onClick = { confirmForget = false; forget() }) { Text("إلغاء التفعيل") } }, dismissButton = { TextButton(onClick = { confirmForget = false }) { Text("رجوع") } })
}
