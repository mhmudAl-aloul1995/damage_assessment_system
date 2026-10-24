package com.phc.inquiry.core.security

import com.phc.inquiry.data.Session
import java.time.Instant
import java.time.OffsetDateTime
import java.time.format.DateTimeParseException

internal fun Session.isUnexpiredAt(now: Instant = Instant.now()): Boolean = try {
    OffsetDateTime.parse(expiresAt).toInstant().isAfter(now)
} catch (_: DateTimeParseException) { false }
