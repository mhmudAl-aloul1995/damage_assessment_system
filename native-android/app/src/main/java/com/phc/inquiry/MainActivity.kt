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
import androidx.compose.ui.unit.LayoutDirection
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
import dagger.hilt.android.AndroidEntryPoint

@AndroidEntryPoint
class MainActivity : ComponentActivity() {
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
                val backStack by navigation.currentBackStackEntryAsState()
                val atHome = backStack?.destination?.route == "dashboard"
                val atSearch = backStack?.destination?.route == "search/{sector}"
                val title = when {
                    atHome -> R.string.portal_name
                    atSearch -> R.string.search
                    backStack?.destination?.route == "citizens" -> R.string.citizen_inquiry
                    backStack?.destination?.route?.startsWith("history/") == true -> R.string.history_title
                    backStack?.destination?.route?.startsWith("attachment") == true -> R.string.attachments_title
                    else -> R.string.details
                }
                var confirmLogout by remember { mutableStateOf(false) }
                Scaffold(
                    topBar = {
                        TopAppBar(title = { Text(stringResource(title), style = MaterialTheme.typography.titleMedium) },
                            colors = TopAppBarDefaults.topAppBarColors(containerColor = MaterialTheme.colorScheme.background),
                            navigationIcon = { if (!atHome) IconButton(onClick = { navigation.popBackStack() }) { Icon(Icons.AutoMirrored.Filled.ArrowBack, stringResource(R.string.back)) } },
                            actions = { IconButton(onClick = { confirmLogout = true }, enabled = !authenticationState.busy) { Icon(Icons.Default.AccountCircle, stringResource(R.string.account)) } })
                    },
                ) { padding ->
                    NavHost(navigation, startDestination = "dashboard", modifier = Modifier.padding(padding)) {
                        composable("dashboard") {
                            val viewModel: DashboardViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            DashboardScreen(session?.user?.name.orEmpty(), state, { navigation.navigate("search/$it") }, viewModel::refresh, { navigation.navigate("citizens") })
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
                        composable("detail/{sector}/{record}", arguments = listOf(navArgument("sector") { type = NavType.StringType }, navArgument("record") { type = NavType.LongType })) {
                            val viewModel: DetailViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            val id = it.arguments?.getLong("record") ?: 0L
                            DetailScreen(state, viewModel::refresh, { navigation.navigate("history/${viewModel.sector}/$id") }, { navigation.navigate("attachments/${viewModel.sector}/$id") })
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
