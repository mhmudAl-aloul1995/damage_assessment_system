package com.phc.inquiry.domain

import com.phc.inquiry.core.security.InquiryFailure
import com.phc.inquiry.data.InquiryRepository
import javax.inject.Inject

class LoginUseCase @Inject constructor(private val repository: InquiryRepository) {
    suspend operator fun invoke(email: String, password: String) {
        val normalizedEmail = email.trim()
        if (!normalizedEmail.contains('@') || normalizedEmail.length > 254 || password.isBlank()) {
            throw InquiryFailure("أدخل بريدًا إلكترونيًا صحيحًا وكلمة المرور.")
        }
        repository.login(normalizedEmail, password)
    }
}
