package com.phc.inquiry

import android.content.Context
import android.content.ContextWrapper
import android.content.SharedPreferences
import android.security.keystore.KeyInfo
import android.security.keystore.KeyProperties
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.Session
import com.phc.inquiry.data.User
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import java.security.KeyFactory
import java.security.KeyStore
import java.security.PrivateKey
import java.util.UUID

@RunWith(AndroidJUnit4::class)
class KeystorePurposeTest {
    @Test fun decryptOnlyEnvelopeRoundTripsWithNonExportableKeyOnTheDevice() {
        val alias = "phc_fixture_envelope_${UUID.randomUUID()}"
        val envelope = KeystoreEnvelope(alias)
        try {
            val original = "fixture-sensitive-key-material".toByteArray()
            val aad = "fixture-account:fixture-file".toByteArray()
            val sealed = envelope.encrypt(original, aad)
            assertTrue(KeystoreEnvelope.isEnvelope(sealed))
            assertArrayEquals(original, KeystoreEnvelope(alias).decrypt(sealed, aad))
            assertFalse(sealed.toString(Charsets.UTF_8).contains("fixture-sensitive"))
            assertFalse(sealed.contentEquals(envelope.encrypt(original, aad)))
            val store = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
            val privateKey = store.getKey(alias, null) as PrivateKey
            assertNull(privateKey.encoded)
            val info = KeyFactory.getInstance("RSA", "AndroidKeyStore").getKeySpec(privateKey, KeyInfo::class.java)
            assertEquals(KeyProperties.PURPOSE_DECRYPT, info.purposes)
        } finally { envelope.forget() }
    }

    @Test fun envelopeRejectsTamperingWrongAccountTruncationAndMissingKeysWithoutRegenerating() {
        val alias = "phc_fixture_envelope_${UUID.randomUUID()}"
        val envelope = KeystoreEnvelope(alias)
        try {
            val aad = "fixture-account:fixture-file".toByteArray()
            val sealed = envelope.encrypt("fixture-plain".toByteArray(), aad)
            for (offset in listOf(0, 8, 264, sealed.lastIndex)) {
                val damaged = sealed.copyOf().apply { this[offset] = (this[offset].toInt() xor 1).toByte() }
                assertThrows(Exception::class.java) { envelope.decrypt(damaged, aad) }
            }
            assertThrows(Exception::class.java) { envelope.decrypt(sealed, "another-account".toByteArray()) }
            assertThrows(Exception::class.java) { envelope.decrypt(sealed.copyOf(100), aad) }
            envelope.forget()
            assertThrows(Exception::class.java) { envelope.decrypt(sealed, aad) }
            assertFalse(envelope.available())
        } finally { envelope.forget() }
    }

    @Test fun sessionSurvivesStoreRecreationAndNeverPersistsPlaintextToken() {
        val context: Context = ApplicationProvider.getApplicationContext()
        val preferencesName = "phc_fixture_session_${UUID.randomUUID()}"
        val isolatedContext = object : ContextWrapper(context) {
            override fun getSharedPreferences(name: String, mode: Int): SharedPreferences = super.getSharedPreferences(if (name == "phc_session") preferencesName else name, mode)
        }
        val session = Session("fixture-token-no-network", "2030-01-01T00:00:00Z", User(900000008, "Fixture", "fixture@example.invalid"))
        val store = KeystoreSessionStore(isolatedContext)
        try {
            store.save(session)
            assertEquals(session, KeystoreSessionStore(isolatedContext).read())
            val encoded = isolatedContext.getSharedPreferences("phc_session", Context.MODE_PRIVATE).getString("ciphertext", "")!!
            assertFalse(encoded.contains(session.token))
            store.clear()
            assertNull(store.read())
        } finally { context.deleteSharedPreferences(preferencesName) }
    }
}
