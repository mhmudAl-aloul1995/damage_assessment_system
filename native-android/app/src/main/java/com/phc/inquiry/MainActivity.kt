package com.phc.inquiry

import android.os.Bundle
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalLayoutDirection
import androidx.compose.ui.res.stringResource
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.automirrored.filled.ArrowBack
import androidx.compose.material.icons.filled.AccountCircle
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.Search
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Place
import androidx.compose.ui.unit.LayoutDirection
import androidx.compose.ui.unit.dp
import androidx.hilt.navigation.compose.hiltViewModel
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.navigation.NavType
import androidx.navigation.compose.*
import androidx.navigation.navArgument
import androidx.paging.compose.collectAsLazyPagingItems
import com.phc.inquiry.core.ui.*
import com.phc.inquiry.feature.authentication.*
import com.phc.inquiry.feature.dashboard.*
import com.phc.inquiry.feature.damageassessment.*
import com.phc.inquiry.feature.portal.*
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
    override fun attachBaseContext(newBase: android.content.Context) {
        val locale = java.util.Locale("ar")
        val configuration = android.content.res.Configuration(newBase.resources.configuration).apply { setLocale(locale); setLayoutDirection(locale) }
        super.attachBaseContext(newBase.createConfigurationContext(configuration))
    }
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.addFlags(WindowManager.LayoutParams.FLAG_SECURE)
        enableEdgeToEdge()
        setContent {
            CompositionLocalProvider(LocalLayoutDirection provides LayoutDirection.Rtl) {
                PhcTheme { InquiryApp() }
            }
        }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
