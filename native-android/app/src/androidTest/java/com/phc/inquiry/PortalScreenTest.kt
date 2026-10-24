package com.phc.inquiry

import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.unit.LayoutDirection
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.phc.inquiry.core.ui.PhcTheme
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import org.junit.Assert.assertEquals
import org.junit.Rule
import org.junit.Test
import org.junit.runner.RunWith

@RunWith(AndroidJUnit4::class)
class PortalScreenTest {
    @get:Rule val compose = createComposeRule()
    @Test fun locallyUnlockedCatalogHidesServerDownloadActions() {
        val form = PublishedForm("heks-phase-three", "heks-shelter", "HEKS الثالثة", "v1", canSubmit = true)
        compose.setContent { PhcTheme {
            SurveysScreen(PortalState(loading = false, forms = listOf(form), offlineReadyKeys = setOf(form.key), usingSavedCatalog = true, localOnly = true), 0, {}, {}, {}, {}, {})
        } }
        compose.onNodeWithTag("download-offline-forms").assertDoesNotExist()
        compose.onNodeWithText("المرسلة").assertDoesNotExist()
        compose.onNodeWithTag("form-${form.key}").assertExists()
    }
    @Test fun offlineCatalogOffersNewSurveysInsteadOfBlockingTheScreenWithANetworkError() {
        var opened = false
        var downloaded = false
        val form = PublishedForm("heks-phase-three", "heks-shelter", "HEKS الثالثة", "v1", canSubmit = true)
        compose.setContent { CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) { PhcTheme {
            SurveysScreen(PortalState(loading = false, forms = listOf(form), error = "NET-CONNECT", offlineReadyKeys = setOf(form.key), usingSavedCatalog = true), 0,
                { opened = true }, {}, {}, {}, {}, downloadOffline = { downloaded = true })
        } } }
        compose.onNodeWithText("تعمل بنسخة النماذج المحفوظة على الجهاز").assertExists()
        compose.onNodeWithTag("form-${form.key}").performScrollTo().performClick()
        compose.onNodeWithTag("download-offline-forms").performScrollTo().performClick()
        compose.runOnIdle { assertEquals(true, opened); assertEquals(true, downloaded) }
    }
    @Test fun homeShowsThePermittedHrCardAndOpensAttendanceModule() {
        var opened: String? = null
        compose.setContent { CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) { PhcTheme {
            PortalHome("حساب اختبار", PortalState(loading = false, modules = listOf(PortalModule("hr", "الموارد البشرية · الحضور", "الحضور والانصراف بالبصمة"))),
                { opened = it }, {}, {}, {}, {})
        } } }
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasTestTag("module-hr"))
        compose.onNodeWithTag("module-hr").assertExists().performClick()
        compose.runOnIdle { assertEquals("hr", opened) }
    }
    @Test fun workspaceTasksShowsPendingReceiptAndOpensTheSameDraft() {
        val pending = DraftSummary("pending-fixture", "borrower-survey", "مسودة متابعة", "damage_assessment_borrowers", null, "2026-10-11T10:00:00Z", true, null)
        var opened: String? = null
        var sent = false
        compose.setContent { PhcTheme {
            WorkspaceTasksScreen(PortalState(loading = false, drafts = listOf(pending)), {}, { sent = true }, {}, {}, { opened = it.uuid })
        } }
        compose.onNodeWithText("بانتظار تأكيد الاستلام", substring = true).assertExists()
        compose.onNodeWithTag("task-pending-fixture").performScrollTo().performClick()
        compose.onNodeWithTag("tasks-sent").performScrollTo().performClick()
        compose.runOnIdle { assertEquals("pending-fixture", opened); assertEquals(true, sent) }
    }
    @Test fun workspaceStatisticsShowsUnavailableInsteadOfZeroAndOpensStatistics() {
        var opened = false
        compose.setContent { PhcTheme {
            com.phc.inquiry.feature.dashboard.WorkspaceStatisticsCard(
                com.phc.inquiry.feature.dashboard.WorkspaceStatisticsState(loading = false, counts = listOf(
                    com.phc.inquiry.feature.dashboard.WorkspaceCount("buildings", 12),
                    com.phc.inquiry.feature.dashboard.WorkspaceCount("housing-units", failed = true))), { opened = true }, {})
        } }
        compose.onNodeWithTag("home-total-buildings").assertTextEquals("12")
        compose.onNodeWithTag("home-total-housing-units").assertTextEquals("—")
        compose.onNodeWithTag("home-open-statistics").performClick()
        compose.runOnIdle { assertEquals(true, opened) }
    }
    @Test fun homeShowsOnlyProvidedModulesAndOpensPublishedSurveys() {
        var opened = false
        compose.setContent { CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) { PhcTheme {
            PortalHome("حساب اختبار", PortalState(loading = false, modules = listOf(PortalModule("damage_assessment", "حصر الأضرار")), forms = listOf(PublishedForm("fixture", "damage_assessment", "استبيان", "v1"))),
                {}, { opened = true }, {}, {}, {})
        } } }
        compose.onNodeWithTag("module-damage_assessment").performScrollTo().assertExists()
        compose.onNodeWithTag("module-heks").assertDoesNotExist()
        compose.onNodeWithTag("home-surveys").performScrollTo().performClick()
        compose.runOnIdle { assertEquals(true, opened) }
    }
    @Test fun localDraftsDistinguishUnconfirmedSubmissionsAndPreventTheirDeletion() {
        val draft = DraftSummary("fixture-uuid", "borrower-survey", "استبيان تجريبي", "damage_assessment_borrowers", null, "2026-10-11T10:00:00Z", true, null)
        var opened: String? = null
        compose.setContent { CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) { PhcTheme {
            SurveysScreen(PortalState(loading = false, drafts = listOf(draft)), 1, {}, { opened = it.uuid }, {}, {}, {})
        } } }
        compose.onNodeWithText("بانتظار تأكيد الاستلام", substring = true).assertExists()
        compose.onNodeWithText("حذف النسخة المحلية").assertDoesNotExist()
        compose.onNodeWithText("استبيان تجريبي").performClick()
        compose.runOnIdle { assertEquals("fixture-uuid", opened) }
    }
    @Test fun receiptFailureDoesNotBlockHomeModules() {
        var opened: String? = null
        compose.setContent { PhcTheme {
            PortalHome("اختبار", PortalState(loading = false, modules = listOf(PortalModule("heks", "HEKS")), receiptsError = "خطأ سجل الإرساليات"),
                { opened = it }, {}, {}, {}, {})
        } }
        compose.onNodeWithText("خطأ سجل الإرساليات").assertDoesNotExist()
        compose.onNodeWithTag("module-heks").performScrollTo().performClick()
        compose.runOnIdle { assertEquals("heks", opened) }
    }
    @Test fun sentTabShowsReceiptFailureAndRetryWithoutFalseEmptyState() {
        var retried = 0
        compose.setContent { PhcTheme {
            SurveysScreen(PortalState(loading = false, receiptsError = "خطأ سجل الإرساليات"), 2, {}, {}, {}, {}, {}, { retried++ })
        } }
        compose.onNodeWithText("خطأ سجل الإرساليات").assertExists()
        compose.onNodeWithText("لا توجد إرساليات").assertDoesNotExist()
        compose.onNodeWithText("إعادة المحاولة").performClick()
        compose.runOnIdle { assertEquals(2, retried) }
    }
    @Test fun draftStorageWarningDoesNotBlockHomeModulesOrClaimNoSavedDrafts() {
        var opened: String? = null
        compose.setContent { PhcTheme {
            PortalHome("اختبار", PortalState(loading = false, modules = listOf(PortalModule("heks", "HEKS")), forms = listOf(PublishedForm("heks-beneficiary", "heks", "HEKS", "v")), draftsError = DRAFT_STORAGE_MESSAGE),
                { opened = it }, {}, {}, {}, {})
        } }
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasText("—"))
        compose.onNodeWithText("—").assertExists()
        compose.onNodeWithText("تعذّر تحميل البيانات").assertDoesNotExist()
        compose.onNodeWithTag("module-heks").performScrollTo().performClick()
        compose.runOnIdle { assertEquals("heks", opened) }
    }
    @Test fun draftTabOffersLocalRetryWithoutFalseEmptyMessage() {
        var retries = 0
        compose.setContent { PhcTheme {
            SurveysScreen(PortalState(loading = false, draftsError = DRAFT_STORAGE_MESSAGE), 1, {}, {}, {}, {}, {}, retryDrafts = { retries++ })
        } }
        compose.onNodeWithText("لا توجد مسودات").assertDoesNotExist()
        compose.onNodeWithText("إعادة محاولة فتح المسودات").performScrollTo().performClick()
        compose.runOnIdle { assertEquals(1, retries) }
    }
    @Test fun homeInquiryShortcutOpensUnifiedInquiryInTheUpdatedDashboard() {
        var opened = false
        compose.setContent { CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) { PhcTheme {
            PortalHome("حساب اختبار", PortalState(loading = false), {}, {}, {}, { opened = true }, {})
        } } }
        compose.onNodeWithTag("home-inquiry").performScrollTo().performClick()
        compose.runOnIdle { assertEquals(true, opened) }
    }
}

