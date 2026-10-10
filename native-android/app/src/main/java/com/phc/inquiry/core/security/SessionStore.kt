package com.phc.inquiry.core.security

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import android.util.Base64
import com.phc.inquiry.data.Session
import dagger.hilt.android.qualifiers.ApplicationContext
import java.security.KeyStore
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
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
    private val json = Json { ignoreUnknownKeys = true }

    private fun key(): SecretKey {
        val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (store.getKey(alias, null) as? SecretKey)?.let { return it }
        return KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore").apply {
            init(KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build())
        }.generateKey()
    }

    override fun read(): Session? {
        val encoded = preferences.getString("ciphertext", null) ?: return null
        return try {
            val parts = encoded.split(":", limit = 2)
            val cipher = Cipher.getInstance("AES/GCM/NoPadding")
            cipher.init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(128, Base64.decode(parts[0], Base64.NO_WRAP)))
            json.decodeFromString<Session>(String(cipher.doFinal(Base64.decode(parts[1], Base64.NO_WRAP)), Charsets.UTF_8))
        } catch (_: Exception) { clear(); null }
    }

    override fun save(session: Session) {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, key())
        val encrypted = cipher.doFinal(json.encodeToString(session).toByteArray(Charsets.UTF_8))
        val encoded = Base64.encodeToString(cipher.iv, Base64.NO_WRAP) + ":" + Base64.encodeToString(encrypted, Base64.NO_WRAP)
        if (!preferences.edit().putString("ciphertext", encoded).commit()) throw InquiryFailure("تعذّر حفظ الجلسة المشفرة على الجهاز.")
    }

    override fun clear() { preferences.edit().clear().commit() }
}
