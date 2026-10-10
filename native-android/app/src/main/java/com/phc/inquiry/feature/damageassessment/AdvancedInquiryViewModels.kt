package com.phc.inquiry.feature.damageassessment

import android.content.Context
import android.graphics.Bitmap
import android.graphics.BitmapFactory



import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import dagger.hilt.android.lifecycle.HiltViewModel
import dagger.hilt.android.qualifiers.ApplicationContext
import javax.inject.Inject
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.*


 data class HistoryState(val loading: Boolean = false, val data: List<AuditEntry> = emptyList(), val track: String = "engineering", val page: Int = 0, val lastPage: Int = 1, val error: String? = null)

@HiltViewModel
class AuditHistoryViewModel @Inject constructor(saved: SavedStateHandle, private val repository: InquiryRepository) : ViewModel() {
    private val sector: String = checkNotNull(saved["sector"])
    private val record: Long = checkNotNull(saved["record"])
    private val mutable = MutableStateFlow(HistoryState())
    val state = mutable.asStateFlow()
    private var job: Job? = null
    init { select("engineering") }
    fun select(track: String) { job?.cancel(); mutable.value = HistoryState(track = track); load() }
    fun load() {
        if (mutable.value.loading) return
        job = viewModelScope.launch {
            val previous = mutable.value
            mutable.value = previous.copy(loading = true, error = null)
            try {
                val page = repository.history(sector, record, previous.track, previous.page + 1)
                mutable.value = previous.copy(data = (previous.data + page.data).distinctBy { it.id }, page = page.currentPage, lastPage = page.lastPage)
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (error: InquiryFailure) { mutable.value = previous.copy(error = error.message) }
        }
    }
}

data class AttachmentsState(val loading: Boolean = true, val files: List<RecordAttachment> = emptyList(), val error: String? = null)
@HiltViewModel
class AttachmentsViewModel @Inject constructor(saved: SavedStateHandle, private val repository: InquiryRepository) : ViewModel() {
    private val sector: String = checkNotNull(saved["sector"])
    private val record: Long = checkNotNull(saved["record"])
    private val mutable = MutableStateFlow(AttachmentsState())
    val state = mutable.asStateFlow()
    init { refresh() }
    fun refresh() { viewModelScope.launch {
        mutable.value = AttachmentsState()
        try { mutable.value = AttachmentsState(false, repository.attachments(sector, record)) }
        catch (cancelled: CancellationException) { throw cancelled }
        catch (error: InquiryFailure) { mutable.value = AttachmentsState(false, error = error.message) }
    } }
}

data class ViewerState(val loading: Boolean = true, val title: String = "", val image: Bitmap? = null, val page: Int = 0, val pageCount: Int = 1, val error: String? = null)
@HiltViewModel
class AttachmentViewerViewModel @Inject constructor(saved: SavedStateHandle, private val repository: InquiryRepository, @ApplicationContext private val context: Context) : ViewModel() {
    private val sector: String = checkNotNull(saved["sector"])
    private val record: Long = checkNotNull(saved["record"])
    private val attachment: Long = checkNotNull(saved["attachment"])
    private val mutable = MutableStateFlow(ViewerState())
    val state = mutable.asStateFlow()
    private var bytes: ByteArray? = null
    private var type: String = ""
    init { load() }
    fun load() { viewModelScope.launch {
        mutable.value = ViewerState()
        try {
            val metadata = repository.attachments(sector, record).firstOrNull { it.id == attachment && it.viewable }
                ?: throw InquiryFailure(context.getString(com.phc.inquiry.R.string.attachment_unavailable))
            type = metadata.contentType
            bytes = repository.attachment(sector, record, attachment)
            mutable.value = mutable.value.copy(title = metadata.name)
            renderPage(0)
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (error: InquiryFailure) { mutable.value = ViewerState(loading = false, error = error.message) }
    } }
    fun page(index: Int) {
        if (mutable.value.loading || index !in 0 until mutable.value.pageCount) return
        viewModelScope.launch { renderPage(index) }
    }
    private suspend fun renderPage(index: Int) {
        mutable.value = mutable.value.copy(loading = true, error = null)
        try {
            val rendered = withContext(Dispatchers.IO) {
                val body = bytes ?: throw java.io.IOException()
                if (type == "application/pdf") {
                    com.phc.inquiry.core.security.AttachmentRenderer.pdf(context, body, index)
                } else {
                    val bounds = BitmapFactory.Options().apply { inJustDecodeBounds = true }
                    BitmapFactory.decodeByteArray(body, 0, body.size, bounds)
                    if (bounds.outWidth <= 0 || bounds.outHeight <= 0) throw java.io.IOException()
                    var sample = 1
                    while (bounds.outWidth / sample > 2048 || bounds.outHeight / sample > 2048) sample *= 2
                    val image = BitmapFactory.decodeByteArray(body, 0, body.size, BitmapFactory.Options().apply { inSampleSize = sample }) ?: throw java.io.IOException()
                    image to 1
                }
            }
            mutable.value = mutable.value.copy(loading = false, image = rendered.first, page = index, pageCount = rendered.second)
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (_: Exception) { mutable.value = mutable.value.copy(loading = false, error = context.getString(com.phc.inquiry.R.string.attachment_render_failed)) }
    }
    override fun onCleared() { bytes = null; super.onCleared() }
}

