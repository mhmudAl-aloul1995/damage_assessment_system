package com.phc.inquiry.core.security

import android.app.KeyguardManager
import android.content.Context
import android.security.keystore.KeyGenParameterSpec
import android.security.keystore.KeyPermanentlyInvalidatedException
import android.security.keystore.UserNotAuthenticatedException
import android.util.AtomicFile
import com.phc.inquiry.data.User
import dagger.hilt.android.qualifiers.ApplicationContext
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import java.io.File
import java.security.KeyStore
import java.security.MessageDigest
import java.time.Instant
import javax.crypto.Cipher
import javax.crypto.SecretKey
import javax.crypto.spec.GCMParameterSpec
import javax.inject.Inject
import javax.inject.Singleton

@Singleton class DeviceOfflineIdentityStore @Inject constructor(@ApplicationContext private val context: Context, policy: TransportPolicy) : OfflineIdentityStore {
    private val origin = policy.baseUrl.toString()
    private val scope = MessageDigest.getInstance("SHA-256").digest(origin.toByteArray()).joinToString("") { "%02x".format(it) }
    private val alias = "phc_offline_identity_$scope"
    private val envelope = KeystoreEnvelope("${alias}_envelope_v2", authenticated = true)
    private val file = File(context.noBackupFilesDir, "offline-identity-$scope.sealed")
    private val json = Json { ignoreUnknownKeys = true }
    private fun keyStore(): KeyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
    private fun secureDevice(): Boolean = context.getSystemService(KeyguardManager::class.java).isDeviceSecure
    override fun available(): Boolean = secureDevice() && (file.exists() || File(file.path + ".bak").exists()) && (envelope.available() || keyStore().containsAlias(alias))
    override fun prepare(enrolling: Boolean) {
        require(secureDevice()) { "فعّل رمزًا أو نمطًا أو كلمة مرور لقفل الجهاز أولًا، ثم أعد المحاولة." }
        if (!enrolling) require(available()) { "الفتح المحلي غير متاح. أعد تفعيله مع وجود نت." }
    }
    override fun credentialOnly(): Boolean = true
    internal fun enrollmentKeySpec(legacy: Boolean = false): KeyGenParameterSpec = envelope.keySpec(legacy)
    private fun cipher(mode: Int, iv: ByteArray? = null): Cipher {
        return try {
            val key = keyStore().getKey(alias, null) as? SecretKey ?: throw InquiryFailure("مفتاح الفتح المحلي غير متاح. أعد التفعيل مع وجود نت.")
            Cipher.getInstance("AES/GCM/NoPadding").apply {
                if (iv == null) init(mode, key) else init(mode, key, GCMParameterSpec(128, iv))
                updateAAD(origin.toByteArray(Charsets.UTF_8))
            }
        } catch (_: KeyPermanentlyInvalidatedException) {
            throw InquiryFailure("أُلغي مفتاح قفل الجهاز. سجّل الدخول وأعد تفعيل الفتح المحلي؛ مسوداتك محفوظة.")
        }
    }
    @Synchronized override fun enroll(user: User) {
        require(secureDevice() && user.id > 0)
        try { envelope.ensureKey() }
        catch (error: Exception) { throw OfflineAccessFailure("OFF-KEY-CREATE", "تعذّر إنشاء مفتاح مرتبط برمز قفل الجهاز. أعد تشغيل الهاتف وأعد التفعيل؛ المسودات محفوظة.", error) }
        val bytes = try {
            val plain = json.encodeToString(OfflineIdentity(user, origin, Instant.now().toString())).toByteArray(Charsets.UTF_8)
            val aad = origin.toByteArray(Charsets.UTF_8)
            envelope.encrypt(plain, aad).also { require(envelope.decrypt(it, aad).contentEquals(plain)) }
        } catch (error: UserNotAuthenticatedException) { throw OfflineAccessFailure("OFF-KEY-AUTH", "لم يكتمل اعتماد رمز قفل الجهاز للتشفير. أعد التفعيل وأكّد الرمز مجددًا.", error) }
        catch (error: InquiryFailure) { throw error }
        catch (error: Exception) { throw OfflineAccessFailure("OFF-KEY-ENCRYPT", "تعذّر تشفير هوية الفتح المحلي على هذا الجهاز. المسودات محفوظة.", error) }
        val atomic = AtomicFile(file)
        val output = try { atomic.startWrite() } catch (error: Exception) { throw OfflineAccessFailure("OFF-IDENTITY-WRITE", "تعذّر حفظ تفعيل الفتح المحلي. تحقق من مساحة الجهاز وأعد المحاولة.", error) }
        try { output.write(bytes); atomic.finishWrite(output) }
        catch (error: Exception) { atomic.failWrite(output); throw OfflineAccessFailure("OFF-IDENTITY-WRITE", "تعذّر حفظ تفعيل الفتح المحلي. تحقق من مساحة الجهاز وأعد المحاولة.", error) }
    }
    @Synchronized override fun unlock(): OfflineIdentity {
        require(secureDevice()) { "قفل الجهاز غير مفعّل. أعد التفعيل مع وجود نت." }
        require(file.length() <= 16384) { "ملف الفتح المحلي غير صالح." }
        val bytes = AtomicFile(file).readFully()
        require(bytes.size in 28..16384) { "ملف الفتح المحلي غير صالح." }
        val plain = if (KeystoreEnvelope.isEnvelope(bytes)) envelope.decrypt(bytes, origin.toByteArray(Charsets.UTF_8))
        else cipher(Cipher.DECRYPT_MODE, bytes.copyOfRange(0, 12)).doFinal(bytes.copyOfRange(12, bytes.size))
        return json.decodeFromString<OfflineIdentity>(plain.toString(Charsets.UTF_8)).also {
            require(it.origin == origin && it.user.id > 0) { "الحساب المحلي لا يخص هذا السيرفر." }
        }
    }
    @Synchronized override fun forget() { AtomicFile(file).delete(); keyStore().deleteEntry(alias); envelope.forget() }
}
