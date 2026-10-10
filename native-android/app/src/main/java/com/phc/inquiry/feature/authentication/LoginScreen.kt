package com.phc.inquiry.feature.authentication

import android.util.Patterns
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardActions
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.ExperimentalComposeUiApi
import androidx.compose.ui.Modifier
import androidx.compose.ui.autofill.*
import androidx.compose.ui.focus.*
import androidx.compose.ui.layout.*
import androidx.compose.ui.platform.*
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.semantics.*
import androidx.compose.ui.text.input.*
import androidx.compose.ui.text.style.TextDirection
import androidx.compose.ui.unit.dp
import com.phc.inquiry.R
import com.phc.inquiry.core.ui.*

@OptIn(ExperimentalComposeUiApi::class)
@Composable
private fun Modifier.phcAutofill(type: AutofillType, onFill: (String) -> Unit): Modifier {
    val currentFill by rememberUpdatedState(onFill)
    val node = remember(type) { AutofillNode(autofillTypes = listOf(type), onFill = { currentFill(it) }) }
    val tree = LocalAutofillTree.current
    val autofill = LocalAutofill.current
    DisposableEffect(node) { tree += node; onDispose { tree.children.remove(node.id) } }
    return onGloballyPositioned { node.boundingBox = it.boundsInWindow() }
        .onFocusChanged { if (it.isFocused) autofill?.requestAutofillForNode(node) else autofill?.cancelAutofillForNode(node) }
}

