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

val ArabicFont = FontFamily(
    Font(R.font.droid_arabic_kufi_regular, FontWeight.Normal),
)
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
    val palette = if (darkTheme) PhcDarkColors else PhcLightColors
    MaterialTheme(colorScheme = palette, typography = ArabicTypography,
        shapes = Shapes(small = RoundedCornerShape(8.dp), medium = RoundedCornerShape(12.dp), large = RoundedCornerShape(16.dp)), content = content)
}

val PhcLightColors = lightColorScheme(
    primary = Color(0xFF176447), onPrimary = Color.White, primaryContainer = Color(0xFFE9F2EC), onPrimaryContainer = Color(0xFF125337),
    secondary = Color(0xFF705B35), onSecondary = Color.White, secondaryContainer = Color(0xFFE9E3D8), onSecondaryContainer = Color(0xFF524326),
    background = Color(0xFFF7F8F5), onBackground = Color(0xFF202824), surface = Color.White, onSurface = Color(0xFF202824),
    surfaceVariant = Color(0xFFEEF2EC), onSurfaceVariant = Color(0xFF5E6B62), outline = Color(0xFF738078), outlineVariant = Color(0xFFE2E7DF),
    error = Color(0xFFB4232D), onError = Color.White, errorContainer = Color(0xFFFFDAD9), onErrorContainer = Color(0xFF68000D),
    surfaceContainer = Color.White, surfaceContainerLow = Color.White, surfaceContainerLowest = Color.White, surfaceContainerHigh = Color(0xFFEEF2EC), surfaceContainerHighest = Color(0xFFE9E3D8), surfaceTint = Color.Transparent,
)
val PhcDarkColors = darkColorScheme(
    primary = Color(0xFFA4D7B8), onPrimary = Color(0xFF073823), primaryContainer = Color(0xFF273F31), onPrimaryContainer = Color(0xFFC6EFD3),
    secondary = Color(0xFFE9D3A6), onSecondary = Color(0xFF3D2E14), secondaryContainer = Color(0xFF403A2B), onSecondaryContainer = Color(0xFFF2E3C8),
    background = Color(0xFF151E1A), onBackground = Color(0xFFEDF3ED), surface = Color(0xFF202C25), onSurface = Color(0xFFEDF3ED),
    surfaceVariant = Color(0xFF2B3930), onSurfaceVariant = Color(0xFFBAC7BC), outline = Color(0xFF8A9A8E), outlineVariant = Color(0xFF3B4A40),
    error = Color(0xFFFFB3B5), onError = Color(0xFF68000D), errorContainer = Color(0xFF8E1520), onErrorContainer = Color(0xFFFFDAD9),
    surfaceContainer = Color(0xFF202C25), surfaceContainerLow = Color(0xFF202C25), surfaceContainerLowest = Color(0xFF151E1A), surfaceContainerHigh = Color(0xFF2B3930), surfaceContainerHighest = Color(0xFF354339), surfaceTint = Color.Transparent,
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
