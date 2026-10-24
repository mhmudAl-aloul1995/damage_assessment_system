package com.phc.inquiry

import com.phc.inquiry.core.security.*
import com.phc.inquiry.data.User

internal class MemoryOfflineIdentityStore : OfflineIdentityStore {
    var identity: OfflineIdentity? = null
    var deviceAuthenticated = false
    var forgotten = false
    override fun available(): Boolean = identity != null
    override fun prepare(enrolling: Boolean) { deviceAuthenticated = false }
    override fun enroll(user: User) {
        check(deviceAuthenticated) { "Device key is locked" }
        identity = OfflineIdentity(user, "https://fixture.example/", "2026-10-23T00:00:00Z")
    }
    override fun unlock(): OfflineIdentity { check(deviceAuthenticated) { "Device key is locked" }; return requireNotNull(identity) }
    override fun forget() { forgotten = true; identity = null }
}
