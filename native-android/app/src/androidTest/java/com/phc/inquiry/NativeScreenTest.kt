package com.phc.inquiry

import android.graphics.Bitmap
import androidx.compose.runtime.*
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.test.junit4.StateRestorationTester
import androidx.test.platform.app.InstrumentationRegistry
import androidx.paging.PagingData
import androidx.paging.compose.collectAsLazyPagingItems
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.authentication.*
import com.phc.inquiry.feature.dashboard.*
import com.phc.inquiry.feature.damageassessment.*
import kotlinx.coroutines.flow.flowOf
import org.junit.Rule
import org.junit.Test
import org.junit.Assert.*
import java.io.File

class NativeScreenTest {
    @get:Rule val compose = createComposeRule()

    private fun snapshot(name: String) {
        compose.waitForIdle()
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        val output = File(context.getExternalFilesDir(null), "design-$name.png")
        output.outputStream().use { compose.onRoot().captureToImage().asAndroidBitmap().compress(Bitmap.CompressFormat.PNG, 100, it) }
    }

    @Test fun enabledHttpLoginAcceptsAccountWithoutATestConfirmation() {
        var submittedEmail: String? = null
        compose.setContent {
            DesignPreviewFrame { LoginScreen(AuthenticationState(restoring = false), true, true) { email, _ -> submittedEmail = email } }
        }
        compose.onNodeWithTag("email").assertIsEnabled().performTextInput("account@example.test")
        compose.onNodeWithTag("password").performScrollTo().assertIsEnabled().performTextInput("fixture-password")
        compose.onNodeWithTag("login").performScrollTo().assertIsEnabled().performClick()
        compose.runOnIdle { assertEquals("account@example.test", submittedEmail) }
    }

    @Test fun invalidEmailNeverSubmits() {
        var submitted = false
        compose.setContent { DesignPreviewFrame { LoginScreen(AuthenticationState(restoring = false), false, false) { _, _ -> submitted = true } } }
        compose.onNodeWithTag("email").performTextInput("invalid")
        compose.onNodeWithTag("password").performScrollTo().performTextInput("fixture-password")
        compose.onNodeWithTag("login").performScrollTo().performClick()
        compose.onNodeWithText("أدخل بريدًا إلكترونيًا صحيحًا.").assertExists()
        compose.runOnIdle { assertFalse(submitted) }
    }

    @Test fun disabledHttpTransportStillBlocksLogin() {
        compose.setContent { DesignPreviewFrame { LoginScreen(AuthenticationState(restoring = false), true, false) { _, _ -> error("Must not submit") } } }
        compose.onNodeWithTag("email").assertIsNotEnabled()
        compose.onNodeWithTag("login").performScrollTo().assertIsNotEnabled()
    }

    @Test fun previewLoginLight() { compose.setContent { LoginPreview() }; snapshot("login-light") }
    @Test fun previewLoginDark() { compose.setContent { LoginDarkPreview() }; snapshot("login-dark") }

    @Test fun authorizedDashboardCardNavigatesToCorrectSector() {
        var selected: String? = null
        compose.setContent { DesignPreviewFrame { DashboardScreen("مستخدم تجريبي", DashboardState(false, listOf(Sector("buildings", ""), Sector("housing-units", ""))), { selected = it }, {}) } }
        snapshot("dashboard")
        compose.onNodeWithTag("sector-housing-units").performScrollTo().performClick()
        compose.runOnIdle { assertEquals("housing-units", selected) }
    }

    @Test fun searchSubmitsOnlyOnActionAndOpensInternalRecordId() {
        var query: String? = null; var selected: Long? = null
        compose.setContent { DesignPreviewFrame {
            val records = remember { flowOf(PagingData.from(listOf(PreviewRecord))) }.collectAsLazyPagingItems()
            SearchScreen("buildings", records, true, 1, { query = it }, { selected = it })
        } }
        compose.onNodeWithTag("record-search").performTextInput("الأمل")
        compose.runOnIdle { assertNull(query) }
        compose.onNodeWithTag("submit-search").performClick()
        compose.runOnIdle { assertEquals("الأمل", query) }
        compose.waitUntil(10000) { compose.onAllNodesWithTag("record-17").fetchSemanticsNodes().isNotEmpty() }
        snapshot("search")
        compose.onNodeWithTag("record-17").performScrollTo().performClick()
        compose.runOnIdle { assertEquals(17L, selected) }
    }

    @Test fun searchDraftSurvivesStateRestoration() {
        val restoration = StateRestorationTester(compose)
        restoration.setContent { DesignPreviewFrame {
            val records = remember { flowOf(PagingData.empty<InquiryRecord>()) }.collectAsLazyPagingItems()
            SearchScreen("buildings", records, false, null, {}, {})
        } }
        compose.onNodeWithTag("record-search").performTextInput("اسم تجريبي")
        restoration.emulateSavedInstanceStateRestore()
        compose.onNodeWithTag("record-search").assertTextContains("اسم تجريبي")
    }

    @Test fun detailShowsMissingValuesAndCopyAction() {
        compose.setContent { DesignPreviewFrame { DetailScreen(DetailState(false, PreviewRecord.copy(municipality = null)), {}) } }
        snapshot("details")
        compose.onNodeWithTag("copy-record").performScrollTo().assertIsEnabled().performClick()
        compose.runOnIdle {
            val context = InstrumentationRegistry.getInstrumentation().targetContext
            val clipboard = context.getSystemService(android.content.Context.CLIPBOARD_SERVICE) as android.content.ClipboardManager
            assertEquals("17", clipboard.primaryClip?.getItemAt(0)?.text?.toString())
        }
        compose.onNodeWithText("غير متوفر").assertExists()
    }
}
