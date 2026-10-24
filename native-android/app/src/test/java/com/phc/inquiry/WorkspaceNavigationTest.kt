package com.phc.inquiry

import com.phc.inquiry.core.ui.WorkspaceDestination
import com.phc.inquiry.core.ui.workspaceDestination
import com.phc.inquiry.feature.portal.DraftSummary
import com.phc.inquiry.feature.portal.latestUnfinishedDraft
import org.junit.Assert.*
import org.junit.Test

class WorkspaceNavigationTest {
    @Test fun mapStatisticsAndModuleDetailsStayInsideWorkspaces() {
        for (route in listOf("explore/{mode}", "explore/map", "dashboard", "module/heks", "attendance", "portal-detail/{module}/{record}", "search/{sector}", "attachments/{sector}/{record}")) {
            assertEquals(route, WorkspaceDestination.Workspaces, workspaceDestination(route))
        }
    }

    @Test fun draftsFormsAndReceiptsBelongToWork() {
        for (route in listOf("portal-tasks", "portal-drafts", "portal-sent", "portal-surveys", "form-start/{form}", "form-screen/{form}/{record}/{draft}", "form-file/{mode}/{uuid}/{file}")) {
            assertEquals(route, WorkspaceDestination.Work, workspaceDestination(route))
        }
    }

    @Test fun localPreparationBelongsToMoreAndHomeHasItsOwnDestination() {
        assertEquals(WorkspaceDestination.More, workspaceDestination("portal-offline"))
        assertEquals(WorkspaceDestination.More, workspaceDestination("portal-account"))
        assertEquals(WorkspaceDestination.Home, workspaceDestination("portal-home"))
    }

    @Test fun continuationPicksLatestUnfinishedDraftIncludingUnconfirmedSubmission() {
        val draft = DraftSummary("old", "form", "مسودة", "heks", null, "2026-10-21T10:00:00Z", false, null)
        val pending = draft.copy(uuid = "pending", updatedAt = "2026-10-22T10:00:00Z", pending = true)
        val received = draft.copy(uuid = "received", updatedAt = "2026-10-23T10:00:00Z", receiptId = 10)
        assertEquals(pending, latestUnfinishedDraft(listOf(received, pending, draft)))
        assertNull(latestUnfinishedDraft(listOf(received)))
        assertNull(latestUnfinishedDraft(emptyList()))
    }
}
