package com.phc.inquiry

import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json
import org.junit.Assert.*
import org.junit.Test

class OfflineAccessTest {
    private val user = User(1, "باحث ميداني", "fixture@example.test")
    private fun online() = Session("server-token-never-offline", "2099-01-01T00:00:00Z", user)
    private class Store : SessionStore {
        var cleared = false
        override fun read(): Session? = null
        override fun save(session: Session) {}
        override fun clear() { cleared = true }
    }
    private val drafts = object : DraftSummaryStore {
        override fun listing(owner: Long) = DraftListing(emptyList())
        override fun delete(owner: Long, uuid: String) = error("Not used")
    }
    private fun forms(): MemoryOfflineForms = MemoryOfflineForms().apply {
        val schema = FormSchema("heks-phase-three", "heks-shelter", "HEKS", "v1", listOf(FormSection("s", listOf(FormField("name", "الاسم")))), false)
        saveCatalog(1, OfflineCatalog(forms = listOf(PublishedForm(schema.key, schema.module, schema.title, schema.version, canSubmit = true)), schemas = mapOf(schema.key to schema)))
    }
    @Test fun offsetExpiryCompletesEnrollmentAndMalformedExpiryFailsBeforePreparingTheDevice() {
        val identities = MemoryOfflineIdentityStore(); val sessions = SessionState()
        val controller = OfflineAccessController(identities, sessions, Store(), forms(), drafts)
        sessions.set(online().copy(expiresAt = "2099-01-01T03:00:00+03:00"))
        val request = controller.prepare(true)
        identities.deviceAuthenticated = true
        assertTrue(controller.complete(request.id))
        assertEquals(user, identities.identity!!.user)
        sessions.set(online().copy(expiresAt = "invalid"))
        assertThrows(IllegalArgumentException::class.java) { controller.prepare(true) }
        assertEquals(user, identities.identity!!.user)
    }
    @Test fun localUnlockAfterLogoutHasNoServerTokenAndPreservesTheOriginalDraftOwner() {
        val identities = MemoryOfflineIdentityStore(); val sessions = SessionState(); val store = Store()
        val controller = OfflineAccessController(identities, sessions, store, forms(), drafts)
        sessions.set(online())
        val enrollment = controller.prepare(true)
        assertTrue(enrollment.credentialOnly)
        identities.deviceAuthenticated = true
        assertTrue(controller.complete(enrollment.id))
        val encoded = Json.encodeToString(identities.identity!!)
        assertFalse(encoded.contains("server-token")); assertFalse(encoded.contains("password"))
        sessions.set(null)
        val unlock = controller.prepare(false)
        identities.deviceAuthenticated = true
        assertFalse(controller.complete(unlock.id))
        assertNull(sessions.session.value); assertNull(sessions.token())
        assertEquals(user, sessions.access.value!!.user); assertTrue(sessions.access.value!!.localOnly); assertTrue(store.cleared)
        val draft = FormDraft(owner = sessions.access.value!!.user.id, origin = "fixture", schema = forms().readCatalog(1)!!.schemas.values.single())
        sessions.set(online())
        assertFalse(sessions.access.value!!.localOnly); assertEquals(draft.owner, sessions.access.value!!.user.id)
        assertEquals("server-token-never-offline", sessions.token())
    }
    @Test fun localAccessCannotBeCreatedWithoutPreviousEnrollmentOrWithAnExpiredOnlineSession() {
        val identities = MemoryOfflineIdentityStore(); val sessions = SessionState()
        val controller = OfflineAccessController(identities, sessions, Store(), forms(), drafts)
        assertThrows(IllegalArgumentException::class.java) { controller.prepare(false) }
        assertThrows(IllegalArgumentException::class.java) { controller.prepare(true) }
        sessions.set(online().copy(expiresAt = "2000-01-01T00:00:00Z"))
        assertThrows(IllegalArgumentException::class.java) { controller.prepare(true) }
        sessions.set(online())
        val noForms = OfflineAccessController(identities, sessions, Store(), MemoryOfflineForms(), drafts)
        val noContentRequest = noForms.prepare(true)
        identities.deviceAuthenticated = true
        assertThrows(InquiryFailure::class.java) { noForms.complete(noContentRequest.id) }
    }
    @Test fun preparationNeverReadsEncryptedLocalStorageAndAnUnreadableCatalogDoesNotHideReadableDrafts() {
        var reads = 0
        val brokenForms = object : OfflineFormStore {
            override fun readCatalog(owner: Long): OfflineCatalog? { reads++; throw java.security.ProviderException("private provider details") }
            override fun saveCatalog(owner: Long, catalog: OfflineCatalog) = error("Not used")
            override fun clearCatalog(owner: Long) = error("Not used")
        }
        val localDrafts = object : DraftSummaryStore {
            override fun listing(owner: Long) = DraftListing(listOf(DraftSummary("fixture", "heks-phase-three", "HEKS", "heks-shelter", null, "now", false, null)))
            override fun delete(owner: Long, uuid: String) = error("Not used")
        }
        val identities = MemoryOfflineIdentityStore(); val sessions = SessionState().apply { set(online()) }
        val controller = OfflineAccessController(identities, sessions, Store(), brokenForms, localDrafts)
        val request = controller.prepare(true)
        assertEquals(0, reads); assertNull(identities.identity)
        identities.deviceAuthenticated = true
        assertTrue(controller.complete(request.id))
        assertEquals(1, reads); assertNotNull(identities.identity)
    }
    @Test fun setupFailuresReportTheirStageWithoutExposingProviderDetailsOrEnrollingAnEmptyAccount() {
        val brokenForms = object : OfflineFormStore {
            override fun readCatalog(owner: Long): OfflineCatalog? = throw java.security.ProviderException("private provider details")
            override fun saveCatalog(owner: Long, catalog: OfflineCatalog) = error("Not used")
            override fun clearCatalog(owner: Long) = error("Not used")
        }
        val identities = MemoryOfflineIdentityStore(); val sessions = SessionState().apply { set(online()) }
        val controller = OfflineAccessController(identities, sessions, Store(), brokenForms, drafts)
        val request = controller.prepare(true)
        identities.deviceAuthenticated = true
        val error = assertThrows(OfflineAccessFailure::class.java) { controller.complete(request.id) }
        assertEquals("OFF-LOCAL-READ", error.code); assertNull(identities.identity)
        assertFalse(offlineAccessMessage(error, true).contains("private"))
        val unknown = offlineAccessMessage(java.security.ProviderException("private provider details"), false)
        assertTrue(unknown.contains("OFF-PREPARE")); assertFalse(unknown.contains("private"))
    }
    @Test fun cancelledOrUnauthenticatedDeviceChallengesNeverOpenAnOfflineAccount() {
        val identities = MemoryOfflineIdentityStore().apply { identity = OfflineIdentity(user, "fixture", "now") }
        val sessions = SessionState(); val controller = OfflineAccessController(identities, sessions, Store(), forms(), drafts)
        val cancelled = controller.prepare(false)
        controller.cancel(); identities.deviceAuthenticated = true
        assertThrows(InquiryFailure::class.java) { controller.complete(cancelled.id) }
        val failed = controller.prepare(false)
        assertThrows(IllegalStateException::class.java) { controller.complete(failed.id) }
        assertNull(sessions.access.value); assertNull(sessions.token())
    }
    @Test fun accountSwitchInvalidatesPendingEnrollmentAndForgettingDoesNotDeleteDrafts() {
        val identities = MemoryOfflineIdentityStore(); val sessions = SessionState().apply { set(online()) }
        val controller = OfflineAccessController(identities, sessions, Store(), forms(), drafts)
        val pending = controller.prepare(true)
        sessions.set(online().copy(user = user.copy(id = 2)))
        identities.deviceAuthenticated = true
        assertThrows(IllegalArgumentException::class.java) { controller.complete(pending.id) }
        assertFalse(identities.available())
        identities.identity = OfflineIdentity(user, "fixture", "now")
        sessions.enterOffline(user)
        controller.forget()
        assertTrue(identities.forgotten); assertNull(sessions.access.value)
    }
}
