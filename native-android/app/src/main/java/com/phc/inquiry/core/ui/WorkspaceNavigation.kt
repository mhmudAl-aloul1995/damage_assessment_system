package com.phc.inquiry.core.ui

import androidx.compose.foundation.layout.heightIn
import androidx.compose.foundation.layout.BoxWithConstraints
import androidx.compose.foundation.layout.fillMaxWidth
import androidx.compose.foundation.layout.requiredWidth
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.Edit
import androidx.compose.material.icons.filled.Home
import androidx.compose.material.icons.filled.List
import androidx.compose.material.icons.filled.Menu
import androidx.compose.material3.*
import androidx.compose.runtime.Composable
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalDensity
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.text.style.TextAlign
import androidx.compose.ui.unit.dp

enum class WorkspaceDestination(val route: String, val label: String) {
    Home("portal-home", "الرئيسية"),
    Workspaces("portal-inquiry", "المساحات"),
    Work("portal-tasks", "أعمالي"),
    More("portal-account", "المزيد"),
}

fun workspaceDestination(route: String): WorkspaceDestination = when {
    route == "portal-home" -> WorkspaceDestination.Home
    route in setOf("portal-account", "portal-offline") -> WorkspaceDestination.More
    route in setOf("portal-tasks", "portal-surveys", "portal-drafts", "portal-sent") ||
        route.startsWith("form-") -> WorkspaceDestination.Work
    else -> WorkspaceDestination.Workspaces
}

@Composable
fun WorkspaceNavigationBar(route: String, onNavigate: (String) -> Unit) {
    BoxWithConstraints(Modifier.fillMaxWidth()) {
    val labelWidth = maxWidth / WorkspaceDestination.entries.size - 4.dp
    NavigationBar(modifier = Modifier.heightIn(min = 80.dp * LocalDensity.current.fontScale.coerceAtLeast(1f)),
        containerColor = MaterialTheme.colorScheme.surface, tonalElevation = 0.dp) {
        WorkspaceDestination.entries.forEach { destination ->
            NavigationBarItem(
                selected = workspaceDestination(route) == destination,
                onClick = { onNavigate(destination.route) },
                modifier = Modifier.testTag("nav-${destination.route}"),
                icon = { Icon(when (destination) {
                    WorkspaceDestination.Home -> Icons.Default.Home
                    WorkspaceDestination.Workspaces -> Icons.Default.List
                    WorkspaceDestination.Work -> Icons.Default.Edit
                    WorkspaceDestination.More -> Icons.Default.Menu
                }, null) },
                label = { Text(destination.label, modifier = Modifier.requiredWidth(labelWidth),
                    style = MaterialTheme.typography.labelSmall, textAlign = TextAlign.Center,
                    maxLines = 1, softWrap = false) },
                colors = NavigationBarItemDefaults.colors(
                    selectedIconColor = MaterialTheme.colorScheme.primary,
                    selectedTextColor = MaterialTheme.colorScheme.primary,
                    indicatorColor = MaterialTheme.colorScheme.primaryContainer,
                    unselectedIconColor = MaterialTheme.colorScheme.onSurfaceVariant,
                    unselectedTextColor = MaterialTheme.colorScheme.onSurfaceVariant,
                ),
            )
        }
    }
    }
}
