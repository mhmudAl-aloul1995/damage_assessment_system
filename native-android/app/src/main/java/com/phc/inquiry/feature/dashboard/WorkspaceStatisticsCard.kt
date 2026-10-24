package com.phc.inquiry.feature.dashboard

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.unit.dp
import com.phc.inquiry.core.ui.sectorTitle

@Composable
fun WorkspaceStatisticsCard(state: WorkspaceStatisticsState, open: () -> Unit, retry: () -> Unit) {
    OutlinedCard(Modifier.fillMaxWidth().testTag("home-statistics"), shape = RoundedCornerShape(16.dp),
        colors = CardDefaults.outlinedCardColors(containerColor = MaterialTheme.colorScheme.surface), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("حصر الأضرار بالأرقام", style = MaterialTheme.typography.titleMedium)
            Text("حصر الأضرار · السجلات المسموحة لحسابك", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            if (state.loading) LinearProgressIndicator(Modifier.fillMaxWidth())
            if (state.counts.isNotEmpty()) Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                state.counts.forEach { count -> Column(Modifier.weight(1f)) {
                    Text(sectorTitle(count.sector), style = MaterialTheme.typography.bodySmall)
                    Text(count.total?.toString() ?: "—", Modifier.testTag("home-total-${count.sector}"), style = MaterialTheme.typography.headlineMedium)
                    if (count.failed) Text("تعذّر التحديث", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error)
                } }
            }
            state.error?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.error) }
            if (!state.loading && state.error == null && state.counts.isEmpty()) Text("اختر قطاعًا من الإحصائيات التفصيلية.", style = MaterialTheme.typography.bodySmall)
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                TextButton(onClick = open, modifier = Modifier.testTag("home-open-statistics")) { Text("عرض الإحصائيات") }
                TextButton(onClick = retry, enabled = !state.loading) { Text(if (state.error != null || state.counts.any { it.failed }) "إعادة المحاولة" else "تحديث") }
            }
        }
    }
}
