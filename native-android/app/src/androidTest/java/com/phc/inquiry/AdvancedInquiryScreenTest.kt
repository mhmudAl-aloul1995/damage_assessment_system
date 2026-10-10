package com.phc.inquiry

import android.graphics.pdf.PdfDocument
import androidx.compose.runtime.*
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.test.platform.app.InstrumentationRegistry
import androidx.paging.PagingData
import androidx.paging.compose.collectAsLazyPagingItems
import com.phc.inquiry.core.security.AttachmentRenderer
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.damageassessment.*
import kotlinx.coroutines.flow.flowOf
import kotlinx.coroutines.runBlocking
import org.junit.Rule
import org.junit.Test
import org.junit.Assert.*

class AdvancedInquiryScreenTest {
    @get:Rule val compose = createComposeRule()

    @Test fun detailActionsRespectCapabilities() {
        var capabilities by mutableStateOf(RecordCapabilities())
        var history = false; var files = false
        compose.setContent { DesignPreviewFrame {
            DetailScreen(DetailState(false, PreviewRecord.copy(capabilities = capabilities)), {}, { history = true }, { files = true })
        } }
        compose.onNodeWithTag("open-history").assertDoesNotExist()
        compose.onNodeWithTag("open-attachments").assertDoesNotExist()
        compose.runOnIdle { capabilities = RecordCapabilities(auditHistory = true, attachments = true) }
        compose.onNodeWithTag("open-history").performScrollTo().performClick()
        compose.onNodeWithTag("open-attachments").performScrollTo().performClick()
        compose.runOnIdle { assertTrue(history); assertTrue(files) }
    }

    @Test fun filtersAreSubmittedExplicitly() {
        var submitted: Map<String, String>? = null
        compose.setContent { DesignPreviewFrame {
            val records = remember { flowOf(PagingData.empty<InquiryRecord>()) }.collectAsLazyPagingItems()
            SearchScreen("buildings", records, false, null, {}, {}, filterState = FilterState(options = FilterOptions(municipalities = listOf("غزة"))),
                onAdvancedSearch = { _, filters -> submitted = filters })
        } }
        compose.onNodeWithText("فلاتر البحث").performClick()
        compose.onNodeWithTag("filter-البلدية").performScrollTo().performClick()
        compose.onNodeWithText("غزة").performClick()
        compose.runOnIdle { assertNull(submitted) }
        compose.onNodeWithText("تطبيق الفلاتر والبحث").performScrollTo().performClick()
        compose.runOnIdle { assertEquals("غزة", submitted?.get("municipality")) }
    }

    @Test fun pdfRendererUsesIsolatedServiceAndRemovesTemporaryFile() = runBlocking {
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        val output = java.io.ByteArrayOutputStream()
        val document = PdfDocument()
        try {
            val page = document.startPage(PdfDocument.PageInfo.Builder(200, 300, 1).create())
            page.canvas.drawColor(android.graphics.Color.WHITE)
            document.finishPage(page); document.writeTo(output)
        } finally { document.close() }
        val result = AttachmentRenderer.pdf(context, output.toByteArray(), 0)
        assertEquals(1, result.second); assertEquals(200, result.first.width)
        assertEquals(android.graphics.Color.WHITE, result.first.getPixel(0, 0))
        result.first.recycle()
        assertTrue(context.cacheDir.listFiles()?.none { it.name.startsWith("phc-preview-") } ?: true)
    }
}
