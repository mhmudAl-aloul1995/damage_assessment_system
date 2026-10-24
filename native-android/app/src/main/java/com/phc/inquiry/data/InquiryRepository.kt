package com.phc.inquiry.data

import com.phc.inquiry.core.security.*
import com.phc.inquiry.core.network.connectionFailureMessage
import java.io.IOException
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.withContext
import kotlinx.coroutines.ensureActive
import retrofit2.HttpException

@Singleton
class SessionState @Inject constructor() {
    private val current = MutableStateFlow<Session?>(null)
    private val notices = MutableStateFlow<String?>(null)
    private val currentAccess = MutableStateFlow<UserAccess?>(null)
    val access = currentAccess.asStateFlow()
    val session = current.asStateFlow()
    val notice = notices.asStateFlow()
    fun set(session: Session?) { current.value = session; currentAccess.value = session?.let { UserAccess(it.user, false) }; if (session != null) notices.value = null }
    fun enterOffline(user: User) { current.value = null; currentAccess.value = UserAccess(user, true); notices.value = null }
    fun expire() { set(null); notices.value = "انتهت الجلسة. سجّل الدخول مجددًا أو افتح العمل المحلي بقفل الجهاز." }
    fun token(): String? = current.value?.token
}
data class UserAccess(val user: User, val localOnly: Boolean)

interface InquiryRepository {
    suspend fun map(sector: String, filters: Map<String, String>): MapPage
    suspend fun statistics(sector: String, filters: Map<String, String>): SectorStatistics
    suspend fun restore()
    suspend fun login(email: String, password: String)
    suspend fun logout()
    suspend fun sectors(): List<Sector>
    suspend fun search(sector: String, query: String, page: Int): RecordPage
    suspend fun detail(sector: String, id: Long): InquiryRecord
    suspend fun searchFiltered(sector: String, query: String, page: Int, filters: Map<String, String>): RecordPage
    suspend fun filters(sector: String, municipality: String?): FilterOptions
    suspend fun history(sector: String, id: Long, track: String, page: Int): AuditPage
    suspend fun attachments(sector: String, id: Long): List<RecordAttachment>
    suspend fun attachment(sector: String, id: Long, attachment: Long): ByteArray
    suspend fun citizens(query: String, page: Int): RecordPage
}

