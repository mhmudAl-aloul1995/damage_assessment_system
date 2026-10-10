package com.phc.inquiry.core.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.unit.dp

@Composable
fun PhcTheme(content: @Composable () -> Unit) {
    val palette = if (isSystemInDarkTheme()) darkColorScheme(primary = Color(0xFF9CD4B9), secondary = Color(0xFFE1CC9A))
    else lightColorScheme(primary = Color(0xFF245D4B), secondary = Color(0xFF756039), background = Color(0xFFF5F7F4), surface = Color(0xFFF5F7F4))
    MaterialTheme(colorScheme = palette, content = content)
}

@Composable
fun LoadingState() {
    Column(Modifier.fillMaxWidth().padding(32.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(16.dp)) {
        CircularProgressIndicator()
        Text("جارٍ تحميل البيانات…")
    }
}

@Composable
fun ErrorState(message: String, retry: () -> Unit) {
    Card(Modifier.fillMaxWidth().padding(vertical = 12.dp), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer)) {
        Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(message, color = MaterialTheme.colorScheme.onErrorContainer)
            OutlinedButton(onClick = retry) { Text("إعادة المحاولة") }
        }
    }
}

fun statusLabel(value: String?): String = when (value) {
    "fully_damaged" -> "ضرر كلي"
    "partially_damaged" -> "ضرر جزئي"
    "committee_review" -> "بحاجة للجنة"
    "no_damage" -> "دون ضرر"
    "pending" -> "بانتظار التدقيق"
    "assigned_engineer" -> "مسند للمهندس"
    "accepted_engineer" -> "مقبول هندسيًا"
    "assigned_lawyer" -> "مسند للمحامي"
    "accepted_lawyer" -> "مقبول قانونيًا"
    "team_approved" -> "معتمد من الفريق"
    "undp_approved" -> "معتمد من UNDP"
    "needs_action" -> "بحاجة لإجراء"
    "rejected" -> "مرفوض"
    "not_completed" -> "غير مكتمل"
    "unclassified", null, "" -> "غير مصنف"
    else -> "غير مصنف ($value)"
}

fun sectorTitle(sector: String): String = if (sector == "housing-units") "الوحدات السكنية" else "المباني"
