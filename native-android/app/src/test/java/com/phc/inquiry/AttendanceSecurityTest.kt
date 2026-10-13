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
    @Test fun noCredentialCanLeaveTheDedicatedHttpsAudience() {
        for (url in listOf("", "http://213.6.135.115/damage_assessment_system/", "https://user:pass@example.test/", "https://example.test/?q=x", "https://example.test")) {
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

    @Test fun exactChallengeBytesAreBoundToEmployeeCredentialActionAndExpiry() {
        val now = Instant.parse("2026-10-12T05:00:00Z")
        val bytes = """{"protocol":"PHC-HR-1","purpose":"attendance","id":"challenge-id","employee_id":12,"credential_id":9,"action":"check_in","nonce":"AAECAw==","expires_at":1791781260}""".toByteArray()
        val expires = Instant.ofEpochSecond(1791781260).toString()
        val challenge = HrChallenge("challenge-id", "PHC-HR-1", Base64.getEncoder().encodeToString(bytes), expires)
        assertArrayEquals(bytes, AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_in", now))
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 13, "attendance", 9, "check_in", now) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 8, "check_in", now) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_out", now) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "device_registration", now = now) }
        assertThrows(InquiryFailure::class.java) { AttendanceProtocol.verify(challenge, 12, "attendance", 9, "check_in", Instant.ofEpochSecond(1791781260)) }
        assertArrayEquals(byteArrayOf(0, 1, 2, 3), AttendanceProtocol.nonce(bytes))
    }

    @Test fun missingImportedHoursNeverBecomeZeroOrDefaultWorkingHours() {
        assertEquals("غير معروفة", attendanceHours(null))
        assertEquals("0:00", attendanceHours(0))
        assertEquals("7:30", attendanceHours(450))
    }
}