@Singleton
class LaravelInquiryRepository @Inject constructor(
    private val api: InquiryApi,
    private val store: SessionStore,
    private val state: SessionState,
    private val policy: TransportPolicy,
    private val offlineIdentities: OfflineIdentityStore,
) : InquiryRepository {
    private suspend fun <T> request(action: suspend () -> T): T = withContext(Dispatchers.IO) {
        if (state.access.value?.localOnly == true) throw InquiryFailure("هذا فتح محلي فقط. سجّل الدخول مع وجود نت لاستخدام خدمات السيرفر.")
        try { action() }
        catch (cancelled: CancellationException) { throw cancelled }
        catch (error: HttpException) {
            if (error.code() == 401) { state.expire(); store.clear() }
            val validationMessage = if (error.code() == 422) runCatching {
                val body = error.response()?.errorBody()?.string()?.take(4096) ?: "{}"
                kotlinx.serialization.json.Json { ignoreUnknownKeys = true }.decodeFromString<ValidationErrors>(body).errors.values.flatten().firstOrNull()?.take(180)
            }.getOrNull() else null
            throw InquiryFailure(when (error.code()) {
                401 -> "انتهت الجلسة. سجّل الدخول مجددًا."
                403 -> "لا تملك صلاحية عرض هذه البيانات."
                404 -> "خدمة الاستعلام أو السجل غير متاح. تأكد من نشر API على السيرفر."
                422 -> validationMessage ?: "تحقق من البيانات المدخلة وحالة الحساب."
                429 -> "طلبات كثيرة. انتظر دقيقة ثم حاول مجددًا."
                413 -> "المرفق أكبر من الحد المسموح للعرض (15 ميغابايت)."
                415 -> "هذا النوع من المرفقات غير مدعوم للعرض."
                else -> "تعذّر إتمام الطلب من السيرفر. حاول مجددًا."
            }, error.code())
        }
        catch (error: InquiryFailure) { throw error }
        catch (error: IOException) { throw InquiryFailure(connectionFailureMessage(error)) }
        catch (_: Exception) { throw InquiryFailure("تعذّرت قراءة استجابة الخدمة. تحقق من تحديث API.") }
    }

    override suspend fun restore() = withContext(Dispatchers.IO) {
        val saved = store.read() ?: return@withContext
        try {
            policy.checkTransport()
            if (!saved.isUnexpiredAt()) { store.clear(); return@withContext }
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (_: Exception) { state.set(null); store.clear(); return@withContext }
        state.set(saved)
        try {
            val user = request { api.me().data }
            if(user.id != saved.user.id) { state.set(null); store.clear(); offlineIdentities.forget(); return@withContext }
            state.set(saved.copy(user = user))
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (error: InquiryFailure) { if(error.statusCode in setOf(401, 403)) { state.set(null); store.clear(); if (error.statusCode == 403) offlineIdentities.forget() } }
    }

    override suspend fun login(email: String, password: String) {
        policy.checkTransport()
        try {
            request {
                val result = api.login(LoginRequest(email, password, "PHC Native Android"))
                val session = Session(result.token, result.expiresAt, result.user)
                store.save(session)
                state.set(session)
            }
        } catch (error: InquiryFailure) {
            if (error.statusCode == 403) offlineIdentities.forget()
            throw error
        }
    }

    override suspend fun logout() {
        if (state.access.value?.localOnly == true) { state.set(null); store.clear(); return }
        try { request { api.logout() } }
        finally { withContext(kotlinx.coroutines.NonCancellable + Dispatchers.IO) { state.set(null); store.clear() } }
    }

    override suspend fun sectors(): List<Sector> = request { api.sectors().data.filter { it.key in SUPPORTED_SECTORS } }
    override suspend fun map(sector: String, filters: Map<String, String>): MapPage = request { api.map(sector, filters) }
    override suspend fun statistics(sector: String, filters: Map<String, String>): SectorStatistics = request { api.statistics(sector, filters) }
    override suspend fun search(sector: String, query: String, page: Int): RecordPage = request { api.search(sector, query, page) }
    override suspend fun detail(sector: String, id: Long): InquiryRecord = request { api.detail(sector, id).data }
    override suspend fun searchFiltered(sector: String, query: String, page: Int, filters: Map<String, String>): RecordPage = request { api.advancedSearch(sector, query, page, filters) }
    override suspend fun filters(sector: String, municipality: String?): FilterOptions = request { api.filters(sector, municipality).data }
    override suspend fun history(sector: String, id: Long, track: String, page: Int): AuditPage = request { api.history(sector, id, track, page) }
    override suspend fun attachments(sector: String, id: Long): List<RecordAttachment> = request { api.attachments(sector, id).data }
    override suspend fun citizens(query: String, page: Int): RecordPage = request { api.citizens(query, page) }
    override suspend fun attachment(sector: String, id: Long, attachment: Long): ByteArray = request {
        api.attachment(sector, id, attachment).use { body ->
            val limit = 15 * 1024 * 1024
            if (body.contentLength() > limit) throw InquiryFailure("المرفق أكبر من الحد المسموح للعرض.")
            val output = java.io.ByteArrayOutputStream()
            body.byteStream().use { input ->
                val buffer = ByteArray(8192)
                var read = input.read(buffer)
                while (read != -1) {
                    kotlinx.coroutines.currentCoroutineContext().ensureActive()
                    if (output.size() + read > limit) throw InquiryFailure("المرفق أكبر من الحد المسموح للعرض.")
                    output.write(buffer, 0, read); read = input.read(buffer)
                }
            }
            output.toByteArray()
        }
    }
}
