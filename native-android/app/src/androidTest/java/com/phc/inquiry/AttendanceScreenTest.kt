package com.phc.inquiry

import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
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
        compose.onNodeWithText("إلغاء / فقدان الهاتف السابق — #9").performClick()
        compose.onNodeWithText("إلغاء الجهاز #9").assertExists()
    }
}
