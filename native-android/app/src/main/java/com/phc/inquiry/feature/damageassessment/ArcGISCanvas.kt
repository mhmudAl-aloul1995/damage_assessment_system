package com.phc.inquiry.feature.damageassessment

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import androidx.compose.ui.viewinterop.AndroidView
import androidx.lifecycle.compose.LocalLifecycleOwner
import com.arcgismaps.ArcGISEnvironment
import com.arcgismaps.Color
import com.arcgismaps.LicenseKey
import com.arcgismaps.geometry.*
import com.arcgismaps.mapping.*
import com.arcgismaps.mapping.layers.OpenStreetMapLayer
import com.arcgismaps.mapping.symbology.*
import com.arcgismaps.mapping.view.*
import com.phc.inquiry.BuildConfig
import com.phc.inquiry.R
import com.phc.inquiry.data.MapFeature
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext

@Composable
fun ArcGISCanvas(features: List<MapFeature>, focusRevision: Int, selectedId: Long?, onSelect: (List<Long>) -> Unit, onBounds: (MapBounds) -> Unit, modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val owner = LocalLifecycleOwner.current
    val selectCallback by rememberUpdatedState(onSelect)
    val boundsCallback by rememberUpdatedState(onBounds)
    val mapView = remember(context) {
        runCatching {
            ArcGISEnvironment.applicationContext = context.applicationContext
            if (BuildConfig.ARCGIS_LICENSE.isNotBlank()) LicenseKey.create(BuildConfig.ARCGIS_LICENSE)?.let { ArcGISEnvironment.setLicense(it) }
            MapView(context).apply {
                map = ArcGISMap(Basemap(OpenStreetMapLayer())).apply { initialViewpoint = Viewpoint(31.42, 34.44, 250000.0) }
                graphicsOverlays.add(GraphicsOverlay())
                isAttributionBarVisible = true
            }
        }.getOrNull()
    }
    if (mapView == null) { Text(stringResource(R.string.map_render_failed), modifier.padding(20.dp)); return }
    val overlay = mapView.graphicsOverlays.first()
    var basemapFailed by remember { mutableStateOf(false) }
    var renderFailed by remember { mutableStateOf(false) }
    var retryRevision by remember { mutableIntStateOf(0) }
    DisposableEffect(mapView, owner) {
        owner.lifecycle.addObserver(mapView)
        onDispose { owner.lifecycle.removeObserver(mapView); mapView.onDestroy(owner) }
    }
    LaunchedEffect(mapView, retryRevision) { basemapFailed = mapView.map?.basemap?.value?.retryLoad()?.isFailure == true }
    LaunchedEffect(mapView) {
        mapView.onSingleTapConfirmed.collect { event ->
            val identified = mapView.identifyGraphicsOverlay(overlay, event.screenCoordinate, 18.0, false, 2000).getOrNull()
            val ids = identified?.graphics?.flatMap { graphicRecordIds(it) }?.distinct().orEmpty()
            selectCallback(ids)
        }
    }
    LaunchedEffect(mapView) {
        mapView.viewpointChanged.collect {
            val area = mapView.visibleArea ?: return@collect
            val projected = GeometryEngine.projectOrNull(area, SpatialReference.wgs84()) ?: return@collect
            val extent = projected.extent
            val bounds = MapBounds(extent.xMin, extent.yMin, extent.xMax, extent.yMax)
            if (bounds.filters() != null) boundsCallback(bounds)
        }
    }
    LaunchedEffect(features) {
        val graphics = withContext(Dispatchers.Default) {
            groupsAtSameGeometry(features).flatMap { group -> runCatching {
                val feature = group.maxBy { damageRank(it.attributes.damageStatus) }
                val geometry = Geometry.fromJsonOrNull(feature.geometry.toString()) ?: return@runCatching null
                val color = Color(damageColor(feature.attributes.damageStatus))
                val outline = SimpleLineSymbol(SimpleLineSymbolStyle.Solid, Color(0xFFFFFFFF.toInt()), 1.5f)
                val symbol = when (geometry) {
                    is Polygon -> SimpleFillSymbol(SimpleFillSymbolStyle.Solid, color, outline)
                    is Polyline -> SimpleLineSymbol(SimpleLineSymbolStyle.Solid, color, 4f)
                    else -> SimpleMarkerSymbol(SimpleMarkerSymbolStyle.Circle, color, if (group.size > 1) 26f else 13f).apply { this.outline = outline }
                }
                val attributes = mapOf("record_ids" to group.joinToString(",") { it.attributes.recordId.toString() })
                val graphics = mutableListOf(Graphic(geometry, attributes, symbol))
                if (group.size > 1) {
                    val label = TextSymbol(group.size.toString(), Color(0xFFFFFFFF.toInt()), 12f, HorizontalAlignment.Center, VerticalAlignment.Middle)
                    graphics += Graphic(geometry.extent.center, attributes, label)
                }
                graphics
            }.getOrNull().orEmpty() }
        }
        overlay.graphics.clear(); overlay.graphics.addAll(graphics)
        val renderedIds = graphics.flatMap(::graphicRecordIds).toSet()
        renderFailed = features.any { it.attributes.recordId !in renderedIds }
    }
    LaunchedEffect(selectedId, features) {
        overlay.clearSelection()
        if (selectedId != null) overlay.selectGraphics(overlay.graphics.filter { selectedId in graphicRecordIds(it) })
    }
    LaunchedEffect(focusRevision) {
        val envelope = withContext(Dispatchers.Default) {
            val geometries = features.mapNotNull { runCatching { Geometry.fromJsonOrNull(it.geometry.toString()) }.getOrNull() }
                .mapNotNull { GeometryEngine.projectOrNull(it, SpatialReference.webMercator()) }
            if (geometries.isEmpty()) return@withContext null
            val extents = geometries.map { it.extent }
            Envelope(xMin = extents.minOf { it.xMin } - 75, yMin = extents.minOf { it.yMin } - 75,
                xMax = extents.maxOf { it.xMax } + 75, yMax = extents.maxOf { it.yMax } + 75, spatialReference = SpatialReference.webMercator())
        }
        if (envelope != null) mapView.setViewpointGeometry(envelope, 48.0)
    }
    Column(modifier) {
        if (basemapFailed) Surface(color = MaterialTheme.colorScheme.errorContainer) {
            Column(Modifier.padding(12.dp)) { Text(stringResource(R.string.map_basemap_failed), style = MaterialTheme.typography.bodySmall); TextButton(onClick = { retryRevision++ }) { Text(stringResource(R.string.refresh)) } }
        }
        if (renderFailed) Text(stringResource(R.string.map_partial_geometry), modifier = Modifier.padding(8.dp), style = MaterialTheme.typography.bodySmall)
        AndroidView(factory = { mapView }, modifier = Modifier.weight(1f).fillMaxWidth())
    }
}

private fun graphicRecordIds(graphic: Graphic): List<Long> = (graphic.attributes["record_ids"] as? String)?.split(',')?.mapNotNull(String::toLongOrNull).orEmpty()
