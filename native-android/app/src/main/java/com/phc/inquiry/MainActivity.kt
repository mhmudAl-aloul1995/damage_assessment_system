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
    Surface(Modifier.fillMaxSize()) {
        when {
            authenticationState.restoring -> Box(Modifier.safeDrawingPadding()) { LoadingState() }
            session == null -> LoginScreen(authenticationState, authentication.policy.usesHttp, authentication.policy.httpReady, authentication::signIn)
            else -> key(session!!.user.id) {
                val navigation = rememberNavController()
                val backStack by navigation.currentBackStackEntryAsState()
                val atHome = backStack?.destination?.route == "dashboard"
                var confirmLogout by remember { mutableStateOf(false) }
                Scaffold(
                    topBar = {
                        TopAppBar(title = { Text(if (atHome) "PHC Inquiry" else "استعلام حصر الأضرار") },
                            navigationIcon = { if (!atHome) TextButton(onClick = { navigation.popBackStack() }) { Text("رجوع") } },
                            actions = { TextButton(onClick = { confirmLogout = true }, enabled = !authenticationState.busy) { Text("خروج") } })
                    },
                ) { padding ->
                    NavHost(navigation, startDestination = "dashboard", modifier = Modifier.padding(padding)) {
                        composable("dashboard") {
                            val viewModel: DashboardViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            DashboardScreen(session?.user?.name.orEmpty(), state, { navigation.navigate("search/$it") }, viewModel::refresh)
                        }
                        composable("search/{sector}", arguments = listOf(navArgument("sector") { type = NavType.StringType })) {
                            val viewModel: SearchViewModel = hiltViewModel()
                            val records = viewModel.records.collectAsLazyPagingItems()
                            val searched by viewModel.hasSearched.collectAsStateWithLifecycle()
                            val total by viewModel.total.collectAsStateWithLifecycle()
                            SearchScreen(viewModel.sector, records, searched, total, viewModel::search) { navigation.navigate("detail/${viewModel.sector}/$it") }
                        }
                        composable("detail/{sector}/{record}", arguments = listOf(navArgument("sector") { type = NavType.StringType }, navArgument("record") { type = NavType.LongType })) {
                            val viewModel: DetailViewModel = hiltViewModel()
                            val state by viewModel.state.collectAsStateWithLifecycle()
                            DetailScreen(state, viewModel::refresh)
                        }
                    }
                }
                if (confirmLogout) {
                    AlertDialog(onDismissRequest = { confirmLogout = false }, title = { Text("تسجيل الخروج؟") },
                        text = { Text("سيُحذف رمز الجلسة من الجهاز وتُرسل محاولة إلغائه إلى السيرفر.") },
                        confirmButton = { TextButton(onClick = { confirmLogout = false; authentication.signOut() }) { Text("خروج") } },
                        dismissButton = { TextButton(onClick = { confirmLogout = false }) { Text("إلغاء") } })
                }
            }
        }
    }
}
