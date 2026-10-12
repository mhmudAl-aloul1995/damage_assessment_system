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
    val palette = if (darkTheme) MetronicDarkColors else MetronicLightColors
    MaterialTheme(colorScheme = palette, typography = ArabicTypography,
        shapes = Shapes(small = RoundedCornerShape(8.dp), medium = RoundedCornerShape(12.dp), large = RoundedCornerShape(16.dp)), content = content)
}

val MetronicLightColors = lightColorScheme(
    primary = Color(0xFF2563EB), onPrimary = Color.White, primaryContainer = Color(0xFFEFF6FF), onPrimaryContainer = Color(0xFF1D4ED8),
    secondary = Color(0xFF5E35B1), onSecondary = Color.White, secondaryContainer = Color(0xFFF3EFFF), onSecondaryContainer = Color(0xFF5E35B1),
    background = Color(0xFFF5F7FA), onBackground = Color(0xFF071437), surface = Color.White, onSurface = Color(0xFF071437),
    surfaceVariant = Color(0xFFF1F3F8), onSurfaceVariant = Color(0xFF56627C), outline = Color(0xFF78829D), outlineVariant = Color(0xFFE4E8EF),
    surfaceContainer = Color.White, surfaceContainerLow = Color.White, surfaceContainerLowest = Color.White, surfaceContainerHigh = Color(0xFFF1F3F8), surfaceContainerHighest = Color(0xFFE9EDF5), surfaceTint = Color.Transparent,
)
val MetronicDarkColors = darkColorScheme(
    primary = Color(0xFF93C5FD), onPrimary = Color(0xFF102B50), primaryContainer = Color(0xFF1D3555), onPrimaryContainer = Color(0xFFBFDBFE),
    secondary = Color(0xFFC4B5FD), onSecondary = Color(0xFF30204F), secondaryContainer = Color(0xFF33264D), onSecondaryContainer = Color(0xFFE5DEFF),
    background = Color(0xFF111827), onBackground = Color(0xFFF1F5F9), surface = Color(0xFF1C2536), onSurface = Color(0xFFF1F5F9),
    surfaceVariant = Color(0xFF273247), onSurfaceVariant = Color(0xFFB4BFD2), outline = Color(0xFF8492AB), outlineVariant = Color(0xFF354157),
    surfaceContainer = Color(0xFF1C2536), surfaceContainerLow = Color(0xFF1C2536), surfaceContainerLowest = Color(0xFF111827), surfaceContainerHigh = Color(0xFF273247), surfaceContainerHighest = Color(0xFF2E3A50), surfaceTint = Color.Transparent,
)

@Composable
fun statusLabel(value: String?): String = stringResource(when (value) {
    "fully_damaged" -> R.string.damage_total
    "partially_damaged" -> R.string.damage_partial
    "committee_review" -> R.string.committee_review
    "no_damage" -> R.string.no_damage
    "destroyed" -> R.string.damage_total
    "severe" -> R.string.damage_severe
    "moderate" -> R.string.damage_moderate
    "minor" -> R.string.damage_minor
    "pending" -> R.string.pending_audit
    "assigned_engineer", "assigned_to_engineer" -> R.string.assigned_engineer
    "accepted_engineer", "accepted_by_engineer" -> R.string.accepted_engineer
    "assigned_lawyer", "assigned_to_lawyer" -> R.string.assigned_lawyer
    "accepted_lawyer", "accepted_by_lawyer" -> R.string.accepted_lawyer
    "approved" -> R.string.approved
    "assigned" -> R.string.assigned_audit
    "accepted", "in_review" -> R.string.in_review
    "need_review", "legal_notes" -> R.string.needs_action
    "rejected_by_engineer" -> R.string.rejected
    "team_approved" -> R.string.team_approved
    "undp_approved" -> R.string.undp_approved
    "needs_action" -> R.string.needs_action
    "rejected" -> R.string.rejected
    "not_completed" -> R.string.not_completed
    "completed" -> R.string.completed
    else -> R.string.unclassified
})

@Composable
fun sectorTitle(sector: String): String = stringResource(when (sector) {
    "housing-units" -> R.string.housing_units
    "public-buildings" -> R.string.public_buildings
    "road-facilities" -> R.string.road_facilities
    "cso-surveys" -> R.string.cso_surveys
    else -> R.string.buildings
})
