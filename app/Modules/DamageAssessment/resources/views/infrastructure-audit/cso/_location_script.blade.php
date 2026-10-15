<script>
    $(function () {
        const cards = Array.from(document.querySelectorAll('[data-cso-location]'));
        if (!cards.length) { return; }
        const modal = document.getElementById('cso_location_viewer');
        const mapContainer = document.getElementById('cso_location_map');
        const fallback = document.getElementById('cso_location_fallback');
        const message = document.getElementById('cso_location_status');
        const fitButton = document.getElementById('cso_location_fit');
        const previewViews = new Map();
        let modulesPromise;
        let selectedCard;
        let opener;
        let largeView;
        let activeGeometry;
        let revision = 0;

        function loadModules() {
            if (modulesPromise) { return modulesPromise; }
            modulesPromise = new Promise((resolve, reject) => {
                const timeout = setTimeout(() => reject(new Error('map-timeout')), 20000);
                const fail = error => { clearTimeout(timeout); reject(error); };
                const modules = () => window.require(['esri/Map', 'esri/views/MapView', 'esri/Graphic', 'esri/geometry/support/jsonUtils'], (...loaded) => {
                    clearTimeout(timeout);
                    resolve(loaded);
                }, fail);
                if (!document.querySelector('[data-cso-map-style]')) {
                    const style = document.createElement('link');
                    style.rel = 'stylesheet';
                    style.href = 'https://js.arcgis.com/4.22/esri/themes/light/main.css';
                    style.dataset.csoMapStyle = '';
                    document.head.append(style);
                }
                if (typeof window.require === 'function') { modules(); return; }
                const script = document.createElement('script');
                script.src = 'https://js.arcgis.com/4.22/';
                script.dataset.csoMapScript = '';
                script.onload = modules;
                script.onerror = fail;
                document.head.append(script);
            });
            return modulesPromise;
        }

        async function createView(card, container, interactive, isCurrent = () => true, registerView = () => {}) {
            const [ArcgisMap, MapView, Graphic, geometryUtils] = await loadModules();
            if (!isCurrent()) { throw new Error('view-cancelled'); }
            const geometry = geometryUtils.fromJSON(JSON.parse(card.dataset.csoLocation));
            const view = new MapView({
                container, map: new ArcgisMap({ basemap: 'satellite' }),
                center: JSON.parse(card.dataset.locationCenter), zoom: 18,
                constraints: { rotationEnabled: false, snapToZoom: false, maxZoom: 22 },
                ui: { components: interactive ? ['zoom', 'attribution'] : ['attribution'] },
                popup: { autoOpenEnabled: false },
            });
            registerView(view);
            view.graphics.add(new Graphic({ geometry, symbol: geometry.type === 'polygon'
                ? { type: 'simple-fill', color: [255, 193, 7, 0.2], outline: { color: [255, 213, 79, 1], width: 3 } }
                : { type: 'simple-marker', color: [0, 158, 247, 1], size: 12, outline: { color: 'white', width: 2 } },
            }));
            try {
                const ready = Promise.all([view.when(), view.map.basemap.loadAll()]);
                await Promise.race([ready, new Promise((_, reject) => {
                    const timer = setTimeout(() => reject(new Error('view-timeout')), 20000);
                    ready.finally(() => clearTimeout(timer)).catch(() => {});
                })]);
                if (!isCurrent()) { throw new Error('view-cancelled'); }
                await fit(view, geometry);
                return { view, geometry };
            } catch (error) {
                view.destroy();
                throw error;
            }
        }

        function fit(view, geometry) {
            return view.goTo(geometry.type === 'polygon' ? geometry.extent.expand(1.6) : { target: geometry, zoom: 19 }, { animate: false });
        }

        async function initializePreview(card) {
            if (card.dataset.locationStarted) { return; }
            card.dataset.locationStarted = 'true';
            try {
                const { view } = await createView(card, card.querySelector('.cso-location-map'), false);
                previewViews.set(card, view);
                card.dataset.locationReady = 'true';
                card.querySelector('.cso-location-status').textContent = 'حدود الموقع فوق صور الأقمار الصناعية · للعرض فقط';
            } catch {
                card.dataset.locationFailed = 'true';
                card.querySelector('.cso-location-status').textContent = 'تعذّر تحميل خريطة الأقمار الصناعية. يظهر شكل الموقع فقط؛ يمكنك فتحه في Google Maps.';
            }
        }

        const observer = new IntersectionObserver(entries => entries.forEach(entry => {
            if (entry.isIntersecting) { observer.unobserve(entry.target); initializePreview(entry.target); }
        }));
        cards.forEach(card => observer.observe(card));

        $('.cso-open-location').on('click', function () {
            selectedCard = this.closest('[data-cso-location]');
            opener = this;
            fallback.replaceChildren(selectedCard.querySelector('.cso-location-outline').cloneNode(true));
            fallback.hidden = false;
            mapContainer.hidden = true;
            message.textContent = 'جاري تحميل الخريطة…';
            fitButton.disabled = true;
            document.getElementById('cso_location_google').href = selectedCard.querySelector('a').href;
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
        modal.addEventListener('shown.bs.modal', async function () {
            const requestRevision = ++revision;
            mapContainer.hidden = false;
            try {
                const { view, geometry } = await createView(selectedCard, mapContainer, true,
                    () => requestRevision === revision, view => { largeView = view; });
                if (requestRevision !== revision) { view.destroy(); return; }
                largeView = view;
                activeGeometry = geometry;
                fallback.hidden = true;
                fitButton.disabled = false;
                message.textContent = 'يمكنك التنقل والتكبير؛ الموقع وحدوده غير قابلين للتعديل.';
            } catch {
                if (requestRevision !== revision) { return; }
                mapContainer.hidden = true;
                message.textContent = 'تعذّر تحميل الخريطة. يظهر شكل الموقع فقط؛ رابط Google Maps متاح.';
            }
        });
        fitButton.addEventListener('click', function () {
            if (largeView) { fit(largeView, activeGeometry).catch(() => {}); }
        });
        modal.addEventListener('hide.bs.modal', function () {
            revision++;
            largeView?.destroy();
            largeView = null;
            fitButton.disabled = true;
        });
        modal.addEventListener('hidden.bs.modal', function () { opener?.focus({ preventScroll: true }); });
        window.addEventListener('pagehide', function () {
            observer.disconnect();
            previewViews.forEach(view => view.destroy());
            largeView?.destroy();
        });
    });
</script>
