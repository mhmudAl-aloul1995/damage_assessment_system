package com.phc.inquiry.feature.portal

import androidx.lifecycle.SavedStateHandle
import androidx.lifecycle.ViewModel
import androidx.lifecycle.viewModelScope
import com.phc.inquiry.data.*
import dagger.hilt.android.lifecycle.HiltViewModel
import javax.inject.Inject
import kotlinx.coroutines.*
import kotlinx.coroutines.flow.*
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlinx.serialization.json.*

data class PortalState(val loading: Boolean = true, val modules: List<PortalModule> = emptyList(), val forms: List<PublishedForm> = emptyList(),
    val drafts: List<DraftSummary> = emptyList(), val receipts: List<SubmissionReceipt> = emptyList(), val receiptsPage: Int = 0, val receiptsLast: Int = 1, val error: String? = null,
    val receiptsLoading: Boolean = false, val receiptsError: String? = null)
@HiltViewModel class PortalViewModel @Inject constructor(private val repository: PortalRepository, private val vault: DraftVault, private val session: SessionState): ViewModel() {
    private val mutable = MutableStateFlow(PortalState())
    val state = mutable.asStateFlow()
    private var job: Job? = null
    private var receiptsJob: Job? = null
    private var owner: Long? = null
    init { viewModelScope.launch { session.session.map { it?.user?.id }.distinctUntilChanged().collect { id -> job?.cancel(); receiptsJob?.cancel(); owner = id; mutable.value = PortalState(); if (id != null) refresh() } } }
    fun refresh() {
        val id = owner ?: return
        job?.cancel()
        job = viewModelScope.launch {
            mutable.update { it.copy(loading = true, error = null) }
            try {
                val drafts = withContext(Dispatchers.IO) { vault.summaries(id).filter { it.formKey in PUBLISHED_FORM_KEYS } }
                mutable.update { it.copy(drafts = drafts) }
                val modules = repository.modules()
                mutable.update { it.copy(modules = modules) }
                val forms = repository.forms()
                mutable.update { it.copy(forms = forms) }
                mutable.update { it.copy(loading = false) }
            } catch (cancelled: CancellationException) { throw cancelled }
            catch (error: Exception) { mutable.update { it.copy(loading = false, error = error.message ?: "تعذّر تحديث البوابة.") } }
        }
    }
    fun localDrafts() {
        val id = owner ?: return
        viewModelScope.launch {
            try { val drafts = withContext(Dispatchers.IO) { vault.summaries(id).filter { it.formKey in PUBLISHED_FORM_KEYS } }; if(owner == id) mutable.update { it.copy(drafts = drafts) } }
            catch (error: Exception) { mutable.update { it.copy(error = "تعذّر فتح المسودات المشفرة. ${error.message.orEmpty()}") } }
        }
    }
    fun moreReceipts() {
        if (mutable.value.receiptsLoading || mutable.value.receiptsPage >= mutable.value.receiptsLast) return
        loadReceipts(reset = false)
    }
    fun refreshReceipts() = loadReceipts(reset = true)
    private fun loadReceipts(reset: Boolean) {
        val id = owner ?: return
        receiptsJob?.cancel()
        receiptsJob = viewModelScope.launch {
            val nextPage = if (reset) 1 else mutable.value.receiptsPage + 1
            mutable.update { it.copy(receiptsLoading = true, receiptsError = null) }
            try {
                val page = repository.receipts(nextPage)
                if (owner == id) mutable.update { it.copy(receiptsLoading = false, receipts = if (reset) page.data else (it.receipts + page.data).distinctBy { row -> row.id }, receiptsPage = page.currentPage, receiptsLast = page.lastPage) }
            }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (error: Exception) { if (owner == id) mutable.update { it.copy(receiptsLoading = false, receiptsError = error.message ?: "تعذّر تحميل سجل الإرساليات.") } }
        }
    }
    fun deleteDraft(uuid: String) {
        val id = owner ?: return
        viewModelScope.launch {
            try { withContext(Dispatchers.IO) { vault.delete(id, uuid) }; localDrafts() }
            catch (error: Exception) { mutable.update { it.copy(error = error.message ?: "تعذّر حذف المسودة.") } }
        }
    }
}
data class PortalRecordsState(val loading: Boolean = false, val rows: List<PortalRecord> = emptyList(), val total: Long = 0, val page: Int = 0, val last: Int = 1, val error: String? = null, val searched: Boolean = false)
@HiltViewModel class PortalRecordsViewModel @Inject constructor(saved: SavedStateHandle, private val repository: PortalRepository): ViewModel() {
    val module = saved.get<String>("module").orEmpty()
    val form = saved.get<String>("form")?.takeUnless { it == "-" }
    private var query = ""
    private var job: Job? = null
    private val mutable = MutableStateFlow(PortalRecordsState())
    val state = mutable.asStateFlow()
    init { search("") }
    fun search(value: String) { query = value.trim(); load(false) }
    fun more() { if (!mutable.value.loading && mutable.value.page < mutable.value.last) load(true) }
    fun retry() = load(false)
    private fun load(append: Boolean) {
        if(query.length > 150) { mutable.update { it.copy(error = "الحد الأقصى للبحث 150 حرفًا.") }; return }
        job?.cancel()
        job = viewModelScope.launch {
            mutable.update { it.copy(loading = true, error = null) }
            try {
                val page = if(append) mutable.value.page + 1 else 1
                val result = if(form != null) repository.formRecords(form, query, page) else repository.records(module, query, page)
                mutable.value = PortalRecordsState(false, if(append) (mutable.value.rows + result.data).distinctBy { it.id } else result.data, result.total, result.currentPage, result.lastPage, searched = true)
            } catch (cancelled: CancellationException) { throw cancelled }
            catch(error: Exception) { mutable.update { it.copy(loading = false, error = error.message) } }
        }
    }
}
data class PortalDetailState(val loading: Boolean = true, val row: PortalRecord? = null, val error: String? = null)
@HiltViewModel class PortalDetailViewModel @Inject constructor(saved: SavedStateHandle, private val repository: PortalRepository): ViewModel() {
    val module = saved.get<String>("module").orEmpty()
    val record = saved.get<Long>("record") ?: 0
    private val mutable = MutableStateFlow(PortalDetailState())
    val state = mutable.asStateFlow()
    init { refresh() }
    fun refresh() { viewModelScope.launch {
        mutable.value = PortalDetailState()
        try { mutable.value = PortalDetailState(false, repository.record(module, record)) }
        catch(cancelled: CancellationException) { throw cancelled }
        catch(error: Exception) { mutable.value = PortalDetailState(false, error = error.message) }
    } }
}

