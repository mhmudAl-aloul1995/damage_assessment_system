package com.phc.inquiry.core.security

import android.app.Service
import android.content.*
import android.graphics.Bitmap
import android.graphics.Color
import android.graphics.pdf.PdfRenderer
import android.os.*
import kotlinx.coroutines.*
import java.io.File
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

private const val RenderDescriptor = "com.phc.inquiry.AttachmentRenderer"

class AttachmentRenderService : Service() {
    override fun onBind(intent: Intent): IBinder = object : Binder() {
        override fun onTransact(code: Int, data: Parcel, reply: Parcel?, flags: Int): Boolean {
            if (code != IBinder.FIRST_CALL_TRANSACTION || reply == null) return super.onTransact(code, data, reply, flags)
            data.enforceInterface(RenderDescriptor)
            try {
                val descriptor = ParcelFileDescriptor.CREATOR.createFromParcel(data)
                val index = data.readInt()
                descriptor.use { ownedDescriptor -> PdfRenderer(ownedDescriptor).use { renderer ->
                    renderer.openPage(index).use { page ->
                        val scale = minOf(1600f / page.width, 1600f / page.height, 1f)
                        val image = Bitmap.createBitmap((page.width * scale).toInt().coerceAtLeast(1), (page.height * scale).toInt().coerceAtLeast(1), Bitmap.Config.ARGB_8888)
                        image.eraseColor(Color.WHITE)
                        page.render(image, null, null, PdfRenderer.Page.RENDER_MODE_FOR_DISPLAY)
                        reply.writeNoException()
                        reply.writeInt(renderer.pageCount)
                        image.writeToParcel(reply, Parcelable.PARCELABLE_WRITE_RETURN_VALUE)
                        image.recycle()
                    }
                } }
            } catch (_: Exception) { reply.writeException(IllegalArgumentException("Unable to render attachment")) }
            return true
        }
    }
}

object AttachmentRenderer {
    suspend fun pdf(context: Context, bytes: ByteArray, index: Int): Pair<Bitmap, Int> = withContext(Dispatchers.IO) {
        val file = File.createTempFile("phc-preview-", ".pdf", context.cacheDir)
        var connection: ServiceConnection? = null
        var bound = false
        try {
            file.writeBytes(bytes)
            val binder = withTimeoutOrNull(20000) {
                suspendCancellableCoroutine<IBinder> { continuation ->
                    val callback = object : ServiceConnection {
                        override fun onServiceConnected(name: ComponentName, service: IBinder) { if (continuation.isActive) continuation.resume(service) }
                        override fun onServiceDisconnected(name: ComponentName) { if (continuation.isActive) continuation.resumeWithException(java.io.IOException()) }
                        override fun onNullBinding(name: ComponentName) { if (continuation.isActive) continuation.resumeWithException(java.io.IOException()) }
                    }
                    connection = callback
                    bound = context.bindService(Intent(context, AttachmentRenderService::class.java), callback, Context.BIND_AUTO_CREATE)
                    if (!bound && continuation.isActive) continuation.resumeWithException(java.io.IOException())
                }
            } ?: throw java.io.IOException("Attachment renderer unavailable")
            val request = Parcel.obtain()
            val response = Parcel.obtain()
            try {
                request.writeInterfaceToken(RenderDescriptor)
                ParcelFileDescriptor.open(file, ParcelFileDescriptor.MODE_READ_ONLY).use { descriptor ->
                    descriptor.writeToParcel(request, 0)
                    request.writeInt(index)
                    if (!binder.transact(IBinder.FIRST_CALL_TRANSACTION, request, response, 0)) throw java.io.IOException()
                }
                response.readException()
                val count = response.readInt()
                val image = Bitmap.CREATOR.createFromParcel(response)
                currentCoroutineContext().ensureActive()
                image to count
            } finally { request.recycle(); response.recycle() }
        } finally {
            if (bound) connection?.let { context.unbindService(it) }
            file.delete()
        }
    }
}
