package com.phc.inquiry.feature.damageassessment

import android.widget.Toast
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.*
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.paging.LoadState
import androidx.paging.compose.LazyPagingItems
import androidx.paging.compose.itemKey
import com.phc.inquiry.R
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.InquiryRecord
import kotlinx.coroutines.launch

@Composable
@OptIn(ExperimentalMaterial3Api::class)
fun SearchScreen(sector: String, records: LazyPagingItems<InquiryRecord>, hasSearched: Boolean, total: Int?, onSearch: (String) -> Unit, onRecord: (Long) -> Unit,
    filterState: FilterState? = null, reloadFilters: (String?) -> Unit = {}, onAdvancedSearch: ((String, Map<String, String>) -> Unit)? = null, onRecordItem: ((InquiryRecord) -> Unit)? = null) {
    var query by rememberSaveable(sector) { mutableStateOf("") }
    val listState = rememberLazyListState()
    val scope = rememberCoroutineScope()
    val keyboard = LocalSoftwareKeyboardController.current
    val focus = LocalFocusManager.current
    var showFilters by rememberSaveable { mutableStateOf(false) }
    var municipality by rememberSaveable { mutableStateOf("") }
    var neighborhood by rememberSaveable { mutableStateOf("") }
    var damage by rememberSaveable { mutableStateOf("") }
    var audit by rememberSaveable { mutableStateOf("") }
    var field by rememberSaveable { mutableStateOf("") }
    val activeFilters = mapOf("municipality" to municipality, "neighborhood" to neighborhood, "damage_status" to damage, "audit_status" to audit, "field_completion" to field).filterValues(String::isNotBlank)
    val submit = { focus.clearFocus(); keyboard?.hide(); if (onAdvancedSearch != null) onAdvancedSearch(query, activeFilters) else onSearch(query); scope.launch { listState.scrollToItem(0) }; Unit }
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.TopCenter) {
        Column(Modifier.widthIn(max = 880.dp).fillMaxSize().imePadding().padding(horizontal = 24.dp)) {
            Column(Modifier.padding(top = 12.dp, bottom = 16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                Text(stringResource(if (sector == "citizens") R.string.citizen_inquiry else if (sector == "housing-units") R.string.search_housing else R.string.search_buildings), style = MaterialTheme.typography.headlineSmall)
                OutlinedTextField(query, { if (it.length <= 150) query = it }, Modifier.fillMaxWidth().testTag("record-search"),
                    label = { Text(stringResource(if (sector == "citizens") R.string.citizen_search_hint else R.string.search_hint)) }, singleLine = true, leadingIcon = { Icon(Icons.Default.Search, null) },
                    trailingIcon = { if (query.isNotEmpty()) IconButton(onClick = { query = "" }) { Icon(Icons.Default.Close, stringResource(R.string.clear_search)) } },
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search), keyboardActions = KeyboardActions(onSearch = { submit() }))
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.CenterVertically) {
                    Button(onClick = submit, modifier = Modifier.weight(1f).heightIn(min = 52.dp).testTag("submit-search"), enabled = sector != "citizens" || query.trim().length >= 2, shape = RoundedCornerShape(12.dp)) {
                        Text(stringResource(R.string.search))
                    }
                    if (hasSearched) IconButton(onClick = { records.refresh() }) { Icon(Icons.Default.Refresh, stringResource(R.string.refresh)) }
                }
                if (filterState != null) TextButton(onClick = { showFilters = true }) { Text(stringResource(R.string.advanced_filters) + if (activeFilters.isNotEmpty()) " (${activeFilters.size})" else "") }
                if (hasSearched) total?.let { Text(stringResource(R.string.results_count, it), style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant) }
            }
            LazyColumn(Modifier.weight(1f).testTag("search-results"), state = listState, verticalArrangement = Arrangement.spacedBy(12.dp), contentPadding = PaddingValues(bottom = 24.dp)) {
                if (!hasSearched) item { EmptyState(stringResource(R.string.search_start), stringResource(if (sector == "citizens") R.string.citizen_instruction else R.string.search_instruction)) }
                if (records.loadState.refresh is LoadState.Loading) item { LoadingState() }
                val refreshError = records.loadState.refresh as? LoadState.Error
                if (refreshError != null) item { ErrorState(refreshError.error.message ?: stringResource(R.string.request_failed), records::retry) }
                if (hasSearched && records.loadState.refresh is LoadState.NotLoading && records.itemCount == 0) item { EmptyState(stringResource(R.string.no_results), stringResource(R.string.no_results_hint)) }
                items(records.itemCount, key = records.itemKey { "${it.sector ?: sector}-${it.recordId}" }) { index ->
                    records[index]?.let { record -> RecordCard(record, onClick = { if (onRecordItem != null) onRecordItem(record) else onRecord(record.recordId) }) }
                }
                if (records.loadState.append is LoadState.Loading) item { LoadingState() }
                (records.loadState.append as? LoadState.Error)?.let { error -> item { ErrorState(error.error.message ?: stringResource(R.string.request_failed), records::retry) } }
            }
        }
    }
    if (showFilters && filterState != null) ModalBottomSheet(onDismissRequest = { showFilters = false }) {
        Column(Modifier.fillMaxWidth().verticalScroll(rememberScrollState()).padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(stringResource(R.string.advanced_filters), style = MaterialTheme.typography.titleLarge)
            if (filterState.loading) LoadingState()
            filterState.error?.let { ErrorState(it) { reloadFilters(municipality.ifBlank { null }) } }
            FilterSelect(stringResource(R.string.municipality), municipality, filterState.options.municipalities, { municipality = it; neighborhood = ""; reloadFilters(it.ifBlank { null }) })
            FilterSelect(stringResource(R.string.neighborhood), neighborhood, filterState.options.neighborhoods, { neighborhood = it })
            FilterSelect(stringResource(R.string.damage_status), damage, filterState.options.damageStatuses, { damage = it }, true)
            FilterSelect(stringResource(R.string.audit_status), audit, filterState.options.auditStatuses, { audit = it }, true)
            FilterSelect(stringResource(R.string.field_work), field, listOf("completed", "not_completed"), { field = it }, true)
            Button(onClick = { showFilters = false; submit() }, Modifier.fillMaxWidth()) { Text(stringResource(R.string.apply_filters)) }
            TextButton(onClick = { municipality = ""; neighborhood = ""; damage = ""; audit = ""; field = ""; reloadFilters(null) }) { Text(stringResource(R.string.reset_filters)) }
        }
    }
}

