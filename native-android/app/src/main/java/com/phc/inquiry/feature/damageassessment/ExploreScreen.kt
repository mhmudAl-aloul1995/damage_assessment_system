package com.phc.inquiry.feature.damageassessment

import androidx.compose.foundation.Canvas
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalFocusManager
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import com.phc.inquiry.R
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*
import java.time.OffsetDateTime
import java.time.format.DateTimeFormatter
import kotlin.math.roundToInt

fun damageColor(value: String?): Int = when (value) {
    "fully_damaged", "destroyed", "rejected", "rejected_by_engineer" -> 0xFFC13939.toInt()
    "partially_damaged", "severe", "needs_action", "need_review", "legal_notes" -> 0xFFBD651C.toInt()
    "committee_review", "moderate", "pending" -> 0xFFA58915.toInt()
    "minor", "assigned_engineer", "assigned_to_engineer", "assigned_lawyer", "assigned_to_lawyer" -> 0xFF2974A1.toInt()
    "no_damage", "approved", "team_approved", "undp_approved", "completed", "accepted_by_engineer", "accepted_engineer", "accepted_by_lawyer", "accepted_lawyer" -> 0xFF16725A.toInt()
    else -> 0xFF727E88.toInt()
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun ExploreScreen(state: ExploreState, initialMode: String, retryCatalog: () -> Unit, selectSector: (String) -> Unit,
    search: (String, Map<String, String>) -> Unit, loadFilters: (String?) -> Unit, nextMap: () -> Unit, retryMap: () -> Unit,
    retryStats: () -> Unit, searchArea: (MapBounds) -> Unit, openRecord: (InquiryRecord) -> Unit) {
    var mode by rememberSaveable { mutableStateOf(initialMode) }
    var query by rememberSaveable(state.sector) { mutableStateOf("") }
    var filtersOpen by rememberSaveable { mutableStateOf(false) }
    var legendOpen by rememberSaveable { mutableStateOf(false) }
    var localFocus by rememberSaveable { mutableIntStateOf(0) }
    var bounds by remember { mutableStateOf<MapBounds?>(null) }
    var selected by remember(state.sector, state.criteria) { mutableStateOf<List<Long>>(emptyList()) }
    val keyboard = LocalSoftwareKeyboardController.current
    val focus = LocalFocusManager.current
    val submit = { focus.clearFocus(); keyboard?.hide(); search(query, state.criteria.filters) }
    Column(Modifier.fillMaxSize()) {
        if (state.catalogLoading) LoadingState()
        state.catalogError?.let { ErrorState(it, retryCatalog) }
        if (!state.catalogLoading && state.catalogError == null && state.sectors.isEmpty()) EmptyState(stringResource(R.string.no_services), stringResource(R.string.no_services_hint))
        if (state.sectors.isNotEmpty()) {
            Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                state.sectors.forEach { sector -> FilterChip(selected = sector.key == state.sector, onClick = { selected = emptyList(); bounds = null; selectSector(sector.key) },
                    label = { Text(sectorTitle(sector.key)) }, modifier = Modifier.testTag("explore-sector-${sector.key}")) }
            }
            Row(Modifier.padding(horizontal = 16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                OutlinedTextField(query, { query = it.take(150) }, Modifier.weight(1f).testTag("explore-query"), label = { Text(stringResource(R.string.map_search_hint)) }, singleLine = true,
                    keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search), keyboardActions = KeyboardActions(onSearch = { submit() }))
                Button(onClick = submit, Modifier.heightIn(min = 48.dp).testTag("explore-search")) { Text(stringResource(R.string.search)) }
            }
            Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp), verticalAlignment = Alignment.CenterVertically) {
                TextButton(onClick = { filtersOpen = true }) { Text(stringResource(R.string.advanced_filters) + if (state.criteria.filters.isEmpty()) "" else " (${state.criteria.filters.size})") }
                Spacer(Modifier.weight(1f))
                if (mode == "map") TextButton(onClick = { legendOpen = true }) { Text(stringResource(R.string.legend)) }
            }
            TabRow(selectedTabIndex = if (mode == "map") 0 else 1) {
                Tab(mode == "map", { mode = "map" }, text = { Text(stringResource(R.string.map_tab)) })
                Tab(mode == "stats", { mode = "stats" }, text = { Text(stringResource(R.string.statistics_title)) }, modifier = Modifier.testTag("statistics-tab"))
            }
            if (mode == "stats") {
                StatisticsScreen(state.statistics, state.statisticsLoading, state.statisticsError, retryStats, Modifier.weight(1f))
            } else {
                Row(Modifier.fillMaxWidth().horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp), horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    TextButton(onClick = { localFocus++ }, enabled = state.map.features.isNotEmpty()) { Text(stringResource(R.string.fit_map)) }
                    TextButton(onClick = { bounds?.let(searchArea); selected = emptyList() }, enabled = bounds != null && !state.mapLoading) { Text(stringResource(R.string.search_map_area)) }
                }
                Box(Modifier.weight(1f).fillMaxWidth()) {
                    ArcGISCanvas(state.map.features, state.focusRevision + localFocus, selected.firstOrNull(), { selected = it }, { bounds = it }, Modifier.fillMaxSize())
                    if (state.mapLoading) LinearProgressIndicator(Modifier.fillMaxWidth().align(Alignment.TopCenter))
                    state.mapError?.let { error -> Surface(Modifier.align(Alignment.TopCenter), tonalElevation = 4.dp) { ErrorState(error, retryMap) } }
                    if (!state.mapLoading && state.mapError == null && state.map.features.isEmpty()) Surface(Modifier.align(Alignment.TopCenter).padding(16.dp), tonalElevation = 4.dp, shape = MaterialTheme.shapes.medium) {
                        Column(Modifier.padding(16.dp)) { Text(stringResource(R.string.map_empty), style = MaterialTheme.typography.titleSmall); Text(stringResource(R.string.map_empty_hint), style = MaterialTheme.typography.bodySmall) }
                    }
                }
                Column(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp)) {
                    Text(stringResource(R.string.map_loaded, state.map.features.size, state.map.scanned), style = MaterialTheme.typography.labelMedium)
                    if (state.areaOnly) Text(stringResource(R.string.map_area_only), style = MaterialTheme.typography.bodySmall)
                    if (state.map.limited) Text(stringResource(R.string.map_limited), color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodySmall)
                    if (state.map.omitted > 0) Text(stringResource(R.string.map_omitted, state.map.omitted), style = MaterialTheme.typography.bodySmall)
                    if (state.map.cursor != null) TextButton(onClick = nextMap, enabled = !state.mapLoading, modifier = Modifier.testTag("map-next")) { Text(stringResource(R.string.map_next)) }
                }
            }
        }
    }
    val selectedIds = selected.toSet()
    val selectedRecords = state.map.features.map { it.attributes }.filter { it.recordId in selectedIds }
    if (selectedRecords.isNotEmpty() && mode == "map") ModalBottomSheet(onDismissRequest = { selected = emptyList() }) {
        LazyColumn(contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            item { Text(stringResource(R.string.map_choose_record), style = MaterialTheme.typography.titleLarge) }
            selectedRecords.forEach { record -> item(key = record.recordId) { RecordCard(record) { selected = emptyList(); openRecord(record) } } }
        }
    }
    if (legendOpen) ModalBottomSheet(onDismissRequest = { legendOpen = false }) {
        Column(Modifier.verticalScroll(rememberScrollState()).padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(stringResource(R.string.legend), style = MaterialTheme.typography.titleLarge)
            Text(stringResource(R.string.map_legend_hint))
            (state.filters.options.damageStatuses.ifEmpty { listOf("fully_damaged", "partially_damaged", "committee_review", "no_damage", "unclassified") }).forEach { LegendRow(it) }
        }
    }
    if (filtersOpen) ExploreFilterSheet(state, loadFilters, { filtersOpen = false }) { filters -> filtersOpen = false; search(query, filters) }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun ExploreFilterSheet(state: ExploreState, reload: (String?) -> Unit, dismiss: () -> Unit, apply: (Map<String, String>) -> Unit) {
    var municipality by rememberSaveable(state.sector) { mutableStateOf(state.criteria.filters["municipality"].orEmpty()) }
    var neighborhood by rememberSaveable(state.sector) { mutableStateOf(state.criteria.filters["neighborhood"].orEmpty()) }
    var damage by rememberSaveable(state.sector) { mutableStateOf(state.criteria.filters["damage_status"].orEmpty()) }
    var audit by rememberSaveable(state.sector) { mutableStateOf(state.criteria.filters["audit_status"].orEmpty()) }
    var field by rememberSaveable(state.sector) { mutableStateOf(state.criteria.filters["field_completion"].orEmpty()) }
    ModalBottomSheet(onDismissRequest = dismiss) {
        Column(Modifier.verticalScroll(rememberScrollState()).padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(stringResource(R.string.advanced_filters), style = MaterialTheme.typography.titleLarge)
            if (state.filters.loading) LoadingState()
            state.filters.error?.let { ErrorState(it) { reload(municipality.ifBlank { null }) } }
            FilterSelect(stringResource(R.string.municipality), municipality, state.filters.options.municipalities, { municipality = it; neighborhood = ""; reload(it.ifBlank { null }) })
            FilterSelect(stringResource(R.string.neighborhood), neighborhood, state.filters.options.neighborhoods, { neighborhood = it })
            FilterSelect(stringResource(R.string.damage_status), damage, state.filters.options.damageStatuses, { damage = it }, true)
            FilterSelect(stringResource(R.string.audit_status), audit, state.filters.options.auditStatuses, { audit = it }, true)
            FilterSelect(stringResource(R.string.field_work), field, listOf("completed", "not_completed"), { field = it }, true)
            Button(onClick = { apply(mapOf("municipality" to municipality, "neighborhood" to neighborhood, "damage_status" to damage, "audit_status" to audit, "field_completion" to field).filterValues(String::isNotBlank)) }, Modifier.fillMaxWidth()) { Text(stringResource(R.string.apply_filters)) }
            TextButton(onClick = { municipality = ""; neighborhood = ""; damage = ""; audit = ""; field = ""; reload(null) }) { Text(stringResource(R.string.reset_filters)) }
        }
    }
}

