package com.phc.inquiry.feature.damageassessment

import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import kotlinx.serialization.json.*
import kotlin.math.abs

data class MapCollection(val features: List<MapFeature> = emptyList(), val cursor: Long? = null, val scanned: Int = 0, val limited: Boolean = false, val omitted: Int = 0) {
    fun append(page: MapPage, after: Long): MapCollection {
        if (page.scanned !in 0..500 || page.omitted !in 0..500 || page.features.size > 500 || page.nextCursor?.let { it <= after } == true) {
            throw InquiryFailure("تعذّرت قراءة دفعة مواقع صحيحة من السيرفر.")
        }
        val candidates = (features + page.features.filter { it.attributes.recordId > 0 && validMapGeometry(it.geometry) }).distinctBy { it.attributes.recordId }
        var vertices = 0
        val merged = candidates.takeWhile { feature ->
            vertices += geometryVertexCount(feature.geometry)
            vertices <= 100000
        }
        val checked = scanned + page.scanned
        val capped = merged.size >= 2000 || checked >= 5000 || candidates.size != merged.size
        val dropped = page.features.count { it.attributes.recordId <= 0 || !validMapGeometry(it.geometry) }
        return MapCollection(merged.take(2000), if (capped) null else page.nextCursor, checked, capped && (page.nextCursor != null || candidates.size > merged.size), omitted + page.omitted + dropped)
    }
}

fun geometryVertexCount(geometry: JsonObject): Int = if (geometry.containsKey("x")) 1 else
    ((geometry["rings"] ?: geometry["paths"]) as? JsonArray)?.sumOf { (it as? JsonArray)?.size ?: 0 } ?: 0

fun damageRank(status: String?): Int = when (status) {
    "fully_damaged", "destroyed" -> 5
    "severe" -> 4
    "partially_damaged", "moderate" -> 3
    "committee_review" -> 2
    "minor" -> 1
    "no_damage" -> 0
    else -> 2
}

fun groupsAtSameGeometry(features: List<MapFeature>): List<List<MapFeature>> = features.groupBy { it.geometry.toString() }.values.toList()

fun validMapGeometry(geometry: JsonObject): Boolean {
    val spatial = geometry["spatialReference"] as? JsonObject ?: return false
    val wkid = ((spatial["latestWkid"] ?: spatial["wkid"]) as? JsonPrimitive)?.intOrNull ?: return false
    if (wkid !in 1..999999) return false
    val x = (geometry["x"] as? JsonPrimitive)?.doubleOrNull
    val y = (geometry["y"] as? JsonPrimitive)?.doubleOrNull
    fun valid(x: Double, y: Double) = x.isFinite() && y.isFinite() && (wkid != 4326 || (abs(x) <= 180 && abs(y) <= 90))
    if (x != null && y != null) return valid(x, y)
    val parts = (geometry["rings"] ?: geometry["paths"]) as? JsonArray ?: return false
    var vertices = 0
    return parts.isNotEmpty() && parts.all { part ->
        val coordinates = part as? JsonArray
        coordinates != null && coordinates.size >= (if (geometry.containsKey("rings")) 4 else 2) && coordinates.all { value ->
            vertices++
            val point = value as? JsonArray
            val px = (point?.getOrNull(0) as? JsonPrimitive)?.doubleOrNull
            val py = (point?.getOrNull(1) as? JsonPrimitive)?.doubleOrNull
            vertices <= 5000 && px != null && py != null && valid(px, py)
        }
    }
}

fun chartFractions(values: Map<String, Long>): Map<String, Float> {
    val total = values.values.sumOf { it.coerceAtLeast(0).toDouble() }
    return values.mapValues { if (total == 0.0) 0f else (it.value.coerceAtLeast(0).toDouble() / total).toFloat() }
}

data class MapBounds(val west: Double, val south: Double, val east: Double, val north: Double) {
    fun filters(): Map<String, String>? {
        if (!listOf(west, south, east, north).all(Double::isFinite) || west < -180 || east > 180 || south < -90 || north > 90 || west >= east || south >= north) return null
        return mapOf("west" to west.toString(), "south" to south.toString(), "east" to east.toString(), "north" to north.toString())
    }
}