@Composable
fun RecordCard(record: InquiryRecord, onClick: () -> Unit) {
    Card(onClick = onClick, modifier = Modifier.fillMaxWidth().testTag("record-${record.recordId}"),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Row(horizontalArrangement = Arrangement.spacedBy(12.dp), verticalAlignment = Alignment.Top) {
                IconTile(Icons.Default.Home)
                Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(recordName(record), style = MaterialTheme.typography.titleMedium)
                    Text(stringResource(R.string.record_number, record.objectid?.content ?: record.recordId.toString()), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
            }
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                Icon(Icons.Default.Place, null, Modifier.size(16.dp), tint = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(recordLocation(record), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            StatusBadge(record.damageStatus)
            record.sector?.let { Text(sectorTitle(it), style = MaterialTheme.typography.labelMedium) }
            HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
            Text(stringResource(R.string.view_details), color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.labelLarge)
        }
    }
}

@Composable
fun DetailScreen(state: DetailState, retry: () -> Unit, onHistory: (() -> Unit)? = null, onAttachments: (() -> Unit)? = null) {
    var showEmpty by rememberSaveable { mutableStateOf(false) }
    val clipboard = LocalClipboardManager.current
    val context = LocalContext.current
    val copied = stringResource(R.string.record_copied)
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.TopCenter) {
        Column(Modifier.widthIn(max = 880.dp).fillMaxSize().verticalScroll(rememberScrollState()).padding(24.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
            if (state.loading) LoadingState()
            state.error?.let { ErrorState(it, retry) }
            state.record?.let { record ->
                Surface(shape = RoundedCornerShape(20.dp), color = MaterialTheme.colorScheme.primaryContainer) {
                    Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text(recordName(record), style = MaterialTheme.typography.headlineSmall, color = MaterialTheme.colorScheme.onPrimaryContainer)
                        Text(stringResource(R.string.record_number, record.objectid?.content ?: record.recordId.toString()), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onPrimaryContainer)
                        StatusBadge(record.damageStatus)
                        TextButton(onClick = {
                            clipboard.setText(AnnotatedString(record.objectid?.content ?: record.recordId.toString()))
                            Toast.makeText(context, copied, Toast.LENGTH_SHORT).show()
                        }, modifier = Modifier.testTag("copy-record")) { Text(stringResource(R.string.copy_record)) }
                    }
                }
                InformationGroup(stringResource(R.string.basic_information)) {
                    InformationRow(stringResource(R.string.record_label), record.objectid?.content ?: record.recordId.toString())
                    InformationRow(stringResource(R.string.inquiry_reference), record.recordId.toString())
                    if (!record.buildingName.isNullOrBlank()) InformationRow(stringResource(R.string.building_label), record.buildingName)
                    record.parentGlobalId?.takeIf(String::isNotBlank)?.let { InformationRow(stringResource(R.string.parent_reference), it) }
                }
                InformationGroup(stringResource(R.string.location)) {
                    InformationRow(stringResource(R.string.municipality), record.municipality)
                    InformationRow(stringResource(R.string.neighborhood), record.neighborhood)
                }
                InformationGroup(stringResource(R.string.record_status)) {
                    InformationRow(stringResource(R.string.damage_status), statusLabel(record.damageStatus))
                    InformationRow(stringResource(R.string.field_work), stringResource(if (record.fieldCompleted) R.string.completed else R.string.not_completed))
                    if (!record.auditStatus.isNullOrBlank()) InformationRow(stringResource(R.string.audit_status), statusLabel(record.auditStatus))
                }
                if (record.capabilities.auditHistory && onHistory != null) OutlinedButton(onClick = onHistory, Modifier.fillMaxWidth().testTag("open-history")) { Text(stringResource(R.string.history_title)) }
                if (record.capabilities.attachments && onAttachments != null) OutlinedButton(onClick = onAttachments, Modifier.fillMaxWidth().testTag("open-attachments")) { Text(stringResource(R.string.attachments_title)) }
                if (record.details.isNotEmpty()) InformationGroup(stringResource(R.string.full_form)) {
                    record.details.filter { showEmpty || !it.value.isNullOrBlank() }.forEach { field -> InformationRow(field.label, field.value) }
                    if (record.details.any { it.value.isNullOrBlank() }) TextButton(onClick = { showEmpty = !showEmpty }) { Text(stringResource(if (showEmpty) R.string.hide_empty_fields else R.string.show_empty_fields)) }
                }
                OutlinedButton(onClick = retry, modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp)) {
                    Icon(Icons.Default.Refresh, null, Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text(stringResource(R.string.refresh))
                }
                Text(stringResource(R.string.detail_footer), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

@Composable
private fun InformationGroup(title: String, content: @Composable ColumnScope.() -> Unit) {
    Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
        Text(title, style = MaterialTheme.typography.titleMedium)
        Surface(Modifier.fillMaxWidth(), shape = RoundedCornerShape(16.dp), color = MaterialTheme.colorScheme.surface,
            border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
            Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp), content = content)
        }
    }
}

@Composable
private fun InformationRow(label: String, value: String?) {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value?.takeIf(String::isNotBlank) ?: stringResource(R.string.unavailable), style = MaterialTheme.typography.bodyLarge)
    }
}
