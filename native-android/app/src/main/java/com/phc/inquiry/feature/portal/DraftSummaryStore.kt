package com.phc.inquiry.feature.portal

import kotlinx.coroutines.CancellationException

data class DraftListing(val drafts: List<DraftSummary>, val unavailableCount: Int = 0)

interface DraftSummaryStore {
    fun listing(owner: Long): DraftListing
    fun delete(owner: Long, uuid: String)
}

internal fun collectDraftSummaries(ids: List<String>, load: (String) -> DraftSummary): DraftListing {
    val readable = mutableListOf<DraftSummary>()
    var unavailable = 0
    for (id in ids) {
        try { readable += load(id) }
        catch (cancelled: CancellationException) { throw cancelled }
        catch (_: Exception) { unavailable++ }
    }
    return DraftListing(readable.sortedByDescending { it.updatedAt }, unavailable)
}

internal fun <T> retryTransientRead(canRetry: (Exception) -> Boolean, action: () -> T): T {
    return try { action() }
    catch (cancelled: CancellationException) { throw cancelled }
    catch (error: Exception) { if (canRetry(error)) action() else throw error }
}

const val DRAFT_STORAGE_MESSAGE = "تعذّر فتح بعض المسودات المشفرة على هذا الجهاز. الملفات محفوظة ولم تُحذف. يمكنك متابعة الاستعلام، ثم إعادة محاولة فتح المسودات."
