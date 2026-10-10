package com.phc.inquiry

import androidx.compose.runtime.CompositionLocalProvider
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import com.phc.inquiry.core.ui.PhcTheme
import com.phc.inquiry.feature.authentication.*
import org.junit.Rule
import org.junit.Test

class NativeScreenTest {
    @get:Rule val compose = createComposeRule()
    @Test fun enabledHttpLoginAcceptsAccountWithoutATestConfirmation() {
        var submittedEmail: String? = null
        compose.setContent {
            CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) {
                PhcTheme { LoginScreen(AuthenticationState(restoring = false), usesHttp = true, httpReady = true) { email, _ -> submittedEmail = email } }
            }
        }
        compose.onNodeWithTag("email").assertIsEnabled().performTextInput("account@example.test")
        compose.onNodeWithTag("password").assertIsEnabled().performTextInput("fixture-password")
        compose.onNodeWithTag("login").performScrollTo().assertIsEnabled().performClick()
        compose.runOnIdle { org.junit.Assert.assertEquals("account@example.test", submittedEmail) }
    }

    @Test fun disabledHttpTransportStillBlocksLogin() {
        compose.setContent {
            PhcTheme { LoginScreen(AuthenticationState(restoring = false), usesHttp = true, httpReady = false) { _, _ -> error("Must not submit") } }
        }
        compose.onNodeWithTag("email").assertIsNotEnabled()
        compose.onNodeWithTag("login").assertIsNotEnabled()
    }
}
