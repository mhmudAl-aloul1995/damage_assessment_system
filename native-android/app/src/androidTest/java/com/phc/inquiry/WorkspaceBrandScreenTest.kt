package com.phc.inquiry

import android.graphics.Bitmap
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.unit.Density
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.compose.ui.semantics.SemanticsActions
import androidx.compose.ui.text.TextLayoutResult
import androidx.core.content.res.ResourcesCompat
import androidx.test.platform.app.InstrumentationRegistry
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import org.junit.Assert.*
import org.junit.Rule
import org.junit.Test
import java.io.File

class WorkspaceBrandScreenTest {
    @get:Rule val compose = createComposeRule()
    private val modules = listOf(
        PortalModule("heks", "HEKS · إصلاح المأوى", "المستفيدون وزيارات المتابعة", recordSearch = true),
        PortalModule("reh", "REH · التأهيل", "السجلات والنماذج", recordSearch = true),
        PortalModule("hr", "شؤون الموظفين", "الحضور والانصراف"),
        PortalModule("damage_assessment", "حصر الأضرار", "السجلات والاستبيانات"),
    )

    private fun snapshot(name: String) {
        compose.waitForIdle()
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        File(context.getExternalFilesDir(null), "brand-$name.png").outputStream().use {
            compose.onRoot().captureToImage().asAndroidBitmap().compress(Bitmap.CompressFormat.PNG, 100, it)
        }
    }

    private fun homePreview(dark: Boolean, fontScale: Float = 1f, width: Int = 390) {
        compose.setContent {
            CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl, LocalDensity provides Density(LocalDensity.current.density, fontScale)) {
                PhcTheme(darkTheme = dark) {
                    Surface(Modifier.width(width.dp).fillMaxHeight(), color = MaterialTheme.colorScheme.background) {
                        Column {
                            Box(Modifier.weight(1f)) { PortalHome("حساب معاينة", PortalState(loading = false, modules = modules), {}, {}, {}, {}, {}) }
                            WorkspaceNavigationBar("portal-home", {})
                        }
                    }
                }
            }
        }
    }

    @Test fun homeLightUsesBundledKufiAndShowsGeneralWorkspaces() {
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        assertNotNull(ResourcesCompat.getFont(context, R.font.droid_arabic_kufi_regular))
        homePreview(false)
        compose.onNodeWithText("مساحة عملك").assertIsDisplayed()
        compose.onNodeWithTag("nav-portal-home").assertIsSelected()
        compose.onNodeWithTag("home-statistics").assertDoesNotExist()
        snapshot("home-light")
    }

    @Test fun homeDarkKeepsTheSameNavigationAndReadableModules() {
        homePreview(true)
        compose.onNodeWithText("مساحة عملك").assertIsDisplayed()
        snapshot("home-dark")
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasTestTag("module-reh"))
        compose.onNodeWithTag("module-reh").assertIsDisplayed()
    }

    @Test fun narrowHomeAtLargeTextKeepsModuleAndTaskActionsReachable() {
        homePreview(false, 1.5f, 320)
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasTestTag("module-hr"))
        compose.onNodeWithTag("module-hr").assertIsDisplayed()
        snapshot("home-large-text")
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasTestTag("home-tasks"))
        compose.onNodeWithTag("home-tasks").assertIsDisplayed()
        compose.onNodeWithTag("nav-portal-account").assertIsDisplayed()
        for (destination in WorkspaceDestination.entries) {
            val layouts = mutableListOf<TextLayoutResult>()
            val label = compose.onNodeWithText(destination.label, useUnmergedTree = true)
            label.performSemanticsAction(SemanticsActions.GetTextLayoutResult) { it(layouts) }
            assertEquals(1, layouts.single().lineCount)
            assertFalse("${destination.label}: width overflow=${layouts.single().didOverflowWidth}, height overflow=${layouts.single().didOverflowHeight}", layouts.single().hasVisualOverflow)
            val labelBounds = label.fetchSemanticsNode().boundsInRoot
            val itemBounds = compose.onNodeWithTag("nav-${destination.route}").fetchSemanticsNode().boundsInRoot
            val textLeft = labelBounds.left + layouts.single().getLineLeft(0)
            val textRight = labelBounds.left + layouts.single().getLineRight(0)
            assertTrue("${destination.label}: text $textLeft..$textRight, label $labelBounds, item $itemBounds",
                textLeft >= itemBounds.left && textRight <= itemBounds.right)
        }
    }

    @Test fun fourNavigationActionsSelectTheirOwnDestinationWithoutGlobalMapTab() {
        compose.setContent { PhcTheme {
            var route by remember { mutableStateOf("portal-home") }
            WorkspaceNavigationBar(route) { route = it }
        } }
        for (destination in WorkspaceDestination.entries) {
            compose.onNodeWithTag("nav-${destination.route}").performClick().assertIsSelected()
        }
        compose.onNodeWithText("الخريطة").assertDoesNotExist()
    }

    @Test fun continueActionOpensTheActualLatestDraft() {
        val draft = DraftSummary("latest-draft", "form", "مسودة ميدانية", "heks", null, "2026-10-23T10:00:00Z", false, null)
        var selected: String? = null
        compose.setContent { PhcTheme {
            PortalHome("اختبار", PortalState(loading = false, drafts = listOf(draft)), {}, {}, {}, {}, {}, onDraft = { selected = it.uuid })
        } }
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasTestTag("home-continue-drafts"))
        compose.onNodeWithTag("home-continue-drafts").performClick()
        compose.runOnIdle { assertEquals(draft.uuid, selected) }
    }

    @Test fun workspaceListDoesNotInventAnUnauthorizedModule() {
        var selected: String? = null
        compose.setContent { PhcTheme { PortalInquiry(PortalState(loading = false, modules = listOf(modules[1])), { selected = it }, {}) } }
        compose.onNodeWithTag("module-heks").assertDoesNotExist()
        compose.onNodeWithTag("module-damage_assessment").assertDoesNotExist()
        compose.onNodeWithTag("module-reh").performClick()
        compose.runOnIdle { assertEquals("reh", selected) }
    }

    @Test fun moduleWithoutRecordSearchDoesNotOfferAnUnavailableAction() {
        compose.setContent { PhcTheme { ModuleScreen(modules[2], emptyList(), { error("Unavailable records") }, {}) } }
        compose.onNodeWithTag("module-records").assertDoesNotExist()
    }

    @Test fun moreOpensOfflinePreparationAndKeepsLogoutAvailable() {
        var offline = false
        var logout = false
        compose.setContent { PhcTheme { AccountScreen("اختبار", "test@example.test", 2, onOffline = { offline = true }) { logout = true } } }
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasTestTag("more-offline"))
        compose.onNodeWithTag("more-offline").performClick()
        compose.onNode(hasScrollToNodeAction()).performScrollToNode(hasText("تسجيل الخروج"))
        compose.onNodeWithText("تسجيل الخروج").performClick()
        compose.runOnIdle { assertTrue(offline); assertTrue(logout) }
    }

    @Test fun preparationUsesTheDownloadCallbackAndReportsActualOfflineCatalog() {
        var downloaded = false
        compose.setContent { PhcTheme {
            OfflinePreparationScreen(PortalState(loading = false, offlineReadyKeys = setOf("one", "two")), { downloaded = true }, {}) {}
        } }
        compose.onNodeWithText("2 نماذج جاهزة", substring = true).assertExists()
        compose.onNodeWithTag("download-offline-forms").performScrollTo().performClick()
        compose.runOnIdle { assertTrue(downloaded) }
    }
}
