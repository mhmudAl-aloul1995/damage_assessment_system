package com.phc.inquiry

import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.damageassessment.*
import kotlinx.serialization.json.*
import org.junit.Assert.*
import org.junit.Test

class MapDataTest {
    private fun feature(id: Long) = MapFeature(Json.parseToJsonElement("""{"x":34.4,"y":31.5,"spatialReference":{"wkid":4326}}""").jsonObject, InquiryRecord(id))

    @Test fun cursorAdvancesWhenFirstBatchHasNoCoordinatesAndDeduplicatesNextBatch() {
        val first = MapCollection().append(MapPage(emptyList(), 500, 500), 0)
        assertEquals(500L, first.cursor); assertEquals(500, first.scanned)
        val next = first.append(MapPage(listOf(feature(501), feature(501), feature(502)), null, 3), 500)
        assertEquals(listOf(501L, 502L), next.features.map { it.attributes.recordId }); assertNull(next.cursor)
    }
    @Test fun brokenCursorAndOversizedBatchAreRejected() {
        listOf(MapPage(emptyList(), 4, 500), MapPage(emptyList(), 9, 501)).forEach {
            try { MapCollection().append(it, 4); fail("Reject broken cursor or unbounded batch") } catch (_: InquiryFailure) {}
        }
    }
    @Test fun totalFeatureAndScanBudgetsAreBounded() {
        var collection = MapCollection()
        repeat(4) { page -> collection = collection.append(MapPage((1..500).map { feature(page * 500L + it) }, (page + 1) * 500L, 500), page * 500L) }
        assertEquals(2000, collection.features.size); assertNull(collection.cursor); assertTrue(collection.limited)
        var missing = MapCollection()
        repeat(10) { page -> missing = missing.append(MapPage(emptyList(), (page + 1) * 500L, 500), page * 500L) }
        assertEquals(5000, missing.scanned); assertNull(missing.cursor); assertTrue(missing.limited)
    }
    @Test fun badCoordinatesAndUnboundedShapesAreDropped() {
        assertTrue(validMapGeometry(feature(1).geometry))
        assertFalse(validMapGeometry(Json.parseToJsonElement("""{"x":999,"y":31,"spatialReference":{"wkid":4326}}""").jsonObject))
        assertFalse(validMapGeometry(Json.parseToJsonElement("""{"x":34,"y":31,"spatialReference":{"wkid":{}}}""").jsonObject))
        val shape = buildJsonObject { put("spatialReference", buildJsonObject { put("wkid", 4326) }); put("rings", buildJsonArray { add(buildJsonArray { repeat(5001) { add(buildJsonArray { add(34); add(31) }) } }) }) }
        assertFalse(validMapGeometry(shape))
    }
    @Test fun acceptsPolygonLineAndProjectedCoordinates() {
        assertTrue(validMapGeometry(Json.parseToJsonElement("""{"rings":[[[34,31],[35,31],[35,32],[34,31]]],"spatialReference":{"wkid":4326}}""").jsonObject))
        assertTrue(validMapGeometry(Json.parseToJsonElement("""{"paths":[[[3830000,3690000],[3831000,3691000]]],"spatialReference":{"wkid":102100,"latestWkid":3857}}""").jsonObject))
    }
    @Test fun chartFractionsHandleZeroNegativeAndLongOverflowWithoutNan() {
        assertEquals(0f, chartFractions(mapOf("empty" to 0, "bad" to -1))["empty"]!!, 0f)
        val values = chartFractions(mapOf("first" to Long.MAX_VALUE, "second" to Long.MAX_VALUE, "bad" to -1))
        assertEquals(0.5f, values["first"]!!, 0.001f); assertEquals(0f, values["bad"]!!, 0f)
    }
    @Test fun boundsRejectWrappedReversedAndNonFiniteAreas() {
        assertNotNull(MapBounds(34.0, 31.0, 35.0, 32.0).filters())
        assertNull(MapBounds(35.0, 31.0, 34.0, 32.0).filters())
        assertNull(MapBounds(Double.NaN, 31.0, 35.0, 32.0).filters())
        assertNull(MapBounds(-181.0, 31.0, 35.0, 32.0).filters())
    }
    @Test fun mapAndStatisticsDecodeCurrentBackendContractWithoutUsingProviderTokens() {
        val json = Json { ignoreUnknownKeys = true }
        val map = json.decodeFromString<MapPage>("""{"features":[{"geometry":{"x":34.4,"y":31.5,"spatialReference":{"wkid":4326}},"attributes":{"record_id":8,"objectid":900,"damage_status":"fully_damaged"}}],"next_cursor":500,"scanned":500}""")
        assertEquals(8L, map.features.single().attributes.recordId); assertEquals(500L, map.nextCursor)
        val stats = json.decodeFromString<SectorStatistics>("""{"summary":{"total":4},"damage":{"fully_damaged":2,"no_damage":2},"fieldwork":{"completed":3},"audit_tracks":{"engineering":{"pending":1}},"chart":{},"neighborhoods":[],"calculated_at":"2026-10-10T12:00:00+03:00"}""")
        assertEquals(4L, stats.summary["total"]); assertEquals(1L, stats.auditTracks["engineering"]?.get("pending"))
    }
    @Test fun detailedGeometryBudgetCapsRenderingAndReportsRejectedShapes() {
        val shape = buildJsonObject { put("spatialReference", buildJsonObject { put("wkid", 4326) }); put("rings", buildJsonArray { add(buildJsonArray { repeat(5000) { add(buildJsonArray { add(34); add(31) }) } }) }) }
        val features = (1L..21L).map { MapFeature(shape, InquiryRecord(it)) }
        val result = MapCollection().append(MapPage(features, null, 21, omitted = 2), 0)
        assertEquals(20, result.features.size); assertEquals(2, result.omitted); assertTrue(result.limited)
    }
    @Test fun sharedUnitLocationsRetainAllIdsAndUseTheWorstLoadedDamage() {
        val units = listOf(feature(1).copy(attributes = InquiryRecord(1, damageStatus = "no_damage")), feature(2).copy(attributes = InquiryRecord(2, damageStatus = "fully_damaged")))
        val group = groupsAtSameGeometry(units).single()
        assertEquals(listOf(1L, 2L), group.map { it.attributes.recordId })
        assertEquals("fully_damaged", group.maxBy { damageRank(it.attributes.damageStatus) }.attributes.damageStatus)
    }
}
