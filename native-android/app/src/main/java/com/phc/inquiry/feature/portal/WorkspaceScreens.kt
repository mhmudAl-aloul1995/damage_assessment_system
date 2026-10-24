package com.phc.inquiry.feature.portal

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.LazyListScope
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.drawBehind
import androidx.compose.ui.geometry.Offset
import androidx.compose.ui.geometry.Size
import androidx.compose.ui.graphics.drawscope.Stroke
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.unit.dp
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.PortalModule

@Composable
fun PortalHome(
    name: String,
    state: PortalState,
    onModule: (String) -> Unit,
    onSurveys: () -> Unit,
    onDrafts: () -> Unit,
    onWorkspaces: () -> Unit,
    retry: () -> Unit,
    onTasks: () -> Unit = onDrafts,
    onDraft: (DraftSummary) -> Unit = { onDrafts() },
) {
    BoxWithConstraints(Modifier.fillMaxSize()) {
        val columns = if (maxWidth >= 360.dp && LocalDensity.current.fontScale < 1.3f) 2 else 1
        LazyColumn(Modifier.fillMaxSize().testTag("workspace-home"), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    Column(Modifier.weight(1f), verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(if (name.isBlank()) "أهلًا بك" else "أهلًا، $name", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        Text("مساحة عملك", style = MaterialTheme.typography.headlineSmall)
                    }
                    BrandMark(88)
                }
            }
            item { WorkspaceWelcome(state, onModule, onSurveys, onWorkspaces) }
            latestUnfinishedDraft(state.drafts)?.let { draft -> item {
                PortalCard("تابع من حيث توقفت", draft.title + if (draft.pending) " · بانتظار تأكيد الاستلام" else " · مسودة محفوظة",
                    { onDraft(draft) }, tag = "home-continue-drafts", icon = Icons.Default.Edit)
            } }
            state.draftsError?.let { item {
                PortalCard("تعذّر فتح بعض المسودات", "افتح أعمالي لإعادة المحاولة. باقي الخدمات متاحة.", onTasks,
                    tag = "home-drafts-warning", icon = Icons.Default.Warning)
            } }
            item {
                Row(Modifier.fillMaxWidth(), verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text("مساحات العمل", Modifier.weight(1f), style = MaterialTheme.typography.titleMedium)
                    TextButton(onClick = onWorkspaces, modifier = Modifier.testTag("home-inquiry")) { Text("عرض الكل") }
                }
            }
            if (state.loading && state.modules.isEmpty()) item { LoadingState() }
            state.error?.let { item { ErrorState(it, retry) } }
            workspaceModules(state.modules, columns, onModule)
            if (!state.loading && state.modules.isEmpty() && state.error == null) item {
                EmptyState("لا توجد مساحات متاحة", "تظهر مساحات العمل وفق صلاحيات حسابك.", Icons.Default.Lock)
            }
            item { Text("متابعة أعمالك", style = MaterialTheme.typography.titleMedium) }
            if (columns == 2) item {
                Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    DraftMetric(state, onDrafts, Modifier.weight(1f))
                    FormsMetric(state, onSurveys, Modifier.weight(1f))
                }
            } else {
                item { DraftMetric(state, onDrafts, Modifier.fillMaxWidth()) }
                item { FormsMetric(state, onSurveys, Modifier.fillMaxWidth()) }
            }
            item {
                OutlinedButton(onClick = onTasks, modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp).testTag("home-tasks")) {
                    Text("فتح أعمالي والإرساليات")
                }
            }
            if (state.usingSavedCatalog) item {
                Text("تعرض نسخة محفوظة من مساحات العمل. أعد التحديث عند توفر الاتصال.", style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

fun latestUnfinishedDraft(drafts: List<DraftSummary>): DraftSummary? =
    drafts.filter { it.receiptId == null }.maxByOrNull { it.updatedAt }

@Composable
private fun WorkspaceWelcome(state: PortalState, onModule: (String) -> Unit, onSurveys: () -> Unit, onWorkspaces: () -> Unit) {
    val palette = MaterialTheme.colorScheme
    val hasForms = state.forms.isNotEmpty()
    val hasAttendance = state.modules.any { it.key == "hr" }
    Surface(shape = RoundedCornerShape(20.dp), color = palette.primary, contentColor = palette.onPrimary) {
        Column(Modifier.fillMaxWidth().drawBehind {
            val stroke = Stroke(1.dp.toPx())
            repeat(2) { index ->
                drawArc(palette.onPrimary.copy(alpha = 0.12f), 180f, 180f, false,
                    Offset(-48.dp.toPx() + index * 20.dp.toPx(), 18.dp.toPx() + index * 22.dp.toPx()),
                    Size(170.dp.toPx(), 240.dp.toPx()), style = stroke)
            }
        }.padding(22.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            Text("كل أعمالك، أقرب إليك", style = MaterialTheme.typography.titleLarge)
            Text("خدمات المنظومة في مكان واحد", style = MaterialTheme.typography.bodyMedium)
            Button(
                onClick = { when { hasForms -> onSurveys(); hasAttendance -> onModule("hr"); else -> onWorkspaces() } },
                modifier = Modifier.heightIn(min = 48.dp).testTag(when { hasForms -> "home-surveys"; hasAttendance -> "home-attendance"; else -> "home-workspaces" }),
                shape = RoundedCornerShape(12.dp),
                colors = ButtonDefaults.buttonColors(containerColor = palette.onPrimary, contentColor = palette.primary),
            ) { Text(when { hasForms -> "تعبئة استبيان"; hasAttendance -> "الحضور والانصراف"; else -> "استعرض مساحات العمل" }) }
        }
    }
}

@Composable
private fun DraftMetric(state: PortalState, onDrafts: () -> Unit, modifier: Modifier) {
    PortalMetric(if (state.draftsError != null && state.drafts.isEmpty()) "—" else state.drafts.count { it.receiptId == null }.toString(),
        if (state.draftsError != null) "مسودات يمكن فتحها" else "مسودات على الجهاز", Icons.Default.Edit, onDrafts, modifier)
}

@Composable
private fun FormsMetric(state: PortalState, onSurveys: () -> Unit, modifier: Modifier) {
    PortalMetric(if (state.loading || (state.error != null && state.forms.isEmpty())) "—" else state.forms.size.toString(),
        "نماذج متاحة", Icons.Default.List, onSurveys, modifier)
}

private fun LazyListScope.workspaceModules(modules: List<PortalModule>, columns: Int, onModule: (String) -> Unit) {
    items(modules.chunked(columns), key = { row -> row.joinToString("|") { it.key } }) { row ->
        Row(Modifier.fillMaxWidth().height(IntrinsicSize.Min), horizontalArrangement = Arrangement.spacedBy(12.dp)) {
            row.forEach { module ->
                Card(onClick = { onModule(module.key) }, modifier = Modifier.weight(1f).fillMaxHeight().testTag("module-${module.key}"),
                    shape = RoundedCornerShape(16.dp), colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface),
                    border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
                        IconTile(moduleIcon(module.key))
                        Text(module.title, style = MaterialTheme.typography.titleSmall)
                        if (module.description.isNotBlank()) Text(module.description, style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
                    }
                }
            }
            if (row.size < columns) Spacer(Modifier.weight(1f))
        }
    }
}

@Composable
fun PortalInquiry(state: PortalState, onModule: (String) -> Unit, retry: () -> Unit) {
    BoxWithConstraints(Modifier.fillMaxSize()) {
        val columns = if (maxWidth >= 360.dp && LocalDensity.current.fontScale < 1.3f) 2 else 1
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
            item {
                Text("مساحات العمل", style = MaterialTheme.typography.headlineSmall)
                Text("اختر المساحة للوصول إلى سجلاتها وخدماتها.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            if (state.loading && state.modules.isEmpty()) item { LoadingState() }
            state.error?.let { item { ErrorState(it, retry) } }
            workspaceModules(state.modules, columns, onModule)
            if (!state.loading && state.modules.isEmpty() && state.error == null) item {
                EmptyState("لا توجد مساحات متاحة", "راجع مسؤول المنظومة للتحقق من صلاحيات حسابك.", Icons.Default.Lock)
            }
            if (state.usingSavedCatalog) item { Text("مساحات من النسخة المحفوظة على الجهاز", style = MaterialTheme.typography.bodySmall) }
        }
    }
}

@Composable
fun OfflinePreparationCard(state: PortalState, downloadOffline: () -> Unit) {
    OutlinedCard(Modifier.fillMaxWidth().testTag("offline-forms-status")) {
        Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Text(if (state.usingSavedCatalog) "تعمل بنسخة النماذج المحفوظة على الجهاز" else "تجهيز العمل دون إنترنت", style = MaterialTheme.typography.titleMedium)
            Text("${state.offlineReadyKeys.size} نماذج جاهزة لبدء استبيانات جديدة دون نت. السجلات الموجودة تحتاج اتصالًا عند فتحها أول مرة.", style = MaterialTheme.typography.bodyMedium)
            if (state.offlineUpdatedAt.isNotBlank()) Text("آخر تنزيل: ${displayTime(state.offlineUpdatedAt)}", style = MaterialTheme.typography.bodySmall)
            state.offlineNotice?.let { Text(it, style = MaterialTheme.typography.bodySmall) }
            if (state.error != null && state.forms.isNotEmpty()) Text("تعذّر الاتصال بالسيرفر. يمكنك استخدام النماذج الجاهزة واستكمال المسودات؛ الإرسال يحتاج اتصالًا.", style = MaterialTheme.typography.bodySmall)
            if (!state.localOnly) OutlinedButton(onClick = downloadOffline, enabled = !state.loading && !state.preparingOffline,
                modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp).testTag("download-offline-forms")) {
                Text(if (state.preparingOffline) "جارٍ تنزيل النماذج…" else "تنزيل النماذج للميدان / تحديثها")
            }
        }
    }
}

@Composable
fun OfflinePreparationScreen(state: PortalState, download: () -> Unit, onForms: () -> Unit, accessContent: @Composable () -> Unit) {
    LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(18.dp)) {
        item { Text("جاهز للميدان", style = MaterialTheme.typography.headlineSmall) }
        item { OfflinePreparationCard(state, download) }
        state.error?.let { item { ErrorState(it, download) } }
        item { accessContent() }
        item { OutlinedButton(onClick = onForms, modifier = Modifier.fillMaxWidth().heightIn(min = 48.dp)) { Text("فتح النماذج المتاحة") } }
    }
}
