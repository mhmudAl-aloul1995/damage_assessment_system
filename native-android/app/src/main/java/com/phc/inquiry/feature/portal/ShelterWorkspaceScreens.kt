package com.phc.inquiry.feature.portal

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.text.selection.SelectionContainer
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalSoftwareKeyboardController
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.input.ImeAction
import androidx.compose.ui.unit.dp
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*

const val SHELTER_WORKSPACE = "heks-shelter"

fun shelterForms(forms: List<PublishedForm>): List<PublishedForm> =
    forms.filter { it.module == SHELTER_WORKSPACE && it.canSubmit && it.targetKind == "record" }

fun shelterDraft(drafts: List<DraftSummary>, record: Long? = null): DraftSummary? =
    latestUnfinishedDraft(drafts.filter { it.module == SHELTER_WORKSPACE && (record == null || it.recordId == record) })

@Composable
fun ShelterWorkspaceScreen(module: PortalModule, state: PortalState, onRecords: () -> Unit,
    onForm: (PublishedForm) -> Unit, onDraft: (DraftSummary) -> Unit, onOffline: () -> Unit) {
    val forms = shelterForms(state.forms)
    LazyColumn(Modifier.fillMaxSize().testTag("shelter-workspace"), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
        item {
            Surface(color = MaterialTheme.colorScheme.primary, contentColor = MaterialTheme.colorScheme.onPrimary, shape = RoundedCornerShape(20.dp)) {
                Column(Modifier.fillMaxWidth().padding(22.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Text("مساحة العمل · HEKS", style = MaterialTheme.typography.labelMedium)
                    Text("المرحلة الثالثة", style = MaterialTheme.typography.headlineSmall)
                    Text("من زيارة المستفيد إلى استكمال الاستبيان", style = MaterialTheme.typography.bodyMedium)
                    if (module.recordSearch) Button(onClick = onRecords, modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp).testTag("shelter-records"),
                        colors = ButtonDefaults.buttonColors(containerColor = MaterialTheme.colorScheme.onPrimary, contentColor = MaterialTheme.colorScheme.primary)) {
                        Icon(Icons.Default.Search, null); Spacer(Modifier.width(8.dp)); Text("فتح سجلات المستفيدين")
                    }
                }
            }
        }
        shelterDraft(state.drafts)?.let { draft -> item {
            PortalCard("تابع استبيانك", draft.title + if (draft.pending) " · بانتظار تأكيد الاستلام" else " · محفوظ على الجهاز",
                { onDraft(draft) }, tag = "shelter-resume", icon = Icons.Default.Edit)
        } }
        state.draftsError?.let { item { Text("تعذّر فتح بعض المسودات. راجع أعمالي لإعادة المحاولة.", color = MaterialTheme.colorScheme.error) } }
        item {
            Text("ابدأ من هنا", style = MaterialTheme.typography.titleLarge)
            Text("النماذج المتاحة لحسابك في هذه المساحة", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        items(forms, key = { it.key }) { form ->
            PortalCard(form.title, if (form.requiresRecord) "اختر سجلًا موجودًا لاستكمال بياناته" else "استبيان جديد أو تحديث بيانات مستفيد",
                { onForm(form) }, tag = "shelter-form-${form.key}", icon = Icons.Default.Edit)
        }
        if (forms.isEmpty()) item { EmptyState("لا يوجد نموذج متاح للتعبئة", "تظهر النماذج عندما تتوفر صلاحية الإرسال لحسابك.", Icons.Default.Lock) }
        item {
            OutlinedCard(Modifier.fillMaxWidth()) {
                Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    Text("قبل الزيارة الميدانية", style = MaterialTheme.typography.titleMedium)
                    val ready = forms.count { it.key in state.offlineReadyKeys }
                    Text(if (ready > 0) "$ready نماذج جاهزة دون إنترنت" else "جهّز النماذج قبل مغادرة المكتب", style = MaterialTheme.typography.bodyMedium)
                    Text("السجل الموجود يحتاج اتصالًا عند فتحه أول مرة. احفظ مسودتك لتستكملها في الميدان.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    TextButton(onClick = onOffline, modifier = Modifier.testTag("shelter-offline")) { Text("إعداد العمل دون إنترنت") }
                }
            }
        }
        if (state.usingSavedCatalog) item { Text("تظهر نسخة محفوظة من الخدمات. فتح السجلات وتحديثها يحتاج اتصالًا.", style = MaterialTheme.typography.bodySmall) }
    }
}

@Composable
private fun ShelterStatus(status: String?) {
    val palette = MaterialTheme.colorScheme
    val warning = status in setOf("بحاجة لاستكمال", "موقوف للأهلية", "مستبعد")
    Surface(shape = RoundedCornerShape(8.dp), color = if (warning) palette.secondaryContainer else palette.primaryContainer,
        contentColor = if (warning) palette.onSecondaryContainer else palette.onPrimaryContainer) {
        Text(status?.takeIf { it.isNotBlank() } ?: "الحالة غير مسجلة", Modifier.padding(horizontal = 10.dp, vertical = 5.dp), style = MaterialTheme.typography.labelSmall)
    }
}

@Composable
fun ShelterRecordsScreen(state: PortalRecordsState, selectingForm: Boolean, onSearch: (String) -> Unit,
    more: () -> Unit, retry: () -> Unit, onRecord: (Long) -> Unit) {
    var query by rememberSaveable { mutableStateOf(state.query) }
    val keyboard = LocalSoftwareKeyboardController.current
    val submit = { if (!state.loading) { keyboard?.hide(); onSearch(query) }; Unit }
    LazyColumn(Modifier.fillMaxSize().testTag("shelter-results"), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item {
            Text("HEKS · المرحلة الثالثة", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
            Text(if (selectingForm) "اختر سجل الاستبيان" else "سجلات المستفيدين", style = MaterialTheme.typography.headlineSmall)
            Text(if (selectingForm) "افتح السجل الذي تريد استكمال بياناته" else "السجلات المتاحة لك حسب صلاحياتك", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
        item {
            OutlinedTextField(query, { query = it.take(150) }, Modifier.fillMaxWidth().testTag("shelter-query"),
                label = { Text("ابحث عن مستفيد") }, supportingText = { Text("الاسم، الهوية، الهاتف أو الكود") },
                leadingIcon = { Icon(Icons.Default.Search, null) }, singleLine = true,
                keyboardOptions = KeyboardOptions(imeAction = ImeAction.Search), keyboardActions = KeyboardActions(onSearch = { submit() }),
                trailingIcon = { if (query.isNotEmpty()) IconButton(onClick = { query = ""; onSearch("") }, enabled = !state.loading) { Icon(Icons.Default.Close, "مسح البحث") } })
            Button(onClick = submit, enabled = !state.loading, modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp).testTag("shelter-search")) { Text("بحث") }
        }
        if (state.loading) item { LinearProgressIndicator(Modifier.fillMaxWidth()) }
        state.error?.let { item { ErrorState(it, retry) } }
        if (state.searched) item {
            Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                Text("${state.total} سجل", style = MaterialTheme.typography.titleMedium)
                Text(if (state.query.isBlank()) "جميع السجلات المتاحة" else "نتائج البحث عن: ${state.query}", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
        items(state.rows, key = { it.id }) { record ->
            Card(onClick = { onRecord(record.id) }, modifier = Modifier.fillMaxWidth().testTag("shelter-record-${record.id}"),
                shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
                Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                    ShelterStatus(record.status)
                    Text(record.title?.takeIf { it.isNotBlank() } ?: "سجل ${record.id}", style = MaterialTheme.typography.titleMedium)
                    record.subtitle?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    Text(if (selectingForm) "اختيار السجل ←" else "عرض التفاصيل ←", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.primary)
                }
            }
        }
        if (state.searched && state.rows.isEmpty() && !state.loading && state.error == null) item {
            EmptyState("لا توجد سجلات مطابقة", "جرّب جزءًا من الاسم أو تحقق من الرقم.", Icons.Default.Search)
        }
        if (state.page < state.last && state.searched) item {
            OutlinedButton(onClick = more, enabled = !state.loading, modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp).testTag("shelter-more")) {
                Text("عرض المزيد · ${state.rows.size} من ${state.total}")
            }
        }
    }
}

@Composable
private fun ShelterFieldGroup(title: String, fields: List<DetailField>) {
    if (fields.isEmpty()) return
    OutlinedCard(Modifier.fillMaxWidth(), colors = CardDefaults.outlinedCardColors(containerColor = MaterialTheme.colorScheme.surface)) {
        Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium)
            fields.forEachIndexed { index, field ->
                Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                    Text(field.label, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    SelectionContainer { Text(field.value?.takeIf { it.isNotBlank() } ?: "غير مسجل", style = MaterialTheme.typography.bodyLarge) }
                }
                if (index < fields.lastIndex) HorizontalDivider(color = MaterialTheme.colorScheme.outlineVariant)
            }
        }
    }
}

@Composable
fun ShelterRecordScreen(state: PortalDetailState, forms: List<PublishedForm>, drafts: List<DraftSummary>, retry: () -> Unit,
    onForm: (PublishedForm, Long) -> Unit, onDraft: (DraftSummary) -> Unit) {
    LazyColumn(Modifier.fillMaxSize().testTag("shelter-detail"), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
        if (state.loading) item { LoadingState() }
        state.error?.let { item { ErrorState(it, retry) } }
        state.row?.takeIf { it.module == SHELTER_WORKSPACE }?.let { record ->
            item {
                Surface(color = MaterialTheme.colorScheme.primaryContainer, shape = RoundedCornerShape(20.dp)) {
                    Column(Modifier.fillMaxWidth().padding(22.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("ملف المستفيد · HEKS", style = MaterialTheme.typography.labelMedium, color = MaterialTheme.colorScheme.onPrimaryContainer)
                        Text(record.title?.takeIf { it.isNotBlank() } ?: "سجل ${record.id}", style = MaterialTheme.typography.headlineSmall, color = MaterialTheme.colorScheme.onPrimaryContainer)
                        record.subtitle?.takeIf { it.isNotBlank() }?.let { Text(it, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onPrimaryContainer) }
                        ShelterStatus(record.status)
                    }
                }
            }
            val draft = shelterDraft(drafts, record.id)
            if (draft != null) item { PortalCard("استكمل المسودة المحفوظة", if (draft.pending) "بانتظار تأكيد الاستلام" else "آخر حفظ: ${displayTime(draft.updatedAt)}",
                { onDraft(draft) }, tag = "shelter-record-resume", icon = Icons.Default.Edit) }
            else items(shelterForms(forms), key = { "action-${it.key}" }) { form ->
                Button(onClick = { onForm(form, record.id) }, modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp).testTag("shelter-edit-${form.key}")) {
                    Text("استكمال ${form.title}")
                }
            }
            val identityKeys = setOf("code", "name", "identity_number", "phone")
            val visitKeys = setOf("area", "visit_date")
            item { ShelterFieldGroup("بيانات المستفيد", record.details.filter { it.key in identityKeys }) }
            item { ShelterFieldGroup("بيانات الزيارة", record.details.filter { it.key in visitKeys }) }
            item { ShelterFieldGroup("بيانات إضافية", record.details.filter { it.key !in identityKeys && it.key !in visitKeys }) }
            item { Text("الحالة المعروضة من المنظومة. إرسال الاستبيان لا يعني اعتماده.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
            item { OutlinedButton(onClick = retry, modifier = Modifier.fillMaxWidth().testTag("shelter-refresh")) { Text("تحديث بيانات السجل") } }
        }
    }
}
