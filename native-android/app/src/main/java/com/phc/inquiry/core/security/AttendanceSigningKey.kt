package com.phc.inquiry.core.security

import android.annotation.SuppressLint
import android.app.Activity
import android.content.Context
import android.hardware.biometrics.BiometricPrompt
import android.os.Build
import android.os.CancellationSignal
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import dagger.hilt.android.qualifiers.ApplicationContext
import java.security.KeyPairGenerator
import java.security.KeyStore
import java.security.MessageDigest
import java.security.Signature
import java.security.spec.ECGenParameterSpec
import java.util.Base64
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException
import kotlinx.coroutines.suspendCancellableCoroutine

data class AttendanceKey(val uuid: String, val alias: String, val publicKey: String, val certificates: List<String>)
data class AttendanceSignOperation(val alias: String, val payload: ByteArray, val label: String)

@Singleton
class AttendanceSigningKey @Inject constructor(@ApplicationContext context: Context) {
    private val preferences = context.getSharedPreferences("phc_hr_keys", Context.MODE_PRIVATE)
    private fun slot(employee: Long, origin: String) = employee.toString() + "_" +
        MessageDigest.getInstance("SHA-256").digest(origin.toByteArray()).joinToString("") { "%02x".format(it) }

    fun current(employee: Long, origin: String): AttendanceKey? {
        val uuid = preferences.getString(slot(employee, origin), null) ?: return null
        val alias = "phc_hr_sign_" + uuid
        val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        if (!store.containsAlias(alias)) return null
        val publicKey = pem("PUBLIC KEY", store.getCertificate(alias).publicKey.encoded)
        return AttendanceKey(uuid, alias, publicKey, store.getCertificateChain(alias).map { pem("CERTIFICATE", it.encoded) })
    }

    @SuppressLint("NewApi")
    fun generate(employee: Long, origin: String, nonce: ByteArray): AttendanceKey {
        if (Build.VERSION.SDK_INT < 28) throw InquiryFailure("هذا الإصدار لا يدعم توقيع الحضور عبر BiometricPrompt؛ استخدم مسار التصحيح المعتمد لدى HR.")
        val uuid = UUID.randomUUID().toString()
        val alias = "phc_hr_sign_" + uuid
        fun create(attestation: Boolean) {
            val specification = KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_SIGN)
                .setAlgorithmParameterSpec(ECGenParameterSpec("secp256r1"))
                .setDigests(KeyProperties.DIGEST_SHA256).setUserAuthenticationRequired(true)
                .setInvalidatedByBiometricEnrollment(true)
            if (Build.VERSION.SDK_INT >= 30) specification.setUserAuthenticationParameters(0, KeyProperties.AUTH_BIOMETRIC_STRONG)
            else specification.setUserAuthenticationValidityDurationSeconds(-1)
            if (attestation) specification.setAttestationChallenge(nonce)
            KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_EC, "AndroidKeyStore").apply { initialize(specification.build()) }.generateKeyPair()
        }
        try { create(true) } catch (failure: java.security.ProviderException) {
            KeyStore.getInstance("AndroidKeyStore").apply { load(null); if (containsAlias(alias)) deleteEntry(alias) }
            create(false)
        }
        if (!preferences.edit().putString(slot(employee, origin), uuid).commit()) throw InquiryFailure("تعذّر حفظ معرف مفتاح الحضور.")
        return current(employee, origin) ?: throw InquiryFailure("تعذّر إنشاء مفتاح توقيع الحضور.")
    }

    private fun pem(type: String, bytes: ByteArray): String = "-----BEGIN " + type + "-----\n" +
        Base64.getEncoder().encodeToString(bytes).chunked(64).joinToString("\n") + "\n-----END " + type + "-----\n"
}

@SuppressLint("NewApi")
suspend fun signAttendance(activity: Activity, operation: AttendanceSignOperation): String {
    if (Build.VERSION.SDK_INT < 28) throw InquiryFailure("هذا الجهاز لا يدعم توقيع الحضور البيومتري.")
    val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
    val signature = try {
        Signature.getInstance("SHA256withECDSA").apply { initSign(store.getKey(operation.alias, null) as java.security.PrivateKey) }
    } catch (failure: java.security.GeneralSecurityException) {
        throw InquiryFailure("مفتاح الحضور غير متاح أو أُلغي بعد تغيير البصمة؛ اطلب إلغاء الجهاز واستبداله لدى HR.")
    }
    return suspendCancellableCoroutine { continuation ->
        val cancellation = CancellationSignal()
        continuation.invokeOnCancellation { cancellation.cancel() }
        val prompt = BiometricPrompt.Builder(activity).setTitle("توثيق " + operation.label)
            .setSubtitle("توقيع طلب الحضور على هذا الجهاز")
            .setNegativeButton("إلغاء", activity.mainExecutor) { _, _ ->
                if (continuation.isActive) continuation.resumeWithException(InquiryFailure("أُلغي التوثيق؛ لم يُسجل حضور."))
            }.apply {
                if (Build.VERSION.SDK_INT >= 30) setAllowedAuthenticators(android.hardware.biometrics.BiometricManager.Authenticators.BIOMETRIC_STRONG)
            }.build()
        prompt.authenticate(BiometricPrompt.CryptoObject(signature), cancellation, activity.mainExecutor, object : BiometricPrompt.AuthenticationCallback() {
            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) {
                if (!continuation.isActive) return
                try {
                    val authenticated = result.cryptoObject?.signature ?: throw InquiryFailure("لم يُوثّق مفتاح التوقيع.")
                    authenticated.update(operation.payload)
                    continuation.resume(Base64.getEncoder().encodeToString(authenticated.sign()))
                } catch (failure: Exception) { continuation.resumeWithException(InquiryFailure("تعذّر توقيع طلب الحضور.")) }
            }
            override fun onAuthenticationError(code: Int, message: CharSequence) {
                if (continuation.isActive) continuation.resumeWithException(InquiryFailure("لم يكتمل توثيق الحضور: " + message.toString().take(160)))
            }
        })
    }
}
