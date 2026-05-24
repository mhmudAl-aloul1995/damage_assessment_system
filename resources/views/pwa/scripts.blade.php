<button
    id="pwa-install-btn"
    type="button"
    class="btn btn-primary shadow"
    style="display: none; position: fixed; inset-inline-end: 20px; bottom: 20px; z-index: 1100;"
>
    {{ app()->getLocale() === 'ar' ? 'تثبيت التطبيق' : 'Install App' }}
</button>

<script>
    window.PHC_PWA_URLS = {
        base: @json(app_path_url('/')),
        serviceWorker: @json(app_path_url('/sw.js')),
        manifest: @json(app_path_url('/manifest.webmanifest')),
        installScript: @json(app_path_url('/pwa-install.js')),
        backgroundSync: @json(app_path_url('/background-sync.js')),
        offline: @json(app_path_url('/offline.html')),
        login: @json(app_path_url('/login')),
    };
</script>
<script src="{{ app_path_url('/pwa-install.js') }}"></script>
<script src="{{ app_path_url('/background-sync.js') }}"></script>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', async () => {
            if (!window.isSecureContext) {
                console.warn('[PHC PWA] Offline mode requires HTTPS on mobile devices.');

                return;
            }

            try {
                const registration = await navigator.serviceWorker.register(window.PHC_PWA_URLS.serviceWorker, {
                    scope: window.PHC_PWA_URLS.base,
                    updateViaCache: 'none',
                });

                await registration.update();
            } catch (error) {
                console.error('Service worker registration failed:', error);
            }
        });
    }
</script>