data class FormState(val loading: Boolean = true, val draft: FormDraft? = null, val section: Int = 0, val errors: Map<String, String> = emptyMap(),
    val saving: Boolean = false, val submitting: Boolean = false, val error: String? = null, val notice: String? = null, val saved: Boolean = false)
@HiltViewModel class FormViewModel @Inject constructor(saved: SavedStateHandle, private val repository: PortalRepository, private val vault: DraftVault, session: SessionState): ViewModel() {
    private val owner = session.session.value?.user?.id ?: 0
    private val form = saved.get<String>("form").orEmpty()
    private val record = saved.get<Long>("record")?.takeIf { it > 0 }
    private val uuid = saved.get<String>("draft")?.takeUnless { it == "-" }
    private val mutable = MutableStateFlow(FormState())
    val state = mutable.asStateFlow()
    private val writes = Mutex()
    private var latestSave: FormDraft? = null
    private val removedFiles = mutableListOf<DraftAttachment>()
    private var saveJob: Job? = null
    init { load() }
    fun load() { viewModelScope.launch {
        mutable.value = FormState()
        try {
            require(form in PUBLISHED_FORM_KEYS) { "هذا الاستبيان غير متاح في تطبيق الموبايل." }
            val draft = if(uuid != null) withContext(Dispatchers.IO) { vault.load(owner, uuid) } else FormDraft(owner = owner, origin = vault.origin, schema = repository.schema(form, record))
            require(draft.schema.key in PUBLISHED_FORM_KEYS) { "هذا الاستبيان غير متاح في تطبيق الموبايل." }
            require(draft.schema.sections.isNotEmpty() && draft.schema.sections.flatMap { it.fields }.size <= 500)
            mutable.value = FormState(false, draft, saved = uuid != null)
            persist(draft)
        } catch(cancelled: CancellationException) { throw cancelled }
        catch(error: Exception) { mutable.value = FormState(false, error = error.message ?: "تعذّر فتح النموذج.") }
    } }
    private fun editable(): FormDraft? = mutable.value.draft?.takeIf { it.pending == null && it.receipt == null && !mutable.value.submitting }
    fun answer(key: String, value: JsonElement) {
        val draft = editable() ?: return
        val updated = draft.copy(answers = JsonObject(draft.answers + (key to value)), updatedAt = java.time.Instant.now().toString())
        mutable.update { it.copy(draft = updated, errors = it.errors.filterKeys { field -> field != key && !field.startsWith("$key.") }, notice = null) }
        persist(updated)
    }
    private fun persist(draft: FormDraft, removed: DraftAttachment? = null) {
        latestSave = draft
        if(removed != null) removedFiles += removed
        mutable.update { it.copy(saving = true, saved = false) }
        if(saveJob?.isActive == true) return
        saveJob = viewModelScope.launch {
            try {
                delay(300)
                while(latestSave != null) {
                    val snapshot = latestSave!!
                    latestSave = null
                    val removals = removedFiles.toList(); removedFiles.clear()
                    writes.withLock { withContext(Dispatchers.IO) { vault.save(snapshot); removals.forEach { vault.removeFile(owner, it) } } }
                }
                mutable.update { it.copy(saved = true) }
            }
            catch(cancelled: CancellationException) { throw cancelled }
            catch(error: Exception) { mutable.update { it.copy(saved = false, error = "تعذّر حفظ المسودة على الجهاز. ${error.message.orEmpty()}") } }
            finally { mutable.update { it.copy(saving = false) } }
        }
    }
    fun next() {
        val draft = mutable.value.draft ?: return
        val section = mutable.value.section
        val errors = FormRules.validate(draft.schema, draft.answers, section)
        mutable.update { it.copy(errors = errors, section = if(errors.isEmpty()) (section + 1).coerceAtMost(draft.schema.sections.size) else section) }
    }
    fun previous() { mutable.update { it.copy(section = (it.section - 1).coerceAtLeast(0)) } }
    fun section(index: Int) { val draft = mutable.value.draft ?: return; mutable.update { it.copy(section = index.coerceIn(0, draft.schema.sections.size)) } }
    fun addAttachment(uri: android.net.Uri) {
        val draft = editable() ?: return
        if (draft.attachments.size >= 5) { mutable.update { it.copy(error = "الحد خمسة مرفقات.") }; return }
        mutable.update { it.copy(submitting = true) }
        viewModelScope.launch {
            try {
                val attachment = withContext(Dispatchers.IO) { vault.importAttachment(owner, uri) }
                val current = mutable.value.draft ?: draft
                if(current.attachments.sumOf { it.size } + attachment.size > 30L * 1024 * 1024) {
                    withContext(Dispatchers.IO) { vault.removeFile(owner, attachment) }
                    throw IllegalArgumentException("الحد الأقصى للمجموع 30 ميغابايت.")
                }
                val updated = current.copy(attachments = current.attachments + attachment, updatedAt = java.time.Instant.now().toString())
                mutable.update { it.copy(draft = updated) }; persist(updated)
            } catch(cancelled: CancellationException) { throw cancelled }
            catch(error: Exception) { mutable.update { it.copy(error = error.message) } }
            finally { mutable.update { it.copy(submitting = false) } }
        }
    }
    fun removeAttachment(localId: String) {
        val draft = editable() ?: return
        val removed = draft.attachments.firstOrNull { it.localId == localId } ?: return
        val updated = draft.copy(attachments = draft.attachments.filterNot { it.localId == localId })
        if(removed.remoteId != null && updated.attachments.none { it.remoteId == removed.remoteId }) {
            mutable.update { it.copy(submitting = true) }
            viewModelScope.launch {
                try { repository.discard(draft.schema.key, draft.uuid, removed.remoteId); mutable.update { it.copy(draft = updated) }; persist(updated, removed) }
                catch(cancelled: CancellationException) { throw cancelled }
                catch(error: Exception) { mutable.update { it.copy(error = "تعذّر تأكيد إزالة المرفق المؤقت. أعد المحاولة. ${error.message.orEmpty()}") } }
                finally { mutable.update { it.copy(submitting = false) } }
            }
        } else { mutable.update { it.copy(draft = updated) }; persist(updated, removed) }
    }
    fun location(location: FormLocation) {
        val draft = editable() ?: return
        val updated = draft.copy(location = location)
        mutable.update { it.copy(draft = updated, notice = "أُضيف الموقع إلى المسودة.") }; persist(updated)
    }
    fun notice(message: String) { mutable.update { it.copy(notice = message) } }
    fun retrySave() { mutable.value.draft?.let { persist(it) } }
    fun acceptReview() {
        val draft = editable() ?: return
        val updated = draft.copy(needsReview = false)
        mutable.update { it.copy(draft = updated) }; persist(updated)
    }
    fun submit() {
        val original = mutable.value.draft ?: return
        if(mutable.value.submitting || mutable.value.saving || original.receipt != null) return
        if(original.needsReview) { mutable.update { it.copy(error = "راجع قيم السيرفر الحالية وأكّد المراجعة قبل الإرسال.", section = original.schema.sections.size) }; return }
        val errors = FormRules.validate(original.schema, original.answers)
        if(original.pending == null && errors.isNotEmpty()) {
            val index = original.schema.sections.indexOfFirst { section -> section.fields.any { field -> errors.keys.any { it == field.key || it.startsWith("${field.key}.") } } }
            mutable.update { it.copy(errors = errors, section = index.coerceAtLeast(0)) }; return
        }
        mutable.update { it.copy(submitting = true, error = null) }
        viewModelScope.launch {
            var draft = original
            try {
                writes.withLock {
                    if(draft.pending == null) {
                        for(attachment in draft.attachments.filter { it.remoteId == null }) {
                            val bytes = withContext(Dispatchers.IO) { vault.bytes(owner, attachment) }
                            val uploaded = repository.upload(draft.schema.key, draft.uuid, attachment.name, attachment.mime, bytes)
                            draft = draft.copy(attachments = draft.attachments.map { if(it.localId == attachment.localId) it.copy(remoteId = uploaded.id) else it })
                            withContext(Dispatchers.IO) { vault.save(draft) }
                            mutable.update { it.copy(draft = draft) }
                        }
                        draft = draft.copy(pending = draft.request())
                        withContext(Dispatchers.IO) { vault.save(draft) }
                        mutable.update { it.copy(draft = draft, saved = true) }
                    }
                    val receipt = repository.submit(draft.schema.key, draft.pending!!)
                    require(receipt.uuid == draft.uuid && receipt.status == "received" && receipt.formKey == draft.schema.key)
                    draft = draft.copy(receipt = receipt)
                    withContext(Dispatchers.IO) { vault.save(draft) }
                    mutable.update { it.copy(draft = draft, saved = true, notice = "تم استلام الاستبيان على السيرفر. رقم الإيصال ${receipt.id}.") }
                }
            } catch(cancelled: CancellationException) { throw cancelled }
            catch(error: Exception) {
                if(error is FormFailure && error.definitelyRejected && draft.pending != null && original.pending == null) {
                    draft = draft.copy(pending = null)
                    try { withContext(Dispatchers.IO) { vault.save(draft) } } catch (_: Exception) { /* Keep in-memory state; next resume retries persisted request. */ }
                }
                val fields = (error as? FormFailure)?.errors.orEmpty().mapKeys { it.key.removePrefix("answers.") }.mapValues { it.value.firstOrNull().orEmpty() }
                mutable.update { it.copy(draft = draft, error = error.message ?: "تعذّر الإرسال.", errors = fields) }
            } finally { mutable.update { it.copy(submitting = false) } }
        }
    }
    fun refreshSchema() {
        val draft = editable() ?: return
        viewModelScope.launch {
            mutable.update { it.copy(loading = true, error = null) }
            try {
                val schema = repository.schema(draft.schema.key, draft.schema.recordId)
                // Existing answers stay intact for explicit user review; new schema/version is never silently submitted.
                val keys = schema.sections.flatMap { it.fields }.map { it.key }.toSet()
                val updated = draft.copy(schema = schema, answers = JsonObject(draft.answers.filterKeys { it in keys }), updatedAt = java.time.Instant.now().toString(), needsReview = true)
                mutable.update { it.copy(loading = false, draft = updated, section = 0, notice = "حُدّث النموذج وإصدار السجل. راجع إجاباتك قبل الإرسال.") }; persist(updated)
            } catch(cancelled: CancellationException) { throw cancelled }
            catch(error: Exception) { mutable.update { it.copy(loading = false, error = error.message) } }
        }
    }
}


