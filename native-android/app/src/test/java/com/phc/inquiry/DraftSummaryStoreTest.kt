package com.phc.inquiry

import com.phc.inquiry.feature.portal.*
import kotlinx.coroutines.CancellationException
import org.junit.Assert.*
import org.junit.Test

class DraftSummaryStoreTest {
    private fun summary(id: String) = DraftSummary(id, "heks-beneficiary", "test", "heks", 1, id, false, null)
    @Test fun unreadableFilesDoNotDiscardReadableDraftsAndEachIsCounted() {
        val loaded = mutableListOf<String>()
        val result = collectDraftSummaries(listOf("1", "broken", "2")) {
            loaded += it
            if (it == "broken") throw java.security.ProviderException("Keystore operation failed")
            summary(it)
        }
        assertEquals(listOf("2", "1"), result.drafts.map { it.uuid }); assertEquals(1, result.unavailableCount)
        assertEquals(listOf("1", "broken", "2"), loaded)
    }
    @Test fun emptyDirectoryIsDifferentFromCompletelyUnreadableStorage() {
        assertEquals(DraftListing(emptyList()), collectDraftSummaries(emptyList()) { error("Unexpected read") })
        val failed = collectDraftSummaries(listOf("broken")) { throw java.io.IOException("invalid ciphertext") }
        assertTrue(failed.drafts.isEmpty()); assertEquals(1, failed.unavailableCount)
    }
    @Test fun cancellationIsNeverCountedAsCorruption() {
        assertThrows(CancellationException::class.java) { collectDraftSummaries(listOf("1")) { throw CancellationException() } }
    }
    @Test fun transientDecryptionIsRetriedOnlyOnce() {
        var attempts = 0
        assertEquals("decrypted", retryTransientRead({ true }) { attempts++; if(attempts == 1) throw java.io.IOException("transient"); "decrypted" })
        assertEquals(2, attempts)
        attempts = 0
        assertThrows(java.io.IOException::class.java) { retryTransientRead({ true }) { attempts++; throw java.io.IOException("still failing") } }
        assertEquals(2, attempts)
    }
    @Test fun permanentDecryptionFailuresAreNotRetried() {
        var attempts = 0
        assertThrows(javax.crypto.AEADBadTagException::class.java) { retryTransientRead({ false }) { attempts++; throw javax.crypto.AEADBadTagException() } }
        assertEquals(1, attempts)
    }
    @Test fun cancelledReadsNeverRetry() {
        var attempts = 0
        assertThrows(CancellationException::class.java) { retryTransientRead({ true }) { attempts++; throw CancellationException() } }
        assertEquals(1, attempts)
    }
}
