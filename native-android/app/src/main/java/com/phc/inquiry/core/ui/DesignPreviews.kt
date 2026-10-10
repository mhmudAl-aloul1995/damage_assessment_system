package com.phc.inquiry.core.ui

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.paging.PagingData
import androidx.paging.compose.collectAsLazyPagingItems
import com.phc.inquiry.R
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.authentication.*
import com.phc.inquiry.feature.dashboard.*
import com.phc.inquiry.feature.damageassessment.*
import kotlinx.coroutines.flow.flowOf

val PreviewRecord = InquiryRecord(recordId = 17, name = "مبنى الأمل — بيانات تجريبية", municipality = "غزة", neighborhood = "حي النصر", damageStatus = "partially_damaged", fieldCompleted = true, auditStatus = "pending")

@Composable
fun DesignPreviewFrame(dark: Boolean = false, content: @Composable () -> Unit) {
    CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) {
        PhcTheme(darkTheme = dark) {
            Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
                Column {
                    Text(stringResource(R.string.preview_fixture), style = MaterialTheme.typography.labelSmall,
                        color = MaterialTheme.colorScheme.onSurfaceVariant, modifier = Modifier.padding(horizontal = 24.dp, vertical = 8.dp))
                    Box(Modifier.weight(1f)) { content() }
                }
            }
        }
    }
}

@Preview(name = "Login — small phone", widthDp = 360, heightDp = 800)
@Composable
fun LoginPreview() = DesignPreviewFrame { LoginScreen(AuthenticationState(restoring = false), true, true) { _, _ -> } }

@Preview(name = "Login — dark", widthDp = 360, heightDp = 800)
@Composable
fun LoginDarkPreview() = DesignPreviewFrame(true) { LoginScreen(AuthenticationState(restoring = false), true, true) { _, _ -> } }

@Preview(name = "Login — tablet", widthDp = 1000, heightDp = 800)
@Composable
fun LoginWidePreview() = DesignPreviewFrame { LoginScreen(AuthenticationState(restoring = false), true, true) { _, _ -> } }

@Preview(name = "Login — large text", widthDp = 320, heightDp = 720, fontScale = 1.4f)
@Composable
fun LoginLargeTextPreview() = DesignPreviewFrame { LoginScreen(AuthenticationState(restoring = false), true, true) { _, _ -> } }

@Preview(name = "Dashboard", widthDp = 400, heightDp = 900)
@Composable
fun DashboardPreview() = DesignPreviewFrame { DashboardScreen("مستخدم تجريبي", DashboardState(false, listOf(Sector("buildings", ""), Sector("housing-units", ""))), {}, {}) }

@Preview(name = "Search results", widthDp = 400, heightDp = 900)
@Composable
fun SearchPreview() = DesignPreviewFrame {
    val records = remember { flowOf(PagingData.from(listOf(PreviewRecord, PreviewRecord.copy(recordId = 18, name = "مبنى السلام — بيانات تجريبية", damageStatus = "no_damage")))) }.collectAsLazyPagingItems()
    SearchScreen("buildings", records, true, 2, {}, {})
}

@Preview(name = "Details", widthDp = 400, heightDp = 900)
@Composable
fun DetailPreview() = DesignPreviewFrame { DetailScreen(DetailState(false, PreviewRecord), {}) }
