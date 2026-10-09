$ErrorActionPreference = 'Stop'
$repository = Split-Path -Parent $PSScriptRoot
$toolchain = Join-Path $repository 'tmp/mobile-toolchain'

if (-not $env:JAVA_HOME) {
    $portableJava = Get-ChildItem (Join-Path $toolchain 'java') -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($portableJava) { $env:JAVA_HOME = $portableJava.FullName }
}
if (-not $env:JAVA_HOME -or -not (Test-Path (Join-Path $env:JAVA_HOME 'bin/java.exe'))) {
    throw 'Install JDK 21 and set JAVA_HOME before building Android.'
}
$env:PATH = (Join-Path $env:JAVA_HOME 'bin') + ';' + $env:PATH

$sdkDirectory = $env:ANDROID_HOME
if (-not $sdkDirectory) { $sdkDirectory = $env:ANDROID_SDK_ROOT }
if (-not $sdkDirectory -and (Test-Path (Join-Path $toolchain 'android-sdk'))) {
    $sdkDirectory = Join-Path $toolchain 'android-sdk'
}
if (-not $sdkDirectory -or -not (Test-Path $sdkDirectory)) {
    throw 'Install Android SDK 35 and set ANDROID_HOME before building Android.'
}
$env:ANDROID_HOME = $sdkDirectory
$env:ANDROID_SDK_ROOT = $sdkDirectory

$gradle = Join-Path $PSScriptRoot 'gradlew.bat'
$portableGradle = Join-Path $toolchain 'gradle/gradle-8.11.1/bin/gradle.bat'
if (Test-Path $portableGradle) {
    $gradle = $portableGradle
    $env:GRADLE_USER_HOME = Join-Path $toolchain 'gradle-cache'
}

Push-Location $PSScriptRoot
try {
    & $gradle assembleDebug testDebugUnitTest lintDebug --no-daemon --console=plain --max-workers=2
    if ($LASTEXITCODE -ne 0) { throw 'Android build or lint failed. See Gradle output above.' }
    Write-Output (Join-Path $PSScriptRoot 'app/build/outputs/apk/debug/app-debug.apk')
} finally {
    Pop-Location
}
