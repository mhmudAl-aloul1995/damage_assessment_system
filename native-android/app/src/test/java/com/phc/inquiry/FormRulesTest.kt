package com.phc.inquiry

import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.*
import org.junit.Assert.*
import org.junit.Test

class FormRulesTest {
    @Test fun retriedOfflineRequestUnlocksForConfirmedValidationOrSchemaRejectionWithoutChangingItsIdentity() {
        val original = FormDraft(owner = 1, origin = "https://fixture/", schema = schema(listOf(FormField("name", "الاسم"))), answers = JsonObject(mapOf("name" to JsonPrimitive("محفوظ"))))
        val pending = original.copy(pending = original.request())
        for (failure in listOf(FormFailure(422, message = "راجع الإجابات"), FormFailure(409, message = "تغيّر إصدار النموذج. حدّث النموذج قبل الإرسال."))) {
            val rejected = pending.afterRejection(failure)
            assertNull(rejected.pending); assertEquals(original.uuid, rejected.uuid); assertEquals(original.answers, rejected.answers)
        }
        for (failure in listOf(FormFailure(null, message = "NET-CONNECT"), FormFailure(500, message = "server"), FormFailure(409, message = "استُخدم معرّف الإرسال لبيانات مختلفة."))) {
            assertEquals(pending, pending.afterRejection(failure))
        }
    }
    @Test fun errorNavigationFindsTheFieldAcrossSectionsWithoutLosingDraftAnswers() {
        val form = schema(listOf(FormField("visit_date", "التاريخ"))).copy(sections = listOf(
            FormSection("تعريف", listOf(FormField("visit_date", "التاريخ"))),
            FormSection("أسرة", listOf(FormField("identity_number", "الهوية"), FormField("family", "الأسرة", "repeat"))),
        ))
        val draft = FormDraft(owner = 1, origin = "https://fixture/", schema = form,
            answers = JsonObject(mapOf("identity_number" to JsonPrimitive("invalid"))))
        val state = FormState(loading = false, draft = draft, section = form.sections.size,
            errors = mapOf("identity_number" to "Invalid identity"))
        val target = state.navigateToError("answers.identity_number")
        assertEquals(1, target.section)
        assertEquals("identity_number", target.errorTarget)
        assertEquals(1, target.errorNavigation)
        assertEquals(draft, target.draft)
        assertEquals(state.errors, target.errors)
        assertEquals("family.0.name", state.navigateToError("family.0.name").errorTarget)
        assertEquals(state, state.navigateToError("unknown_field"))
        val pending = state.copy(draft = draft.copy(pending = draft.request()))
        assertEquals(pending, pending.navigateToError("identity_number"))
    }
    @Test fun schemaRefreshOpensReviewAndPreservesLocalAnswersIdentityAndFiles() {
        val oldSchema = schema(listOf(FormField("name", "الاسم"), FormField("removed", "قديم")))
        val latest = oldSchema.copy(version = "new-version", recordVersion = "new-record-version",
            sections = listOf(FormSection("updated", listOf(FormField("name", "الاسم"), FormField("added", "جديد")))),
            answers = JsonObject(mapOf("name" to JsonPrimitive("Server value"))))
        val original = FormDraft(owner = 1, origin = "https://fixture/", schema = oldSchema,
            answers = JsonObject(mapOf("name" to JsonPrimitive("Local value"), "removed" to JsonPrimitive("old"))),
            location = FormLocation(31.5, 34.4))
        val state = FormState(loading = true, draft = original, error = "stale", errors = mapOf("name" to "stale"))
        val refreshed = state.withRefreshedSchema(latest)
        val draft = requireNotNull(refreshed.draft)
        assertEquals(latest.sections.size, refreshed.section)
        assertFalse(refreshed.loading)
        assertNull(refreshed.error)
        assertTrue(refreshed.errors.isEmpty())
        assertTrue(draft.needsReview)
        assertEquals(original.uuid, draft.uuid)
        assertEquals(original.location, draft.location)
        assertEquals(original.attachments, draft.attachments)
        assertEquals("Local value", draft.answers["name"]?.jsonPrimitive?.content)
        assertEquals("old", draft.answers["removed"]?.jsonPrimitive?.content)
        assertFalse(draft.request().answers.containsKey("removed"))
        assertEquals("new-version", draft.request().formVersion)
        assertEquals("new-record-version", draft.request().recordVersion)
    }
    @Test fun schemaRefreshNeverReplacesAPendingSubmission() {
        val form = schema(listOf(FormField("name", "الاسم")))
        val draft = FormDraft(owner = 1, origin = "https://fixture/", schema = form)
        val pending = FormState(draft = draft.copy(pending = draft.request()))
        assertThrows(IllegalArgumentException::class.java) { pending.withRefreshedSchema(form.copy(version = "new")) }
    }
    @Test fun preservesVisitTimeAndRejectsExclusiveChoiceContradictions() {
        val form = schema(listOf(FormField("visit", "زيارة", "datetime"), FormField("vulnerability", "الهشاشة", "multiselect", choices = listOf(FormChoice("none", "لا يوجد"), FormChoice("disabled", "إعاقة")), exclusiveChoices = listOf("none"))))
        val values = Json.parseToJsonElement("""{"visit":"2026-10-11T09:35:21Z","vulnerability":["none","disabled"]}""").jsonObject
        assertEquals(setOf("vulnerability"), FormRules.validate(form, values).keys)
        assertEquals("2026-10-11T09:35:21Z", FormRules.normalize(form, values).getValue("visit").jsonPrimitive.content)
    }
    private fun schema(fields: List<FormField>) = FormSchema("fixture", "heks", "اختبار", "v1", listOf(FormSection("section", fields)), false)
    @Test fun validatesRequiredBooleanBoundsAndNestedRepeatPaths() {
        val form = schema(listOf(FormField("alive", "alive", "boolean", true),
            FormField("families", "families", "repeat", children = listOf(FormField("count", "count", "integer", min = 0.0, max = 100.0)), maxItems = 2)))
        val answers = Json.parseToJsonElement("""{"families":[{"count":"101"}]}""").jsonObject
        val errors = FormRules.validate(form, answers)
        assertTrue(errors.containsKey("alive"))
        assertTrue(errors.containsKey("families.0.count"))
        assertTrue(FormRules.validate(form, Json.parseToJsonElement("""{"alive":false,"families":[{"count":"4"}]}""").jsonObject).isEmpty())
    }
    @Test fun hiddenQuestionsAreNotValidatedAndNeverSubmittedWithStaleAnswers() {
        val form = schema(listOf(FormField("status", "status"), FormField("place", "place", required = true, visibleWhen = FormCondition("status", "displaced"))))
        val answers = Json.parseToJsonElement("""{"status":"resident","place":"old-secret"}""").jsonObject
        assertTrue(FormRules.validate(form, answers).isEmpty())
        assertEquals(JsonNull, FormRules.normalize(form, answers)["place"])
        assertTrue(FormRules.validate(form, Json.parseToJsonElement("""{"status":"displaced"}""").jsonObject).containsKey("place"))
    }
    @Test fun normalizesArabicNumbersAndKeepsIdentitiesAsStrings() {
        val form = schema(listOf(FormField("area", "area", "decimal"), FormField("count", "count", "integer"), FormField("identity", "identity")))
        val values = Json.parseToJsonElement("""{"area":"١٢٫٥","count":"٤","identity":"0012"}""").jsonObject
        assertTrue(FormRules.validate(form, values).isEmpty())
        val normalized = FormRules.normalize(form, values)
        assertEquals(12.5, normalized.getValue("area").jsonPrimitive.double, 0.0)
        assertEquals(4L, normalized.getValue("count").jsonPrimitive.long)
        assertEquals("0012", normalized.getValue("identity").jsonPrimitive.content)
        assertTrue(normalized.getValue("identity").jsonPrimitive.isString)
    }
    @Test fun rejectsUnsupportedTypesMalformedChoicesAndNonFiniteValues() {
        val form = schema(listOf(FormField("secret", "secret", "unimplemented"), FormField("num", "num", "decimal"), FormField("choice", "choice", "select", choices = listOf(FormChoice("yes", "نعم")))))
        val answers = Json.parseToJsonElement("""{"secret":"anything","num":"NaN","choice":"other"}""").jsonObject
        assertEquals(setOf("secret", "num", "choice"), FormRules.validate(form, answers).keys)
    }
    @Test fun pendingSubmissionSurvivesSerializationAndIsIndependentOfLaterAnswers() {
        val form = schema(listOf(FormField("name", "name")))
        val initial = FormDraft(uuid = "1c3558b3-994f-4357-a110-f07a06eae8fe", owner = 1, origin = "https://fixture/", schema = form, answers = JsonObject(mapOf("name" to JsonPrimitive("old"))))
        val frozen = initial.copy(pending = initial.request())
        val changed = frozen.copy(answers = JsonObject(mapOf("name" to JsonPrimitive("new"))))
        val restored = Json.decodeFromString<FormDraft>(Json.encodeToString(changed))
        assertEquals(initial.uuid, restored.pending?.uuid)
        assertEquals("old", restored.pending?.answers?.get("name")?.jsonPrimitive?.content)
        assertEquals("new", restored.answers["name"]?.jsonPrimitive?.content)
    }
}

