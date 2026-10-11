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
    @Test fun homeShowsOnlyProvidedModulesAndOpensPublishedSurveys() {
        var opened = false
        compose.setContent { CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) { PhcTheme {
            PortalHome("حساب اختبار", PortalState(loading = false, modules = listOf(PortalModule("damage_assessment", "حصر الأضرار"))),
                {}, { opened = true }, {}, {}, {})
        } } }
        compose.onNodeWithTag("module-damage_assessment").assertExists()
        compose.onNodeWithTag("module-heks").assertDoesNotExist()
        compose.onNodeWithTag("home-surveys").performClick()
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
}

