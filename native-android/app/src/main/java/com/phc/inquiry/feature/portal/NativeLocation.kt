package com.phc.inquiry.feature.portal

import android.Manifest
import android.content.Context
import android.content.pm.PackageManager
import android.location.Location
import android.location.LocationListener
import android.location.LocationManager
import android.os.Bundle
import android.os.Looper
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext
import androidx.core.content.ContextCompat
import com.phc.inquiry.data.FormLocation
import kotlinx.coroutines.*

@Composable fun rememberLocationPicker(onResult: (FormLocation) -> Unit, onMessage: (String) -> Unit): () -> Unit {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val manager = remember { context.getSystemService(Context.LOCATION_SERVICE) as LocationManager }
    var listener by remember { mutableStateOf<LocationListener?>(null) }
    var timeout by remember { mutableStateOf<Job?>(null) }
    val currentResult by rememberUpdatedState(onResult)
    val currentMessage by rememberUpdatedState(onMessage)
    fun stop() { listener?.let { manager.removeUpdates(it) }; listener = null; timeout?.cancel(); timeout = null }
    fun capture() {
        stop()
        val fine = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_FINE_LOCATION) == PackageManager.PERMISSION_GRANTED
        val coarse = ContextCompat.checkSelfPermission(context, Manifest.permission.ACCESS_COARSE_LOCATION) == PackageManager.PERMISSION_GRANTED
        if(!fine && !coarse) { currentMessage("لم تُمنح صلاحية الموقع؛ يمكنك الإرسال دون موقع."); return }
        val provider = when {
            fine && manager.isProviderEnabled(LocationManager.GPS_PROVIDER) -> LocationManager.GPS_PROVIDER
            manager.isProviderEnabled(LocationManager.NETWORK_PROVIDER) -> LocationManager.NETWORK_PROVIDER
            else -> { currentMessage("فعّل خدمة الموقع ثم أعد المحاولة. الموقع اختياري."); return }
        }
        currentMessage("جارٍ تحديد الموقع…")
        val callback = object : LocationListener {
            override fun onLocationChanged(location: Location) {
                if(location.latitude.isFinite() && location.longitude.isFinite() && location.latitude in -90.0..90.0 && location.longitude in -180.0..180.0) {
                    stop(); currentResult(FormLocation(location.latitude, location.longitude, location.accuracy.toDouble()))
                }
            }
            @Deprecated("Compatibility with Android 8") override fun onStatusChanged(provider: String?, status: Int, extras: Bundle?) {}
            override fun onProviderEnabled(provider: String) {}
            override fun onProviderDisabled(provider: String) { stop(); currentMessage("خدمة الموقع معطلة.") }
        }
        listener = callback
        try {
            @Suppress("DEPRECATION")
            manager.requestSingleUpdate(provider, callback, Looper.getMainLooper())
            timeout = scope.launch { delay(30000); stop(); currentMessage("تعذّر تحديد موقع حديث. أعد المحاولة أو تابع دون موقع.") }
        } catch (_: Exception) { stop(); currentMessage("تعذّر الوصول إلى الموقع. يمكنك المتابعة دون موقع.") }
    }
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { granted ->
        if(granted.values.any { it }) capture() else currentMessage("يمكن إكمال الاستبيان دون صلاحية الموقع.")
    }
    DisposableEffect(Unit) { onDispose { stop() } }
    return {
        val available = listOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION).any { ContextCompat.checkSelfPermission(context, it) == PackageManager.PERMISSION_GRANTED }
        if(available) capture() else permission.launch(arrayOf(Manifest.permission.ACCESS_FINE_LOCATION, Manifest.permission.ACCESS_COARSE_LOCATION))
    }
}

