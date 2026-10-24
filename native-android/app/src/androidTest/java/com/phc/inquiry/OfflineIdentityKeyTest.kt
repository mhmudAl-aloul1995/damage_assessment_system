package com.phc.inquiry

import android.app.KeyguardManager
import android.content.Context
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.phc.inquiry.core.security.*
import okhttp3.HttpUrl.Companion.toHttpUrl
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import java.security.KeyStore
import java.security.MessageDigest
import java.util.UUID
import android.security.keystore.KeyProperties
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*

@RunWith(AndroidJUnit4::class)
class OfflineIdentityKeyTest {
    @Test fun enrollmentAcceptsServerExpiryWithTimezoneOffsetOnTheActualAndroidRuntime() {
        var prepared = false
        val identities = object : OfflineIdentityStore {
            override fun available(): Boolean = false
            override fun prepare(enrolling: Boolean) { prepared = true }
            override fun enroll(user: User) = error("Must not enroll before device authentication")
            override fun unlock(): OfflineIdentity = error("Must not unlock before device authentication")
            override fun forget() = error("Must not forget")
        }
        val sessions = SessionState().apply { set(Session("fixture-never-sent", "2099-01-01T03:00:00+03:00", User(900000009, "Fixture", "fixture@example.invalid"))) }
        val sessionStore = object : SessionStore {
            override fun read(): Session? = null
            override fun save(session: Session) = error("Must not save")
            override fun clear() = error("Must not clear")
        }
        val forms = object : OfflineFormStore {
            override fun readCatalog(owner: Long): OfflineCatalog? = error("Must not read before device authentication")
            override fun saveCatalog(owner: Long, catalog: OfflineCatalog) = error("Must not save")
            override fun clearCatalog(owner: Long) = error("Must not clear")
        }
        val drafts = object : DraftSummaryStore {
            override fun listing(owner: Long): DraftListing = error("Must not read before device authentication")
            override fun delete(owner: Long, uuid: String) = error("Must not delete")
        }
        val controller = OfflineAccessController(identities, sessions, sessionStore, forms, drafts)
        assertTrue(controller.prepare(true).enrolling)
        assertTrue(prepared)
        assertFalse(sessions.access.value!!.localOnly)
    }

    @Test fun offlineIdentityRequiresADeviceAuthenticatedKeyAndPreparationNeverGrantsAccess() {
        val context: Context = ApplicationProvider.getApplicationContext()
        val origin = "https://fixture.example/offline-key-test/${UUID.randomUUID()}/"
        val store = DeviceOfflineIdentityStore(context, TransportPolicy(origin.toHttpUrl(), false))
        try {
            store.forget()
            println("PHC device secure=${context.getSystemService(KeyguardManager::class.java).isDeviceSecure}")
            if (!context.getSystemService(KeyguardManager::class.java).isDeviceSecure) {
                assertThrows(IllegalArgumentException::class.java) { store.prepare(true) }
            } else {
                store.prepare(true)
                val scope = MessageDigest.getInstance("SHA-256").digest(origin.toByteArray()).joinToString("") { "%02x".format(it) }
                val keyStore = KeyStore.getInstance("AndroidKeyStore").apply { load(null) }
                assertFalse(keyStore.containsAlias("phc_offline_identity_$scope"))
                assertFalse(keyStore.containsAlias("phc_offline_identity_${scope}_envelope_v2"))
            }
            assertTrue(store.enrollmentKeySpec().isUserAuthenticationRequired)
            assertEquals(15, store.enrollmentKeySpec().userAuthenticationValidityDurationSeconds)
            assertEquals(KeyProperties.PURPOSE_DECRYPT, store.enrollmentKeySpec().purposes)
            assertTrue(store.enrollmentKeySpec(legacy = true).isUserAuthenticationRequired)
            assertEquals(15, store.enrollmentKeySpec(legacy = true).userAuthenticationValidityDurationSeconds)
            assertFalse(store.available())
        } finally { store.forget() }
    }
}
