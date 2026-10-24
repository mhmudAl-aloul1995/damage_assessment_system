package com.phc.inquiry

import com.phc.inquiry.core.security.isUnexpiredAt
import com.phc.inquiry.data.Session
import com.phc.inquiry.data.User
import org.junit.Assert.*
import org.junit.Test
import java.time.Instant

class SessionExpiryTest {
    private fun session(expiry: String) = Session("fixture-never-sent", expiry, User(1, "Fixture", "fixture@example.invalid"))

    @Test fun expiryUsesTheSameInstantForUtcAndPositiveAndNegativeOffsetsIncludingFractions() {
        val now = Instant.parse("2030-01-01T00:00:00Z")
        for (expiry in listOf("2030-01-01T00:00:00Z", "2030-01-01T03:00:00+03:00", "2029-12-31T21:00:00-03:00", "2030-01-01T00:00:00+00:00")) {
            assertTrue(expiry, session(expiry).isUnexpiredAt(now.minusNanos(1)))
            assertFalse(expiry, session(expiry).isUnexpiredAt(now))
            assertFalse(expiry, session(expiry).isUnexpiredAt(now.plusNanos(1)))
        }
        assertTrue(session("2030-01-01T03:00:00.123456+03:00").isUnexpiredAt(now))
    }

    @Test fun invalidOrMissingTimezoneNeverGrantsAnUnexpiredSession() {
        for (expiry in listOf("", "not-a-date", "2030-01-01T00:00:00", "2030-01-01 00:00:00", "2030-99-01T00:00:00Z")) {
            assertFalse(expiry, session(expiry).isUnexpiredAt(Instant.EPOCH))
        }
    }
}
