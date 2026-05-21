<button
    id="pwa-install-btn"
    type="button"
    class="btn btn-primary shadow"
    style="display: none; position: fixed; inset-inline-end: 20px; bottom: 20px; z-index: 1100;"
>
    {{ app()->getLocale() === 'ar' ? 'تثبيت التطبيق' : 'Install App' }}
</button>

<script src="{{ asset('pwa-install.js') }}"></script>
<script src="{{ asset('background-sync.js') }}"></script>
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js').catch((error) => {
                console.error('Service worker registration failed:', error);
            });
        });
    }
</script>
