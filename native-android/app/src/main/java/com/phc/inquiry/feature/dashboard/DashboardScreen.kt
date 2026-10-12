package com.phc.inquiry.feature.dashboard

import androidx.compose.foundation.BorderStroke
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.unit.dp
import com.phc.inquiry.R
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.Sector

@Composable
fun DashboardScreen(name: String, state: DashboardState, onSector: (String) -> Unit, retry: () -> Unit, onCitizens: () -> Unit = {}, onExplore: (String) -> Unit = {}) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.TopCenter) {
        LazyColumn(Modifier.widthIn(max = 880.dp).fillMaxSize(), contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
            item { Column(verticalArrangement = Arrangement.spacedBy(6.dp)) {
                Text("حصر الأضرار", style = MaterialTheme.typography.headlineSmall)
                Text("السجلات والخريطة والإحصائيات في مساحة واحدة", color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodyMedium)
            } }
            item {
                Surface(color = MaterialTheme.colorScheme.surface, shape = RoundedCornerShape(16.dp), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
                    Column(Modifier.fillMaxWidth().padding(20.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Text("ابدأ بالسجل الذي تريد متابعته", style = MaterialTheme.typography.titleMedium)
                        Text("ابحث بالاسم أو برقم OBJECTID كاملًا.", style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                        if(state.sectors.any { it.key == "buildings" }) Button(onClick = { onSector("buildings") }, Modifier.fillMaxWidth().heightIn(min = 50.dp), shape = RoundedCornerShape(10.dp)) {
                            Icon(Icons.Default.Search, null, Modifier.size(20.dp)); Spacer(Modifier.width(8.dp)); Text("البحث عن مبنى")
                        }
                    }
                }
            }
            item { Column { Text(stringResource(R.string.available_services), style = MaterialTheme.typography.titleLarge)
                Text(stringResource(R.string.permission_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) } }
            if (state.loading) item { LoadingState() }
            state.error?.let { error -> item { ErrorState(error, retry) } }
            if (state.sectors.isNotEmpty()) item {
                Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                    OutlinedCard(onClick = { onExplore("map") }, Modifier.weight(1f).testTag("open-map"), colors = CardDefaults.outlinedCardColors(containerColor = MaterialTheme.colorScheme.surface)) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) { IconTile(Icons.Default.Place); Text(stringResource(R.string.map_tab), style = MaterialTheme.typography.titleMedium); Text("استكشاف المواقع", style = MaterialTheme.typography.bodySmall) }
                    }
                    OutlinedCard(onClick = { onExplore("stats") }, Modifier.weight(1f).testTag("open-statistics"), colors = CardDefaults.outlinedCardColors(containerColor = MaterialTheme.colorScheme.surface)) {
                        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) { IconTile(Icons.Default.Info); Text(stringResource(R.string.statistics_title), style = MaterialTheme.typography.titleMedium); Text("الأعداد والنسب", style = MaterialTheme.typography.bodySmall) }
                    }
                }
            }
            if (!state.loading && state.error == null && state.sectors.isEmpty()) item { EmptyState(stringResource(R.string.no_services), stringResource(R.string.no_services_hint), Icons.Default.Lock) }
            items(state.sectors, key = { it.key }) { sector -> SectorCard(sector) { onSector(sector.key) } }
            if (state.sectors.any { it.citizenInquiry }) item {
                Card(onClick = onCitizens, modifier = Modifier.fillMaxWidth()) {
                    Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text(stringResource(R.string.citizen_inquiry), style = MaterialTheme.typography.titleLarge)
                        Text(stringResource(R.string.citizen_description), style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
        }
    }
}

@Composable
private fun SectorCard(sector: Sector, onClick: () -> Unit) {
    Card(onClick = onClick, modifier = Modifier.fillMaxWidth().testTag("sector-${sector.key}"),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.surface), border = BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
        Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                IconTile(if (sector.key == "housing-units") Icons.Default.Home else Icons.Default.Place)
                Text(sectorTitle(sector.key), style = MaterialTheme.typography.titleMedium, modifier = Modifier.weight(1f))
            }
            Text(stringResource(when (sector.key) { "housing-units" -> R.string.housing_description; "buildings" -> R.string.building_description; else -> R.string.sector_description }),
                style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                Text(stringResource(R.string.start_inquiry), color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.labelLarge)
                Icon(Icons.Default.Search, null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.primary)
            }
        }
    }
}
