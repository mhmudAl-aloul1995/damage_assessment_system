package com.phc.inquiry.feature.portal

import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.List
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavType
import androidx.navigation.compose.*
import androidx.navigation.navArgument
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.data.User
import com.phc.inquiry.feature.authentication.SessionViewModel
import com.phc.inquiry.feature.damageassessment.AttachmentViewerScreen

@OptIn(ExperimentalMaterial3Api::class)
@Composable fun OfflineWorkspace(user: User, authentication: SessionViewModel) {
    val navigation = rememberNavController()
    val portal: PortalViewModel = hiltViewModel()
    val state by portal.state.collectAsStateWithLifecycle()
    val current by navigation.currentBackStackEntryAsState()
    val editing = current?.destination?.route?.startsWith("form/") == true || current?.destination?.route?.startsWith("file/") == true
    LaunchedEffect(current?.destination?.route) { portal.localDrafts() }
    Scaffold(topBar = { if (!editing) TopAppBar(title = { Text("وضع الميدان · فتح محلي") }, actions = { TextButton(onClick = authentication::lockOffline) { Text("قفل") } }) },
        bottomBar = { if (!editing) NavigationBar {
            listOf("surveys" to "النماذج", "drafts" to "المسودات", "account" to "حسابي").forEach { (route, label) ->
                NavigationBarItem(selected = current?.destination?.route == route, onClick = { navigation.navigate(route) { popUpTo("surveys"); launchSingleTop = true } }, icon = { Icon(when (route) { "surveys" -> Icons.Default.List; "drafts" -> Icons.Default.Edit; else -> Icons.Default.AccountCircle }, label) }, label = { Text(label) })
            }
        } }) { padding ->
        NavHost(navigation, "surveys", Modifier.padding(padding)) {
            composable("surveys") { SurveysScreen(state, 0, { form -> navigation.navigate("form/${form.key}/0/-") }, { draft -> navigation.navigate("form/${draft.formKey}/${draft.recordId ?: 0}/${draft.uuid}") }, portal::deleteDraft, portal::refresh, {}, retryDrafts = portal::localDrafts) }
            composable("drafts") { SurveysScreen(state, 1, { form -> navigation.navigate("form/${form.key}/0/-") }, { draft -> navigation.navigate("form/${draft.formKey}/${draft.recordId ?: 0}/${draft.uuid}") }, portal::deleteDraft, portal::refresh, {}, retryDrafts = portal::localDrafts) }
            composable("account") { Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                Text(user.name, style = MaterialTheme.typography.headlineSmall); Text(user.email)
                Text("هذا وصول محلي للنماذج المنزّلة والمسودات فقط. تبقى محفوظة للحساب نفسه. سجّل الدخول مع وجود نت عند الاستعداد للإرسال.")
                Button(onClick = authentication::lockOffline, modifier = Modifier.fillMaxWidth()) { Text("تسجيل الدخول للإرسال") }
                OutlinedButton(onClick = authentication::lockOffline, modifier = Modifier.fillMaxWidth()) { Text("قفل العمل المحلي") }
            } }
            composable("form/{form}/{record}/{draft}", arguments = listOf(navArgument("record") { type = NavType.LongType })) {
                val model: FormViewModel = hiltViewModel()
                val formState by model.state.collectAsStateWithLifecycle()
                val location = rememberLocationPicker(model::location, model::notice)
                FormScreen(formState, model, { portal.localDrafts(); navigation.navigate("drafts") { popUpTo("surveys"); launchSingleTop = true } }, location, authentication::lockOffline) { uuid, id -> navigation.navigate("file/local/$uuid/$id") }
            }
            composable("file/{mode}/{uuid}/{file}") {
                val model: PortalFileViewModel = hiltViewModel()
                val fileState by model.state.collectAsStateWithLifecycle()
                AttachmentViewerScreen(fileState, model::load, model::page)
            }
        }
    }
}
