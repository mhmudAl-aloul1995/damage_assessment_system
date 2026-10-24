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
    private val policy = TransportPolicy("https://fixture.example/portal-test/${UUID.randomUUID()}/".toHttpUrl(), false)
    private fun draft(owner: Long, origin: String = policy.baseUrl.toString()) = FormDraft(owner = owner, origin = origin,
        schema = FormSchema("fixture-form", "heks", "Fixture", "v1", listOf(FormSection("Section", listOf(FormField("name", "Name")))), false),
        answers = JsonObject(mapOf("name" to JsonPrimitive("fixture-sensitive-answer-123"))))
    @Test fun offlineTemplatesSurviveReopeningAreEncryptedAndNeverBecomeRespondentDrafts() {
        val owner = 900000006L
        val vault = DraftVault(context, policy)
        val template = FormSchema("heks-phase-three", "heks-shelter", "fixture-offline-template", "v1", listOf(FormSection("Section", listOf(FormField("name", "Name")))), false)
        val catalog = OfflineCatalog(listOf(PortalModule("heks-shelter", "HEKS")), listOf(PublishedForm(template.key, template.module, template.title, template.version, canSubmit = true)), mapOf(template.key to template), "2026-10-23T07:00:00Z")
        try {
            vault.saveCatalog(owner, catalog)
            assertEquals(catalog, DraftVault(context, policy).readCatalog(owner))
            assertEquals(setOf(template.key), vault.readCatalog(owner)!!.readyKeys)
            assertNull(vault.readCatalog(owner + 1))
            assertNull(DraftVault(context, TransportPolicy("https://other.example/".toHttpUrl(), false)).readCatalog(owner))
            assertTrue(vault.listing(owner).drafts.isEmpty())
            val bytes = File(context.noBackupFilesDir, "form-vault").walkTopDown().filter { it.name == "offline-catalog.cache" }.map { it.readBytes().toString(Charsets.UTF_8) }.toList()
            assertTrue(bytes.none { it.contains("fixture-offline-template") })
            val privateSchema = template.copy(answers = JsonObject(mapOf("name" to JsonPrimitive("private"))))
            assertThrows(IllegalArgumentException::class.java) { vault.saveCatalog(owner, catalog.copy(schemas = mapOf(template.key to privateSchema))) }
            assertEquals(catalog, vault.readCatalog(owner))
        } finally { vault.clearCatalog(owner) }
    }
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
    @Test fun damagedCiphertextDoesNotHideReadableDraftsOrModifyTheFailedFile() {
        val vault = DraftVault(context, policy)
        val healthy = draft(900000005)
        val damaged = draft(900000005)
        vault.save(healthy); vault.save(damaged)
        val file = File(context.noBackupFilesDir, "form-vault").walkTopDown().first { it.name == damaged.uuid + ".draft" }
        val original = file.readBytes()
        val tampered = original.copyOf().apply { this[lastIndex] = (this[lastIndex].toInt() xor 1).toByte() }
        try {
            file.writeBytes(tampered)
            val result = vault.listing(healthy.owner)
            assertEquals(listOf(healthy.uuid), result.drafts.map { it.uuid }); assertEquals(1, result.unavailableCount)
            assertArrayEquals(tampered, file.readBytes())
            assertThrows(Exception::class.java) { vault.load(damaged.owner, damaged.uuid) }
        } finally {
            file.writeBytes(original)
            vault.delete(damaged.owner, damaged.uuid); vault.delete(healthy.owner, healthy.uuid)
        }
    }
}

