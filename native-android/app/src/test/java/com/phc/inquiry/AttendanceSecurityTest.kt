package com.phc.inquiry

import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.HrChallenge
import com.phc.inquiry.feature.attendance.attendanceHours
import java.time.Instant
import java.util.Base64
import okhttp3.*
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.*
import org.junit.Test

class AttendanceSecurityTest {
    @Test fun noCredentialCanLeaveTheConfiguredAudience() {
        for (url in listOf("", "ftp://example.test/", "https://user:pass@example.test/", "https://example.test/?q=x", "https://example.test")) {
            assertFalse(AttendanceTransport(url).configured)
            assertThrows(InquiryFailure::class.java) { AttendanceTransport(url).base() }
        }
        val policy = AttendanceTransport("https://gateway.example.test/system/")
        var received: Request? = null
        val client = OkHttpClient.Builder().addInterceptor(AttendanceInterceptor(policy) { "hr-only-token" }).addInterceptor {
            received = it.request()
            Response.Builder().request(it.request()).protocol(Protocol.HTTP_1_1).code(200).message("OK").body("{}".toResponseBody()).build()
        }.build()
        client.newCall(Request.Builder().url("https://gateway.example.test/system/api/v1/hr/attendance/today").build()).execute().close()
        assertEquals("Bearer hr-only-token", received?.header("Authorization"))
        for (url in listOf("http://gateway.example.test/system/api/v1/hr/me", "https://other.example.test/system/api/v1/hr/me", "https://gateway.example.test/system/api/v1/me", "https://gateway.example.test:444/system/api/v1/hr/me")) {
            assertThrows(InquiryFailure::class.java) { client.newCall(Request.Builder().url(url).build()).execute() }
        }
        assertThrows(InquiryFailure::class.java) { client.newCall(Request.Builder().url("https://gateway.example.test/system/api/v1/hr/devices/register").header("Authorization", "Bearer other-account").post("{}".toRequestBody()).build()).execute() }
        client.newCall(Request.Builder().url("https://gateway.example.test/system/api/v1/hr/auth/login").header("Authorization", "Bearer old-token").post("{}".toRequestBody()).build()).execute().close()
        assertNull(received?.header("Authorization"))
    }

    @Test fun httpWorksOnlyForTheExactConfiguredOriginAndHrPaths() {
        val policy = AttendanceTransport("http://attendance.example.test:8080/system/")
        assertTrue(policy.configured)
        assertTrue(policy.accepts("http://attendance.example.test:8080/system/api/v1/hr/me".toHttpUrl()))
        for (url in listOf("https://attendance.example.test:8080/system/api/v1/hr/me", "http://attendance.example.test/system/api/v1/hr/me", "http://other.example.test:8080/system/api/v1/hr/me", "http://attendance.example.test:8080/system/api/v1/me")) {
            assertFalse(policy.accepts(url.toHttpUrl()))
        }
    }

    @Test fun receiptTimesUseTheEmployeeTimezone() {
        assertEquals("08:10", com.phc.inquiry.feature.attendance.attendanceReceiptTime("2026-10-12 05:10:00", "Asia/Hebron"))
        assertEquals("05:10", com.phc.inquiry.feature.attendance.attendanceReceiptTime("2026-10-12T05:10:00Z", "UTC"))
        assertEquals("معتمد", com.phc.inquiry.feature.attendance.attendanceDeviceStatus("approved"))
    }

    @Test fun exactChallengeBytesAreBoundToEmployeeCredentialActionAndExpiry() {
        val now = Instant.parse("2026-10-12T05:00:00Z")
        val bytes = """{"protocol":"PHC-HR-1","purpose":"attendance","id":"challenge-id","employee_id":12,"credential_id":9,"action":"check_in","assignment_id":24,"work_date":"2026-10-12","nonce":"AAECAw==","expires_at":1791781260}""".toByteArray()
        val expires = Instant.ofEpochSecond(1791781260).toString()
        val challenge = HrChallenge("challenge-id", "PHC-HR-1", Base64.getEncoder().encodeToString(bytes), expires)
        assertArrayEquals(bytes, AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_in", now))
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 13, "attendance", 9, "check_in", now) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 8, "check_in", now) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_out", now) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "device_registration", now = now) }
        assertArrayEquals(bytes, AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_in", now, assignment = 24, workDate = "2026-10-12"))
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_in", now, assignment = 25) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_in", now, workDate = "2026-10-13") }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_in", Instant.ofEpochSecond(1791781260)) }
        assertArrayEquals(byteArrayOf(0, 1, 2, 3), AttendanceProtocol.nonce(bytes))
    }

    @Test fun missingImportedHoursNeverBecomeZeroOrDefaultWorkingHours() {
        assertEquals("غير معروفة", attendanceHours(null))
        assertEquals("0:00", attendanceHours(0))
        assertEquals("7:30", attendanceHours(450))
    }
}
