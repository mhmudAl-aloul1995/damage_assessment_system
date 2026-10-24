package com.phc.inquiry

import android.graphics.Bitmap
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.test.platform.app.InstrumentationRegistry
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import org.junit.Assert.*
import org.junit.Rule
import org.junit.Test
import java.io.File

class ShelterWorkspaceScreenTest {
    @get:Rule val compose = createComposeRule()
    private val form = PublishedForm("heks-phase-three", SHELTER_WORKSPACE, "استبيان المرحلة الثالثة", "v1", canSubmit = true)
    private val module = PortalModule(SHELTER_WORKSPACE, "HEKS · المرحلة الثالثة", recordSearch = true)
    private val record = PortalRecord(71, SHELTER_WORKSPACE, "مستفيد تجريبي", "HEKS-071 · غزة", "بحاجة لاستكمال", details = listOf(
        DetailField("code", "الكود", "HEKS-071"), DetailField("name", "اسم المستفيد", "مستفيد تجريبي"),
        DetailField("identity_number", "الهوية", "TEST-071"), DetailField("phone", "الهاتف", null),
        DetailField("area", "المنطقة", "غزة"), DetailField("visit_date", "تاريخ الزيارة", "2026-10-24")))

    private fun preview(dark: Boolean = false, large: Boolean = false, content: @Composable () -> Unit) {
        compose.setContent {
            CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl,
                LocalDensity provides Density(LocalDensity.current.density, if (large) 1.5f else 1f)) {
                PhcTheme(darkTheme = dark) {
                    Surface(Modifier.width(if (large) 320.dp else 390.dp).fillMaxHeight(), color = MaterialTheme.colorScheme.background) {
                        Column {
                            Box(Modifier.weight(1f)) { content() }
                            WorkspaceNavigationBar("module/heks-shelter", {})
                        }
                    }
                }
            }
        }
    }
    private fun snapshot(name: String) {
        compose.waitForIdle()
        val instrumentation = InstrumentationRegistry.getInstrumentation()
        instrumentation.waitForIdleSync()
        instrumentation.uiAutomation.waitForIdle(500, 5000)
        val context = instrumentation.targetContext
        File(context.getExternalFilesDir(null), "phase2-$name.png").outputStream().use {
            requireNotNull(instrumentation.uiAutomation.takeScreenshot()).compress(Bitmap.CompressFormat.PNG, 100, it)
        }
    }

    @Test fun workspaceToRecordToFormUsesTheSelectedRecordAndRealActions() {
        var opened: Pair<String, Long>? = null
        preview {
            var screen by remember { mutableIntStateOf(0) }
            when (screen) {
                0 -> ShelterWorkspaceScreen(module, PortalState(loading = false, forms = listOf(form), offlineReadyKeys = setOf(form.key)), { screen = 1 }, {}, {}, {})
                1 -> ShelterRecordsScreen(PortalRecordsState(rows = listOf(record), total = 1, page = 1, searched = true), false, {}, {}, {}, { assertEquals(71L, it); screen = 2 })
                else -> ShelterRecordScreen(PortalDetailState(false, record), listOf(form), emptyList(), {}, { selected, id -> opened = selected.key to id }, {})
            }
        }
        snapshot("workspace-light")
        compose.onNodeWithTag("shelter-records").performClick()
        compose.onNodeWithTag("shelter-record-71").performScrollTo().performClick()
        compose.onNodeWithTag("shelter-edit-${form.key}").performScrollTo().performClick()
        compose.runOnIdle { assertEquals(form.key to 71L, opened) }
    }

    @Test fun recordsShowTheServerTotalSeparatelyFromTheLoadedPage() {
        preview { ShelterRecordsScreen(PortalRecordsState(rows = listOf(record), total = 41, page = 1, last = 3, searched = true), false, {}, {}, {}, {}) }
        compose.onNodeWithText("41 سجل").assertIsDisplayed()
        snapshot("records-light")
        compose.onNodeWithTag("shelter-results").performScrollToNode(hasTestTag("shelter-more"))
        compose.onNodeWithText("عرض المزيد · 1 من 41").assertIsDisplayed()
    }

    @Test fun lightDetailsPreserveAdditionalFieldsReturnedByTheServer() {
        val extended = record.copy(details = record.details + DetailField("notes", "ملاحظات", "ملاحظة تجريبية"))
        preview { ShelterRecordScreen(PortalDetailState(false, extended), listOf(form), emptyList(), {}, { _, _ -> }, {}) }
        snapshot("detail-light")
        compose.onNodeWithTag("shelter-detail").performScrollToNode(hasText("بيانات إضافية"))
        compose.onNodeWithText("ملاحظة تجريبية").assertIsDisplayed()
    }

    @Test fun darkDetailGroupsFieldsAndKeepsMissingValuesExplicit() {
        preview(dark = true) { ShelterRecordScreen(PortalDetailState(false, record), listOf(form), emptyList(), {}, { _, _ -> }, {}) }
        snapshot("detail-dark")
        compose.onNodeWithTag("shelter-detail").performScrollToNode(hasText("غير مسجل"))
        compose.onNodeWithText("غير مسجل").assertIsDisplayed()
        compose.onNodeWithTag("shelter-detail").performScrollToNode(hasText("بيانات الزيارة"))
        compose.onNodeWithText("بيانات الزيارة").assertIsDisplayed()
    }

    @Test fun matchingDraftResumesWithoutCreatingASecondDraft() {
        val draft = DraftSummary("saved", form.key, form.title, SHELTER_WORKSPACE, 71, "2026-10-24T10:00:00Z", true, null)
        var opened: String? = null
        preview { ShelterRecordScreen(PortalDetailState(false, record), listOf(form), listOf(draft), {}, { _, _ -> error("Should resume") }, { opened = it.uuid }) }
        compose.onNodeWithTag("shelter-edit-${form.key}").assertDoesNotExist()
        compose.onNodeWithTag("shelter-record-resume").performScrollTo().performClick()
        compose.runOnIdle { assertEquals("saved", opened) }
    }

    @Test fun deniedAndUnrelatedFormsDoNotOfferAnEditAction() {
        preview { ShelterRecordScreen(PortalDetailState(false, record), listOf(form.copy(canSubmit = false), form.copy(key = "other", module = "heks")), emptyList(), {}, { _, _ -> error("Unauthorized") }, {}) }
        compose.onNodeWithTag("shelter-edit-${form.key}").assertDoesNotExist()
        compose.onNodeWithTag("shelter-edit-other").assertDoesNotExist()
    }

    @Test fun keyboardSearchAndClearSubmitTheDisplayedQuery() {
        val queries = mutableListOf<String>()
        preview { ShelterRecordsScreen(PortalRecordsState(), false, { queries += it }, {}, {}, {}) }
        compose.onNodeWithTag("shelter-query").performTextInput("مستفيد")
        compose.onNodeWithTag("shelter-query").performImeAction()
        compose.onNodeWithContentDescription("مسح البحث").performClick()
        compose.runOnIdle { assertEquals(listOf("مستفيد", ""), queries) }
    }

    @Test fun pagingAndFailureRetryAreSeparateFromAnEmptyResult() {
        var more = false
        var retried = false
        preview { ShelterRecordsScreen(PortalRecordsState(rows = listOf(record), total = 2, page = 1, last = 2, searched = true, error = "تعذّر الاتصال", appendFailed = true), false, {}, { more = true }, { retried = true }, {}) }
        compose.onNodeWithText("إعادة المحاولة").performScrollTo().performClick()
        compose.onNodeWithTag("shelter-results").performScrollToNode(hasTestTag("shelter-more"))
        compose.onNodeWithTag("shelter-more").performClick()
        compose.onNodeWithText("لا توجد سجلات مطابقة").assertDoesNotExist()
        compose.runOnIdle { assertTrue(retried); assertTrue(more) }
    }

    @Test fun narrowLargeTextKeepsWorkspaceActionsReachable() {
        var offline = false
        var selected: String? = null
        preview(large = true) { ShelterWorkspaceScreen(module, PortalState(loading = false, forms = listOf(form)), {}, { selected = it.key }, {}, { offline = true }) }
        snapshot("workspace-large-text")
        compose.onNodeWithTag("shelter-workspace").performScrollToNode(hasTestTag("shelter-form-${form.key}"))
        compose.onNodeWithTag("shelter-form-${form.key}").performClick()
        compose.onNodeWithTag("shelter-workspace").performScrollToNode(hasTestTag("shelter-offline"))
        compose.onNodeWithTag("shelter-offline").performClick()
        compose.runOnIdle { assertEquals(form.key, selected); assertTrue(offline) }
    }
}
