package com.phc.inquiry

import com.phc.inquiry.data.OfflineCatalog
import com.phc.inquiry.data.OfflineFormStore
import kotlinx.serialization.encodeToString
import kotlinx.serialization.json.Json

internal class MemoryOfflineForms : OfflineFormStore {
    private val saved = mutableMapOf<Long, String>()
    override fun readCatalog(owner: Long): OfflineCatalog? = saved[owner]?.let { Json.decodeFromString<OfflineCatalog>(it) }
    override fun saveCatalog(owner: Long, catalog: OfflineCatalog) { saved[owner] = Json.encodeToString(catalog) }
    override fun clearCatalog(owner: Long) { saved.remove(owner) }
}
