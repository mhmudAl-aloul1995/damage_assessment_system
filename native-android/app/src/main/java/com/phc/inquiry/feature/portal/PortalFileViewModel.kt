package com.phc.inquiry.feature.portal

import android.content.Context
import android.graphics.BitmapFactory
import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.AttachmentRenderer
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.damageassessment.ViewerState
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.*

@HiltViewModel class PortalFileViewModel @Inject constructor(saved: SavedStateHandle, private val repository: PortalRepository, private val vault: DraftVault, session: SessionState, @ApplicationContext private val context: Context): ViewModel() {
    private val owner = session.session.value?.user?.id ?: 0
    private val mode = saved.get<String>("mode").orEmpty()
    private val uuid = saved.get<String>("uuid").orEmpty()
    private val file = saved.get<String>("file").orEmpty()
    private val mutable = MutableStateFlow(ViewerState())
    val state = mutable.asStateFlow()
    private var bytes: ByteArray? = null
    private var mime = ""
    private var job: Job? = null
    init { load() }
    fun load() {
        job?.cancel()
        job = viewModelScope.launch {
            mutable.value = ViewerState()
            try {
                if(mode == "local") {
                    val draft = withContext(Dispatchers.IO) { vault.load(owner, uuid) }
                    val attachment = draft.attachments.firstOrNull { it.localId == file } ?: throw IllegalArgumentException("المرفق غير متاح لهذه المسودة.")
                    mime = attachment.mime
                    bytes = withContext(Dispatchers.IO) { vault.bytes(owner, attachment) }
                    mutable.value = mutable.value.copy(title = attachment.name)
                } else {
                    val receipt = repository.receipt(uuid)
                    val attachment = receipt.attachments.firstOrNull { it.id == file.toLongOrNull() } ?: throw IllegalArgumentException("المرفق غير متاح لهذا الإرسال.")
                    mime = attachment.mime
                    bytes = repository.attachment(attachment.id)
                    mutable.value = mutable.value.copy(title = attachment.name)
                }
                require(mime in setOf("image/jpeg", "image/png", "application/pdf"))
                render(0)
            } catch(cancelled: CancellationException) { throw cancelled }
            catch(error: Exception) { mutable.value = ViewerState(loading = false, error = error.message ?: "تعذّر عرض المرفق.") }
        }
    }
    fun page(index: Int) {
        if(mutable.value.loading || index !in 0 until mutable.value.pageCount) return
        job = viewModelScope.launch { render(index) }
    }
    private suspend fun render(index: Int) {
        mutable.update { it.copy(loading = true, error = null) }
        try {
            val rendered = withContext(Dispatchers.IO) {
                val body = bytes ?: throw java.io.IOException()
                if(mime == "application/pdf") AttachmentRenderer.pdf(context, body, index)
                else {
                    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
                    BitmapFactory.decodeByteArray(body, 0, body.size, bounds)
                    if(bounds.outWidth <= 0 || bounds.outHeight <= 0) throw java.io.IOException()
                    var sample = 1
                    while(bounds.outWidth / sample > 2048 || bounds.outHeight / sample > 2048) sample *= 2
                    val image = BitmapFactory.decodeByteArray(body, 0, body.size, BitmapFactory.Options().apply { inSampleSize = sample }) ?: throw java.io.IOException()
                    image to 1
                }
            }
            mutable.update { it.copy(loading = false, image = rendered.first, page = index, pageCount = rendered.second) }
        } catch(cancelled: CancellationException) { throw cancelled }
        catch (_: Exception) { mutable.update { it.copy(loading = false, error = "تعذّر قراءة الصورة أو PDF. جرّب مرفقًا صالحًا.") } }
    }
    override fun onCleared() { bytes = null; super.onCleared() }
}

