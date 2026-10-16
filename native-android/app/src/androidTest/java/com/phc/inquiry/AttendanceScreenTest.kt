package com.phc.inquiry

import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import androidx.compose.ui.graphics.asAndroidBitmap
import androidx.test.platform.app.InstrumentationRegistry
import android.graphics.Bitmap
import java.io.File
import com.phc.inquiry.core.ui.DesignPreviewFrame
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.attendance.*
import org.junit.Rule
import org.junit.Test

class AttendanceScreenTest {
    @get:Rule val compose = createComposeRule()
    private fun render(state: AttendanceState) {
        compose.setContent { DesignPreviewFrame { AttendanceScreen(state, {}, {}, {}, { error("Capture must stay disabled") }, {}, {}, { _, _ -> }, {}, {}) } }
    }

    private fun snapshot(name: String) {
        compose.waitForIdle()
        val context = InstrumentationRegistry.getInstrumentation().targetContext
        File(context.getExternalFilesDir(null), "attendance-$name.png").outputStream().use {
            compose.onRoot().captureToImage().asAndroidBitmap().compress(Bitmap.CompressFormat.PNG, 100, it)
        }
    }

    @Test fun androidPermitsHttpOnlyForConfiguredHosts() {
        val base = java.net.URI(BuildConfig.HR_API_BASE_URL)
        if (base.scheme == "http") {
            org.junit.Assert.assertTrue(android.security.NetworkSecurityPolicy.getInstance().isCleartextTrafficPermitted(base.host))
        }
        org.junit.Assert.assertFalse(android.security.NetworkSecurityPolicy.getInstance().isCleartextTrafficPermitted("unconfigured.invalid"))
    }

    @Test fun serverActivationAndDeviceApprovalAreRequiredBeforeCapture() {
        render(AttendanceState(configured = true, signedIn = true, employee = HrEmployee(1, name = "موظف", captureEnabled = false),
            currentDevice = HrDevice(1, "device", "Android", "approved"), selectedShift = 2,
            today = HrAttendanceDay("2026-10-12", "pending", timezone = "Asia/Hebron", sessions = listOf(HrShift(2, "صباحي", "Asia/Hebron", active = true)))))
        compose.onNodeWithTag("attendance-disabled").assertExists()
        compose.onNodeWithTag("attendance-check-in").performScrollTo().assertIsNotEnabled()
        compose.onNodeWithTag("attendance-check-out").performScrollTo().assertIsNotEnabled()
        compose.onAllNodesWithText("ساعات العمل: غير معروفة").onFirst().assertExists()
    }

    @Test fun pendingDeviceCannotCaptureEvenWhenTheServerIsActivated() {
        render(AttendanceState(configured = true, signedIn = true, employee = HrEmployee(1, name = "موظف", captureEnabled = true),
            currentDevice = HrDevice(1, "device", "Android", "pending"), selectedShift = 2,
            today = HrAttendanceDay("2026-10-12", "pending", timezone = "Asia/Hebron")))
        compose.onNodeWithTag("attendance-check-in").performScrollTo().assertIsNotEnabled()
    }

    @Test fun overnightOpenSessionEnablesCheckoutEvenWhenTodaysProjectionHasNoCheckin() {
        render(AttendanceState(configured = true, signedIn = true, employee = HrEmployee(1, name = "موظف", captureEnabled = true),
            currentDevice = HrDevice(1, "device", "Android", "approved"),
            today = HrAttendanceDay("2026-10-13", "pending", timezone = "Asia/Hebron", captureState = HrCaptureState(
                HrOpenSession(5, 2, "2026-10-12", "2026-10-12 19:00:00", true),
                listOf(HrCaptureOption("check_out", 2, "2026-10-12", "ليلي", "Asia/Hebron"))))))
        compose.onNodeWithTag("attendance-check-out").performScrollTo().assertIsEnabled()
        compose.onNodeWithTag("attendance-check-in").performScrollTo().assertIsNotEnabled()
    }

    @Test fun correctedUnknownTimesNeverEnableASecondRawCheckin() {
        render(AttendanceState(configured = true, signedIn = true, employee = HrEmployee(1, name = "موظف", captureEnabled = true),
            currentDevice = HrDevice(1, "device", "Android", "approved"), selectedShift = 2,
            today = HrAttendanceDay("2026-10-12", "approved_present", timezone = "Asia/Hebron",
                sessions = listOf(HrShift(2, "صباحي", "Asia/Hebron", active = true)))))
        compose.onNodeWithTag("attendance-check-in").performScrollTo().assertIsNotEnabled()
        compose.onNodeWithTag("attendance-check-out").performScrollTo().assertIsNotEnabled()
    }

    @Test fun aReplacementPhoneCanReportThePreviousApprovedDeviceAsLost() {
        render(AttendanceState(configured = true, signedIn = true, devices = listOf(HrDevice(9, "lost-device", "الهاتف السابق", "approved"))))
        compose.onNodeWithText("جهازي").performClick()
        compose.onNodeWithText("إلغاء / فقدان الهاتف السابق — #9").performScrollTo().performClick()
        compose.onNodeWithText("إلغاء الجهاز #9").assertExists()
    }

    @Test fun approvedDeviceCanStartBiometricCheckinAndBrowseHistory() {
        var action: String? = null
        val day = HrAttendanceDay("2026-10-12", "pending", schedule = "الوردية الصباحية", timezone = "Asia/Hebron", captureState = HrCaptureState(options = listOf(HrCaptureOption("check_in", 2, "2026-10-12", "صباحي", "Asia/Hebron"))))
        val state = AttendanceState(configured = true, signedIn = true, name = "موظف تجريبي", employee = HrEmployee(1, name = "موظف", captureEnabled = true), currentDevice = HrDevice(1, "device", "Android", "approved"), selectedShift = 2, today = day, history = listOf(day))
        compose.setContent { DesignPreviewFrame { AttendanceScreen(state, {}, {}, {}, { action = it }, {}, {}, { _, _ -> }, {}, {}) } }
        snapshot("today")
        compose.onNodeWithTag("attendance-check-in").performScrollTo().assertIsEnabled().performClick()
        org.junit.Assert.assertEquals("check_in", action)
        compose.onNodeWithText("سجلي").performScrollTo().performClick()
        compose.onNodeWithText("السابق").assertExists()
        compose.onNodeWithText("التالي").assertExists()
        compose.onNodeWithTag("attendance-check-in").assertDoesNotExist()
        snapshot("history")
    }
}
