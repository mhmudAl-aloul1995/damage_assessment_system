package com.phc.inquiry.core.network

import java.io.EOFException
import java.io.IOException
import java.io.InterruptedIOException
import java.net.ConnectException
import java.net.NoRouteToHostException
import java.net.SocketException
import java.net.UnknownHostException
import javax.net.ssl.SSLException

fun connectionFailureMessage(error: IOException): String = when (error) {
    is InterruptedIOException -> "لم تصل استجابة السيرفر ضمن المهلة. قد يكون الاتصال أو السيرفر بطيئًا. أعد المحاولة. (NET-TIMEOUT)"
    is UnknownHostException -> "تعذّر العثور على عنوان السيرفر من هذه الشبكة. (NET-DNS)"
    is NoRouteToHostException, is ConnectException -> "تعذّر الوصول إلى السيرفر من هذه الشبكة. جرّب شبكة أخرى أو أبلغ مسؤول السيرفر. (NET-CONNECT)"
    is SSLException -> "تعذّر إنشاء اتصال آمن بالسيرفر. أبلغ مسؤول السيرفر. (NET-TLS)"
    is EOFException, is SocketException -> "انقطع الاتصال قبل اكتمال استجابة السيرفر. أعد المحاولة. (NET-INTERRUPTED)"
    else -> "تعذّر إكمال الاتصال بالسيرفر؛ وجود الإنترنت وحده لا يؤكد الوصول إليه. أعد المحاولة أو جرّب شبكة أخرى. (NET-IO)"
}