@Composable
fun StatisticsScreen(statistics: SectorStatistics?, loading: Boolean, error: String?, retry: () -> Unit, modifier: Modifier = Modifier) {
    LazyColumn(modifier.fillMaxWidth(), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
        item { Text(stringResource(R.string.stats_scope), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        if (loading) item { LoadingState() }
        error?.let { item { ErrorState(it, retry) } }
        if (statistics != null) {
            val metrics = listOf("total" to R.string.stats_total, "completed" to R.string.stats_completed, "pending" to R.string.stats_pending, "approved" to R.string.stats_approved, "action_required" to R.string.stats_action)
            metrics.chunked(2).forEach { group -> item {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) { group.forEach { (key, label) ->
                    Card(Modifier.weight(1f).testTag("metric-$key")) { Column(Modifier.padding(16.dp)) { Text(statistics.summary[key]?.coerceAtLeast(0)?.toString() ?: "—", style = MaterialTheme.typography.headlineMedium); Text(stringResource(label), style = MaterialTheme.typography.labelLarge) } }
                } }
            } }
            if (statistics.summary["total"] == 0L) item { Text(stringResource(R.string.stats_no_data)) }
            item { NativeChart(stringResource(R.string.damage_distribution), statistics.damage, donut = true) }
            item { NativeChart(stringResource(R.string.field_distribution), statistics.fieldwork) }
            item { NativeChart(stringResource(R.string.audit_distribution), statistics.audit) }
            statistics.auditTracks.filterValues { it.isNotEmpty() }.forEach { (track, values) -> item { NativeChart(stringResource(if (track == "engineering") R.string.engineering_track else R.string.legal_track), values) } }
            statistics.calculatedAt?.let { timestamp -> item { val formatted = runCatching { OffsetDateTime.parse(timestamp).format(DateTimeFormatter.ofPattern("yyyy/MM/dd HH:mm")) }.getOrDefault(timestamp.take(25)); Text(stringResource(R.string.stats_updated, formatted), style = MaterialTheme.typography.bodySmall) } }
            item { OutlinedButton(onClick = retry, Modifier.fillMaxWidth()) { Text(stringResource(R.string.refresh)) } }
        }
    }
}

@Composable
private fun NativeChart(title: String, values: Map<String, Long>, donut: Boolean = false) {
    val fractions = chartFractions(values)
    val background = MaterialTheme.colorScheme.surfaceVariant
    Card(Modifier.fillMaxWidth()) {
        Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            if (donut) Box(Modifier.fillMaxWidth(), contentAlignment = Alignment.Center) {
                Canvas(Modifier.size(150.dp)) {
                    val stroke = Stroke(24.dp.toPx())
                    drawArc(background, 0f, 360f, false, style = stroke)
                    var start = -90f
                    fractions.forEach { (key, fraction) -> val sweep = fraction * 360f; if (sweep > 0f) drawArc(Color(damageColor(key)), start, sweep, false, style = stroke); start += sweep }
                }
            }
            values.forEach { (key, count) ->
                Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                    LegendRow(key, stringResource(R.string.chart_count, count.coerceAtLeast(0), ((fractions[key] ?: 0f) * 100).roundToInt()))
                    if (!donut) LinearProgressIndicator(progress = { fractions[key] ?: 0f }, modifier = Modifier.fillMaxWidth().height(8.dp), color = Color(damageColor(key)), trackColor = background)
                }
            }
        }
    }
}

@Composable
private fun LegendRow(key: String, count: String? = null) {
    Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(10.dp)) {
        Box(Modifier.size(14.dp).background(Color(damageColor(key)), CircleShape))
        Text(statusLabel(key), style = MaterialTheme.typography.bodyMedium, modifier = Modifier.weight(1f))
        count?.let { Text(it, style = MaterialTheme.typography.labelMedium) }
    }
}