private fun InquiryApp(authentication: SessionViewModel = hiltViewModel()) {
    val authenticationState by authentication.state.collectAsStateWithLifecycle()
    val session by authentication.session.collectAsStateWithLifecycle()
    Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
        when {
            authenticationState.restoring -> Box(Modifier.safeDrawingPadding()) { LoadingState() }
            session == null -> LoginScreen(authenticationState, authentication.policy.usesHttp, authentication.policy.httpReady, authentication::signIn)
            else -> key(session!!.user.id) {
                val navigation = rememberNavController()
                val portal: PortalViewModel = hiltViewModel()
                val portalState by portal.state.collectAsStateWithLifecycle()
                val backStack by navigation.currentBackStackEntryAsState()
                val route = backStack?.destination?.route.orEmpty()
                val root = route in setOf("portal-home", "portal-inquiry", "portal-surveys", "portal-drafts", "portal-account", "explore/{mode}")
                val editingForm = route.startsWith("form-screen/")
                val atHome = route == "portal-home"
                val atSearch = backStack?.destination?.route == "search/{sector}"
                val title = when {
                    atHome -> R.string.portal_name
                    atSearch -> R.string.search
                    backStack?.destination?.route?.startsWith("explore/") == true -> R.string.explore_title
                    backStack?.destination?.route == "citizens" -> R.string.citizen_inquiry
                    backStack?.destination?.route?.startsWith("history/") == true -> R.string.history_title
                    backStack?.destination?.route?.startsWith("attachment") == true -> R.string.attachments_title
                    else -> R.string.details
                }
                var confirmLogout by remember { mutableStateOf(false) }
                LaunchedEffect(route) { if(route.startsWith("portal-")) portal.localDrafts() }
                val openForm: (com.phc.inquiry.data.PublishedForm) -> Unit = { navigation.navigate("form-start/${it.key}") }
                val openModule: (String) -> Unit = { if(it == "damage_assessment") navigation.navigate("dashboard") else navigation.navigate("module/$it") }
                Scaffold(
                    topBar = {
                        if (!editingForm) {
                        TopAppBar(title = { Text(if(route == "dashboard") "حصر الأضرار" else if(route.startsWith("form-file/")) stringResource(R.string.attachments_title) else if(route.startsWith("portal-") || route.startsWith("module/") || route.startsWith("form-start/")) "بوابة المنظومة" else stringResource(title), style = MaterialTheme.typography.titleMedium) },
                            colors = TopAppBarDefaults.topAppBarColors(containerColor = MaterialTheme.colorScheme.background),
                            navigationIcon = { if (!root) IconButton(onClick = { navigation.popBackStack() }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) } },
                            actions = { IconButton(onClick = { confirmLogout = true }, enabled = !authenticationState.busy) { Icon(Icons.Default.AccountCircle, stringResource(R.string.account)) } })
                        }
                    },
                    bottomBar = { if(!editingForm) NavigationBar {
                        val tabs = listOf(Triple("portal-home", "الرئيسية", Icons.Default.Home), Triple("portal-inquiry", "الاستعلام", Icons.Default.Search), Triple("portal-surveys", "الاستبيانات", Icons.Default.Edit), Triple("explore/map", "الخريطة", Icons.Default.Place), Triple("portal-account", "حسابي", Icons.Default.AccountCircle))
                        tabs.forEach { (target, label, icon) -> NavigationBarItem(selected = route == target || (target == "portal-surveys" && route == "portal-drafts") || (target == "explore/map" && route.startsWith("explore/")),
                            onClick = { navigation.navigate(target) { popUpTo("portal-home") { saveState = true }; launchSingleTop = true; restoreState = true } }, icon = { Icon(icon, label) }, label = { Text(label, style = MaterialTheme.typography.labelSmall) }) }
                    } },
                ) { padding ->
                    NavHost(navigation, startDestination = "portal-home", modifier = Modifier.padding(padding)) {
                        composable("portal-home") { PortalHome(session?.user?.name.orEmpty(), portalState, openModule, { navigation.navigate("portal-surveys") }, { navigation.navigate("portal-drafts") }, { navigation.navigate("dashboard") }, portal::refresh) }
                        composable("portal-inquiry") { PortalInquiry(portalState, openModule, portal::refresh) }
                        composable("portal-surveys") { SurveysScreen(portalState, 0, openForm, { navigation.navigate("form-screen/${it.formKey}/${it.recordId ?: 0}/${it.uuid}") }, portal::deleteDraft, portal::refresh, portal::moreReceipts, portal::refreshReceipts, portal::localDrafts) { uuid, id -> navigation.navigate("form-file/server/$uuid/$id") } }
                        composable("portal-drafts") { SurveysScreen(portalState, 1, openForm, { navigation.navigate("form-screen/${it.formKey}/${it.recordId ?: 0}/${it.uuid}") }, portal::deleteDraft, portal::refresh, portal::moreReceipts, portal::refreshReceipts, portal::localDrafts) { uuid, id -> navigation.navigate("form-file/server/$uuid/$id") } }
                        composable("portal-account") { AccountScreen(session?.user?.name.orEmpty(), session?.user?.email.orEmpty(), portalState.modules.size) { confirmLogout = true } }
                        composable("module/{module}") { entry ->
                            val module = entry.arguments?.getString("module").orEmpty()
                            ModuleScreen(portalState.modules.firstOrNull { it.key == module }, portalState.forms.filter { it.module == module }, { navigation.navigate("portal-records/$module/-") }, openForm)
                        }
                        composable("form-start/{form}") { entry ->
                            val key = entry.arguments?.getString("form").orEmpty()
                            val form = portalState.forms.firstOrNull { it.key == key }
                            Column(Modifier.fillMaxWidth().padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                                Text(form?.title ?: "اختيار الاستبيان", style = MaterialTheme.typography.headlineSmall)
                                if(form != null) {
                                    if(!form.requiresRecord) Button(onClick = { navigation.navigate("form-screen/$key/0/-") }, modifier = Modifier.fillMaxWidth()) { Text("تعبئة استبيان جديد") }
                                    OutlinedButton(onClick = { navigation.navigate("portal-records/${form.module}/$key") }, modifier = Modifier.fillMaxWidth()) { Text("اختيار سجل موجود لتحديثه") }
                                } else ErrorState("النموذج غير متاح لحسابك.", portal::refresh)
                            }
                        }
                        composable("portal-records/{module}/{form}") {
                            val vm: PortalRecordsViewModel = hiltViewModel()
                            val state by vm.state.collectAsStateWithLifecycle()
                            PortalRecordsScreen(state, vm.form != null, vm::search, vm::more, vm::retry) { id -> navigation.navigate(if(vm.form != null) "form-screen/${vm.form}/$id/-" else "portal-detail/${vm.module}/$id") }
                        }
                        composable("portal-detail/{module}/{record}", arguments = listOf(navArgument("record") { type = NavType.LongType })) {
                            val vm: PortalDetailViewModel = hiltViewModel()
                            val state by vm.state.collectAsStateWithLifecycle()
                            PortalDetailScreen(state, portalState.forms, vm::refresh) { form, id -> navigation.navigate("form-screen/${form.key}/$id/-") }
                        }
                        composable("form-screen/{form}/{record}/{draft}", arguments = listOf(navArgument("record") { type = NavType.LongType })) {
                            val vm: FormViewModel = hiltViewModel()
                            val state by vm.state.collectAsStateWithLifecycle()
                            val location = rememberLocationPicker(vm::location, vm::notice)
                            FormScreen(state, vm, { portal.refresh(); navigation.navigate("portal-drafts") { popUpTo("portal-home"); launchSingleTop = true } }, location) { uuid, id -> navigation.navigate("form-file/local/$uuid/$id") }
                        }
                        composable("form-file/{mode}/{uuid}/{file}") {
                            val vm: PortalFileViewModel = hiltViewModel()
                            val state by vm.state.collectAsStateWithLifecycle()
                            AttachmentViewerScreen(state, vm::load, vm::page)
                        }
                        composable("dashboard") {
                            val viewModel: DashboardViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            DashboardScreen(session?.user?.name.orEmpty(), state, { navigation.navigate("search/$it") }, viewModel::refresh, { navigation.navigate("citizens") }, { navigation.navigate("explore/$it") })
                        }
                        composable("search/{sector}", arguments = listOf(navArgument("sector") { type = NavType.StringType })) {
                            val viewModel: SearchViewModel = hiltViewModel()
                            val records = viewModel.records.collectAsLazyPagingItems()
                            val searched by viewModel.hasSearched.collectAsStateWithLifecycle()
                            val total by viewModel.total.collectAsStateWithLifecycle()
                            val filters by viewModel.filterOptions.collectAsStateWithLifecycle()
                            SearchScreen(viewModel.sector, records, searched, total, { viewModel.search(it) }, { navigation.navigate("detail/${viewModel.sector}/$it") },
                                filterState = filters, reloadFilters = viewModel::loadFilters, onAdvancedSearch = viewModel::search)
                        }
                        composable("citizens") {
                            val viewModel: SearchViewModel = hiltViewModel()
                            val records = viewModel.records.collectAsLazyPagingItems()
                            val searched by viewModel.hasSearched.collectAsStateWithLifecycle()
                            val total by viewModel.total.collectAsStateWithLifecycle()
                            SearchScreen("citizens", records, searched, total, { viewModel.search(it) }, {}, onRecordItem = { record ->
                                record.sector?.takeIf { it in setOf("buildings", "housing-units") }?.let { navigation.navigate("detail/$it/${record.recordId}") }
                            })
                        }
                        composable("explore/{mode}", arguments = listOf(navArgument("mode") { type = NavType.StringType })) {
                            val viewModel: ExploreViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            ExploreScreen(state, viewModel.initialMode, viewModel::catalog, viewModel::selectSector, viewModel::search,
                                viewModel::loadFilters, { viewModel.loadMap(false) }, { viewModel.loadMap(true) }, viewModel::loadStatistics, viewModel::searchArea,
                                { record -> navigation.navigate("detail/${state.sector}/${record.recordId}") })
                        }
                        composable("detail/{sector}/{record}", arguments = listOf(navArgument("sector") { type = NavType.StringType }, navArgument("record") { type = NavType.LongType })) {
                            val viewModel: DetailViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            val id = it.arguments?.getLong("record") ?: 0L
                            Column(Modifier.fillMaxSize()) {
                                Box(Modifier.weight(1f)) { DetailScreen(state, viewModel::refresh, { navigation.navigate("history/${viewModel.sector}/$id") }, { navigation.navigate("attachments/${viewModel.sector}/$id") }) }
                                portalState.forms.filter { form -> form.sector == viewModel.sector && form.targetKind == "record" }.forEach { form ->
                                    FilledTonalButton(onClick = { navigation.navigate("form-screen/${form.key}/$id/-") }, modifier = Modifier.fillMaxWidth().padding(horizontal = 20.dp, vertical = 6.dp)) { Text("فتح نموذج تحديث الاستبيان") }
                                }
                            }
                        }
                        composable("history/{sector}/{record}", arguments = listOf(navArgument("sector") { type = NavType.StringType }, navArgument("record") { type = NavType.LongType })) {
                            val viewModel: AuditHistoryViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            AuditHistoryScreen(state, viewModel::select, viewModel::load)
                        }
                        composable("attachments/{sector}/{record}", arguments = listOf(navArgument("sector") { type = NavType.StringType }, navArgument("record") { type = NavType.LongType })) { entry ->
                            val viewModel: AttachmentsViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            AttachmentsScreen(state, viewModel::refresh) { id -> navigation.navigate("attachment/${entry.arguments?.getString("sector")}/${entry.arguments?.getLong("record")}/$id") }
                        }
                        composable("attachment/{sector}/{record}/{attachment}", arguments = listOf(navArgument("sector") { type = NavType.StringType }, navArgument("record") { type = NavType.LongType }, navArgument("attachment") { type = NavType.LongType })) {
                            val viewModel: AttachmentViewerViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            AttachmentViewerScreen(state, viewModel::load, viewModel::page)
                        }
                    }
                }
                if (confirmLogout) {
                    AlertDialog(onDismissRequest = { confirmLogout = false }, title = { Text(session?.user?.name.orEmpty()) },
                        text = { Column { Text(session?.user?.email.orEmpty()); Text(stringResource(R.string.logout_message)) } },
                        confirmButton = { TextButton(onClick = { confirmLogout = false; authentication.signOut() }) { Text(stringResource(R.string.logout)) } },
                        dismissButton = { TextButton(onClick = { confirmLogout = false }) { Text(stringResource(R.string.cancel)) } })
                }
            }
        }
    }
}
