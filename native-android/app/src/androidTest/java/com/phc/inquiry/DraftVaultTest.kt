package com.phc.inquiry

import android.content.Context
import androidx.test.core.app.ApplicationProvider
import androidx.test.ext.junit.runners.AndroidJUnit4
import com.phc.inquiry.core.security.TransportPolicy
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import kotlinx.serialization.json.*
import okhttp3.HttpUrl.Companion.toHttpUrl
import org.junit.Assert.*
import org.junit.Test
import org.junit.runner.RunWith
import java.io.File
import java.util.UUID

@RunWith(AndroidJUnit4::class)
class DraftVaultTest {
    private val context: Context = ApplicationProvider.getApplicationContext()
    private val policy = TransportPolicy("https://fixture.example/portal-test/".toHttpUrl(), false)
    private fun draft(owner: Long, origin: String = policy.baseUrl.toString()) = FormDraft(owner = owner, origin = origin,
        schema = FormSchema("fixture-form", "heks", "Fixture", "v1", listOf(FormSection("Section", listOf(FormField("name", "Name")))), false),
        answers = JsonObject(mapOf("name" to JsonPrimitive("fixture-sensitive-answer-123"))))
    @Test fun encryptedDraftRoundTripContainsNoPlaintextAndIsIsolatedByAccount() {
        val vault = DraftVault(context, policy)
        val draft = draft(900000001)
        try {
            vault.save(draft)
            assertEquals(draft, vault.load(draft.owner, draft.uuid))
            val file = File(context.noBackupFilesDir, "form-vault").walkTopDown().first { it.name == draft.uuid + ".draft" }
            assertFalse(file.readBytes().toString(Charsets.UTF_8).contains("fixture-sensitive-answer-123"))
            assertThrows(Exception::class.java) { vault.load(900000002, draft.uuid) }
        } finally { vault.delete(draft.owner, draft.uuid) }
    }
    @Test fun apiOriginAndUuidValidationPreventCrossServerAndPathAccess() {
        val vault = DraftVault(context, policy)
        assertThrows(IllegalArgumentException::class.java) { vault.save(draft(900000003, "https://other.example/")) }
        assertThrows(IllegalArgumentException::class.java) { vault.load(900000003, "../outside") }
    }
    @Test fun unresolvedSubmissionCannotBeDeletedAndPendingPayloadSurvivesReload() {
        val vault = DraftVault(context, policy)
        val draft = draft(900000004)
        val pending = draft.copy(pending = draft.request())
        try {
            vault.save(pending)
            assertEquals(pending.pending, vault.load(draft.owner, draft.uuid).pending)
            assertThrows(IllegalArgumentException::class.java) { vault.delete(draft.owner, draft.uuid) }
        } finally { vault.save(draft); vault.delete(draft.owner, draft.uuid) }
    }
}

