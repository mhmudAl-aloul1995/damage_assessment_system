package com.phc.inquiry

import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.*
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.*
import org.junit.Assert.*
import org.junit.Test

class FormRulesTest {
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