@OptIn(ExperimentalComposeUiApi::class)
@Composable
fun LoginScreen(state: AuthenticationState, usesHttp: Boolean, httpReady: Boolean, onLogin: (String, String) -> Unit) {
    var email by rememberSaveable { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var visible by remember { mutableStateOf(false) }
    var attempted by remember { mutableStateOf(false) }
    val passwordFocus = remember { FocusRequester() }
    val focus = LocalFocusManager.current
    val keyboard = LocalSoftwareKeyboardController.current
    val enabled = !state.busy && (!usesHttp || httpReady)
    val toggleDescription = stringResource(if (visible) R.string.hide_password else R.string.show_password)
    val emailInvalid = attempted && !Patterns.EMAIL_ADDRESS.matcher(email.trim()).matches()
    val passwordInvalid = attempted && password.isEmpty()
    val submit = {
        attempted = true
        if (enabled && Patterns.EMAIL_ADDRESS.matcher(email.trim()).matches() && password.isNotEmpty()) {
            focus.clearFocus(); keyboard?.hide(); onLogin(email.trim(), password); attempted = false; password = ""
        }
    }
    BoxWithConstraints(Modifier.fillMaxSize().safeDrawingPadding().imePadding()) {
        val wide = maxWidth >= 760.dp
        Row(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(horizontal = if (wide) 48.dp else 24.dp, vertical = 32.dp),
            horizontalArrangement = Arrangement.spacedBy(48.dp, Alignment.CenterHorizontally), verticalAlignment = Alignment.CenterVertically) {
            if (wide) Column(Modifier.widthIn(max = 340.dp).weight(1f), verticalArrangement = Arrangement.spacedBy(20.dp)) {
                BrandMark(112)
                Text(stringResource(R.string.council_name), style = MaterialTheme.typography.titleLarge)
                Text(stringResource(R.string.portal_name), style = MaterialTheme.typography.headlineLarge, color = MaterialTheme.colorScheme.primary)
                Text(stringResource(R.string.dashboard_description), style = MaterialTheme.typography.bodyLarge, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
            Column(Modifier.widthIn(max = 440.dp).fillMaxWidth(), verticalArrangement = Arrangement.spacedBy(24.dp)) {
                if (!wide) Row(verticalAlignment = Alignment.CenterVertically, horizontalArrangement = Arrangement.spacedBy(16.dp)) {
                    BrandMark(72)
                    Column(verticalArrangement = Arrangement.spacedBy(4.dp)) {
                        Text(stringResource(R.string.council_name), style = MaterialTheme.typography.titleSmall)
                        Text(stringResource(R.string.portal_name), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.primary)
                    }
                }
                Column(verticalArrangement = Arrangement.spacedBy(8.dp)) {
                    Text(stringResource(R.string.login_title), style = MaterialTheme.typography.headlineMedium)
                    Text(stringResource(R.string.login_intro), style = MaterialTheme.typography.bodyMedium, color = MaterialTheme.colorScheme.onSurfaceVariant)
                }
                Surface(shape = RoundedCornerShape(20.dp), color = MaterialTheme.colorScheme.surface, border = androidx.compose.foundation.BorderStroke(1.dp, MaterialTheme.colorScheme.outlineVariant)) {
                    Column(Modifier.padding(24.dp), verticalArrangement = Arrangement.spacedBy(16.dp)) {
                        OutlinedTextField(email, { email = it }, Modifier.fillMaxWidth().testTag("email").phcAutofill(AutofillType.EmailAddress) { email = it },
                            label = { Text(stringResource(R.string.email_label)) }, placeholder = { Text(stringResource(R.string.email_hint)) },
                            singleLine = true, enabled = enabled, isError = emailInvalid,
                            textStyle = MaterialTheme.typography.bodyLarge.copy(textDirection = TextDirection.Ltr),
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email, imeAction = ImeAction.Next, autoCorrectEnabled = false),
                            keyboardActions = KeyboardActions(onNext = { passwordFocus.requestFocus() }),
                            supportingText = if (emailInvalid) { { Text(stringResource(R.string.invalid_email)) } } else null)
                        OutlinedTextField(password, { password = it }, Modifier.fillMaxWidth().testTag("password").focusRequester(passwordFocus).phcAutofill(AutofillType.Password) { password = it },
                            label = { Text(stringResource(R.string.password_label)) }, singleLine = true, enabled = enabled, isError = passwordInvalid,
                            keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password, imeAction = ImeAction.Done), keyboardActions = KeyboardActions(onDone = { submit() }),
                            visualTransformation = if (visible) VisualTransformation.None else PasswordVisualTransformation(),
                            trailingIcon = { TextButton(onClick = { visible = !visible }, modifier = Modifier.semantics {
                                contentDescription = toggleDescription
                            }) { Text(stringResource(if (visible) R.string.hide_short else R.string.show_short), style = MaterialTheme.typography.labelMedium) } },
                            supportingText = if (passwordInvalid) { { Text(stringResource(R.string.required_password)) } } else null)
                        state.message?.let { Text(it, color = MaterialTheme.colorScheme.error, style = MaterialTheme.typography.bodyMedium,
                            modifier = Modifier.testTag("auth-error").semantics { liveRegion = LiveRegionMode.Polite }) }
                        if (usesHttp) Row(horizontalArrangement = Arrangement.spacedBy(8.dp), verticalAlignment = Alignment.Top) {
                            Icon(Icons.Default.Warning, null, Modifier.size(18.dp), tint = MaterialTheme.colorScheme.secondary)
                            Text(stringResource(if (httpReady) R.string.http_notice else R.string.http_blocked), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.secondary)
                        }
                        Button(onClick = submit, modifier = Modifier.fillMaxWidth().heightIn(min = 56.dp).testTag("login"), enabled = enabled && email.isNotBlank() && password.isNotBlank(), shape = RoundedCornerShape(12.dp)) {
                            if (state.busy) CircularProgressIndicator(Modifier.size(22.dp), strokeWidth = 2.dp, color = MaterialTheme.colorScheme.onPrimary)
                            else Text(stringResource(R.string.login_title))
                        }
                    }
                }
                Text(stringResource(R.string.login_footer), style = MaterialTheme.typography.bodySmall, color = MaterialTheme.colorScheme.onSurfaceVariant)
            }
        }
    }
}

