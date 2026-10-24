package com.phc.inquiry.data

import kotlinx.serialization.Serializable

@Serializable data class OfflineCatalog(
    val modules: List<PortalModule> = emptyList(),
    val forms: List<PublishedForm> = emptyList(),
    val schemas: Map<String, FormSchema> = emptyMap(),
    val updatedAt: String = "",
    @kotlinx.serialization.Transient val storageWarning: String? = null,
) {
    val readyKeys: Set<String> get() = forms.filter { form ->
        form.canSubmit && !form.requiresRecord && schemas[form.key]?.let { it.recordId == null && !it.requiresRecord } == true
    }.map { it.key }.toSet()
}

interface OfflineFormStore {
    fun readCatalog(owner: Long): OfflineCatalog?
    fun saveCatalog(owner: Long, catalog: OfflineCatalog)
    fun clearCatalog(owner: Long)
}
