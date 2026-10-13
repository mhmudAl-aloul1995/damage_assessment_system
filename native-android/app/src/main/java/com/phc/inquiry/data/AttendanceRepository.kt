package com.phc.inquiry.data

import com.phc.inquiry.BuildConfig
import com.phc.inquiry.core.security.*
import java.time.Instant
import java.util.concurrent.TimeUnit
import java.util.concurrent.atomic.AtomicLong
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import kotlinx.serialization.json.Json
import okhttp3.MediaType.Companion.toMediaType
import okhttp3.OkHttpClient
import retrofit2.HttpException
import retrofit2.Retrofit
import retrofit2.converter.kotlinx.serialization.asConverterFactory

@Singleton
class AttendanceRepository @Inject constructor(private val store: AttendanceSessionStore) {
    val policy = AttendanceTransport(BuildConfig.HR_API_BASE_URL)
    @Volatile private var current: HrSession? = null
    private val generation = AtomicLong(0)
    private val api: AttendanceApi by lazy {
        val client = OkHttpClient.Builder().connectTimeout(15, TimeUnit.SECONDS).readTimeout(20, TimeUnit.SECONDS).callTimeout(30, TimeUnit.SECONDS)
            .followRedirects(false).followSslRedirects(false).retryOnConnectionFailure(false)
            .addInterceptor(AttendanceInterceptor(policy) { current?.token }).build()
        Retrofit.Builder().baseUrl(policy.base()).client(client)
            .addConverterFactory(Json { ignoreUnknownKeys = true }.asConverterFactory("application/json".toMediaType())).build().create(AttendanceApi::class.java)
    }
    fun session(): HrSession? = current
    fun clear() { generation.incrementAndGet(); current = null; store.clear() }

    fun restore(userId: Long): HrSession? {
        val restored = store.read()
        if (restored == null || restored.employee.userId != userId || restored.origin != BuildConfig.HR_API_BASE_URL || Instant.parse(restored.expiresAt) <= Instant.now()) {
            clear(); return null
        }
        current = restored
        return restored
    }

    suspend fun login(userId: Long, email: String, password: String): HrSession = request {
        val expectedGeneration = generation.get()
        val response = api.login(LoginRequest(email, password, "PHC Android attendance"))
        if (response.employee.userId != userId || generation.get() != expectedGeneration) throw InquiryFailure("حساب الحضور تغيّر أو لا يطابق الحساب المفتوح في التطبيق.")
        val session = HrSession(response.token, response.expiresAt, response.employee, BuildConfig.HR_API_BASE_URL)
        store.save(session); current = session
        session
    }
    suspend fun logout() {
        try { if (current != null) request { api.logout() } } finally { clear() }
    }
    suspend fun me() = request { api.me().data }
    suspend fun today() = request { api.today().data }
    suspend fun history(month: String) = request { api.history(month).data }
    suspend fun devices() = request { api.devices().data }
    suspend fun registrationChallenge() = request { api.registrationChallenge().data }
    suspend fun register(data: RegisterHrDevice) = request { api.register(data).data }
    suspend fun revoke(id: Long, reason: String) = request { api.revoke(id, HrDeviceDecision(reason = reason)).data }
    suspend fun challenge(data: HrChallengeRequest) = request { api.challenge(data).data }
    suspend fun submit(action: String, data: SignedHrAttendance): HrReceipt = request {
        val receipt = if (action == "check_in") api.checkIn(data).data else api.checkOut(data).data
        if (!receipt.confirmed || receipt.action != action) throw InquiryFailure("لم يُؤكد الخادم عملية الحضور.")
        receipt
    }

    private suspend fun <T> request(operation: suspend () -> T): T = withContext(Dispatchers.IO) {
        policy.base()
        val session = current
        if (session != null && Instant.parse(session.expiresAt) <= Instant.now()) { clear(); throw InquiryFailure("انتهت جلسة الحضور؛ سجّل الدخول مجدداً.") }
        try { operation() }
        catch (cancelled: CancellationException) { throw cancelled }
        catch (failure: HttpException) {
            if (failure.code() == 401) clear()
            val validation = if (failure.code() == 422) runCatching {
                Json { ignoreUnknownKeys = true }.decodeFromString<ValidationErrors>(failure.response()?.errorBody()?.string()?.take(4096) ?: "{}").errors.values.flatten().firstOrNull()
            }.getOrNull() else null
            throw InquiryFailure(when(failure.code()) {
                401 -> "انتهت جلسة الحضور؛ سجّل الدخول مجدداً."
                403 -> "لا تتوفر صلاحية العملية أو اعتماد الجهاز لهذا الحساب."
                422 -> validation?.take(180) ?: "تغيّرت حالة الحضور أو بيانات الطلب؛ حدّث الصفحة."
                429 -> "طلبات كثيرة؛ انتظر دقيقة وأعد المحاولة."
                503 -> "التسجيل غير متاح حتى تهيئة بوابة الحضور والتحقق منها."
                else -> "تعذّر تأكيد العملية من خادم الحضور."
            }, failure.code())
        } catch (failure: java.io.IOException) { throw InquiryFailure(failure.message?.take(180) ?: "تعذّر الوصول إلى بوابة الحضور الآمنة.") }
    }
}
