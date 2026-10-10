package com.phc.inquiry.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.JsonPrimitive
import retrofit2.http.*

@Serializable
data class LoginRequest(val email: String, val password: String, @SerialName("device_name") val deviceName: String)

@Serializable
data class User(val id: Long, val name: String, val email: String, val locale: String? = null)

@Serializable
data class LoginResponse(@SerialName("access_token") val token: String, @SerialName("expires_at") val expiresAt: String, val user: User)

@Serializable
data class Session(val token: String, val expiresAt: String, val user: User)

@Serializable
data class DataResponse<T>(val data: T)

@Serializable
data class Sector(val key: String, val title: String, @SerialName("citizen_inquiry") val citizenInquiry: Boolean = false)

@Serializable
data class DetailField(val key: String, val label: String, val value: String? = null)
@Serializable
data class RecordCapabilities(@SerialName("audit_history") val auditHistory: Boolean = false, val attachments: Boolean = false, @SerialName("full_details") val fullDetails: Boolean = false)
@Serializable
data class FilterOptions(val municipalities: List<String> = emptyList(), val neighborhoods: List<String> = emptyList(), @SerialName("damage_statuses") val damageStatuses: List<String> = emptyList(), @SerialName("audit_statuses") val auditStatuses: List<String> = emptyList())
@Serializable
data class AuditEntry(val id: Long, val track: String, val status: String? = null, val label: String? = null, @SerialName("user_name") val userName: String? = null, val notes: String? = null, @SerialName("created_at") val createdAt: String? = null)
@Serializable
data class AuditPage(val data: List<AuditEntry>, val total: Int, @SerialName("current_page") val currentPage: Int, @SerialName("last_page") val lastPage: Int)
@Serializable
data class RecordAttachment(val id: Long, val name: String, @SerialName("content_type") val contentType: String, val size: Long? = null, val viewable: Boolean = false)
@Serializable
data class ValidationErrors(val errors: Map<String, List<String>> = emptyMap())

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
    val sector: String? = null,
    val details: List<DetailField> = emptyList(),
    val capabilities: RecordCapabilities = RecordCapabilities(),
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
    @GET("api/v1/damage-assessment/{sector}")
    suspend fun advancedSearch(@Path("sector") sector: String, @Query("search") search: String, @Query("page") page: Int, @QueryMap filters: Map<String, String>): RecordPage
    @GET("api/v1/damage-assessment/{sector}/filters")
    suspend fun filters(@Path("sector") sector: String, @Query("municipality") municipality: String?): DataResponse<FilterOptions>
    @GET("api/v1/damage-assessment/{sector}/{record}/history")
    suspend fun history(@Path("sector") sector: String, @Path("record") record: Long, @Query("track") track: String, @Query("page") page: Int): AuditPage
    @GET("api/v1/damage-assessment/{sector}/{record}/attachments")
    suspend fun attachments(@Path("sector") sector: String, @Path("record") record: Long): DataResponse<List<RecordAttachment>>
    @Streaming @GET("api/v1/damage-assessment/{sector}/{record}/attachments/{attachment}")
    suspend fun attachment(@Path("sector") sector: String, @Path("record") record: Long, @Path("attachment") attachment: Long): okhttp3.ResponseBody
    @GET("api/v1/damage-assessment/citizens")
    suspend fun citizens(@Query("search") search: String, @Query("page") page: Int): RecordPage
}
