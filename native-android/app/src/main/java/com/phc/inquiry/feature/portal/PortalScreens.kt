package com.phc.inquiry.feature.portal

import androidx.activity.compose.BackHandler
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.lazy.rememberLazyListState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.focus.FocusRequester
import androidx.compose.ui.focus.focusRequester
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.unit.dp
import androidx.compose.ui.graphics.vector.ImageVector
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*
import kotlinx.serialization.json.*
import java.time.LocalDate
import java.time.format.DateTimeFormatter

fun moduleTitle(key: String): String = when(key) {
    "hr" -> "الموارد البشرية · الحضور"
    "damage_assessment" -> "حصر الأضرار"
    "damage_assessment_borrowers" -> "المقترضون"
    "heks" -> "إصلاح المأوى · HEKS"
    "heks-shelter" -> "HEKS · المرحلة الثالثة"
    "administration" -> "الإدارة"
    else -> key
}
private fun moduleIcon(key: String): ImageVector = when(key) { "hr" -> Icons.Default.CheckCircle; "damage_assessment" -> Icons.Default.Place; "damage_assessment_borrowers" -> Icons.Default.Home; "heks", "heks-shelter" -> Icons.Default.Favorite; else -> Icons.Default.AccountCircle }
@Composable private fun PortalCard(title: String, subtitle: String, action: () -> Unit, modifier: Modifier = Modifier, tag: String = "", icon: ImageVector = Icons.Default.List) {
    Card(onClick = action, modifier = modifier.fillMaxWidth().testTag(tag), shape = RoundedCornerShape(12.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
        border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Row(Modifier.padding(16.dp), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            IconTile(icon)
            Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text(title, style = MaterialTheme.typography.titleMedium)
                if(subtitle.isNotBlank()) Text(subtitle, style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Icon(Icons.Default.KeyboardArrowLeft, "فتح", tint = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.size(20.dp))
        }
    }
}
@Composable fun PortalHome(name: String, state: PortalState, onModule: (String) -> Unit, onSurveys: () -> Unit, onDrafts: () -> Unit, onDamage: () -> Unit, retry: () -> Unit, onInquiry: () -> Unit = onDamage,
    onTasks: () -> Unit = onDrafts, summaryContent: @Composable () -> Unit = {}) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
            Column(Modifier.weight(1f)) { Text("أهلًا، $name", style = MaterialTheme.typography.titleMedium); Text("المجلس الفلسطيني للإسكان", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) }
            BrandMark(92)
        } }
        item { PortalCard("البحث في المنظومة", "المباني، الوحدات، المستفيدون والسجلات", onInquiry, tag = "home-inquiry", icon = Icons.Default.Search) }
        item { Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Button(onClick = onSurveys, modifier = Modifier.weight(1f).heightIn(min = 52.dp).testTag("home-surveys"), shape = RoundedCornerShape(10.dp)) { Icon(Icons.Default.Edit, null, Modifier.size(18.dp)); Spacer(Modifier.width(8.dp)); Text("تعبئة استبيان") }
            OutlinedButton(onClick = onTasks, modifier = Modifier.weight(1f).heightIn(min = 52.dp).testTag("home-tasks"), shape = RoundedCornerShape(10.dp)) { Text("متابعة أعمالي") }
        } }
        item { Row(horizontalArrangement = Arrangement.spacedBy(12.dp), modifier = Modifier.fillMaxWidth()) {
            PortalMetric(if(state.draftsError != null && state.drafts.isEmpty()) "—" else "${state.drafts.count { it.receiptId == null }}", if(state.draftsError != null) "مسودات يمكن فتحها" else "مسودات على الجهاز", Icons.Default.Edit, onDrafts, Modifier.weight(1f))
            PortalMetric(if(state.loading || (state.error != null && state.forms.isEmpty())) "—" else "${state.forms.size}", "نماذج متاحة", Icons.Default.List, onSurveys, Modifier.weight(1f))
        } }
        item { summaryContent() }
        if(state.drafts.any { it.receiptId == null }) item {
            PortalCard("تابع من حيث توقفت", "${state.drafts.count { it.receiptId == null }} مسودات محفوظة؛ افتحها للاستكمال أو متابعة تأكيد الإرسال.", onDrafts, tag = "home-continue-drafts", icon = Icons.Default.Edit)
        }
        state.draftsError?.let { item { OutlinedCard(onClick = onDrafts, modifier = Modifier.fillMaxWidth().testTag("home-drafts-warning")) { Column(Modifier.padding(18.dp)) {
            Text("تنبيه المسودات المحفوظة", style = MaterialTheme.typography.titleSmall)
            Text("تعذّر فتح بعض المسودات على الجهاز. يمكنك متابعة الاستعلام وفتح المسودات لإعادة المحاولة.", style = MaterialTheme.typography.bodySmall)
        } } } }
        item { Text("مساحات العمل المتاحة لحسابك", style = MaterialTheme.typography.titleMedium) }
        if(state.loading) item { LoadingState() }
        state.error?.let { item { ErrorState(it, retry) } }
        items(state.modules, key = { it.key }) { module -> PortalCard(module.title, module.description, { onModule(module.key) }, tag = "module-${module.key}", icon = moduleIcon(module.key)) }
        if(!state.loading && state.modules.isEmpty() && state.error == null) item { EmptyState("لا توجد خدمات متاحة", "تظهر الموديولات وفق صلاحيات حسابك.", Icons.Default.Lock) }
        if(state.error != null) item { OutlinedButton(onClick = onDamage, modifier = Modifier.fillMaxWidth()) { Text("فتح خدمات حصر الأضرار الحالية") } }
    }
}
@Composable private fun PortalMetric(value: String, label: String, icon: ImageVector, action: () -> Unit, modifier: Modifier) {
    Card(onClick = action, modifier = modifier, shape = RoundedCornerShape(12.dp), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Column(Modifier.fillMaxWidth().padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween, verticalAlignment = Alignment.CenterVertically) { IconTile(icon); Text(value, style = MaterialTheme.typography.headlineMedium) }
            Text(label, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
        }
    }
}
@Composable fun PortalInquiry(state: PortalState, onModule: (String) -> Unit, retry: () -> Unit) {
    LazyColumn(contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Text("الموديولات", style = MaterialTheme.typography.headlineSmall); Text("اختر مساحة العمل للوصول إلى السجلات والخدمات المتاحة.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
        if(state.loading) item { LoadingState() }
        state.error?.let { item { ErrorState(it, retry) } }
        items(state.modules, key = { it.key }) { module -> PortalCard(module.title, module.description, { onModule(module.key) }, icon = moduleIcon(module.key)) }
    }
}
@Composable fun ModuleScreen(module: PortalModule?, forms: List<PublishedForm>, onRecords: () -> Unit, onForm: (PublishedForm) -> Unit) {
    LazyColumn(contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
        item { Text(module?.title ?: "الموديول", style = MaterialTheme.typography.headlineSmall); Text(module?.description.orEmpty(), color = MaterialTheme.colorScheme.onSurfaceVariant) }
        item { PortalCard("الاستعلام عن السجلات", "ابحث واعرض التفاصيل الحالية من المنظومة", onRecords) }
        if(forms.isNotEmpty()) item { Text("النماذج المتاحة", style = MaterialTheme.typography.titleLarge) }
        items(forms, key = { it.key }) { form -> PortalCard(form.title, if(form.requiresRecord) "تحديث استبيان سجل موجود" else "استبيان جديد أو تحديث سجل", { onForm(form) }) }
        if(module?.sections?.isNotEmpty() == true) item {
            Text("أقسام الموديول في المنظومة", style = MaterialTheme.typography.titleMedium)
            module.sections.forEach { Text("• $it", color = MaterialTheme.colorScheme.onSurfaceVariant) }
        }
    }
}
@Composable fun PortalRecordsScreen(state: PortalRecordsState, selectingForm: Boolean, onSearch: (String) -> Unit, more: () -> Unit, retry: () -> Unit, onRecord: (Long) -> Unit) {
    var query by remember { mutableStateOf("") }
    LazyColumn(contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item {
            Text(if(selectingForm) "اختر سجل الاستبيان" else "البحث في السجلات", style = MaterialTheme.typography.headlineSmall)
            OutlinedTextField(query, { query = it.take(150) }, Modifier.fillMaxWidth().padding(top = 14.dp).testTag("portal-query"), label = { Text("الاسم أو الرقم") }, singleLine = true,
                trailingIcon = { IconButton(onClick = { onSearch(query) }, enabled = !state.loading) { Icon(Icons.Default.Search, "بحث") } })
            FilledTonalButton(onClick = { onSearch(query) }, enabled = !state.loading, modifier = Modifier.fillMaxWidth().padding(top = 8.dp)) { Text("بحث") }
        }
        if(state.loading) item { LoadingState() }
        state.error?.let { item { ErrorState(it, retry) } }
        if(state.searched) item { Text("${state.total} نتيجة", color = MaterialTheme.colorScheme.onSurfaceVariant) }
        items(state.rows, key = { it.id }) { record -> PortalCard(record.title ?: "سجل ${record.id}", listOfNotNull(record.subtitle, record.status).joinToString(" · "), { onRecord(record.id) }, tag = "portal-record-${record.id}") }
        if(state.searched && state.rows.isEmpty() && !state.loading && state.error == null) item { EmptyState("لا توجد نتائج", "جرّب اسمًا أو رقمًا آخر.", Icons.Default.Search) }
        if(state.page < state.last) item { OutlinedButton(onClick = more, enabled = !state.loading, modifier = Modifier.fillMaxWidth()) { Text("تحميل المزيد") } }
    }
}
@Composable fun PortalDetailScreen(state: PortalDetailState, forms: List<PublishedForm>, retry: () -> Unit, onForm: (PublishedForm, Long) -> Unit) {
    LazyColumn(contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
        if(state.loading) item { LoadingState() }
        state.error?.let { item { ErrorState(it, retry) } }
        state.row?.let { record ->
            item { Text(record.title ?: "سجل ${record.id}", style = MaterialTheme.typography.headlineSmall); Text(moduleTitle(record.module), color = MaterialTheme.colorScheme.primary) }
            items(record.details, key = { it.key }) { field -> Column {
                Text(field.label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
                Text(field.value?.takeIf { it.isNotBlank() } ?: "غير مسجل", style = MaterialTheme.typography.bodyLarge)
                HorizontalDivider(Modifier.padding(top = 12.dp))
            } }
            items(forms.filter { it.module == record.module && it.key != "heks-followup" }, key = { it.key }) { form -> PortalCard(form.title, "فتح النموذج وتحديث البيانات", { onForm(form, record.id) }) }
        }
    }
}
@Composable fun SurveysScreen(state: PortalState, initialTab: Int, onForm: (PublishedForm) -> Unit, onDraft: (DraftSummary) -> Unit, deleteDraft: (String) -> Unit, retry: () -> Unit, moreReceipts: () -> Unit, refreshReceipts: () -> Unit = {}, retryDrafts: () -> Unit = {}, onAttachment: (String, Long) -> Unit = { _, _ -> }) {
    var tab by remember(initialTab) { mutableIntStateOf(initialTab) }
    var toDelete by remember { mutableStateOf<DraftSummary?>(null) }
    LaunchedEffect(tab) { if (tab == 2) refreshReceipts() }
    Column {
        TabRow(tab) { listOf("النماذج", "المسودات", "المرسلة").forEachIndexed { index, title -> Tab(tab == index, { tab = index }, text = { Text(title) }) } }
        LazyColumn(contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            if (if (tab == 2) state.receiptsLoading else tab == 0 && state.loading) item { LoadingState() }
            if (tab == 2) state.receiptsError?.let { item { ErrorState(it, refreshReceipts) } }
            else if (tab == 0) state.error?.let { item { ErrorState(it, retry) } }
            when(tab) {
                0 -> {
                    item { Text("ابدأ استبيانًا", style = MaterialTheme.typography.headlineSmall); Text("النماذج المنشورة والمسموحة لحسابك فقط.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    items(state.forms, key = { it.key }) { form -> PortalCard(form.title, moduleTitle(form.module), { onForm(form) }, tag = "form-${form.key}") }
                    if(!state.loading && state.forms.isEmpty()) item { EmptyState("لا توجد نماذج متاحة", "تحتاج النماذج إلى صلاحية الحفظ وتحديث API.", Icons.Default.Lock) }
                    if(state.forms.any { !it.canSubmit }) item { Text("لإرسال الاستبيانات، سجّل الخروج ثم ادخل مجددًا بعد نشر تحديث الخادم.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
                }
                1 -> {
                    item { Text("محفوظة على هذا الجهاز", style = MaterialTheme.typography.headlineSmall); Text("المسودات مشفرة ومخصصة لهذا الحساب. الحفظ هنا لا يعني الاستلام على السيرفر.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    state.draftsError?.let { item { OutlinedCard(Modifier.fillMaxWidth().testTag("draft-storage-warning")) { Column(Modifier.padding(18.dp)) {
                        Text("تعذّر فتح بعض المسودات", style = MaterialTheme.typography.titleMedium)
                        Text(it)
                        TextButton(onClick = retryDrafts) { Text("إعادة محاولة فتح المسودات") }
                    } } } }
                    items(state.drafts, key = { it.uuid }) { draft -> Column {
                        PortalCard(draft.title, when { draft.receiptId != null -> "تم الاستلام · إيصال ${draft.receiptId}"; draft.pending -> "بانتظار تأكيد الاستلام · أعد المحاولة"; else -> "مسودة محلية · ${displayTime(draft.updatedAt)}" }, { onDraft(draft) })
                        if(!draft.pending || draft.receiptId != null) TextButton(onClick = { toDelete = draft }) { Text("حذف النسخة المحلية") }
                    } }
                    if(state.drafts.isEmpty() && state.draftsError == null) item { EmptyState("لا توجد مسودات", "ابدأ نموذجًا؛ تُحفظ إجاباتك تلقائيًا.", Icons.Default.Edit) }
                }
                2 -> {
                    item { Text("استلمها السيرفر", style = MaterialTheme.typography.headlineSmall); Text("إيصال الاستلام يؤكد حفظ البيانات؛ ولا يغيّر حالة اعتمادها داخل الموديول.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
                    items(state.receipts, key = { it.id }) { receipt -> OutlinedCard(Modifier.fillMaxWidth()) { Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(6.dp)) {
                        Text(state.forms.firstOrNull { it.key == receipt.formKey }?.title ?: moduleTitle(receipt.module), style = MaterialTheme.typography.titleMedium)
                        Text("إيصال #${receipt.id} · سجل #${receipt.recordId}"); Text(displayTime(receipt.receivedAt), color = MaterialTheme.colorScheme.onSurfaceVariant)
                        receipt.attachments.forEach { file -> TextButton(onClick = { onAttachment(receipt.uuid, file.id) }) { Text("عرض ${file.name}") } }
                    } } }
                    if(state.receipts.isEmpty() && state.receiptsPage > 0 && !state.receiptsLoading && state.receiptsError == null) item { EmptyState("لا توجد إرساليات", "تظهر الإيصالات بعد نجاح الإرسال.", Icons.Default.CheckCircle) }
                    if(state.receiptsPage > 0 && state.receiptsPage < state.receiptsLast) item { OutlinedButton(onClick = moreReceipts, enabled = !state.receiptsLoading, modifier = Modifier.fillMaxWidth()) { Text("تحميل المزيد") } }
                }
            }
        }
    }
    toDelete?.let { draft -> AlertDialog(onDismissRequest = { toDelete = null }, title = { Text("حذف النسخة المحلية؟") },
        text = { Text("ستُحذف هذه المسودة ومرفقاتها من الجهاز. الإيصال والسجل المحفوظان على السيرفر يبقيان كما هما.") },
        confirmButton = { TextButton(onClick = { deleteDraft(draft.uuid); toDelete = null }) { Text("حذف") } },
        dismissButton = { TextButton(onClick = { toDelete = null }) { Text("إلغاء") } }) }
}
@Composable fun WorkspaceTasksScreen(state: PortalState, onDrafts: () -> Unit, onSent: () -> Unit, onSurveys: () -> Unit, retryDrafts: () -> Unit, onDraft: (DraftSummary) -> Unit) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item { Text("مهامي", style = MaterialTheme.typography.headlineSmall); Text("استكمل المسودات وتابع تأكيد استلام ما أرسلته.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        item { PortalCard("سجل الإرساليات", "عرض إيصالات الاستلام والمرفقات المرسلة", onSent, tag = "tasks-sent", icon = Icons.Default.CheckCircle) }
        state.draftsError?.let { message -> item { OutlinedCard(Modifier.fillMaxWidth()) { Column(Modifier.padding(16.dp)) {
            Text("تعذّر فتح بعض المسودات", style = MaterialTheme.typography.titleMedium)
            Text(message, style = MaterialTheme.typography.bodySmall)
            TextButton(onClick = retryDrafts) { Text("إعادة محاولة فتح المسودات") }
        } } } }
        item { Text("بانتظار الاستكمال أو التأكيد", style = MaterialTheme.typography.titleMedium) }
        items(state.drafts.filter { it.receiptId == null }, key = { it.uuid }) { draft ->
            PortalCard(draft.title, if(draft.pending) "بانتظار تأكيد الاستلام · أعد المحاولة من نفس المسودة" else "مسودة محفوظة · ${moduleTitle(draft.module)}", { onDraft(draft) }, tag = "task-${draft.uuid}", icon = Icons.Default.Edit)
        }
        if(state.drafts.none { it.receiptId == null } && state.draftsError == null) item { EmptyState("لا توجد مسودات معلّقة", "يمكنك بدء استبيان جديد أو مراجعة الإرساليات.", Icons.Default.CheckCircle) }
        item { Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            Button(onClick = onSurveys, Modifier.weight(1f)) { Text("استبيان جديد") }
            OutlinedButton(onClick = onDrafts, Modifier.weight(1f)) { Text("كل المسودات") }
        } }
    }
}

@Composable fun AccountScreen(name: String, email: String, moduleCount: Int, logout: () -> Unit) {
    LazyColumn(contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
        item { BrandMark(64); Text(name, style = MaterialTheme.typography.headlineSmall); Text(email, color = MaterialTheme.colorScheme.onSurfaceVariant) }
        item { OutlinedCard(Modifier.fillMaxWidth()) { Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) { Text("صلاحيات الحساب", style = MaterialTheme.typography.titleMedium); Text("$moduleCount موديولات متاحة"); Text("تُراجع الصلاحيات من السيرفر عند كل عملية.") } } }
        item { Text("المسودات المحلية مشفرة وتبقى مرتبطة بهذا الحساب على هذا الجهاز.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
        item { OutlinedButton(onClick = logout, modifier = Modifier.fillMaxWidth()) { Text("تسجيل الخروج") } }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable fun FormScreen(state: FormState, viewModel: FormViewModel, close: () -> Unit, onLocation: () -> Unit = {}, onAttachment: (String, String) -> Unit = { _, _ -> }) {
    var confirmSend by remember { mutableStateOf(false) }
    var confirmLeave by remember { mutableStateOf(false) }
    val picker = rememberLauncherForActivityResult(ActivityResultContracts.OpenDocument()) { uri -> uri?.let(viewModel::addAttachment) }
    val busy = state.saving || state.submitting
    BackHandler(enabled = state.draft?.receipt == null) { if(busy) viewModel.notice("انتظر اكتمال الحفظ قبل المغادرة.") else confirmLeave = true }
    if(state.loading && state.draft == null) { LoadingState(); return }
    if(state.draft == null) { ErrorState(state.error ?: "تعذّر فتح النموذج.", viewModel::load); return }
    val draft = state.draft
    val schema = draft.schema
    val editable = draft.pending == null && draft.receipt == null && !state.submitting
    val reviewing = state.section >= schema.sections.size || draft.pending != null || draft.receipt != null
    val listState = rememberLazyListState()
    val fieldFocus = remember(state.errorNavigation) { FocusRequester() }
    val visibleFields = schema.sections.getOrNull(state.section)?.fields.orEmpty().filter { FormRules.visible(it, draft.answers) }
    LaunchedEffect(state.errorNavigation) {
        val target = state.errorTarget?.substringBefore('.')
        val index = visibleFields.indexOfFirst { it.key == target }
        if(index >= 0 && !reviewing) {
            val errorItems = if(state.errors.isNotEmpty()) state.errors.size else if(state.error != null) 1 else 0
            val noticeItems = if(state.notice != null) 1 else 0
            listState.scrollToItem(errorItems + noticeItems + 1 + index)
        }
    }
    Column(Modifier.fillMaxSize()) {
        Column(Modifier.fillMaxWidth().padding(horizontal = 24.dp, vertical = 14.dp)) {
            Text(schema.title, style = MaterialTheme.typography.titleLarge)
            Text(when { draft.receipt != null -> "تم الاستلام · إيصال #${draft.receipt.id}"; draft.pending != null -> "بانتظار تأكيد السيرفر · الإجابات محفوظة"; state.saving -> "جارٍ الحفظ على الجهاز…"; state.saved -> "محفوظ على الجهاز"; else -> "لم يكتمل الحفظ المحلي" },
                color = if(state.saved || draft.receipt != null) MaterialTheme.colorScheme.primary else MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodySmall, modifier = Modifier.testTag("draft-status"))
            if(!reviewing) { LinearProgressIndicator(progress = { (state.section + 1f) / (schema.sections.size + 1f) }, modifier = Modifier.fillMaxWidth().padding(top = 12.dp)); Text("الخطوة ${state.section + 1} من ${schema.sections.size + 1}", style = MaterialTheme.typography.labelMedium) }
        }
        LazyColumn(Modifier.weight(1f).imePadding(), state = listState, contentPadding = PaddingValues(horizontal = 24.dp, vertical = 12.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
            if(state.errors.isNotEmpty()) {
                items(state.errors.entries.toList(), key = { "error-${it.key}" }) { entry ->
                    val field = schema.sections.flatMap { it.fields }.firstOrNull { it.key == entry.key.substringBefore('.') }
                    Card(onClick = { viewModel.goToError(entry.key) }, enabled = editable && field != null,
                        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer), modifier = Modifier.fillMaxWidth().testTag("error-${entry.key}")) {
                        Column(Modifier.padding(16.dp)) { Text(field?.label ?: entry.key, style = MaterialTheme.typography.titleSmall); Text(entry.value); if(editable && field != null) Text("اضغط للانتقال إلى الحقل وتعديله", style = MaterialTheme.typography.labelMedium) }
                    }
                }
            } else state.error?.let { item { Surface(color = MaterialTheme.colorScheme.errorContainer, shape = RoundedCornerShape(16.dp)) { Column(Modifier.padding(16.dp)) { Text(it, color = MaterialTheme.colorScheme.onErrorContainer); if(editable) TextButton(onClick = viewModel::refreshSchema, enabled = !busy) { Text("تحديث النموذج والسجل للمراجعة") } } } } }
            state.notice?.let { item { Text(it, color = MaterialTheme.colorScheme.primary) } }
            if(reviewing) {
                item { Text(if(draft.receipt != null) "تم حفظ البيانات على السيرفر" else "راجع إجاباتك", style = MaterialTheme.typography.headlineSmall) }
                if(draft.needsReview) {
                    item { Text("السجل الحالي على السيرفر مقابل إجابات مسودتك", style = MaterialTheme.typography.titleMedium) }
                    item { Row(verticalAlignment = Alignment.CenterVertically) { Checkbox(false, { viewModel.acceptReview() }, enabled = editable); Text("راجعت التغييرات وأريد إرسال إجابات هذه المسودة", modifier = Modifier.weight(1f)) } }
                    items(schema.sections.flatMap { it.fields }.filter { draft.answers[it.key] != schema.answers[it.key] }, key = { "conflict-${it.key}" }) { field ->
                        OutlinedCard(Modifier.fillMaxWidth()) { Column(Modifier.padding(14.dp)) {
                            Text(field.label, style = MaterialTheme.typography.titleSmall)
                            Text("السيرفر: ${reviewValue(field, schema.answers[field.key])}", color = MaterialTheme.colorScheme.onSurfaceVariant)
                            Text("المسودة: ${reviewValue(field, draft.answers[field.key])}")
                        } }
                    }
                }
                if(draft.pending != null && draft.receipt == null) item { Text("لم يتأكد الاستلام بعد. إعادة المحاولة ترسل الطلب نفسه بمعرّفه نفسه لمنع التكرار.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
                schema.sections.forEachIndexed { index, section ->
                    item {
                        Row(verticalAlignment = Alignment.CenterVertically) {
                            Text(section.title, style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
                            if(editable) TextButton(onClick = { viewModel.section(index) }) { Text("تعديل") }
                        }
                    }
                    items(section.fields.filter { FormRules.visible(it, draft.answers) }, key = { it.key }) { field ->
                        Column { Text(field.label, style = MaterialTheme.typography.labelLarge, color = MaterialTheme.colorScheme.onSurfaceVariant); Text(reviewValue(field, draft.answers[field.key]), style = MaterialTheme.typography.bodyMedium) }
                    }
                }
                item { Text("المرفقات والموقع", style = MaterialTheme.typography.titleMedium) }
                items(draft.attachments, key = { it.localId }) { attachment ->
                    Row(verticalAlignment = Alignment.CenterVertically) {
                        Column(Modifier.weight(1f)) { Text(attachment.name); Text("${attachment.size / 1024} كيلوبايت", color = MaterialTheme.colorScheme.onSurfaceVariant) }
                        TextButton(onClick = { onAttachment(draft.uuid, attachment.localId) }, enabled = !busy) { Text("عرض") }
                        if(editable) TextButton(onClick = { viewModel.removeAttachment(attachment.localId) }) { Text("إزالة") }
                    }
                }
                if(editable) item {
                    OutlinedButton(onClick = { picker.launch(arrayOf("image/jpeg", "image/png", "application/pdf")) }, enabled = draft.attachments.size < 5, modifier = Modifier.fillMaxWidth()) { Text("إضافة صورة أو PDF") }
                    OutlinedButton(onClick = onLocation, modifier = Modifier.fillMaxWidth()) { Icon(Icons.Default.Place, null); Text(if(draft.location == null) "إضافة موقعي الحالي (اختياري)" else "تحديث الموقع") }
                }
                draft.location?.let { location -> item { Text("الموقع: ${"%.5f".format(location.latitude)}، ${"%.5f".format(location.longitude)} · دقة ${location.accuracy?.toInt() ?: 0} م", color = MaterialTheme.colorScheme.onSurfaceVariant) } }
                if(editable) item { Text("بعد الإرسال سيصدر إيصال استلام. تُطبَّق إجراءات المراجعة المعتمدة في الموديول.", color = MaterialTheme.colorScheme.onSurfaceVariant) }
            } else {
                val section = schema.sections[state.section]
                item { Text(section.title, style = MaterialTheme.typography.headlineSmall) }
                items(section.fields.filter { FormRules.visible(it, draft.answers) }, key = { it.key }) { field ->
                    NativeFormField(field, draft.answers[field.key], state.errors, field.key, editable, focusRequester = if(state.errorTarget == field.key) fieldFocus else null, onValue = { viewModel.answer(field.key, it) })
                }
            }
        }
        Surface(shadowElevation = 4.dp) { Row(Modifier.fillMaxWidth().padding(16.dp), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            if(draft.receipt != null) { Button(onClick = close, modifier = Modifier.fillMaxWidth()) { Text("العودة للاستبيانات") } }
            else if(reviewing) {
                if(editable) OutlinedButton(onClick = viewModel::previous, enabled = !busy, modifier = Modifier.weight(1f)) { Text("السابق") }
                Button(onClick = { if(draft.pending != null) viewModel.submit() else confirmSend = true }, enabled = !busy && !draft.needsReview, modifier = Modifier.weight(1f).testTag("submit-form")) {
                    Text(if(state.submitting) "جارٍ الإرسال…" else if(draft.pending != null) "تأكيد الاستلام / إعادة المحاولة" else "إرسال الاستبيان")
                }
            } else {
                OutlinedButton(onClick = { if(state.section > 0) viewModel.previous() else confirmLeave = true }, enabled = !busy, modifier = Modifier.weight(1f)) { Text(if(state.section > 0) "السابق" else "حفظ وإغلاق") }
                Button(onClick = viewModel::next, enabled = !state.submitting, modifier = Modifier.weight(1f)) { Text(if(state.section == schema.sections.lastIndex) "مراجعة الإجابات" else "التالي") }
            }
        } }
    }
    if(confirmSend) AlertDialog(onDismissRequest = { confirmSend = false }, title = { Text("إرسال الاستبيان؟") },
        text = { Text("ستُحفظ الإجابات في المنظومة بصلاحيات حسابك. تأكد من مراجعتها.") },
        confirmButton = { TextButton(onClick = { confirmSend = false; viewModel.submit() }) { Text("تأكيد الإرسال") } },
        dismissButton = { TextButton(onClick = { confirmSend = false }) { Text("مراجعة") } })
    if(confirmLeave) AlertDialog(onDismissRequest = { confirmLeave = false }, title = { Text("حفظ وإغلاق النموذج") }, text = { Text(if(state.saved) "إجاباتك محفوظة كمسودة على الجهاز. يمكنك إكمالها من الاستبيانات ← المسودات." else "لم يتأكد الحفظ المحلي. ابقَ في النموذج حتى يكتمل الحفظ.") },
        confirmButton = { if(state.saved) TextButton(onClick = close, enabled = !busy) { Text("إغلاق") } else TextButton(onClick = { viewModel.retrySave() }, enabled = !busy) { Text("إعادة محاولة الحفظ") } },
        dismissButton = { Column { TextButton(onClick = { confirmLeave = false }) { Text("متابعة التعبئة") }; if(!state.saved && !busy) TextButton(onClick = close) { Text("الخروج دون حفظ آخر التغييرات") } } })
}
private fun reviewValue(field: FormField, value: JsonElement?): String = when {
    value == null || value == JsonNull -> "غير مسجل"
    field.type == "boolean" -> if(FormRules.text(value) in setOf("true", "1")) "نعم" else "لا"
    field.type == "select" -> field.choices.firstOrNull { it.value == FormRules.text(value) }?.label ?: FormRules.text(value)
    field.type == "datetime" -> displayTime(FormRules.text(value))
    field.type == "multiselect" -> (value as? JsonArray).orEmpty().joinToString("، ") { selected -> field.choices.firstOrNull { it.value == FormRules.text(selected) }?.label ?: FormRules.text(selected) }.ifBlank { "غير مسجل" }
    field.type == "repeat" -> (value as? JsonArray).orEmpty().mapIndexed { index, row -> "${index + 1}. " + field.children.joinToString(" · ") { child -> "${child.label}: ${reviewValue(child, (row as? JsonObject)?.get(child.key))}" } }.joinToString("\n").ifBlank { "لا توجد عناصر" }
    else -> FormRules.text(value).ifBlank { "غير مسجل" }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable private fun NativeFormField(field: FormField, value: JsonElement?, errors: Map<String, String>, path: String, enabled: Boolean, focusRequester: FocusRequester? = null, onValue: (JsonElement) -> Unit) {
    val label = field.label + if(field.required) " *" else ""
    val error = errors[path]
    val inputModifier = Modifier.fillMaxWidth().then(if(focusRequester != null) Modifier.focusRequester(focusRequester) else Modifier).testTag("field-$path")
    LaunchedEffect(focusRequester) {
        if(focusRequester != null && field.type !in setOf("repeat", "multiselect")) {
            withFrameNanos { }
            focusRequester.requestFocus()
        }
    }
    when(field.type) {
        "boolean", "select" -> {
            var expanded by remember { mutableStateOf(false) }
            val choices = if(field.type == "boolean") listOf(FormChoice("true", "نعم"), FormChoice("false", "لا")) else field.choices
            val selected = if(field.type == "boolean") when(FormRules.text(value)) { "1" -> "true"; "0" -> "false"; else -> FormRules.text(value) } else FormRules.text(value)
            ExposedDropdownMenuBox(expanded, { if(enabled) expanded = !expanded }) {
                OutlinedTextField(choices.firstOrNull { it.value == selected }?.label ?: selected, {}, readOnly = true, enabled = enabled,
                    label = { Text(label) }, isError = error != null, supportingText = { error?.let { Text(it) } },
                    trailingIcon = { ExposedDropdownMenuDefaults.TrailingIcon(expanded) }, modifier = inputModifier.menuAnchor())
                ExposedDropdownMenu(expanded, { expanded = false }) {
                    if(!field.required) DropdownMenuItem(text = { Text("غير مسجل") }, onClick = { onValue(JsonNull); expanded = false })
                    choices.forEach { choice -> DropdownMenuItem(text = { Text(choice.label) }, onClick = { onValue(if(field.type == "boolean") JsonPrimitive(choice.value == "true") else JsonPrimitive(choice.value)); expanded = false }) }
                }
            }
        }
        "multiselect" -> Column {
            Text(label, style = MaterialTheme.typography.titleSmall)
            val selected = (value as? JsonArray).orEmpty().map { FormRules.text(it) }.toSet()
            field.choices.forEach { choice -> Row(verticalAlignment = Alignment.CenterVertically) {
                Checkbox(choice.value in selected, { checked ->
                    val updated = if(!checked) selected - choice.value else if(choice.value in field.exclusiveChoices) setOf(choice.value) else (selected - field.exclusiveChoices.toSet()) + choice.value
                    onValue(JsonArray(updated.map(::JsonPrimitive)))
                }, enabled = enabled)
                Text(choice.label)
            } }
            error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        }
        "repeat" -> OutlinedCard(Modifier.fillMaxWidth()) { Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text(label, style = MaterialTheme.typography.titleMedium)
            val rows = (value as? JsonArray).orEmpty()
            rows.forEachIndexed { index, item ->
                Text("العنصر ${index + 1}", style = MaterialTheme.typography.labelLarge)
                field.children.forEach { child -> NativeFormField(child, (item as? JsonObject)?.get(child.key), errors, "$path.$index.${child.key}", enabled) { changed ->
                    onValue(JsonArray(rows.mapIndexed { i, row -> if(i == index) JsonObject((row as? JsonObject).orEmpty() + (child.key to changed)) else row }))
                } }
                if(enabled) TextButton(onClick = { onValue(JsonArray(rows.filterIndexed { i, _ -> i != index })) }) { Text("حذف العنصر") }
                HorizontalDivider()
            }
            if(enabled) OutlinedButton(onClick = { onValue(JsonArray(rows + JsonObject(emptyMap()))) }, enabled = rows.size < field.maxItems) { Text("إضافة عنصر") }
            error?.let { Text(it, color = MaterialTheme.colorScheme.error) }
        } }
        "date", "datetime" -> {
            var showDate by remember { mutableStateOf(false) }
            var showTime by remember { mutableStateOf(false) }
            val time = localDateTime(FormRules.text(value))
            val dateTime = field.type == "datetime"
            val display = if(dateTime) time?.format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm:ss")).orEmpty() else FormRules.text(value).take(10)
            OutlinedTextField(display, { onValue(JsonPrimitive(it.take(10))) }, readOnly = dateTime, label = { Text(label) }, enabled = enabled, isError = error != null,
                supportingText = { Text(error ?: if(dateTime) "اختر التاريخ والوقت المحلي" else "سنة-شهر-يوم") }, trailingIcon = { Row {
                    IconButton(onClick = { showDate = true }, enabled = enabled) { Icon(Icons.Default.DateRange, "اختيار التاريخ") }
                    if(dateTime) TextButton(onClick = { showTime = true }, enabled = enabled) { Text("وقت") }
                } },
                singleLine = true, modifier = inputModifier)
            if(showDate) {
                val picker = rememberDatePickerState(initialSelectedDateMillis = time?.toLocalDate()?.toEpochDay()?.times(86400000))
                DatePickerDialog(onDismissRequest = { showDate = false }, confirmButton = { TextButton(onClick = {
                    picker.selectedDateMillis?.let {
                        val day = LocalDate.ofEpochDay(it / 86400000)
                        val text = if(dateTime) day.atTime(time?.toLocalTime() ?: java.time.LocalTime.MIDNIGHT).atZone(java.time.ZoneId.systemDefault()).toOffsetDateTime().toString() else day.format(DateTimeFormatter.ISO_DATE)
                        onValue(JsonPrimitive(text))
                    }; showDate = false
                }) { Text("اختيار") } }, dismissButton = { TextButton(onClick = { showDate = false }) { Text("إلغاء") } }) { DatePicker(picker) }
            }
            if(showTime) {
                val picker = rememberTimePickerState(initialHour = time?.hour ?: 0, initialMinute = time?.minute ?: 0, is24Hour = true)
                AlertDialog(onDismissRequest = { showTime = false }, title = { Text("وقت الزيارة") }, text = { TimePicker(picker) },
                    confirmButton = { TextButton(onClick = {
                        val day = time?.toLocalDate() ?: LocalDate.now()
                        onValue(JsonPrimitive(day.atTime(picker.hour, picker.minute).atZone(java.time.ZoneId.systemDefault()).toOffsetDateTime().toString())); showTime = false
                    }) { Text("اختيار") } }, dismissButton = { TextButton(onClick = { showTime = false }) { Text("إلغاء") } })
            }
        }
        else -> OutlinedTextField(FormRules.text(value), { onValue(JsonPrimitive(it)) }, modifier = inputModifier, enabled = enabled,
            label = { Text(label) }, isError = error != null, supportingText = { error?.let { Text(it) } }, singleLine = field.type != "multiline", minLines = if(field.type == "multiline") 3 else 1,
            keyboardOptions = KeyboardOptions(keyboardType = when(field.type) { "integer" -> KeyboardType.Number; "decimal" -> KeyboardType.Decimal; "phone" -> KeyboardType.Phone; else -> KeyboardType.Text }))
    }
}

private fun localDateTime(value: String): java.time.LocalDateTime? = runCatching { java.time.OffsetDateTime.parse(value).atZoneSameInstant(java.time.ZoneId.systemDefault()).toLocalDateTime() }.getOrNull()
    ?: runCatching { java.time.LocalDateTime.parse(value) }.getOrNull()
    ?: runCatching { LocalDate.parse(value.take(10)).atStartOfDay() }.getOrNull()

private fun displayTime(value: String): String = localDateTime(value)?.format(DateTimeFormatter.ofPattern("yyyy-MM-dd HH:mm")) ?: value

