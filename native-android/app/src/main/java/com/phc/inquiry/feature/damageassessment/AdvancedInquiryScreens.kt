package com.phc.inquiry.feature.damageassessment

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.verticalScroll
import androidx.compose.foundation.horizontalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.phc.inquiry.R
import com.phc.inquiry.core.ui.*

@Composable
fun FilterSelect(label: String, value: String, options: List<String>, onSelect: (String) -> Unit, statuses: Boolean = false) {
    var expanded by remember { mutableStateOf(false) }
    Column {
        Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Box {
            OutlinedButton(onClick = { expanded = true }, modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp).testTag("filter-$label")) {
                Text(if (value.isBlank()) stringResource(R.string.all_options) else if (statuses) statusLabel(value) else value)
            }
            DropdownMenu(expanded, onDismissRequest = { expanded = false }) {
                DropdownMenuItem(text = { Text(stringResource(R.string.all_options)) }, onClick = { expanded = false; onSelect("") })
                options.forEach { option -> DropdownMenuItem(text = { Text(if (statuses) statusLabel(option) else option) }, onClick = { expanded = false; onSelect(option) }) }
            }
        }
    }
}

@Composable
fun AuditHistoryScreen(state: HistoryState, select: (String) -> Unit, more: () -> Unit) {
    LazyColumn(Modifier.fillMaxSize().widthIn(max = 880.dp), contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Text(stringResource(R.string.history_title), style = MaterialTheme.typography.headlineSmall) }
        item { Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            FilterChip(selected = state.track == "engineering", onClick = { select("engineering") }, label = { Text(stringResource(R.string.engineering_track)) })
            FilterChip(selected = state.track == "legal", onClick = { select("legal") }, label = { Text(stringResource(R.string.legal_track)) })
        } }
        if (state.loading) item { LoadingState() }
        state.error?.let { item { ErrorState(it, more) } }
        if (!state.loading && state.error == null && state.data.isEmpty()) item { EmptyState(stringResource(R.string.no_history), stringResource(R.string.history_empty_hint)) }
        items(state.data, key = { "${it.track}-${it.id}" }) { entry ->
            Card(Modifier.fillMaxWidth(), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface)) {
                Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(entry.label ?: entry.status ?: stringResource(R.string.unavailable), style = MaterialTheme.typography.titleMedium)
                    entry.userName?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
                    entry.createdAt?.let { Text(it.replace('T', ' ').take(19), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    entry.notes?.takeIf(String::isNotBlank)?.let { Text(it, style = MaterialTheme.typography.bodyMedium) }
                }
            }
        }
        if (!state.loading && state.error == null && state.page < state.lastPage) item { OutlinedButton(onClick = more, Modifier.fillMaxWidth()) { Text(stringResource(R.string.load_more)) } }
    }
}

@Composable
fun AttachmentsScreen(state: AttachmentsState, retry: () -> Unit, open: (Long) -> Unit) {
    LazyColumn(Modifier.fillMaxSize().widthIn(max = 880.dp), contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Text(stringResource(R.string.attachments_title), style = MaterialTheme.typography.headlineSmall); Text(stringResource(R.string.attachment_limit_hint), style = MaterialTheme.typography.bodySmall) }
        if (state.loading) item { LoadingState() }
        state.error?.let { item { ErrorState(it, retry) } }
        if (!state.loading && state.error == null && state.files.isEmpty()) item { EmptyState(stringResource(R.string.no_attachments), stringResource(R.string.attachment_limit_hint)) }
        items(state.files, key = { it.id }) { file ->
            Card(onClick = { open(file.id) }, enabled = file.viewable, modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(file.name, style = MaterialTheme.typography.titleMedium)
                    Text(file.contentType, style = MaterialTheme.typography.bodySmall)
                    Text(stringResource(if (file.viewable) R.string.view_details else R.string.unsupported_attachment), style = MaterialTheme.typography.labelMedium)
                }
            }
        }
    }
}

@Composable
fun AttachmentViewerScreen(state: ViewerState, retry: () -> Unit, page: (Int) -> Unit) {
    var zoom by rememberSaveable(state.page) { mutableStateOf(1f) }
    Column(Modifier.fillMaxSize().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(state.title, style = MaterialTheme.typography.titleMedium)
        if (state.loading) LoadingState()
        state.error?.let { ErrorState(it, retry) }
        state.image?.let { image ->
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedButton(onClick = { zoom = (zoom - 0.25f).coerceAtLeast(1f) }, enabled = zoom > 1f) { Text(stringResource(R.string.zoom_out)) }
                OutlinedButton(onClick = { zoom = (zoom + 0.25f).coerceAtMost(3f) }, enabled = zoom < 3f) { Text(stringResource(R.string.zoom_in)) }
            }
            BoxWithConstraints(Modifier.weight(1f).fillMaxWidth()) {
                val width = maxWidth
                Row(Modifier.fillMaxSize().horizontalScroll(rememberScrollState())) {
                    Column(Modifier.width(width * zoom).fillMaxHeight().verticalScroll(rememberScrollState())) {
                        Image(image.asImageBitmap(), state.title, Modifier.width(width * zoom).height(width * image.height.toFloat() / image.width * zoom), contentScale = ContentScale.Fit)
                    }
                }
            }
            if (state.pageCount > 1) {
                Text(stringResource(R.string.page_counter, state.page + 1, state.pageCount), style = MaterialTheme.typography.labelMedium)
                Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    OutlinedButton(onClick = { page(state.page - 1) }, enabled = !state.loading && state.page > 0) { Text(stringResource(R.string.previous_page)) }
                    OutlinedButton(onClick = { page(state.page + 1) }, enabled = !state.loading && state.page + 1 < state.pageCount) { Text(stringResource(R.string.next_page)) }
                }
            }
        }
    }
}
