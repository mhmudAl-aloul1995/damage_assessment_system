package com.phc.inquiry.core.security

import okhttp3.HttpUrl
import okhttp3.HttpUrl.Companion.toHttpUrlOrNull
import okhttp3.Interceptor
import okhttp3.Response

class AttendanceTransport(private val configuredUrl: String) {
    fun base(): HttpUrl {
        val url = configuredUrl.toHttpUrlOrNull()
        if (url == null || url.scheme != "https" || url.username.isNotEmpty() || url.password.isNotEmpty() ||
            url.query != null || url.fragment != null || !configuredUrl.endsWith("/")) {
            throw InquiryFailure("لم يُضبط رابط بوابة الحضور HTTPS المعتمد لهذه النسخة.")
        }
        return url
    }
    val configured: Boolean get() = runCatching { base() }.isSuccess

    fun accepts(url: HttpUrl): Boolean {
        val base = base()
        return url.scheme == "https" && url.host == base.host && url.port == base.port &&
            url.encodedPath.startsWith(base.encodedPath + "api/v1/hr/") && url.username.isEmpty() && url.password.isEmpty()
    }
}

class AttendanceInterceptor(private val policy: AttendanceTransport, private val token: () -> String?) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val request = chain.request()
        if (!policy.accepts(request.url)) throw InquiryFailure("تم منع إرسال بيانات الحضور خارج البوابة الآمنة.")
        val path = request.url.encodedPath.removePrefix(policy.base().encodedPath + "api/v1/hr/")
        val login = path == "auth/login" && request.method == "POST"
        val posts = setOf("auth/login", "auth/logout", "devices/challenge", "devices/register", "attendance/challenge", "attendance/check-in", "attendance/check-out")
        if (request.method != "GET" && (request.method != "POST" || (path !in posts && !Regex("devices/[1-9][0-9]*/revoke").matches(path)))) {
            throw InquiryFailure("عملية الحضور غير مسموحة.")
        }
        val builder = request.newBuilder().header("Accept", "application/json").header("Accept-Language", "ar").removeHeader("Authorization")
        if (!login) {
            val current = token() ?: throw InquiryFailure("سجّل الدخول إلى خدمة الحضور الآمنة.")
            val supplied = request.header("Authorization")
            if (supplied != null && supplied != "Bearer $current") throw InquiryFailure("تغيّر حساب الحضور أثناء الطلب.")
            builder.header("Authorization", "Bearer $current")
        }
        return chain.proceed(builder.build())
    }
}
