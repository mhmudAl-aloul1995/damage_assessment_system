package com.phc.inquiry.feature.portal

import android.content.Context
import android.net.Uri
import android.os.Build
import android.security.KeyStoreException
import android.util.AtomicFile
import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.core.security.KeystoreEnvelope
import com.phc.inquiry.core.security.TransportPolicy
import com.phc.inquiry.data.*
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.serialization.*
import kotlinx.serialization.json.*
import java.io.File
import java.security.KeyStore
import java.security.MessageDigest
import java.util.UUID
import javax.crypto.Cipher
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.CancellationException

@Serializable data class DraftAttachment(val localId: String, val name: String, val mime: String, val size: Long, val remoteId: Long? = null)
data class DraftSummary(val uuid: String, val formKey: String, val title: String, val module: String, val recordId: Long?, val updatedAt: String, val pending: Boolean, val receiptId: Long?)
@Serializable data class FormDraft(val uuid: String = UUID.randomUUID().toString(), val owner: Long, val origin: String, val schema: FormSchema,
    val answers: JsonObject = schema.answers, val attachments: List<DraftAttachment> = emptyList(), val location: FormLocation? = null,
    val updatedAt: String = java.time.Instant.now().toString(), val pending: SubmissionRequest? = null, val receipt: SubmissionReceipt? = null, val needsReview: Boolean = false) {
    fun request() = SubmissionRequest(uuid, schema.version, schema.recordId, schema.recordVersion, FormRules.normalize(schema, answers), attachments.mapNotNull { it.remoteId }.distinct(), location)
    fun summary() = DraftSummary(uuid, schema.key, schema.title, schema.module, schema.recordId, updatedAt, pending != null, receipt?.id)
    fun afterRejection(error: FormFailure): FormDraft = if (receipt == null && error.canCorrectAnswers) copy(pending = null) else this
}

