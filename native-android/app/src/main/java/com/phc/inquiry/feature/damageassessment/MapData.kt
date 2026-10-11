package com.phc.inquiry.feature.damageassessment

import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import kotlinx.serialization.json.*
import kotlin.math.abs

data class MapCollection(val features: List<MapFeature> = emptyList(), val cursor: Long? = null, val scanned: Int = 0, val limited: Boolean = false, val omitted: Int = 0) {
    fun append(page: MapPage, after: Long, polygonsOnly: Boolean = false): MapCollection {
        if (page.scanned !in 0..500 || page.omitted !in 0..500 || page.features.size > 500 || page.nextCursor?.let { it <= after } == true) {
            throw InquiryFailure("تعذّرت قراءة دفعة مواقع صحيحة من السيرفر.")
        }
        fun usable(feature: MapFeature) = feature.attributes.recordId > 0 && validMapGeometry(feature.geometry) && (!polygonsOnly || validBuildingPolygon(feature.geometry))
        val candidates = (features.filter(::usable) + page.features.filter(::usable)).distinctBy { it.attributes.recordId }
        var vertices = 0
        val merged = candidates.takeWhile { feature ->
            vertices += geometryVertexCount(feature.geometry)
            vertices <= 100000
        }
        val checked = scanned + page.scanned
        val capped = merged.size >= 2000 || checked >= 5000 || candidates.size != merged.size
        val dropped = page.features.count { !usable(it) }
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

fun isBuildingMap(sector: String): Boolean = sector in setOf("buildings", "housing-units", "public-buildings", "cso-surveys")

fun validBuildingPolygon(geometry: JsonObject): Boolean = geometry["rings"] is JsonArray && validMapGeometry(geometry) &&
    (geometry["rings"] as JsonArray).all { ring ->
        val points = ring as JsonArray
        val first = points.first() as JsonArray
        val last = points.last() as JsonArray
        (first[0] as JsonPrimitive).doubleOrNull == (last[0] as JsonPrimitive).doubleOrNull &&
            (first[1] as JsonPrimitive).doubleOrNull == (last[1] as JsonPrimitive).doubleOrNull
    }

data class MapSymbolGroup(val features: List<MapFeature>, val damageStatus: String?, val building: MapBuilding? = null) {
    val geometry: JsonObject get() = features.first().geometry
}

fun mapSymbolGroups(features: List<MapFeature>, sector: String): List<MapSymbolGroup> {
    if (!isBuildingMap(sector)) return groupsAtSameGeometry(features).map { group ->
        MapSymbolGroup(group, group.maxBy { damageRank(it.attributes.damageStatus) }.attributes.damageStatus)
    }
    fun identity(value: String?): String? = value?.trim()?.removeSurrounding("{", "}")?.lowercase()?.takeIf(String::isNotBlank)
    return features.groupBy { feature ->
        val globalid = identity(feature.building?.globalid) ?: if (sector == "housing-units") identity(feature.attributes.parentGlobalId) else null
        val objectid = feature.building?.objectid?.content?.takeIf { it.isNotBlank() && it != "null" }
            ?: if (sector != "housing-units") feature.attributes.objectid?.content?.takeIf { it.isNotBlank() && it != "null" } else null
        globalid?.let { "globalid:$it" } ?: objectid?.let { "objectid:$it" } ?: "record:${feature.attributes.recordId}"
    }.values.map { group ->
        val statuses = group.map { feature ->
            feature.building?.damageStatus ?: if (sector == "housing-units") "unclassified" else feature.attributes.damageStatus ?: "unclassified"
        }.distinct()
        val status = statuses.singleOrNull() ?: "unclassified"
        val building = group.firstNotNullOfOrNull { it.building }?.copy(damageStatus = status)
        MapSymbolGroup(group, status, building)
    }
}

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
