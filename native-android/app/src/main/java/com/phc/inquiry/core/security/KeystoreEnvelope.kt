package com.phc.inquiry.core.security

import android.os.Build
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyProperties
import java.security.KeyPairGenerator
import java.security.KeyStore
import java.security.PrivateKey
import java.security.SecureRandom
import java.security.spec.MGF1ParameterSpec
import javax.crypto.Cipher
import javax.crypto.spec.GCMParameterSpec
import javax.crypto.spec.OAEPParameterSpec
import javax.crypto.spec.PSource
import javax.crypto.spec.SecretKeySpec

/** Uses a decrypt-only Keystore key, including on providers that discard combined AES purposes. */
internal class KeystoreEnvelope(private val alias: String, private val authenticated: Boolean = false) {
    data class Writer(val header: ByteArray, val cipher: Cipher)

    private fun store(): KeyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
    fun available(): Boolean = store().containsAlias(alias)
    fun forget() { store().deleteEntry(alias) }

    @Suppress("DEPRECATION")
    fun keySpec(legacy: Boolean = false): KeyGenParameterSpec = KeyGenParameterSpec.Builder(alias, KeyProperties.PURPOSE_DECRYPT)
        .setKeySize(2048).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_RSA_OAEP)
        .setDigests(KeyProperties.DIGEST_SHA256).apply {
            if (authenticated) {
                setUserAuthenticationRequired(true)
                if (Build.VERSION.SDK_INT >= 30 && !legacy) setUserAuthenticationParameters(15, KeyProperties.AUTH_DEVICE_CREDENTIAL)
                else setUserAuthenticationValidityDurationSeconds(15)
            }
        }.build()

    @Synchronized fun ensureKey() {
        if (available()) return
        fun generate(legacy: Boolean) {
            KeyPairGenerator.getInstance(KeyProperties.KEY_ALGORITHM_RSA, "AndroidKeyStore").apply { initialize(keySpec(legacy)) }.generateKeyPair()
        }
        try { generate(false) }
        catch (error: Exception) {
            if (!authenticated || Build.VERSION.SDK_INT < 30) throw error
            store().deleteEntry(alias)
            generate(true)
        }
    }

    private fun rsa(mode: Int): Cipher = Cipher.getInstance("RSA/ECB/OAEPPadding").apply {
        val store = store()
        val key = if (mode == Cipher.ENCRYPT_MODE) store.getCertificate(alias)?.publicKey else store.getKey(alias, null) as? PrivateKey
        requireNotNull(key) { "مفتاح التشفير المحلي غير متاح." }
        init(mode, key, OAEPParameterSpec("SHA-256", "MGF1", MGF1ParameterSpec.SHA1, PSource.PSpecified.DEFAULT))
    }

    fun writer(aad: ByteArray): Writer {
        ensureKey()
        val material = ByteArray(32).also { SecureRandom().nextBytes(it) }
        try {
            val wrapped = rsa(Cipher.ENCRYPT_MODE).doFinal(material)
            require(wrapped.size == WRAPPED_SIZE)
            val recovered = rsa(Cipher.DECRYPT_MODE).doFinal(wrapped)
            try { require(java.security.MessageDigest.isEqual(material, recovered)) }
            finally { recovered.fill(0) }
            val cipher = Cipher.getInstance("AES/GCM/NoPadding").apply { init(Cipher.ENCRYPT_MODE, SecretKeySpec(material, "AES")) }
            require(cipher.iv.size == 12)
            val header = MAGIC + wrapped + cipher.iv
            cipher.updateAAD(aad); cipher.updateAAD(header)
            return Writer(header, cipher)
        } finally { material.fill(0) }
    }

    fun encrypt(plain: ByteArray, aad: ByteArray): ByteArray = writer(aad).let { it.header + it.cipher.doFinal(plain) }

    fun decrypt(bytes: ByteArray, aad: ByteArray): ByteArray {
        require(isEnvelope(bytes) && bytes.size >= HEADER_SIZE + 16) { "ملف التشفير غير صالح." }
        val material = rsa(Cipher.DECRYPT_MODE).doFinal(bytes.copyOfRange(MAGIC.size, MAGIC.size + WRAPPED_SIZE))
        try {
            require(material.size == 32)
            return Cipher.getInstance("AES/GCM/NoPadding").run {
                init(Cipher.DECRYPT_MODE, SecretKeySpec(material, "AES"), GCMParameterSpec(128, bytes.copyOfRange(HEADER_SIZE - 12, HEADER_SIZE)))
                updateAAD(aad); updateAAD(bytes.copyOfRange(0, HEADER_SIZE))
                doFinal(bytes.copyOfRange(HEADER_SIZE, bytes.size))
            }
        } finally { material.fill(0) }
    }

    companion object {
        private val MAGIC = "PHCENV02".toByteArray(Charsets.US_ASCII)
        private const val WRAPPED_SIZE = 256
        private const val HEADER_SIZE = 8 + WRAPPED_SIZE + 12
        const val MAX_OVERHEAD = 512
        fun isEnvelope(bytes: ByteArray): Boolean = bytes.size >= MAGIC.size && MAGIC.indices.all { bytes[it] == MAGIC[it] }
    }
}
