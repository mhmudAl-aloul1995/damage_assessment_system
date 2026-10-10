param([string]$BaseUrl = '', [switch]$Verify, [switch]$CompileDeviceTests)
$ErrorActionPreference = 'Stop'
$repository = Split-Path -Parent $PSScriptRoot
$toolchain = Join-Path $repository 'tmp/mobile-toolchain'
if (-not $env:JAVA_HOME) {
    $javaDirectory = Get-ChildItem (Join-Path $toolchain 'java') -Directory -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($javaDirectory) { $env:JAVA_HOME = $javaDirectory.FullName }
}
if (-not $env:JAVA_HOME) { throw 'Set JAVA_HOME to JDK 17 or 21.' }
$env:PATH = (Join-Path $env:JAVA_HOME 'bin') + ';' + $env:PATH
if (-not $env:ANDROID_HOME) { $env:ANDROID_HOME = Join-Path $toolchain 'android-sdk' }
$env:ANDROID_SDK_ROOT = $env:ANDROID_HOME
$gradleExecutable = Join-Path $toolchain 'gradle/gradle-8.11.1/bin/gradle.bat'
if (Test-Path $gradleExecutable) { $env:GRADLE_USER_HOME = Join-Path $toolchain 'gradle-cache' }
else { $gradleExecutable = Join-Path $PSScriptRoot 'gradlew.bat' }
$buildArguments = @('assembleDebug', '--no-daemon', '--console=plain', '--max-workers=2')
if ($Verify) { $buildArguments += @('testDebugUnitTest', 'lintDebug') }
if ($CompileDeviceTests) { $buildArguments += 'assembleDebugAndroidTest' }
if ($BaseUrl) { $buildArguments += "-PphcBaseUrl=$BaseUrl" }
Push-Location $PSScriptRoot
try {
    & $gradleExecutable @buildArguments
    if ($LASTEXITCODE -ne 0) { throw 'Native Android build failed; inspect Gradle output.' }
    Write-Output (Join-Path $PSScriptRoot 'app/build/outputs/apk/debug/app-debug.apk')
} finally { Pop-Location }
