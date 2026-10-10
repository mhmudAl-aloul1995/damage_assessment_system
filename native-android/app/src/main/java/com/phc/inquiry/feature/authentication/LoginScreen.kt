package com.phc.inquiry.feature.authentication

import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.testTag
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import com.phc.inquiry.R

@Composable
fun LoginScreen(state: AuthenticationState, usesHttp: Boolean, httpReady: Boolean, onLogin: (String, String) -> Unit) {
    var email by rememberSaveable { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var visible by remember { mutableStateOf(false) }
    Box(Modifier.fillMaxSize().safeDrawingPadding().imePadding(), contentAlignment = Alignment.TopCenter) {
        Column(Modifier.widthIn(max = 520.dp).fillMaxWidth().verticalScroll(rememberScrollState()).padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
            Image(painterResource(R.drawable.phc_logo), "شعار مجلس الإسكان الفلسطيني", Modifier.size(84.dp).align(Alignment.CenterHorizontally))
            Text("مجلس الإسكان الفلسطيني", style = MaterialTheme.typography.titleLarge)
            Text("استعلم بثقة ووضوح", style = MaterialTheme.typography.headlineMedium)
            Text("المباني والوحدات السكنية\nمن سجلات حصر الأضرار المعتمدة في المنظومة.", color = MaterialTheme.colorScheme.onSurfaceVariant)
            if (usesHttp) {
                Card(colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.tertiaryContainer)) {
                    Column(Modifier.padding(16.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Text("اتصال HTTP غير مشفّر", style = MaterialTheme.typography.titleSmall)
                        Text(if (httpReady) "يمكنك تسجيل الدخول بحساب المنظومة. كلمة المرور والبيانات تنتقل عبر اتصال غير مشفّر." else "اتصال HTTP غير مسموح في هذه النسخة أو لهذا السيرفر.")
                    }
                }
            }
            OutlinedTextField(email, { email = it }, Modifier.fillMaxWidth().testTag("email"), label = { Text("البريد الإلكتروني") }, singleLine = true, enabled = !state.busy && (!usesHttp || httpReady), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email))
            OutlinedTextField(password, { password = it }, Modifier.fillMaxWidth().testTag("password"), label = { Text("كلمة المرور") }, singleLine = true, enabled = !state.busy && (!usesHttp || httpReady), keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password), visualTransformation = if (visible) VisualTransformation.None else PasswordVisualTransformation(), trailingIcon = { TextButton(onClick = { visible = !visible }) { Text(if (visible) "إخفاء" else "إظهار") } })
            state.message?.let { Text(it, color = MaterialTheme.colorScheme.error, modifier = Modifier.testTag("auth-error")) }
            Button(onClick = { onLogin(email, password); password = "" }, Modifier.fillMaxWidth().heightIn(min = 52.dp).testTag("login"), enabled = !state.busy && email.isNotBlank() && password.isNotBlank() && (!usesHttp || httpReady)) {
                if (state.busy) CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                else Text("تسجيل الدخول")
            }
            Text("للاستعلام فقط · الوصول حسب صلاحيات حسابك", style = MaterialTheme.typography.bodySmall)
        }
    }
}
