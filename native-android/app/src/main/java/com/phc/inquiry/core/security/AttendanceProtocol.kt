package com.phc.inquiry.core.security

import com.phc.inquiry.data.HrChallenge
import java.time.Instant
import java.util.Base64
import kotlinx.serialization.json.*

object AttendanceProtocol {
    private val json = Json { ignoreUnknownKeys = true }
    fun verify(challenge: HrChallenge, employee: Long, purpose: String, credential: Long? = null, action: String? = null, now: Instant = Instant.now()): ByteArray {
        if (challenge.protocol != "PHC-HR-1" || challenge.payload.length > 8192) throw InquiryFailure("بروتوكول تحدي الحضور غير صالح.")
        val bytes = runCatching { Base64.getDecoder().decode(challenge.payload) }.getOrElse { throw InquiryFailure("بيانات التحدي غير صالحة.") }
        val claims = runCatching { json.parseToJsonElement(bytes.toString(Charsets.UTF_8)).jsonObject }.getOrElse { throw InquiryFailure("بيانات التحدي غير صالحة.") }
        if (claims["protocol"]?.jsonPrimitive?.content != challenge.protocol || claims["purpose"]?.jsonPrimitive?.content != purpose ||
            claims["id"]?.jsonPrimitive?.content != challenge.id || claims["employee_id"]?.jsonPrimitive?.longOrNull != employee ||
            (credential != null && claims["credential_id"]?.jsonPrimitive?.longOrNull != credential) ||
            (action != null && claims["action"]?.jsonPrimitive?.content != action)) throw InquiryFailure("التحدي لا يطابق الموظف والجهاز والعملية.")
        val expires = claims["expires_at"]?.jsonPrimitive?.longOrNull ?: throw InquiryFailure("وقت التحدي غير صالح.")
        if (Instant.parse(challenge.expiresAt).epochSecond != expires || now.epochSecond >= expires) throw InquiryFailure("انتهت صلاحية التحدي؛ أعد المحاولة.")
        return bytes
    }

    fun nonce(payload: ByteArray): ByteArray = Base64.getDecoder().decode(json.parseToJsonElement(payload.toString(Charsets.UTF_8)).jsonObject.getValue("nonce").jsonPrimitive.content)
}
