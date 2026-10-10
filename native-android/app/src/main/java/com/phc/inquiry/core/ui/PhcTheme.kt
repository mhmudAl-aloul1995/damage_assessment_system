package com.phc.inquiry.core.ui

import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.TextStyle
import androidx.compose.ui.text.font.*
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import com.phc.inquiry.R

private val ArabicFont = FontFamily(Font(R.font.noto_sans_arabic))
private fun arabicStyle(size: Int, height: Int, weight: FontWeight = FontWeight.Normal) =
    TextStyle(fontFamily = ArabicFont, fontWeight = weight, fontSize = size.sp, lineHeight = height.sp)
private val ArabicTypography = Typography(
    headlineLarge = arabicStyle(30, 44, FontWeight.Bold), headlineMedium = arabicStyle(26, 40, FontWeight.Bold),
    headlineSmall = arabicStyle(22, 34, FontWeight.Bold), titleLarge = arabicStyle(20, 32, FontWeight.SemiBold),
    titleMedium = arabicStyle(16, 26, FontWeight.SemiBold), titleSmall = arabicStyle(14, 24, FontWeight.SemiBold),
    bodyLarge = arabicStyle(16, 28), bodyMedium = arabicStyle(14, 24), bodySmall = arabicStyle(12, 22),
    labelLarge = arabicStyle(14, 24, FontWeight.SemiBold), labelMedium = arabicStyle(12, 22, FontWeight.Medium), labelSmall = arabicStyle(11, 20),
)

@Composable
fun PhcTheme(darkTheme: Boolean = isSystemInDarkTheme(), content: @Composable () -> Unit) {
    val palette = if (darkTheme) darkColorScheme(
        primary = Color(0xFF9AD6BD), onPrimary = Color(0xFF003828), primaryContainer = Color(0xFF174C3B), onPrimaryContainer = Color(0xFFCAF3DE),
        secondary = Color(0xFFE0C38F), onSecondary = Color(0xFF3E2E0C), secondaryContainer = Color(0xFF493B20), onSecondaryContainer = Color(0xFFF8E0B1),
        background = Color(0xFF101B17), onBackground = Color(0xFFE1ECE5), surface = Color(0xFF16231E), onSurface = Color(0xFFE1ECE5),
        surfaceVariant = Color(0xFF2A3932), onSurfaceVariant = Color(0xFFB9C9C0), outline = Color(0xFF81998C), outlineVariant = Color(0xFF384A40),
    ) else lightColorScheme(
        primary = Color(0xFF125C4A), onPrimary = Color.White, primaryContainer = Color(0xFFE1F0E8), onPrimaryContainer = Color(0xFF0B3D32),
        secondary = Color(0xFF705A2D), onSecondary = Color.White, secondaryContainer = Color(0xFFF6EDDA), onSecondaryContainer = Color(0xFF5A441B),
        background = Color(0xFFF6F8F7), onBackground = Color(0xFF172B26), surface = Color.White, onSurface = Color(0xFF172B26),
        surfaceVariant = Color(0xFFEDF2EF), onSurfaceVariant = Color(0xFF5C6F68), outline = Color(0xFF788A82), outlineVariant = Color(0xFFDCE5DF),
    )
    MaterialTheme(colorScheme = palette, typography = ArabicTypography,
        shapes = Shapes(small = RoundedCornerShape(12.dp), medium = RoundedCornerShape(16.dp), large = RoundedCornerShape(20.dp)), content = content)
}

@Composable
fun statusLabel(value: String?): String = stringResource(when (value) {
    "fully_damaged" -> R.string.damage_total
    "partially_damaged" -> R.string.damage_partial
    "committee_review" -> R.string.committee_review
    "no_damage" -> R.string.no_damage
    "pending" -> R.string.pending_audit
    "assigned_engineer" -> R.string.assigned_engineer
    "accepted_engineer" -> R.string.accepted_engineer
    "assigned_lawyer" -> R.string.assigned_lawyer
    "accepted_lawyer" -> R.string.accepted_lawyer
    "team_approved" -> R.string.team_approved
    "undp_approved" -> R.string.undp_approved
    "needs_action" -> R.string.needs_action
    "rejected" -> R.string.rejected
    "not_completed" -> R.string.not_completed
    "completed" -> R.string.completed
    else -> R.string.unclassified
})

@Composable
fun sectorTitle(sector: String): String = stringResource(if (sector == "housing-units") R.string.housing_units else R.string.buildings)
