package com.phc.inquiry.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import kotlinx.serialization.json.*
import okhttp3.MultipartBody
import okhttp3.RequestBody
import okhttp3.ResponseBody
import retrofit2.http.*

@Serializable data class PortalModule(val key: String, val title: String, val description: String = "", val sections: List<String> = emptyList(), @SerialName("record_search") val recordSearch: Boolean = false)
@Serializable data class PortalRecord(val id: Long, val module: String, val title: String? = null, val subtitle: String? = null, val status: String? = null, val version: String? = null, val details: List<DetailField> = emptyList())
@Serializable data class PortalPage(val data: List<PortalRecord>, val total: Long = 0, @SerialName("current_page") val currentPage: Int = 1, @SerialName("last_page") val lastPage: Int = 1)
@Serializable data class PublishedForm(val key: String, val module: String, val title: String, val version: String, @SerialName("requires_record") val requiresRecord: Boolean = false, @SerialName("can_submit") val canSubmit: Boolean = false, val sector: String? = null, @SerialName("target_kind") val targetKind: String = "record")
@Serializable data class FormChoice(val value: String, val label: String)
@Serializable data class FormCondition(val field: String, val equals: String)
@Serializable data class FormField(val key: String, val label: String, val type: String = "text", val required: Boolean = false, val choices: List<FormChoice> = emptyList(), val children: List<FormField> = emptyList(),
    @SerialName("visible_when") val visibleWhen: FormCondition? = null, @SerialName("max_length") val maxLength: Int? = null,
    val min: Double? = null, val max: Double? = null, @SerialName("max_items") val maxItems: Int = 20, @SerialName("exclusive_choices") val exclusiveChoices: List<String> = emptyList())
@Serializable data class FormSection(val title: String, val fields: List<FormField>)
@Serializable data class FormSchema(val key: String, val module: String, val title: String, val version: String, val sections: List<FormSection>,
    @SerialName("requires_record") val requiresRecord: Boolean, @SerialName("record_id") val recordId: Long? = null,
    @SerialName("record_version") val recordVersion: String? = null, val answers: JsonObject = JsonObject(emptyMap()))
@Serializable data class FormFile(val id: Long, val name: String, val mime: String, val size: Long)
@Serializable data class FormLocation(val latitude: Double, val longitude: Double, val accuracy: Double? = null)
@Serializable data class SubmissionRequest(@SerialName("submission_uuid") val uuid: String, @SerialName("form_version") val formVersion: String,
    @SerialName("record_id") val recordId: Long? = null, @SerialName("record_version") val recordVersion: String? = null,
    val answers: JsonObject, val attachments: List<Long> = emptyList(), val location: FormLocation? = null)
@Serializable data class SubmissionReceipt(val id: Long, @SerialName("submission_uuid") val uuid: String, @SerialName("form_key") val formKey: String, val module: String,
    @SerialName("record_id") val recordId: Long, @SerialName("record_version") val recordVersion: String,
    val status: String, @SerialName("received_at") val receivedAt: String, val attachments: List<FormFile> = emptyList())
@Serializable data class SubmissionResponse(val data: SubmissionReceipt, val replayed: Boolean)
@Serializable data class ReceiptPage(val data: List<SubmissionReceipt>, @SerialName("current_page") val currentPage: Int = 1, @SerialName("last_page") val lastPage: Int = 1)
@Serializable data class DiscardFileRequest(@SerialName("submission_uuid") val uuid: String)

interface PortalApi {
    @GET("api/v1/portal/modules") suspend fun modules(@Header("Authorization") authorization: String): DataResponse<List<PortalModule>>
    @GET("api/v1/portal/{module}/records") suspend fun records(@Header("Authorization") authorization: String, @Path("module") module: String, @Query("search") search: String, @Query("page") page: Int): PortalPage
    @GET("api/v1/portal/{module}/records/{record}") suspend fun record(@Header("Authorization") authorization: String, @Path("module") module: String, @Path("record") id: Long): DataResponse<PortalRecord>
    @GET("api/v1/forms") suspend fun forms(@Header("Authorization") authorization: String): DataResponse<List<PublishedForm>>
    @GET("api/v1/forms/{form}/schema") suspend fun schema(@Header("Authorization") authorization: String, @Path("form") form: String, @Query("record") id: Long?): DataResponse<FormSchema>
    @GET("api/v1/forms/{form}/records") suspend fun formRecords(@Header("Authorization") authorization: String, @Path("form") form: String, @Query("search") search: String, @Query("page") page: Int): PortalPage
    @POST("api/v1/forms/{form}/submissions") suspend fun submit(@Header("Authorization") authorization: String, @Path("form") form: String, @Body request: SubmissionRequest): SubmissionResponse
    @Multipart @POST("api/v1/forms/{form}/attachments") suspend fun upload(@Header("Authorization") authorization: String, @Path("form") form: String, @Part("submission_uuid") uuid: RequestBody, @Part file: MultipartBody.Part): DataResponse<FormFile>
    @GET("api/v1/submissions") suspend fun receipts(@Header("Authorization") authorization: String, @Query("page") page: Int): ReceiptPage
    @GET("api/v1/submissions/{uuid}") suspend fun receipt(@Header("Authorization") authorization: String, @Path("uuid") uuid: String): DataResponse<SubmissionReceipt>
    @Streaming @GET("api/v1/form-attachments/{attachment}") suspend fun attachment(@Header("Authorization") authorization: String, @Path("attachment") id: Long): ResponseBody
    @POST("api/v1/forms/{form}/attachments/{attachment}/discard") suspend fun discard(@Header("Authorization") authorization: String, @Path("form") form: String, @Path("attachment") id: Long, @Body request: DiscardFileRequest): JsonObject
}


