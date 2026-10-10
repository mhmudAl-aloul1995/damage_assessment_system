package com.phc.inquiry.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonPrimitive
import retrofit2.http.*

@Serializable
data class LoginRequest(val email: String, val password: String, @SerialName("device_name") val deviceName: String = "PHC Native Android")

@Serializable
data class User(val id: Long, val name: String, val email: String, val locale: String? = null)

@Serializable
data class LoginResponse(@SerialName("access_token") val token: String, @SerialName("expires_at") val expiresAt: String, val user: User)

@Serializable
data class Session(val token: String, val expiresAt: String, val user: User)

@Serializable
data class DataResponse<T>(val data: T)

@Serializable
data class Sector(val key: String, val title: String)

@Serializable
data class InquiryRecord(
    @SerialName("record_id") val recordId: Long,
    val objectid: JsonPrimitive? = null,
    val name: String? = null,
    @SerialName("building_name") val buildingName: String? = null,
    val municipality: String? = null,
    val neighborhood: String? = null,
    @SerialName("parentglobalid") val parentGlobalId: String? = null,
    @SerialName("damage_status") val damageStatus: String? = null,
    @SerialName("field_completed") val fieldCompleted: Boolean = false,
    @SerialName("audit_status") val auditStatus: String? = null,
) {
    val displayName: String get() = name?.takeIf { it.isNotBlank() } ?: buildingName?.takeIf { it.isNotBlank() } ?: "سجل ${objectid?.content ?: recordId}"
    val location: String get() = listOfNotNull(municipality, neighborhood).filter { it.isNotBlank() }.joinToString(" · ").ifBlank { "الموقع غير مسجل" }
}

@Serializable
data class RecordPage(val data: List<InquiryRecord>, val total: Int, @SerialName("current_page") val currentPage: Int, @SerialName("last_page") val lastPage: Int)

interface InquiryApi {
    @POST("api/v1/auth/login") suspend fun login(@Body request: LoginRequest): LoginResponse
    @POST("api/v1/auth/logout") suspend fun logout()
    @GET("api/v1/me") suspend fun me(): DataResponse<User>
    @GET("api/v1/damage-assessment/sectors") suspend fun sectors(): DataResponse<List<Sector>>
    @GET("api/v1/damage-assessment/{sector}")
    suspend fun search(@Path("sector") sector: String, @Query("search") search: String, @Query("page") page: Int): RecordPage
    @GET("api/v1/damage-assessment/{sector}/{record}")
    suspend fun detail(@Path("sector") sector: String, @Path("record") record: Long): DataResponse<InquiryRecord>
}
