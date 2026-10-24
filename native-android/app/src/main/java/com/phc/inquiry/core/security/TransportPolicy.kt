package com.phc.inquiry.core.security

import java.io.IOException
import okhttp3.HttpUrl
import okhttp3.Interceptor
import okhttp3.Response

class InquiryFailure(message: String, val statusCode: Int? = null, cause: Throwable? = null) : IOException(message, cause)

class TransportPolicy(val baseUrl: HttpUrl, private val allowHttp: Boolean) {
    val usesHttp: Boolean get() = baseUrl.scheme == "http"
    val httpReady: Boolean get() = allowHttp && baseUrl.host == "213.6.135.115" && baseUrl.port == 80

    fun checkTransport() {
        if (baseUrl.username.isNotEmpty() || baseUrl.password.isNotEmpty()) throw InquiryFailure("إعداد عنوان السيرفر غير صالح.")
        if (usesHttp && (!httpReady || baseUrl.host != "213.6.135.115" || baseUrl.port != 80)) {
            throw InquiryFailure("اتصال HTTP غير مسموح في هذه النسخة أو لهذا السيرفر.")
        }
    }

    fun sameApi(url: HttpUrl): Boolean = url.scheme == baseUrl.scheme && url.host == baseUrl.host && url.port == baseUrl.port &&
        url.encodedPath.startsWith(baseUrl.encodedPath + "api/v1/")
}

class InquiryInterceptor(private val policy: TransportPolicy, private val localOnly: () -> Boolean = { false }, private val token: () -> String?) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        policy.checkTransport()
        val request = chain.request()
        if (!policy.sameApi(request.url)) throw InquiryFailure("تم منع الاتصال بعنوان خارج API المنظومة.")
        val relative = request.url.encodedPath.removePrefix(policy.baseUrl.encodedPath)
        if (localOnly() && relative != "api/v1/auth/login") throw InquiryFailure("هذا فتح محلي فقط. سجّل الدخول مع وجود نت لاستخدام السيرفر.")
        val authentication = request.method == "POST" && relative in setOf("api/v1/auth/login", "api/v1/auth/logout")
        val formWrite = request.method == "POST" && Regex("api/v1/forms/[a-z-]+/(submissions|attachments|attachments/[1-9][0-9]*/discard)").matches(relative)
        if (request.method != "GET" && !authentication && !formWrite) throw InquiryFailure("هذه العملية غير مسموحة في التطبيق.")
        val builder = request.newBuilder().header("Accept", "application/json").header("Accept-Language", "ar")
        if (relative != "api/v1/auth/login") {
            val current = token()?.let { "Bearer $it" }
            val supplied = request.header("Authorization")
            if (supplied != null && supplied != current) throw InquiryFailure("تغيّر الحساب. افتح المسودة بالحساب الذي أنشأها.")
            current?.let { builder.header("Authorization", it) }
        }
        return chain.proceed(builder.build())
    }
}
