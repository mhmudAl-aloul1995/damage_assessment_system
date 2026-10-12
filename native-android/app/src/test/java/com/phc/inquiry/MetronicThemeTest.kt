package com.phc.inquiry

import androidx.compose.material3.ColorScheme
import androidx.compose.ui.graphics.Color
import com.phc.inquiry.core.ui.MetronicDarkColors
import com.phc.inquiry.core.ui.MetronicLightColors
import org.junit.Assert.assertTrue
import org.junit.Test
import kotlin.math.pow

class MetronicThemeTest {
    private fun luminance(color: Color): Double {
        fun channel(value: Float): Double = if (value <= 0.04045f) value / 12.92 else ((value + 0.055) / 1.055).pow(2.4)
        return 0.2126 * channel(color.red) + 0.7152 * channel(color.green) + 0.0722 * channel(color.blue)
    }
    private fun readable(palette: ColorScheme) {
        for ((foreground, background) in listOf(palette.onPrimary to palette.primary, palette.onSecondary to palette.secondary,
            palette.onSurface to palette.surface, palette.onSurfaceVariant to palette.surface, palette.onSurfaceVariant to palette.surfaceVariant,
            palette.onBackground to palette.background, palette.onPrimaryContainer to palette.primaryContainer, palette.onSecondaryContainer to palette.secondaryContainer,
            palette.primary to palette.surface)) {
            val foregroundLuminance = luminance(foreground); val backgroundLuminance = luminance(background)
            val ratio = (maxOf(foregroundLuminance, backgroundLuminance) + 0.05) / (minOf(foregroundLuminance, backgroundLuminance) + 0.05)
            assertTrue("Unreadable theme text, contrast $ratio", ratio >= 4.5)
        }
    }
    @Test fun lightThemeTextAndActionsMeetSmallTextContrastRequirements() = readable(MetronicLightColors)
    @Test fun darkThemeTextAndActionsMeetSmallTextContrastRequirements() = readable(MetronicDarkColors)
}
