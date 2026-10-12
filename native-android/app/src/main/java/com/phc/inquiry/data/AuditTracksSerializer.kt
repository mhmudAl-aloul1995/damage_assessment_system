package com.phc.inquiry.data

import kotlinx.serialization.builtins.MapSerializer
import kotlinx.serialization.builtins.serializer
import kotlinx.serialization.json.JsonArray
import kotlinx.serialization.json.JsonElement
import kotlinx.serialization.json.JsonObject
import kotlinx.serialization.json.JsonTransformingSerializer

/** PHP represents an empty associative array as []; only that empty shape is normalized. */
object AuditTracksSerializer : JsonTransformingSerializer<Map<String, Map<String, Long>>>(
    MapSerializer(String.serializer(), MapSerializer(String.serializer(), Long.serializer()))
) {
    override fun transformDeserialize(element: JsonElement): JsonElement = when {
        element is JsonArray && element.isEmpty() -> JsonObject(emptyMap())
        element is JsonObject -> JsonObject(element.mapValues { (_, track) ->
            if (track is JsonArray && track.isEmpty()) JsonObject(emptyMap()) else track
        })
        else -> element
    }
}
