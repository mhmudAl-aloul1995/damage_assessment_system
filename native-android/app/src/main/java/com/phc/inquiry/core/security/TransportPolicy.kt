package com.phc.inquiry.core.security

import java.io.IOException
import okhttp3.HttpUrl
import okhttp3.Interceptor
import okhttp3.Response

class InquiryFailure(message: String) : IOException(message)

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

class InquiryInterceptor(private val policy: TransportPolicy, private val token: () -> String?) : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        policy.checkTransport()
        val request = chain.request()
        if (!policy.sameApi(request.url)) throw InquiryFailure("تم منع الاتصال بعنوان خارج API المنظومة.")
        val relative = request.url.encodedPath.removePrefix(policy.baseUrl.encodedPath)
        val authentication = request.method == "POST" && relative in setOf("api/v1/auth/login", "api/v1/auth/logout")
        if (request.method != "GET" && !authentication) throw InquiryFailure("التطبيق مخصص للاستعلام فقط.")
        val builder = request.newBuilder().header("Accept", "application/json").header("Accept-Language", "ar")
        if (relative != "api/v1/auth/login") token()?.let { builder.header("Authorization", "Bearer $it") }
        return chain.proceed(builder.build())
    }
}
