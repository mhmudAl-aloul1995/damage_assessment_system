package com.phc.inquiry

import com.phc.inquiry.core.security.*
import okhttp3.*
import okhttp3.HttpUrl.Companion.toHttpUrl
import okhttp3.RequestBody.Companion.toRequestBody
import okhttp3.ResponseBody.Companion.toResponseBody
import org.junit.Assert.*
import org.junit.Test

class TransportPolicyTest {
    @Test fun formWritesAreNarrowlyScopedAndCannotSwitchAccountTokens() {
        val base = "https://example.test/system/".toHttpUrl()
        val client = OkHttpClient.Builder().addInterceptor(InquiryInterceptor(TransportPolicy(base, false)) { "owner-token" })
            .addInterceptor { chain -> Response.Builder().request(chain.request()).protocol(Protocol.HTTP_1_1).code(200).message("OK").body("{}".toResponseBody()).build() }.build()
        for (path in listOf("forms/borrower-survey/submissions", "forms/heks-followup/attachments")) {
            client.newCall(Request.Builder().url(base.toString() + "api/v1/" + path).header("Authorization", "Bearer owner-token").post("{}".toRequestBody()).build()).execute().close()
        }
        for (path in listOf("forms/borrower-survey/delete", "forms/borrower-survey/submissions/extra", "forms/%2e%2e/submissions", "portal/heks/records")) {
            assertThrows(InquiryFailure::class.java) { client.newCall(Request.Builder().url(base.toString() + "api/v1/" + path).post("{}".toRequestBody()).build()).execute() }
        }
        assertThrows(InquiryFailure::class.java) { client.newCall(Request.Builder().url(base.toString() + "api/v1/forms/borrower-survey/submissions").header("Authorization", "Bearer previous-owner").post("{}".toRequestBody()).build()).execute() }
    }
    private val publicBase = "http://213.6.135.115/damage_assessment_system/".toHttpUrl()

    @Test fun explicitlyEnabledHttpWorksWithoutAnAccountAllowlist() {
        val policy = TransportPolicy(publicBase, true)
        assertTrue(policy.httpReady)
        policy.checkTransport()
    }

    @Test fun releaseAndOtherHostsCannotUseCleartext() {
        assertThrows(InquiryFailure::class.java) { TransportPolicy(publicBase, false).checkTransport() }
        assertThrows(InquiryFailure::class.java) { TransportPolicy("http://example.com/".toHttpUrl(), true).checkTransport() }
        TransportPolicy("https://example.test/".toHttpUrl(), false).checkTransport()
    }

    @Test fun tokenIsRestrictedToApiAndBusinessMutationsAreDenied() {
        val policy = TransportPolicy("https://example.test/system/".toHttpUrl(), false)
        var received: Request? = null
        val client = OkHttpClient.Builder().addInterceptor(InquiryInterceptor(policy) { "secret-test-token" })
            .addInterceptor { chain ->
                received = chain.request()
                Response.Builder().request(chain.request()).protocol(Protocol.HTTP_1_1).code(200).message("OK").body("{}".toResponseBody()).build()
            }.build()
        client.newCall(Request.Builder().url("https://example.test/system/api/v1/me").build()).execute().close()
        assertEquals("Bearer secret-test-token", received?.header("Authorization"))
        client.newCall(Request.Builder().url("https://example.test/system/api/v1/auth/login").post("{}".toRequestBody()).build()).execute().close()
        assertNull(received?.header("Authorization"))
        for (url in listOf("https://other.test/system/api/v1/me", "https://example.test/elsewhere", "https://example.test:444/system/api/v1/me")) {
            assertThrows(InquiryFailure::class.java) { client.newCall(Request.Builder().url(url).build()).execute() }
        }
        assertThrows(InquiryFailure::class.java) { client.newCall(Request.Builder().url("https://example.test/system/api/v1/buildings/1").delete().build()).execute() }
    }
}
