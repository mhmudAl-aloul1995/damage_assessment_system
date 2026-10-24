package com.phc.inquiry.core.security

import android.content.Context
import android.util.Base64
import com.phc.inquiry.data.Session
import dagger.hilt.android.qualifiers.ApplicationContext
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json

interface SessionStore {
    fun read(): Session?
    fun save(session: Session)
    fun clear()
}

@Singleton
class KeystoreSessionStore @Inject constructor(@ApplicationContext context: Context) : SessionStore {
    private val preferences = context.getSharedPreferences("phc_session", Context.MODE_PRIVATE)
    private val alias = "phc_inquiry_session_v1"
    private val envelope = KeystoreEnvelope("phc_inquiry_session_envelope_v2")
    private val aad = "phc_session".toByteArray(Charsets.UTF_8)
    private val json = Json { ignoreUnknownKeys = true }

    private fun key(): SecretKey {
        val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (store.getKey(alias, null) as? SecretKey)?.let { return it }
        throw InquiryFailure("مفتاح الجلسة القديمة غير متاح. أعد تسجيل الدخول.")
    }

    override fun read(): Session? {
        val encoded = preferences.getString("ciphertext", null) ?: return null
        return try {
            val plain = if (encoded.startsWith("env2:")) envelope.decrypt(Base64.decode(encoded.substring(5), Base64.NO_WRAP), aad)
            else {
                val parts = encoded.split(":", limit = 2)
                val cipher = Cipher.getInstance("AES/GCM/NoPadding")
                cipher.init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(128, Base64.decode(parts[0], Base64.NO_WRAP)))
                cipher.doFinal(Base64.decode(parts[1], Base64.NO_WRAP))
            }
            json.decodeFromString<Session>(String(plain, Charsets.UTF_8))
        } catch (_: Exception) { clear(); null }
    }

    override fun save(session: Session) {
        val plain = json.encodeToString(session).toByteArray(Charsets.UTF_8)
        val encrypted = envelope.encrypt(plain, aad)
        require(envelope.decrypt(encrypted, aad).contentEquals(plain))
        val encoded = "env2:" + Base64.encodeToString(encrypted, Base64.NO_WRAP)
        if (!preferences.edit().putString("ciphertext", encoded).commit()) throw InquiryFailure("تعذّر حفظ الجلسة المشفرة على الجهاز.")
    }

    override fun clear() { preferences.edit().clear().commit() }
}
