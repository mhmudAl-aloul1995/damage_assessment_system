package com.phc.inquiry.feature.authentication

import android.app.Activity
import android.app.KeyguardManager
import android.hardware.biometrics.BiometricManager
import android.hardware.biometrics.BiometricPrompt
import android.os.Build
import android.os.CancellationSignal
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.platform.LocalContext
import com.phc.inquiry.core.security.OfflineAuthRequest

@Suppress("DEPRECATION")
@Composable fun OfflineDevicePrompt(request: OfflineAuthRequest?, onSuccess: (String) -> Unit, onCancel: (String, String) -> Unit) {
    val context = LocalContext.current
    val succeeded by rememberUpdatedState(onSuccess)
    val cancelled by rememberUpdatedState(onCancel)
    var credentialRequest by rememberSaveable { mutableStateOf<String?>(null) }
    val launcher = rememberLauncherForActivityResult(ActivityResultContracts.StartActivityForResult()) { result ->
        val id = credentialRequest
        credentialRequest = null
        if (id != null) {
            if (result.resultCode == Activity.RESULT_OK) succeeded(id)
            else cancelled(id, "أُلغي توثيق قفل الجهاز. لم يُفتح الحساب المحلي.")
        }
    }
    DisposableEffect(request?.id) {
        val cancellation = CancellationSignal()
        if (request != null) {
            try {
                val title = if (request.enrolling) "تفعيل العمل دون نت" else "فتح العمل المحلي"
                if (Build.VERSION.SDK_INT >= 30) {
                    BiometricPrompt.Builder(context).setTitle(title).setSubtitle(if (request.credentialOnly) "أكّد رمز أو نمط أو كلمة مرور قفل الجهاز" else "استخدم البصمة أو رمز قفل الجهاز")
                        .setAllowedAuthenticators(if (request.credentialOnly) BiometricManager.Authenticators.DEVICE_CREDENTIAL else BiometricManager.Authenticators.BIOMETRIC_STRONG or BiometricManager.Authenticators.DEVICE_CREDENTIAL)
                        .build().authenticate(cancellation, context.mainExecutor, object : BiometricPrompt.AuthenticationCallback() {
                            override fun onAuthenticationSucceeded(result: BiometricPrompt.AuthenticationResult) { succeeded(request.id) }
                            override fun onAuthenticationError(code: Int, message: CharSequence) { cancelled(request.id, "لم يكتمل توثيق قفل الجهاز. أعد المحاولة.") }
                        })
                } else if (credentialRequest != request.id) {
                    val intent = context.getSystemService(KeyguardManager::class.java).createConfirmDeviceCredentialIntent(title, "توثيق الوصول إلى النماذج والمسودات المحفوظة")
                    if (intent == null) cancelled(request.id, "فعّل رمزًا لقفل الجهاز أولًا.")
                    else { credentialRequest = request.id; launcher.launch(intent) }
                }
            } catch (_: Exception) { cancelled(request.id, "تعذّر تشغيل توثيق قفل الجهاز. أعد المحاولة.") }
        }
        onDispose { cancellation.cancel() }
    }
}
