package com.phc.inquiry.data

import com.phc.inquiry.core.security.*
import java.io.IOException
import java.net.SocketTimeoutException
import java.time.Instant
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.withContext
import retrofit2.HttpException

@Singleton
class SessionState @Inject constructor() {
    private val current = MutableStateFlow<Session?>(null)
    private val notices = MutableStateFlow<String?>(null)
    val session = current.asStateFlow()
    val notice = notices.asStateFlow()
    fun set(session: Session?) { current.value = session; if (session != null) notices.value = null }
    fun expire() { current.value = null; notices.value = "انتهت الجلسة. سجّل الدخول مجددًا." }
    fun token(): String? = current.value?.token
}

interface InquiryRepository {
    suspend fun restore()
    suspend fun login(email: String, password: String)
    suspend fun logout()
    suspend fun sectors(): List<Sector>
    suspend fun search(sector: String, query: String, page: Int): RecordPage
    suspend fun detail(sector: String, id: Long): InquiryRecord
}

@Singleton
class LaravelInquiryRepository @Inject constructor(
    private val api: InquiryApi,
    private val store: SessionStore,
    private val state: SessionState,
    private val policy: TransportPolicy,
) : InquiryRepository {
    private suspend fun <T> request(action: suspend () -> T): T = withContext(Dispatchers.IO) {
        try { action() }
        catch (cancelled: CancellationException) { throw cancelled }
        catch (error: HttpException) {
            if (error.code() == 401) { state.expire(); store.clear() }
            throw InquiryFailure(when (error.code()) {
                401 -> "انتهت الجلسة. سجّل الدخول مجددًا."
                403 -> "لا تملك صلاحية عرض هذه البيانات."
                404 -> "خدمة الاستعلام أو السجل غير متاح. تأكد من نشر API على السيرفر."
                422 -> "تحقق من البيانات المدخلة وحالة الحساب."
                429 -> "طلبات كثيرة. انتظر دقيقة ثم حاول مجددًا."
                else -> "تعذّر إتمام الطلب من السيرفر. حاول مجددًا."
            })
        }
        catch (error: InquiryFailure) { throw error }
        catch (_: SocketTimeoutException) { throw InquiryFailure("انتهت مهلة الاتصال. تحقق من الشبكة وحاول مجددًا.") }
        catch (_: IOException) { throw InquiryFailure("تعذّر الاتصال بالسيرفر. تحقق من الإنترنت.") }
        catch (_: Exception) { throw InquiryFailure("تعذّرت قراءة استجابة الخدمة. تحقق من تحديث API.") }
    }

    override suspend fun restore() = withContext(Dispatchers.IO) {
        val saved = store.read() ?: return@withContext
        try {
            policy.checkTransport()
            if (!Instant.parse(saved.expiresAt).isAfter(Instant.now())) { store.clear(); return@withContext }
            state.set(saved)
            val user = request { api.me().data }
            state.set(saved.copy(user = user))
        } catch (cancelled: CancellationException) { throw cancelled }
        catch (_: Exception) { state.set(null); store.clear() }
    }

    override suspend fun login(email: String, password: String) {
        policy.checkTransport()
        request {
            val result = api.login(LoginRequest(email, password, "PHC Native Android"))
            val session = Session(result.token, result.expiresAt, result.user)
            store.save(session)
            state.set(session)
        }
    }

    override suspend fun logout() {
        try { request { api.logout() } }
        finally { withContext(kotlinx.coroutines.NonCancellable + Dispatchers.IO) { state.set(null); store.clear() } }
    }

    override suspend fun sectors(): List<Sector> = request { api.sectors().data.filter { it.key in setOf("buildings", "housing-units") } }
    override suspend fun search(sector: String, query: String, page: Int): RecordPage = request { api.search(sector, query, page) }
    override suspend fun detail(sector: String, id: Long): InquiryRecord = request { api.detail(sector, id).data }
}
