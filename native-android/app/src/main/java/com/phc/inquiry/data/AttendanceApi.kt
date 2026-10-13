package com.phc.inquiry.data

import kotlinx.serialization.SerialName
import kotlinx.serialization.Serializable
import retrofit2.http.*

@Serializable
data class HrEmployee(val id: Long, @SerialName("user_id") val userId: Long = 0, val name: String, @SerialName("employee_number") val number: String = "", @SerialName("capture_enabled") val captureEnabled: Boolean = false, val blockers: List<String> = emptyList())
@Serializable
data class HrLoginResponse(@SerialName("access_token") val token: String, @SerialName("expires_at") val expiresAt: String, val employee: HrEmployee)
@Serializable
data class HrSession(val token: String, val expiresAt: String, val employee: HrEmployee, val origin: String)
@Serializable
data class HrChallenge(val id: String, val protocol: String, val payload: String, @SerialName("expires_at") val expiresAt: String)
@Serializable
data class HrDevice(@SerialName("credential_id") val credentialId: Long, @SerialName("device_uuid") val uuid: String, val label: String, val status: String, @SerialName("attestation_status") val attestationStatus: String = "unverified")
@Serializable
data class RegisterHrDevice(@SerialName("challenge_id") val challengeId: String, @SerialName("device_uuid") val uuid: String, val label: String, @SerialName("public_key") val publicKey: String, val signature: String, @SerialName("attestation_chain") val chain: List<String> = emptyList())
@Serializable
data class HrChallengeRequest(@SerialName("credential_id") val credentialId: Long, val action: String, @SerialName("assignment_id") val assignmentId: Long? = null)
@Serializable
data class SignedHrAttendance(@SerialName("challenge_id") val challengeId: String, val signature: String)
@Serializable
data class HrDeviceDecision(val decision: String = "revoked", val reason: String)
@Serializable
data class HrReceipt(@SerialName("event_id") val eventId: Long, val action: String, @SerialName("work_date") val workDate: String, @SerialName("occurred_at_utc") val occurredAt: String, val confirmed: Boolean)
@Serializable
data class HrShift(@SerialName("assignment_id") val id: Long? = null, val schedule: String? = null, val timezone: String, val active: Boolean = false, @SerialName("check_in") val checkIn: String? = null, @SerialName("check_out") val checkOut: String? = null, @SerialName("worked_minutes") val workedMinutes: Int? = null)
@Serializable
data class HrAttendanceDay(val date: String, val status: String, val schedule: String? = null, val timezone: String, @SerialName("check_in") val checkIn: String? = null, @SerialName("check_out") val checkOut: String? = null, @SerialName("worked_minutes") val workedMinutes: Int? = null, @SerialName("late_minutes") val lateMinutes: Int? = null, @SerialName("early_minutes") val earlyMinutes: Int? = null, val conflict: Boolean = false, val sessions: List<HrShift> = emptyList(), @SerialName("capture_state") val captureState: HrCaptureState = HrCaptureState())
@Serializable
data class HrCaptureOption(val action: String, @SerialName("assignment_id") val assignmentId: Long, @SerialName("work_date") val workDate: String, val schedule: String, val timezone: String)
@Serializable
data class HrOpenSession(@SerialName("event_id") val eventId: Long, @SerialName("assignment_id") val assignmentId: Long, @SerialName("work_date") val workDate: String, @SerialName("check_in_utc") val checkInUtc: String, @SerialName("checkout_eligible") val checkoutEligible: Boolean)
@Serializable
data class HrCaptureState(@SerialName("open_session") val openSession: HrOpenSession? = null, val options: List<HrCaptureOption> = emptyList())

interface AttendanceApi {
    @POST("api/v1/hr/auth/login") suspend fun login(@Body data: LoginRequest): HrLoginResponse
    @POST("api/v1/hr/auth/logout") suspend fun logout()
    @GET("api/v1/hr/me") suspend fun me(): DataResponse<HrEmployee>
    @GET("api/v1/hr/attendance/today") suspend fun today(): DataResponse<HrAttendanceDay>
    @GET("api/v1/hr/attendance/history") suspend fun history(@Query("month") month: String): DataResponse<List<HrAttendanceDay>>
    @POST("api/v1/hr/devices/challenge") suspend fun registrationChallenge(): DataResponse<HrChallenge>
    @POST("api/v1/hr/devices/register") suspend fun register(@Body data: RegisterHrDevice): DataResponse<HrDevice>
    @GET("api/v1/hr/devices/status") suspend fun devices(): DataResponse<List<HrDevice>>
    @POST("api/v1/hr/devices/{id}/revoke") suspend fun revoke(@Path("id") credential: Long, @Body data: HrDeviceDecision): DataResponse<HrDevice>
    @POST("api/v1/hr/attendance/challenge") suspend fun challenge(@Body data: HrChallengeRequest): DataResponse<HrChallenge>
    @POST("api/v1/hr/attendance/check-in") suspend fun checkIn(@Body data: SignedHrAttendance): DataResponse<HrReceipt>
    @POST("api/v1/hr/attendance/check-out") suspend fun checkOut(@Body data: SignedHrAttendance): DataResponse<HrReceipt>
}
