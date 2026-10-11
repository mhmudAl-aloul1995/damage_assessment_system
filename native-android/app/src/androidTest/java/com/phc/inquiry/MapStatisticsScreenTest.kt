package com.phc.inquiry

import androidx.compose.ui.Modifier
import androidx.compose.ui.test.*
import androidx.compose.ui.test.junit4.createComposeRule
import com.arcgismaps.ArcGISEnvironment
import com.arcgismaps.geometry.Geometry
import com.arcgismaps.geometry.GeometryEngine
import com.arcgismaps.geometry.SpatialReference
import androidx.test.platform.app.InstrumentationRegistry
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.*
import com.phc.inquiry.feature.damageassessment.*
import org.junit.Assert.*
import org.junit.Rule
import org.junit.Test
import kotlinx.serialization.json.*

class MapStatisticsScreenTest {
    @get:Rule val compose = createComposeRule()
    @Test fun statisticsShowScopedCountsAndAccessibleDamageLabels() {
        compose.setContent { DesignPreviewFrame { StatisticsScreen(SectorStatistics(summary = mapOf("total" to 20, "completed" to 15, "pending" to 10, "approved" to 5, "action_required" to 0),
            damage = mapOf("fully_damaged" to 8, "partially_damaged" to 12)), false, null, {}, Modifier) } }
        compose.onNodeWithTag("metric-total").assertExists()
        compose.onNodeWithText("20").assertExists()
        compose.onNodeWithText("توزيع الأضرار").performScrollTo().assertIsDisplayed()
        compose.onNodeWithText("ضرر كلي").assertExists()
    }
    @Test fun statisticsErrorsExposeAnExplicitRetry() {
        var retried = false
        compose.setContent { DesignPreviewFrame { StatisticsScreen(null, false, "خطأ تجريبي", { retried = true }) } }
        compose.onNodeWithText("خطأ تجريبي").assertExists()
        compose.onNodeWithText("إعادة المحاولة").performClick()
        compose.runOnIdle { assertTrue(retried) }
    }
    @Test fun mapGeometryParsesAndProjectsThroughActualNativeSdk() {
        ArcGISEnvironment.applicationContext = InstrumentationRegistry.getInstrumentation().targetContext
        val point = Geometry.fromJsonOrNull("""{"x":34.4,"y":31.5,"spatialReference":{"wkid":4326}}""")
        assertNotNull(point)
        val projected = GeometryEngine.projectOrNull(requireNotNull(point), SpatialReference.webMercator())
        assertNotNull(projected)
        assertTrue(requireNotNull(projected).extent.xMin > 3000000)
    }

    @Test fun buildingSummaryShowsParentObjectidAndDamageInsteadOfUnitDamage() {
        val feature = MapFeature(Json.parseToJsonElement("""{"x":34.4,"y":31.5,"spatialReference":{"wkid":4326}}""").jsonObject,
            InquiryRecord(8, objectid = JsonPrimitive(900), damageStatus = "fully_damaged"),
            MapBuilding("parent", JsonPrimitive(7000), "برج الأمل", "no_damage"))
        compose.setContent { DesignPreviewFrame { BuildingMapSummary(mapSymbolGroups(listOf(feature), "housing-units").single(), true) } }
        compose.onNodeWithText("برج الأمل").assertExists()
        compose.onNodeWithText("OBJECTID: 7000").assertExists()
        compose.onNodeWithText("OBJECTID: 900").assertDoesNotExist()
        compose.onNodeWithText("ضرر كلي").assertDoesNotExist()
        compose.onNodeWithText("تصنيف ضرر المبنى").assertExists()
        compose.onNodeWithText("1 وحدة محمّلة ضمن البحث والفلاتر").assertExists()
    }
}
