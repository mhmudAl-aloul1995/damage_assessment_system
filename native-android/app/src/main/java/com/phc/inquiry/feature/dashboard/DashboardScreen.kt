package com.phc.inquiry.feature.dashboard

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.unit.dp
import com.phc.inquiry.R
import com.phc.inquiry.core.ui.*

@Composable
fun DashboardScreen(name: String, state: DashboardState, onSector: (String) -> Unit, retry: () -> Unit) {
    LazyColumn(Modifier.fillMaxSize().widthIn(max = 900.dp), contentPadding = PaddingValues(20.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item {
            Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.primaryContainer)) {
                Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
                    Image(painterResource(R.drawable.phc_logo), "شعار PHC", Modifier.size(56.dp))
                    Text("مرحبًا، $name", style = MaterialTheme.typography.headlineSmall)
                    Text("منظومة الاستعلام\nابحث عن مبنى أو وحدة سكنية، واطّلع على حالتها.")
                }
            }
        }
        item { Text("حصر الأضرار", style = MaterialTheme.typography.titleLarge) }
        if (state.loading) item { LoadingState() }
        state.error?.let { error -> item { ErrorState(error, retry) } }
        if (!state.loading && state.error == null && state.sectors.isEmpty()) item { Text("لا توجد قطاعات متاحة لهذا الحساب. راجع مسؤول الصلاحيات.") }
        items(state.sectors, key = { it.key }) { sector ->
            Card(onClick = { onSector(sector.key) }, modifier = Modifier.fillMaxWidth()) {
                Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(sectorTitle(sector.key), style = MaterialTheme.typography.titleLarge)
                    Text(if (sector.key == "buildings") "بحث بالاسم أو رقم السجل، وعرض الموقع وحالة الضرر والتدقيق." else "بحث عن وحدة أو باسم المبنى المرتبط بها، وعرض بياناتها الأساسية.")
                    Text("بدء الاستعلام ←", color = MaterialTheme.colorScheme.primary)
                }
            }
        }
        item { Text("تظهر هنا القطاعات المتاحة لحسابك فقط. البيانات من السيرفر ولا تُحفظ محليًا.", style = MaterialTheme.typography.bodySmall) }
    }
}
