package com.phc.inquiry.feature.damageassessment

import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import androidx.paging.LoadState
import androidx.paging.compose.LazyPagingItems
import androidx.paging.compose.itemKey
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.InquiryRecord

@Composable
fun SearchScreen(sector: String, records: LazyPagingItems<InquiryRecord>, hasSearched: Boolean, total: Int?, onSearch: (String) -> Unit, onRecord: (Long) -> Unit) {
    var query by rememberSaveable { mutableStateOf("") }
    Column(Modifier.fillMaxSize().imePadding().padding(horizontal = 20.dp)) {
        Text(sectorTitle(sector), style = MaterialTheme.typography.headlineSmall, modifier = Modifier.padding(vertical = 12.dp))
        OutlinedTextField(query, { if (it.length <= 150) query = it }, Modifier.fillMaxWidth().testTag("record-search"), label = { Text("الاسم أو رقم السجل") }, singleLine = true, keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search), keyboardActions = KeyboardActions(onSearch = { onSearch(query) }))
        Row(Modifier.fillMaxWidth().padding(vertical = 12.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Button(onClick = { onSearch(query) }, modifier = Modifier.weight(1f)) { Text("بحث") }
            if (hasSearched) OutlinedButton(onClick = { records.refresh() }) { Text("تحديث") }
        }
        total?.let { Text("$it نتيجة", style = MaterialTheme.typography.labelLarge) }
        LazyColumn(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(12.dp), contentPadding = PaddingValues(vertical = 12.dp)) {
            if (!hasSearched) item { Text("أدخل اسمًا أو رقمًا ثم اضغط بحث. اترك الحقل فارغًا لعرض السجلات المسموح بها.") }
            if (records.loadState.refresh is LoadState.Loading) item { LoadingState() }
            val refreshError = records.loadState.refresh as? LoadState.Error
            if (refreshError != null) item { ErrorState(refreshError.error.message ?: "تعذّر تحميل النتائج", records::retry) }
            if (hasSearched && records.loadState.refresh is LoadState.NotLoading && records.itemCount == 0) item { Text("لا توجد نتائج مطابقة ضمن صلاحيات حسابك.") }
            items(records.itemCount, key = records.itemKey { it.recordId }) { index ->
                records[index]?.let { record -> RecordCard(record, onClick = { onRecord(record.recordId) }) }
            }
            if (records.loadState.append is LoadState.Loading) item { LoadingState() }
            (records.loadState.append as? LoadState.Error)?.let { error -> item { ErrorState(error.error.message ?: "تعذّر تحميل المزيد", records::retry) } }
        }
    }
}

@Composable
fun RecordCard(record: InquiryRecord, onClick: () -> Unit) {
    Card(onClick = onClick, modifier = Modifier.fillMaxWidth()) {
        Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(record.displayName, style = MaterialTheme.typography.titleMedium)
            Text("رقم السجل: ${record.objectid?.content ?: record.recordId}", style = MaterialTheme.typography.bodySmall)
            Text(record.location)
            SuggestionChip(onClick = onClick, label = { Text(statusLabel(record.damageStatus)) })
            Text("عرض التفاصيل ←", color = MaterialTheme.colorScheme.primary)
        }
    }
}

@Composable
fun DetailScreen(state: DetailState, retry: () -> Unit) {
    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        if (state.loading) LoadingState()
        state.error?.let { ErrorState(it, retry) }
        state.record?.let { record ->
            Text(record.displayName, style = MaterialTheme.typography.headlineMedium)
            Card(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                    InformationRow("رقم السجل", record.objectid?.content)
                    InformationRow("مرجع الاستعلام", record.recordId.toString())
                    InformationRow("المبنى", record.buildingName)
                    InformationRow("البلدية", record.municipality)
                    InformationRow("الحي", record.neighborhood)
                    InformationRow("حالة الضرر", statusLabel(record.damageStatus))
                    InformationRow("العمل الميداني", if (record.fieldCompleted) "مكتمل" else "غير مكتمل")
                    InformationRow("حالة التدقيق", statusLabel(record.auditStatus))
                    record.parentGlobalId?.let { InformationRow("مرجع المبنى المرتبط", it) }
                }
            }
            Text("بيانات أساسية للاستعلام فقط، كما وردت من المنظومة.", style = MaterialTheme.typography.bodySmall)
            OutlinedButton(onClick = retry) { Text("تحديث التفاصيل") }
        }
    }
}

@Composable
private fun InformationRow(label: String, value: String?) {
    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
        Text(label, style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
        Text(value?.takeIf { it.isNotBlank() } ?: "غير مسجل", style = MaterialTheme.typography.bodyLarge)
    }
}
