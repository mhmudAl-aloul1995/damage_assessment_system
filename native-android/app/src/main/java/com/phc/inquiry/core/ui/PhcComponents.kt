package com.phc.inquiry.core.ui

import androidx.compose.foundation.Image
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.*
import androidx.compose.ui.unit.dp
import com.phc.inquiry.R
import com.phc.inquiry.data.InquiryRecord

@Composable
fun BrandMark(size: Int = 72) {
    Surface(color = Color.White, shape = RoundedCornerShape(20.dp), border = BorderStroke(1.dp, Color(0xFFE0E7E2))) {
        Image(painterResource(R.drawable.phc_logo), stringResource(R.string.logo_description), Modifier.size(size.dp).padding(8.dp))
    }
}

@Composable
fun IconTile(icon: ImageVector, modifier: Modifier = Modifier) {
    Surface(modifier.size(48.dp), shape = RoundedCornerShape(14.dp), color = MaterialTheme.colorScheme.primaryContainer) {
        Box(contentAlignment = Alignment.Center) { Icon(icon, null, tint = MaterialTheme.colorScheme.onPrimaryContainer, modifier = Modifier.size(24.dp)) }
    }
}

@Composable
fun LoadingState() {
    Column(Modifier.fillMaxWidth().padding(32.dp).semantics { liveRegion = LiveRegionMode.Polite }, horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(16.dp)) {
        CircularProgressIndicator(Modifier.size(28.dp), strokeWidth = 3.dp)
        Text(stringResource(R.string.loading), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
    }
}

@Composable
fun EmptyState(title: String, description: String, icon: ImageVector = Icons.Default.Search) {
    Column(Modifier.fillMaxWidth().padding(vertical = 32.dp, horizontal = 16.dp), horizontalAlignment = Alignment.CenterHorizontally, verticalArrangement = Arrangement.spacedBy(12.dp)) {
        IconTile(icon)
        Text(title, style = MaterialTheme.typography.titleMedium)
        Text(description, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant, textAlign = androidx.compose.ui.text.style.TextAlign.Center)
    }
}

@Composable
fun ErrorState(message: String, retry: () -> Unit) {
    Surface(Modifier.fillMaxWidth(), color = MaterialTheme.colorScheme.errorContainer, shape = RoundedCornerShape(16.dp)) {
        Column(Modifier.padding(20.dp).semantics { liveRegion = LiveRegionMode.Polite }, verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(stringResource(R.string.request_failed), style = MaterialTheme.typography.titleSmall, color = MaterialTheme.colorScheme.onErrorContainer)
            Text(message, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onErrorContainer)
            TextButton(onClick = retry) { Text(stringResource(R.string.retry)) }
        }
    }
}

@Composable
fun StatusBadge(value: String?) {
    val palette = MaterialTheme.colorScheme
    val severe = value in setOf("fully_damaged", "rejected")
    Surface(shape = RoundedCornerShape(8.dp), color = if (severe) palette.errorContainer else palette.primaryContainer,
        contentColor = if (severe) palette.onErrorContainer else palette.onPrimaryContainer) {
        Row(Modifier.padding(horizontal = 10.dp, vertical = 4.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(6.dp)) {
            Icon(if (severe) Icons.Default.Warning else Icons.Default.Info, null, Modifier.size(14.dp))
            Text(statusLabel(value), style = MaterialTheme.typography.labelMedium)
        }
    }
}

@Composable
fun recordName(record: InquiryRecord): String = record.name?.takeIf(String::isNotBlank)
    ?: record.buildingName?.takeIf(String::isNotBlank)
    ?: stringResource(R.string.unnamed_record, record.objectid?.content ?: record.recordId.toString())

@Composable
fun recordLocation(record: InquiryRecord): String {
    val location = listOfNotNull(record.municipality, record.neighborhood).filter(String::isNotBlank).joinToString(" · ")
    return location.ifBlank { stringResource(R.string.unrecorded_location) }
}
