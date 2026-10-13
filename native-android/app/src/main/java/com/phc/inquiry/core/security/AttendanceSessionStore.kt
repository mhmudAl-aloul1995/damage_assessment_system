package com.phc.inquiry.core.security

import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import com.phc.inquiry.data.HrSession
import dagger.hilt.android.qualifiers.ApplicationContext
import java.security.KeyStore
import java.util.Base64
import javax.crypto.Cipher
import javax.crypto.KeyGenerator
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import javax.inject.Inject
import javax.inject.Singleton
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json

@Singleton
class AttendanceSessionStore @Inject constructor(@ApplicationContext context: Context) {
    private val preferences = context.getSharedPreferences("phc_hr_session", Context.MODE_PRIVATE)
    private val alias = "phc_hr_session_encryption_v1"
    private val json = Json { ignoreUnknownKeys = true }
    private fun key(): SecretKey {
        val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
        (store.getKey(alias, null) as? SecretKey)?.let { return it }
        return KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore").apply {
            init(KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_ENCRYPT or KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE).build())
        }.generateKey()
    }
    fun read(): HrSession? {
        val encoded = preferences.getString("ciphertext", null) ?: return null
        return runCatching {
            val parts = encoded.split(":", limit = 2)
            val cipher = Cipher.getInstance("AES/GCM/NoPadding")
            cipher.init(Cipher.DECRYPT_MODE, key(), GCMParameterSpec(128, Base64.getDecoder().decode(parts[0])))
            json.decodeFromString<HrSession>(cipher.doFinal(Base64.getDecoder().decode(parts[1])).toString(Charsets.UTF_8))
        }.getOrElse { clear(); null }
    }
    fun save(session: HrSession) {
        val cipher = Cipher.getInstance("AES/GCM/NoPadding")
        cipher.init(Cipher.ENCRYPT_MODE, key())
        val encrypted = cipher.doFinal(json.encodeToString(session).toByteArray(Charsets.UTF_8))
        val encoded = Base64.getEncoder().encodeToString(cipher.iv) + ":" + Base64.getEncoder().encodeToString(encrypted)
        if (!preferences.edit().putString("ciphertext", encoded).commit()) throw InquiryFailure("تعذّر حفظ جلسة الحضور الآمنة.")
    }
    fun clear() { preferences.edit().clear().commit() }
}
