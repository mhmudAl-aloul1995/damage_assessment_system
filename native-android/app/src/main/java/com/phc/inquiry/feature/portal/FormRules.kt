package com.phc.inquiry.feature.portal

import com.phc.inquiry.data.*
import kotlinx.serialization.json.*
import java.time.LocalDate

object FormRules {
    val supported = setOf("text", "phone", "multiline", "date", "datetime", "integer", "decimal", "select", "multiselect", "boolean", "repeat")
    fun visible(field: FormField, answers: JsonObject): Boolean = field.visibleWhen?.let { answers[it.field]?.jsonPrimitive?.contentOrNull == it.equals } ?: true
    fun text(value: JsonElement?): String = (value as? JsonPrimitive)?.contentOrNull.orEmpty()
    private fun digits(value: String): String = value.map { when(it) { in '٠'..'٩' -> '0' + (it - '٠'); in '۰'..'۹' -> '0' + (it - '۰'); '٫' -> '.'; else -> it } }.joinToString("").trim()
    fun validate(schema: FormSchema, answers: JsonObject, section: Int? = null): Map<String, String> {
        val errors = linkedMapOf<String, String>()
        val sections = section?.let { listOf(schema.sections[it]) } ?: schema.sections
        sections.forEach { group -> group.fields.forEach { field -> if (visible(field, answers)) validateField(field, answers[field.key], field.key, errors) } }
        return errors
    }
    private fun validateField(field: FormField, value: JsonElement?, path: String, errors: MutableMap<String, String>) {
        val text = text(value)
        if (field.type !in supported) { errors[path] = "نوع سؤال غير مدعوم؛ حدّث التطبيق."; return }
        val empty = value == null || value == JsonNull || (value is JsonPrimitive && text.isBlank()) || (value is JsonArray && value.isEmpty())
        if (empty) { if (field.required) errors[path] = "هذا الحقل مطلوب."; return }
        when(field.type) {
            "repeat" -> {
                val rows = value as? JsonArray
                if (rows == null || rows.size > field.maxItems) { errors[path] = "عدد عناصر المجموعة غير صالح."; return }
                rows.forEachIndexed { index, row ->
                    val item = row as? JsonObject
                    if (item == null) errors["$path.$index"] = "بيانات المجموعة غير صالحة."
                    else field.children.forEach { validateField(it, item[it.key], "$path.$index.${it.key}", errors) }
                }
            }
            "multiselect" -> {
                val selected = value as? JsonArray
                if (selected == null || selected.size > field.maxItems || selected.any { text(it) !in field.choices.map(FormChoice::value) } || (selected.size > 1 && selected.any { text(it) in field.exclusiveChoices }))
                    errors[path] = "اختر من الخيارات المتاحة."
            }
            "select" -> if (text !in field.choices.map(FormChoice::value)) errors[path] = "اختر من الخيارات المتاحة."
            "boolean" -> if (text !in setOf("true", "false", "1", "0")) errors[path] = "اختر نعم أو لا."
            "integer", "decimal" -> {
                val normalized = digits(text)
                val number = normalized.toDoubleOrNull()
                if (number == null || !number.isFinite() || (field.type == "integer" && (!Regex("[+-]?[0-9]+").matches(normalized) || normalized.toLongOrNull() == null))) errors[path] = "أدخل رقمًا صالحًا."
                else if (field.min != null && number < field.min || field.max != null && number > field.max) errors[path] = "الرقم خارج الحدود المسموحة."
            }
            "date" -> if (runCatching { LocalDate.parse(text.take(10)) }.isFailure) errors[path] = "أدخل التاريخ بصيغة سنة-شهر-يوم."
            "datetime" -> if (runCatching { java.time.OffsetDateTime.parse(text) }.isFailure && runCatching { java.time.LocalDateTime.parse(text) }.isFailure && runCatching { LocalDate.parse(text) }.isFailure) errors[path] = "أدخل تاريخًا ووقتًا صالحين مثل 2026-10-11T14:30."
            else -> if (field.maxLength != null && text.length > field.maxLength) errors[path] = "الحد الأقصى ${field.maxLength} حرفًا."
        }
    }
    fun normalize(schema: FormSchema, answers: JsonObject): JsonObject = JsonObject(schema.sections.flatMap { it.fields }.associate { field ->
        field.key to if (visible(field, answers)) normalizeField(field, answers[field.key]) else JsonNull
    })
    private fun normalizeField(field: FormField, value: JsonElement?): JsonElement {
        if (value == null || value == JsonNull) return JsonNull
        return when(field.type) {
            "integer" -> digits(text(value)).toLongOrNull()?.let(::JsonPrimitive) ?: JsonNull
            "decimal" -> digits(text(value)).toDoubleOrNull()?.takeIf { it.isFinite() }?.let(::JsonPrimitive) ?: JsonNull
            "boolean" -> when(text(value)) { "true", "1" -> JsonPrimitive(true); "false", "0" -> JsonPrimitive(false); else -> JsonNull }
            "repeat" -> JsonArray((value as? JsonArray).orEmpty().map { row ->
                val item = row as? JsonObject ?: JsonObject(emptyMap())
                JsonObject(field.children.associate { it.key to normalizeField(it, item[it.key]) })
            })
            "multiselect" -> JsonArray((value as? JsonArray).orEmpty().map { JsonPrimitive(text(it)) })
            "date" -> text(value).take(10).takeIf { it.isNotBlank() }?.let(::JsonPrimitive) ?: JsonNull
            else -> text(value).trim().takeIf { it.isNotBlank() }?.let(::JsonPrimitive) ?: JsonNull
        }
    }
}