@Singleton class DraftVault @Inject constructor(@ApplicationContext private val context: Context, policy: TransportPolicy) : DraftSummaryStore, OfflineFormStore {
    val origin = policy.baseUrl.toString()
    private val root = File(context.noBackupFilesDir, "form-vault")
    private val json = Json { ignoreUnknownKeys = true; encodeDefaults = true }
    private val alias = "phc_forms_v1"
    private val envelope = KeystoreEnvelope("phc_forms_envelope_v2")
    private fun directory(owner: Long): File {
        require(owner > 0)
        val scope = MessageDigest.getInstance("SHA-256").digest("$origin|$owner".toByteArray()).joinToString("") { "%02x".format(it) }
        return File(root, scope).apply { if (!exists() && !mkdirs()) throw InquiryFailure("تعذّر إنشاء مخزن المسودات.") }
    }
    private fun safeId(id: String): String = UUID.fromString(id).toString()
    private fun legacyKey(): SecretKey {
        val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (store.getKey(alias, null) as? SecretKey)?.let { return it }
        throw InquiryFailure(DRAFT_STORAGE_MESSAGE)
    }
    private fun aad(file: File): ByteArray = (file.parentFile!!.name + ":" + file.name).toByteArray(Charsets.UTF_8)
    private fun write(file: File, bytes: ByteArray) {
        java.io.ByteArrayInputStream(bytes).use { writeStream(file, it, bytes.size) }
    }
    private fun writeStream(file: File, input: java.io.InputStream, limit: Int): Long {
        val writer = try { envelope.writer(aad(file)) }
        catch (_: Exception) { throw InquiryFailure("تعذّر تشغيل التشفير لحفظ المسودة على هذا الجهاز. أعد فتح التطبيق وحاول مجددًا.") }
        val cipher = writer.cipher
        val atomic = AtomicFile(file)
        val output = atomic.startWrite()
        try {
            output.write(writer.header)
            val buffer = ByteArray(8192)
            var total = 0L
            while(true) {
                val count = input.read(buffer)
                if(count < 0) break
                total += count
                if(total > limit) throw InquiryFailure("الملف يتجاوز الحد المسموح أو مساحة المخزن المتاحة.")
                cipher.update(buffer, 0, count)?.let(output::write)
            }
            if(total == 0L) throw InquiryFailure("الملف فارغ.")
            output.write(cipher.doFinal())
            atomic.finishWrite(output)
            return total
        }
        catch (error: Exception) {
            atomic.failWrite(output)
            if (error is java.security.GeneralSecurityException || error is java.security.ProviderException) throw InquiryFailure("تعذّر إتمام الحفظ المشفر على الجهاز. لا تغلق النموذج قبل نجاح الحفظ.")
            throw error
        }
    }
    private fun read(file: File, max: Int): ByteArray {
        if (file.length() > max + KeystoreEnvelope.MAX_OVERHEAD) throw InquiryFailure("حجم الملف المحفوظ غير صالح.")
        val bytes = try { AtomicFile(file).readFully() } catch (_: java.io.IOException) { throw InquiryFailure(DRAFT_STORAGE_MESSAGE) }
        if (bytes.size < 28 || bytes.size > max + KeystoreEnvelope.MAX_OVERHEAD) throw InquiryFailure("تعذّر فتح الملف المشفر.")
        return try {
            retryTransientRead(::isTransientKeystoreFailure) {
                if (KeystoreEnvelope.isEnvelope(bytes)) envelope.decrypt(bytes, aad(file))
                else Cipher.getInstance("AES/GCM/NoPadding").run {
                    init(Cipher.DECRYPT_MODE, legacyKey(), GCMParameterSpec(128, bytes.copyOfRange(0, 12)))
                    updateAAD(aad(file))
                    doFinal(bytes.copyOfRange(12, bytes.size))
                }
            }
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (error: Exception) { throw InquiryFailure(DRAFT_STORAGE_MESSAGE, cause = error) }
    }
    private fun isTransientKeystoreFailure(error: Exception): Boolean = Build.VERSION.SDK_INT >= 33 &&
        generateSequence<Throwable>(error) { it.cause }.take(8).filterIsInstance<KeyStoreException>().any { it.isTransientFailure }
    @Synchronized fun save(draft: FormDraft) {
        require(draft.origin == origin)
        val bytes = json.encodeToString(draft).toByteArray()
        if (bytes.size > 2 * 1024 * 1024) throw InquiryFailure("المسودة أكبر من الحد المسموح.")
        val dir = directory(draft.owner)
        if ((dir.listFiles { f -> f.extension == "draft" }?.size ?: 0) >= 100 && !File(dir, safeId(draft.uuid) + ".draft").exists()) throw InquiryFailure("الحد مئة مسودة. احذف المسودات غير المطلوبة.")
        write(File(dir, safeId(draft.uuid) + ".draft"), bytes)
    }
    @Synchronized override fun readCatalog(owner: Long): OfflineCatalog? {
        val file = File(directory(owner), "offline-catalog.cache")
        if (!file.exists() && !File(file.path + ".bak").exists()) return null
        return json.decodeFromString<OfflineCatalog>(read(file, 2 * 1024 * 1024).toString(Charsets.UTF_8))
    }
    @Synchronized override fun saveCatalog(owner: Long, catalog: OfflineCatalog) {
        require(catalog.schemas.values.all { it.recordId == null && it.recordVersion == null && it.answers.isEmpty() && !it.requiresRecord })
        val bytes = json.encodeToString(catalog).toByteArray(Charsets.UTF_8)
        require(bytes.size <= 2 * 1024 * 1024) { "حجم النماذج يتجاوز مساحة التنزيل المسموحة." }
        write(File(directory(owner), "offline-catalog.cache"), bytes)
    }
    @Synchronized override fun clearCatalog(owner: Long) { AtomicFile(File(directory(owner), "offline-catalog.cache")).delete() }
    @Synchronized fun load(owner: Long, uuid: String): FormDraft {
        val draft = json.decodeFromString<FormDraft>(read(File(directory(owner), safeId(uuid) + ".draft"), 2 * 1024 * 1024).toString(Charsets.UTF_8))
        require(draft.owner == owner && draft.origin == origin && draft.uuid == uuid)
        return draft
    }
    @Synchronized fun list(owner: Long): List<FormDraft> = directory(owner).listFiles { file -> file.extension == "draft" }.orEmpty()
        .map { load(owner, it.nameWithoutExtension) }.sortedByDescending { it.updatedAt }
    @Synchronized fun summaries(owner: Long): List<DraftSummary> = directory(owner).listFiles { file -> file.extension == "draft" }.orEmpty().asSequence()
        .map { load(owner, it.nameWithoutExtension).summary() }.sortedByDescending { it.updatedAt }.toList()
    @Synchronized override fun listing(owner: Long): DraftListing {
        val files = directory(owner).listFiles { file -> file.extension == "draft" } ?: throw InquiryFailure(DRAFT_STORAGE_MESSAGE)
        return collectDraftSummaries(files.map { it.nameWithoutExtension }) { load(owner, it).summary() }
    }
    @Synchronized override fun delete(owner: Long, uuid: String) {
        val draft = load(owner, uuid)
        require(draft.pending == null || draft.receipt != null) { "لا تحذف إرسالًا ينتظر تأكيد الاستلام." }
        draft.attachments.forEach { File(directory(owner), safeId(it.localId) + ".file").delete() }
        AtomicFile(File(directory(owner), safeId(uuid) + ".draft")).delete()
    }
    @Synchronized fun importAttachment(owner: Long, uri: Uri): DraftAttachment {
        val mime = context.contentResolver.getType(uri).orEmpty()
        if (mime !in setOf("image/jpeg", "image/png", "application/pdf")) throw InquiryFailure("اختر صورة JPEG أو PNG أو ملف PDF.")
        val dir = directory(owner)
        val remaining = 300L * 1024 * 1024 - dir.listFiles().orEmpty().sumOf { it.length() }
        if(remaining <= KeystoreEnvelope.MAX_OVERHEAD) throw InquiryFailure("مخزن المرفقات ممتلئ. أكمل المسودات واحذف النسخ المحلية غير المطلوبة.")
        val id = UUID.randomUUID().toString()
        val name = "مرفق-${id.take(8)}." + when(mime) { "image/jpeg" -> "jpg"; "image/png" -> "png"; else -> "pdf" }
        val size = context.contentResolver.openInputStream(uri)?.use { input -> writeStream(File(dir, "$id.file"), input, minOf(15 * 1024 * 1024L, remaining - KeystoreEnvelope.MAX_OVERHEAD).toInt()) }
            ?: throw InquiryFailure("تعذّر قراءة الملف المختار.")
        return DraftAttachment(id, name, mime, size)
    }
    @Synchronized fun bytes(owner: Long, attachment: DraftAttachment): ByteArray = read(File(directory(owner), safeId(attachment.localId) + ".file"), 15 * 1024 * 1024)
    @Synchronized fun removeFile(owner: Long, attachment: DraftAttachment) { AtomicFile(File(directory(owner), safeId(attachment.localId) + ".file")).delete() }
}


