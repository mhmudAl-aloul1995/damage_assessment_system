package com.phc.inquiry

import com.phc.inquiry.data.PublishedForm
import com.phc.inquiry.feature.portal.*
import org.junit.Assert.*
import org.junit.Test

class ShelterWorkspaceTest {
    @Test fun actionsOnlyContainPermittedRecordFormsForThisWorkspace() {
        val allowed = PublishedForm("heks-phase-three", SHELTER_WORKSPACE, "استبيان", "v1", canSubmit = true)
        assertEquals(listOf(allowed), shelterForms(listOf(allowed, allowed.copy(key = "denied", canSubmit = false),
            allowed.copy(key = "other", module = "heks"), allowed.copy(key = "visit", targetKind = "visit"))))
    }

    @Test fun resumeUsesTheLatestUnsentDraftForTheExactWorkspaceAndRecord() {
        val draft = DraftSummary("correct", "heks-phase-three", "استبيان", SHELTER_WORKSPACE, 7, "2026-10-24T10:00:00Z", false, null)
        assertEquals(draft, shelterDraft(listOf(draft,
            draft.copy(uuid = "other-record", recordId = 8, updatedAt = "2026-10-24T12:00:00Z"),
            draft.copy(uuid = "other-module", module = "heks", updatedAt = "2026-10-24T13:00:00Z"),
            draft.copy(uuid = "received", receiptId = 2, updatedAt = "2026-10-24T14:00:00Z")), 7))
        assertNull(shelterDraft(listOf(draft), 8))
    }
}
