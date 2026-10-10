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
fun DashboardScreen(name: String, state: DashboardState, onSector: (String) -> Unit, retry: () -> Unit, onCitizens: () -> Unit = {}) {
    Box(Modifier.fillMaxSize(), contentAlignment = Alignment.TopCenter) {
        LazyColumn(Modifier.widthIn(max = 880.dp).fillMaxSize(), contentPadding = PaddingValues(24.dp), verticalArrangement = Arrangement.spacedBy(20.dp)) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                    BrandMark(64)
                    Column { Text(stringResource(R.string.welcome, name), style = MaterialTheme.typography.titleLarge)
                        Text(stringResource(R.string.portal_name), color = MaterialTheme.colorScheme.onSurfaceVariant, style = MaterialTheme.typography.bodyMedium) }
                }
            }
            item {
                Surface(color = MaterialTheme.colorScheme.primary, contentColor = MaterialTheme.colorScheme.onPrimary, shape = RoundedCornerShape(20.dp)) {
                    Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        Icon(Icons.Default.Search, null, Modifier.size(28.dp))
                        Text(stringResource(R.string.dashboard_intro), style = MaterialTheme.typography.headlineSmall)
                        Text(stringResource(R.string.dashboard_description), style = MaterialTheme.typography.bodyMedium)
                    }
                }
            }
            item { Column { Text(stringResource(R.string.available_services), style = MaterialTheme.typography.titleLarge)
                Text(stringResource(R.string.permission_hint), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant) } }
            if (state.loading) item { LoadingState() }
            state.error?.let { error -> item { ErrorState(error, retry) } }
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
        Column(Modifier.padding(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(14.dp)) {
                IconTile(if (sector.key == "housing-units") Icons.Default.Home else Icons.Default.Place)
                Text(sectorTitle(sector.key), style = MaterialTheme.typography.titleLarge, modifier = Modifier.weight(1f))
            }
            Text(stringResource(if (sector.key == "housing-units") R.string.housing_description else R.string.building_description),
                style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
            Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.CenterVertically) {
                Text(stringResource(R.string.start_inquiry), color = MaterialTheme.colorScheme.primary, style = MaterialTheme.typography.labelLarge)
                Icon(Icons.Default.Search, null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.primary)
            }
        }
    }
}
