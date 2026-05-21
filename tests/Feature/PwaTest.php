<?php

use Illuminate\Support\Facades\File;

test('login page exposes pwa metadata and service worker registration', function () {
    $response = $this->get('/login');

    $response->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('manifest.json', false)
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('pwa-install.js', false)
        ->assertSee('background-sync.js', false)
        ->assertSee("navigator.serviceWorker.register('/sw.js')", false);
});

test('manifest contains installable mobile app settings', function () {
    $manifest = json_decode(File::get(public_path('manifest.json')), true);

    expect($manifest)
        ->toHaveKey('name', 'Palestinian Housing Council')
        ->toHaveKey('short_name', 'PHC')
        ->toHaveKey('display', 'standalone')
        ->toHaveKey('start_url', '/login')
        ->toHaveKey('icons');

    expect(collect($manifest['icons'])->pluck('sizes'))->toContain('192x192', '512x512');
});

test('service worker precaches offline shell and avoids third party caching', function () {
    $serviceWorker = File::get(public_path('sw.js'));

    expect($serviceWorker)
        ->toContain("const OFFLINE_URL = '/offline.html'")
        ->toContain("cache.addAll(PRECACHE_URLS.map((url) => new Request(url, { cache: 'reload' })))")
        ->toContain('requestUrl.origin !== self.location.origin')
        ->toContain('caches.match(OFFLINE_URL)');
});

test('offline sync queues marked forms and service worker replays queued requests', function () {
    $backgroundSync = File::get(public_path('background-sync.js'));
    $serviceWorker = File::get(public_path('sw.js'));

    expect($backgroundSync)
        ->toContain("form.matches('[data-offline-sync=\"true\"]')")
        ->toContain('event.defaultPrevented')
        ->toContain('window.phcOfflineSync')
        ->toContain('await registration.sync.register(SYNC_TAG)');

    expect($serviceWorker)
        ->toContain("const SYNC_TAG = 'phc-offline-sync'")
        ->toContain("self.addEventListener('sync'")
        ->toContain('syncOfflineRequests()')
        ->toContain("credentials: 'same-origin'");
});
