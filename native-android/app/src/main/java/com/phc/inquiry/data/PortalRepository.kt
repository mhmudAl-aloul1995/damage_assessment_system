package com.phc.inquiry.data

import com.phc.inquiry.core.security.*
import com.phc.inquiry.core.network.connectionFailureMessage
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.*
import kotlinx.serialization.json.*
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.MultipartBody
import okhttp3.RequestBody.Companion.toRequestBody
import retrofit2.HttpException
import java.io.IOException

class FormFailure(val code: Int?, val errors: Map<String, List<String>> = emptyMap(), message: String) : IOException(message) {
    val definitelyRejected: Boolean get() = code in setOf(400, 401, 403, 404, 409, 413, 415, 422, 429)
    val canCorrectAnswers: Boolean get() = code == 422 || (code == 409 && message in setOf("تغيّر إصدار النموذج. حدّث النموذج قبل الإرسال.", "تغيّر السجل على السيرفر. راجع آخر نسخة قبل الإرسال."))
}

@Singleton
class PortalRepository @Inject constructor(private val api: PortalApi, private val session: SessionState, private val store: SessionStore, private val offline: OfflineFormStore) {
    private val catalogWrites = kotlinx.coroutines.sync.Mutex()
    private fun owner(): Long = session.access.value?.user?.id ?: throw FormFailure(401, message = "سجّل الدخول أو افتح العمل المحلي بقفل الجهاز.")
    private fun checkOwner(id: Long) { if (owner() != id) throw FormFailure(null, message = "تغيّر الحساب. افتح النماذج بالحساب الذي نزّلها.") }
    suspend fun cachedCatalog(): OfflineCatalog? = withContext(Dispatchers.IO) {
        val id = owner()
        offline.readCatalog(id)?.also { checkOwner(id) }
    }
    suspend fun refreshCatalog(): OfflineCatalog = withContext(Dispatchers.IO) {
        val id = owner()
        try {
            val modules = modules()
            val forms = forms()
            catalogWrites.lock()
            try {
                checkOwner(id)
                val old = runCatching { offline.readCatalog(id) }.getOrNull()
                val retained = old?.schemas.orEmpty().filterKeys { key -> forms.any { it.key == key && it.canSubmit && !it.requiresRecord } }
                val catalog = OfflineCatalog(modules, forms, retained, old?.updatedAt.orEmpty())
                try { offline.saveCatalog(id, catalog); catalog }
                catch (cancelled: CancellationException) { throw cancelled }
                catch (_: Exception) {
                    runCatching { offline.clearCatalog(id) }
                    catalog.copy(schemas = emptyMap(), storageWarning = "تعمل النماذج عبر الاتصال، لكن تعذّر حفظها للميدان على هذا الجهاز. أعد التنزيل وتأكد من مساحة التخزين؛ المسودات لها حفظ مستقل.")
                }
            } finally { catalogWrites.unlock() }
        } catch (error: FormFailure) {
            if (error.code in setOf(401, 403)) offline.clearCatalog(id)
            throw error
        }
    }
    suspend fun downloadForm(form: String): OfflineCatalog = withContext(Dispatchers.IO) {
        val id = owner()
        val schema = try { schema(form, null, refresh = true) }
        catch (error: FormFailure) {
            if (error.code in setOf(401, 403, 404)) offline.clearCatalog(id)
            throw error
        }
        require(!schema.requiresRecord && schema.recordId == null && schema.recordVersion == null && schema.answers.isEmpty()) { "هذا النموذج يحتاج سجلًا متصلاً بالسيرفر. افتحه مسبقًا واحفظه كمسودة." }
        catalogWrites.lock()
        try {
            checkOwner(id)
            val catalog = offline.readCatalog(id) ?: throw FormFailure(null, message = "حدّث قائمة النماذج قبل التنزيل.")
            require(catalog.forms.any { it.key == form && it.canSubmit && !it.requiresRecord }) { "النموذج غير متاح للتنزيل لحسابك." }
            catalog.copy(schemas = catalog.schemas + (form to schema), updatedAt = java.time.Instant.now().toString()).also { offline.saveCatalog(id, it) }
        } finally { catalogWrites.unlock() }
    }
    private fun requirePublishedForm(form: String) {
        if (form !in PUBLISHED_FORM_KEYS) throw FormFailure(404, message = "هذا الاستبيان غير متاح في تطبيق الموبايل.")
    }
    private suspend fun <T> request(mutation: Boolean = false, action: suspend (String) -> T): T = withContext(Dispatchers.IO) {
        val owner = session.session.value ?: throw FormFailure(401, message = "هذا فتح محلي فقط. سجّل الدخول مع وجود نت للإرسال أو تحديث بيانات السيرفر؛ مسودتك محفوظة.")
        try {
            val result = action("Bearer ${owner.token}")
            if (session.session.value?.user?.id != owner.user.id) throw FormFailure(null, message = "تغيّر الحساب. افتح المسودة بالحساب الذي أنشأها.")
            result
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (error: HttpException) {
            if (error.code() == 401 && session.session.value?.user?.id == owner.user.id) { session.expire(); store.clear() }
            val json = runCatching { Json.parseToJsonElement(error.response()?.errorBody()?.string()?.take(16384).orEmpty()).jsonObject }.getOrNull()
            val fields = json?.get("errors")?.let { runCatching { it.jsonObject.mapValues { entry -> entry.value.jsonArray.map { value -> value.jsonPrimitive.content.take(200) } } }.getOrNull() }.orEmpty()
            val message = when(error.code()) {
                401 -> "انتهت الجلسة. سجّل الدخول مجددًا؛ المسودة محفوظة لهذا الحساب."
                403 -> "لا يملك حسابك صلاحية هذه العملية. قد تحتاج تسجيل الدخول مجددًا لتحديث صلاحيات التطبيق."
                404 -> "الخدمة أو السجل غير متاح. تأكد من نشر تحديث API."
                409 -> json?.get("message")?.jsonPrimitive?.content?.take(240) ?: "تغيّر النموذج أو السجل. راجع النسخة الحالية."
                422 -> fields.values.flatten().firstOrNull() ?: json?.get("message")?.jsonPrimitive?.content?.take(240) ?: "تحقق من الإجابات."
                429 -> "طلبات كثيرة. انتظر دقيقة ثم أعد المحاولة."
                413 -> "حجم المرفق أكبر من الحد المسموح."
                503 -> if (json?.get("code")?.jsonPrimitive?.contentOrNull == "mobile_form_storage_not_ready")
                    "خدمة إرسال الاستبيانات وسجل الإرساليات تحتاج تهيئة من مسؤول السيرفر. يمكنك متابعة الاستعلام وحفظ المسودات على الجهاز."
                    else if (mutation) "تعذّر تأكيد استلام السيرفر. أعد المحاولة من نفس المسودة." else "الخدمة غير متاحة مؤقتًا. أعد المحاولة لاحقًا."
                else -> if (mutation) "تعذّر تأكيد استلام السيرفر. أعد المحاولة من نفس المسودة." else "تعذّر تحميل البيانات من السيرفر. أعد المحاولة لاحقًا."
            }
            throw FormFailure(error.code(), fields, message)
        } catch (error: FormFailure) { throw error }
        catch (error: InquiryFailure) { throw FormFailure(error.statusCode, message = error.message ?: "تعذّر تنفيذ الطلب.") }
        catch (error: IOException) { throw FormFailure(null, message = connectionFailureMessage(error) + if (mutation) " لم يتم تأكيد الاستلام؛ أعد المحاولة من نفس المسودة المحفوظة." else "") }
        catch (_: Exception) { throw FormFailure(null, message = if (mutation) "تعذّرت قراءة الاستجابة. المسودة محفوظة؛ تحقق من تحديث API." else "تعذّرت قراءة بيانات السيرفر. تأكد من تحديث الخدمة.") }
    }
    suspend fun modules(): List<PortalModule> = request { api.modules(it).data.filter { module -> module.key in MODULE_KEYS } }
    suspend fun forms(): List<PublishedForm> = request { api.forms(it).data.filter { form -> form.module in MODULE_KEYS && form.key in PUBLISHED_FORM_KEYS } }
    suspend fun records(module: String, query: String, page: Int): PortalPage = request { api.records(it, module, query, page) }
    suspend fun record(module: String, id: Long): PortalRecord = request { api.record(it, module, id).data }
    suspend fun formRecords(form: String, query: String, page: Int): PortalPage = request { requirePublishedForm(form); api.formRecords(it, form, query, page) }
    suspend fun schema(form: String, id: Long?, refresh: Boolean = false): FormSchema {
        requirePublishedForm(form)
        if (id == null && !refresh) {
            val catalog = try { cachedCatalog() }
            catch (cancelled: CancellationException) { throw cancelled }
            catch (_: Exception) { null }
            if (form in catalog?.readyKeys.orEmpty()) return requireNotNull(catalog?.schemas?.get(form))
        }
        return request {
            api.schema(it, form, id).data.also { schema ->
                require(schema.key == form && schema.recordId == id && schema.sections.isNotEmpty() && schema.sections.flatMap { it.fields }.size <= 500) { "تعذّرت قراءة النموذج. حدّث خدمة الاستبيانات." }
            }
        }
    }
    suspend fun submit(form: String, body: SubmissionRequest): SubmissionReceipt = request(mutation = true) { requirePublishedForm(form); api.submit(it, form, body).data }
    suspend fun receipts(page: Int = 1): ReceiptPage = request { api.receipts(it, page).let { rows -> rows.copy(data = rows.data.filter { receipt -> receipt.formKey in PUBLISHED_FORM_KEYS }) } }
    suspend fun receipt(uuid: String): SubmissionReceipt = request { api.receipt(it, uuid).data.also { receipt -> requirePublishedForm(receipt.formKey) } }
    suspend fun discard(form: String, uuid: String, id: Long) = request(mutation = true) { requirePublishedForm(form); api.discard(it, form, id, DiscardFileRequest(uuid)); Unit }
    suspend fun attachment(id: Long): ByteArray = request { auth ->
        api.attachment(auth, id).use { body ->
            val limit = 15 * 1024 * 1024
            if(body.contentLength() > limit) throw FormFailure(413, message = "حجم المرفق أكبر من الحد المسموح.")
            val output = java.io.ByteArrayOutputStream()
            body.byteStream().use { input ->
                val buffer = ByteArray(8192)
                while(true) {
                    currentCoroutineContext().ensureActive()
                    val count = input.read(buffer)
                    if(count < 0) break
                    if(output.size() + count > limit) throw FormFailure(413, message = "حجم المرفق أكبر من الحد المسموح.")
                    output.write(buffer, 0, count)
                }
            }
            output.toByteArray()
        }
    }
    suspend fun upload(form: String, uuid: String, name: String, mime: String, bytes: ByteArray): FormFile = request(mutation = true) {
        requirePublishedForm(form)
        if (bytes.size > 15 * 1024 * 1024) throw FormFailure(413, message = "الحد الأقصى للمرفق 15 ميغابايت.")
        api.upload(it, form, uuid.toRequestBody("text/plain".toMediaType()), MultipartBody.Part.createFormData("file", name.replace("\"", "_").replace("\r", "").replace("\n", ""), bytes.toRequestBody(mime.toMediaType()))).data
    }
}
val MODULE_KEYS = setOf("damage_assessment", "damage_assessment_borrowers", "heks", "heks-shelter", "hr", "administration")
val PUBLISHED_FORM_KEYS = setOf("borrower-survey", "heks-beneficiary", "heks-followup", "heks-phase-three")

