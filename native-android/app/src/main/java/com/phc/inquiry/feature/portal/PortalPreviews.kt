package com.phc.inquiry.feature.portal

import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.runtime.Composable
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.tooling.preview.Preview
import androidx.compose.ui.unit.LayoutDirection
import com.phc.inquiry.core.ui.PhcTheme
import com.phc.inquiry.data.PortalModule
import com.phc.inquiry.data.PublishedForm

@Preview(name = "Portal · Arabic", widthDp = 390, heightDp = 844, showBackground = true)
@Preview(name = "Portal · Dark", widthDp = 390, heightDp = 844, showBackground = true, uiMode = 0x20)
@Preview(name = "Portal · Large text", widthDp = 390, heightDp = 844, showBackground = true, fontScale = 1.5f)
@Composable private fun PortalHomePreview() {
    CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) { PhcTheme {
        PortalHome("حساب تجريبي", PortalState(false, modules = listOf(
            PortalModule("damage_assessment", "حصر الأضرار", "المباني والوحدات السكنية والقطاعات"),
            PortalModule("damage_assessment_borrowers", "المقترضون", "استبيانات الأسرة والوحدة السكنية"),
            PortalModule("heks", "إصلاح المأوى · HEKS", "المستفيدون وزيارات المتابعة"),
            PortalModule("administration", "الإدارة", "الاستعلام عن الحسابات"),
        ), forms = listOf(PublishedForm("borrower-survey", "damage_assessment_borrowers", "استبيان المقترض", "fixture"))), {}, {}, {}, {}, {})
    } }
}

