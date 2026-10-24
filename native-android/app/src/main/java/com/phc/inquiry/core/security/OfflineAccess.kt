package com.phc.inquiry.core.security

import com.phc.inquiry.data.*
import com.phc.inquiry.feature.portal.DraftSummaryStore
import kotlinx.serialization.Serializable
import java.util.UUID
import javax.inject.Inject
import javax.inject.Singleton

@Serializable data class OfflineIdentity(val user: User, val origin: String, val enrolledAt: String)
data class OfflineAuthRequest(val id: String = UUID.randomUUID().toString(), val enrolling: Boolean, val credentialOnly: Boolean = false)
class OfflineAccessFailure(val code: String, message: String, cause: Throwable? = null) : Exception("$message ($code)", cause)

fun offlineAccessMessage(error: Exception, completing: Boolean): String = when (error) {
    is OfflineAccessFailure -> error.message.orEmpty()
    is InquiryFailure, is IllegalArgumentException -> error.message ?: "تعذّر الفتح المحلي. أعد المحاولة."
    else -> if (completing) "تعذّر إكمال الفتح المحلي. أعد المحاولة؛ مسوداتك محفوظة. (OFF-COMPLETE)" else "تعذّر تجهيز قفل الجهاز. أعد المحاولة؛ مسوداتك محفوظة. (OFF-PREPARE)"
}

interface OfflineIdentityStore {
    fun available(): Boolean
    fun prepare(enrolling: Boolean)
    fun credentialOnly(): Boolean = false
    fun enroll(user: User)
    fun unlock(): OfflineIdentity
    fun forget()
}

@Singleton class OfflineAccessController @Inject constructor(
    private val identities: OfflineIdentityStore,
    private val sessions: SessionState,
    private val sessionStore: SessionStore,
    private val forms: OfflineFormStore,
    private val drafts: DraftSummaryStore,
) {
    private var pending: Pair<OfflineAuthRequest, Session?>? = null
    fun available(): Boolean = identities.available()
    @Synchronized fun prepare(enrolling: Boolean): OfflineAuthRequest {
        val online = sessions.session.value
        if (enrolling) {
            require(online != null && online.isUnexpiredAt()) { "سجّل الدخول مع وجود نت قبل تفعيل الفتح المحلي." }
        } else {
            require(sessions.access.value == null && identities.available()) { "الفتح المحلي غير مفعّل. سجّل الدخول ونزّل النماذج ثم فعّله من حسابي." }
        }
        identities.prepare(enrolling)
        return OfflineAuthRequest(enrolling = enrolling, credentialOnly = enrolling || identities.credentialOnly()).also { pending = it to online }
    }
    @Synchronized fun complete(requestId: String): Boolean {
        val (request, owner) = pending?.takeIf { it.first.id == requestId } ?: throw InquiryFailure("انتهى طلب الفتح. أعد المحاولة.")
        pending = null
        if (request.enrolling) {
            require(owner != null && sessions.session.value == owner && owner.isUnexpiredAt()) { "تغيّر الحساب أو انتهت الجلسة. أعد تسجيل الدخول والتفعيل." }
            ensureLocalContent(owner.user.id)
            identities.enroll(owner.user)
        } else {
            require(sessions.access.value == null && sessions.session.value == null) { "تغيّر الحساب أثناء التوثيق. أعد المحاولة." }
            val identity = identities.unlock()
            require(identity.user.id > 0) { "الحساب المحلي غير صالح. أعد تفعيله مع وجود نت." }
            sessionStore.clear()
            sessions.enterOffline(identity.user)
        }
        return request.enrolling
    }
    private fun ensureLocalContent(owner: Long) {
        var unreadable = false
        val templates = try { forms.readCatalog(owner)?.readyKeys?.isNotEmpty() == true } catch (_: Exception) { unreadable = true; false }
        if (templates) return
        val savedDrafts = try { drafts.listing(owner).drafts.isNotEmpty() } catch (_: Exception) { unreadable = true; false }
        if (savedDrafts) return
        if (unreadable) throw OfflineAccessFailure("OFF-LOCAL-READ", "تعذّرت قراءة النماذج والمسودات المحلية. أعد تنزيل النموذج وتحقق من إمكانية فتح مسودة قبل التفعيل.")
        throw InquiryFailure("نزّل نموذجًا للميدان أو احفظ مسودة أولًا، ثم فعّل الفتح دون نت.")
    }
    @Synchronized fun cancel() { pending = null }
    fun forget() { cancel(); identities.forget(); if (sessions.access.value?.localOnly == true) sessions.set(null) }
}
