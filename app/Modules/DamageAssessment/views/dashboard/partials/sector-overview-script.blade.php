<script>
(function () {
    'use strict';
    const root = document.getElementById('sector-overview');
    const initial = JSON.parse(document.getElementById('sector-overview-data').textContent);
    const labels = initial.labels;
    const number = new Intl.NumberFormat(initial.locale);
    const form = document.getElementById('sector-overview-filters');
    const status = document.getElementById('sector-data-status');
    const error = document.getElementById('sector-data-error');
    const content = document.getElementById('sector-data-content');
    const mapStatus = document.getElementById('sector-map-status');
    const extentButton = document.getElementById('sector-map-extent');
    const colors = {
        fully_damaged: '#dc3545', partially_damaged: '#f59e0b', committee_review: '#8b5cf6',
        no_damage: '#10b981', unclassified: '#94a3b8', destroyed: '#dc3545',
        severe: '#f97316', moderate: '#f59e0b', minor: '#3b82f6',
        not_completed: '#94a3b8', pending: '#f59e0b', in_review: '#3b82f6', approved: '#10b981'
    };
    let version = 0, controller, sdkPromise, mapPromise, mapContext;
    let currentStatistics = initial.statistics;
    let currentParameters = new URLSearchParams(new FormData(form));

    function element(tag, className, text) {
        const item = document.createElement(tag);
        item.className = className || '';
        if (text !== undefined) item.textContent = text;
        return item;
    }
    function dot(color) {
        const item = element('span', 'sector-overview-legend-dot');
        item.style.backgroundColor = color;
        item.setAttribute('aria-hidden', 'true');
        return item;
    }
    function renderCharts(data) {
        root.querySelectorAll('[data-metric]').forEach(item => { item.textContent = number.format(data.summary[item.dataset.metric]); });
        const total = data.summary.total;
        const damageChart = document.getElementById('sector-damage-chart');
        const progressChart = document.getElementById('sector-progress-chart');
        const mapLegend = document.getElementById('sector-map-legend');
        damageChart.replaceChildren(); progressChart.replaceChildren(); mapLegend.replaceChildren();
        Object.keys(data.damage).forEach(key => {
            const item = element('span', 'd-inline-flex align-items-center gap-2', labels.damage[key]);
            item.prepend(dot(colors[key])); mapLegend.append(item);
        });
        if (!total) {
            damageChart.append(element('p', 'text-muted py-10 mb-0', labels.no_data));
            progressChart.append(element('p', 'text-muted py-10 mb-0 text-center', labels.no_data));
            return;
        }
        let angle = 0;
        const segments = Object.entries(data.damage).map(([key, count]) => {
            const start = angle; angle += count / total * 360;
            return `${colors[key]} ${start}deg ${angle}deg`;
        });
        const donut = element('div', 'sector-overview-donut');
        donut.style.background = `conic-gradient(${segments.join(',')})`;
        donut.setAttribute('aria-hidden', 'true');
        const center = element('div', 'sector-overview-donut-center');
        center.append(element('strong', 'fs-2x text-gray-900', number.format(total)), element('span', 'text-muted fs-7', labels.total));
        donut.append(center);
        const legend = element('ul', 'list-unstyled m-0 flex-grow-1');
        Object.entries(data.damage).forEach(([key, count]) => {
            const row = element('li', 'd-flex align-items-center gap-2 mb-3');
            row.append(dot(colors[key]), element('span', 'flex-grow-1', labels.damage[key]), element('strong', '', number.format(count)));
            legend.append(row);
        });
        damageChart.append(donut, legend);
        const rate = Math.round(data.summary.completed / total * 100);
        const heading = element('div', 'd-flex justify-content-between mb-5');
        heading.append(element('span', 'text-muted', labels.completion_rate), element('strong', 'text-success fs-4', number.format(rate) + '%'));
        progressChart.append(heading);
        Object.entries(data.progress).forEach(([key, count]) => {
            const row = element('div', 'mb-4');
            const heading = element('div', 'd-flex justify-content-between mb-2');
            heading.append(element('span', 'fs-7', labels.progress[key]), element('strong', 'fs-7', number.format(count)));
            const track = element('div', 'sector-overview-bar');
            const fill = element('div', 'sector-overview-bar-fill');
            fill.style.width = `${count / total * 100}%`; fill.style.backgroundColor = colors[key];
            track.setAttribute('aria-hidden', 'true'); track.append(fill);
            row.append(heading, track); progressChart.append(row);
        });
    }
    function loadSdk() {
        if (sdkPromise) return sdkPromise;
        sdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://js.arcgis.com/4.22/';
            const fail = () => {
                clearTimeout(timeout); script.onload = null; script.onerror = null; script.remove(); reject(new Error('Map SDK unavailable'));
            };
            const timeout = setTimeout(fail, 30000);
            script.onerror = fail;
            script.onload = () => { clearTimeout(timeout); resolve(); };
            document.head.append(script);
        }).catch(reason => { sdkPromise = null; throw reason; });
        return sdkPromise;
    }
    function getMap() {
        if (mapPromise) return mapPromise;
        mapPromise = loadSdk().then(() => new Promise((resolve, reject) => {
                window.require(['esri/Map', 'esri/views/MapView', 'esri/layers/GraphicsLayer', 'esri/Graphic', 'esri/geometry/support/jsonUtils', 'esri/widgets/BasemapToggle'],
                    (Map, MapView, GraphicsLayer, Graphic, geometryUtils, BasemapToggle) => {
                        const layer = new GraphicsLayer();
                        const view = new MapView({ container: 'sector-map', map: new Map({ basemap: 'osm', layers: [layer] }), center: [34.44, 31.42], zoom: 10 });
                        const timeout = setTimeout(() => { view.destroy(); reject(new Error('Map loading timed out')); }, 30000);
                        view.ui.add(new BasemapToggle({ view, nextBasemap: 'satellite' }), 'top-left');
                        view.when(() => {
                            clearTimeout(timeout); mapContext = { layer, view, Graphic, geometryUtils }; resolve(mapContext);
                        }, reason => { clearTimeout(timeout); view.destroy(); reject(reason); });
                    }, reject);
        })).catch(reason => { mapPromise = null; throw reason; });
        return mapPromise;
    }
    async function json(url, signal) {
        const response = await fetch(url, { signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        if (!response.ok) throw new Error('Data unavailable');
        return response.json();
    }
    function showMapError() {
        mapStatus.replaceChildren(element('span', '', labels.map_error));
        const retry = element('button', 'btn btn-sm btn-light-primary ms-3', labels.retry);
        retry.type = 'button';
        retry.addEventListener('click', () => loadMap(currentParameters, version, controller.signal));
        mapStatus.append(retry);
    }
    async function loadMap(parameters, requestVersion, signal) {
        mapStatus.textContent = labels.loading_map; extentButton.disabled = true;
        let scanned = 0, mapped = 0;
        try {
            const context = await getMap();
            if (requestVersion !== version || signal.aborted) return;
            context.layer.removeAll();
            let cursor = 0;
            do {
                const query = new URLSearchParams(parameters); query.set('after_id', cursor);
                const data = await json(root.dataset.mapUrl + '?' + query, signal);
                if (requestVersion !== version) return;
                const graphics = [];
                data.features.forEach(feature => {
                    const geometry = context.geometryUtils.fromJSON(feature.geometry);
                    if (!geometry) return;
                    const key = feature.attributes.damage_status, color = colors[key];
                    let symbol;
                    if (geometry.type === 'polygon') symbol = { type: 'simple-fill', color: color + '80', outline: { color, width: 1 } };
                    else if (geometry.type === 'polyline') symbol = { type: 'simple-line', color, width: 3 };
                    else symbol = { type: 'simple-marker', color, size: 8, outline: { color: '#fff', width: 1 } };
                    graphics.push(new context.Graphic({
                        geometry, symbol, attributes: { ...feature.attributes, damage_label: labels.damage[key] },
                        popupTemplate: {
                            title: labels.objectid + ': {objectid}',
                            content: [{ type: 'fields', fieldInfos: [
                                { fieldName: 'municipality', label: labels.municipality },
                                { fieldName: 'neighborhood', label: labels.neighborhood },
                                { fieldName: 'damage_label', label: labels.damage_status }
                            ] }]
                        }
                    }));
                });
                context.layer.addMany(graphics); mapped += graphics.length; scanned += data.scanned; cursor = data.next_cursor;
                mapStatus.textContent = `${labels.loading_map} ${number.format(scanned)} / ${number.format(currentStatistics.summary.total)}`;
            } while (cursor !== null);
            mapStatus.textContent = currentStatistics.summary.total === 0 ? labels.no_data
                : `${labels.map_count}: ${number.format(mapped)} · ${labels.missing_locations}: ${number.format(scanned - mapped)}`;
            extentButton.disabled = mapped === 0;
            if (mapped) zoomAll();
        } catch (reason) {
            if (reason.name !== 'AbortError' && requestVersion === version) {
                if (mapContext) mapContext.layer.removeAll();
                showMapError();
            }
        }
    }
    function zoomAll() {
        if (mapContext && mapContext.layer.graphics.length) {
            const graphics = mapContext.layer.graphics.toArray(), first = graphics[0].geometry;
            const samePoint = first.type === 'point' && graphics.every(graphic => graphic.geometry.type === 'point' && graphic.geometry.x === first.x && graphic.geometry.y === first.y);
            mapContext.view.goTo(samePoint ? { target: first, zoom: 15 } : graphics).catch(() => {});
        }
    }
    async function update() {
        const requestVersion = ++version;
        if (controller) controller.abort();
        controller = new AbortController();
        const signal = controller.signal, parameters = new URLSearchParams(new FormData(form));
        content.setAttribute('aria-busy', 'true'); status.textContent = labels.loading; error.classList.add('d-none');
        try {
            const data = await json(root.dataset.statsUrl + '?' + parameters, signal);
            if (requestVersion !== version) return;
            currentStatistics = data; currentParameters = parameters; renderCharts(data);
            const neighborhoods = form.elements.neighborhood, selected = neighborhoods.value;
            neighborhoods.replaceChildren(new Option(labels.all, ''), ...data.neighborhoods.map(value => new Option(value, value)));
            neighborhoods.value = selected; content.classList.remove('d-none');
            const url = new URL(window.location.href);
            ['municipality', 'neighborhood', 'damage_status', 'after_id'].forEach(key => url.searchParams.delete(key));
            parameters.forEach((value, key) => { if (value) url.searchParams.set(key, value); });
            window.history.replaceState({}, '', url); status.textContent = '';
            loadMap(parameters, requestVersion, signal);
        } catch (reason) {
            if (reason.name !== 'AbortError' && requestVersion === version) {
                error.textContent = labels.data_error; error.classList.remove('d-none'); content.classList.add('d-none'); status.textContent = '';
            }
        } finally {
            if (requestVersion === version) content.setAttribute('aria-busy', 'false');
        }
    }
    form.addEventListener('submit', event => { event.preventDefault(); update(); });
    form.elements.municipality.addEventListener('change', () => { form.elements.neighborhood.value = ''; update(); });
    document.getElementById('sector-reset').addEventListener('click', () => {
        ['municipality', 'neighborhood', 'damage_status'].forEach(key => { form.elements[key].value = ''; }); update();
    });
    extentButton.addEventListener('click', zoomAll);
    renderCharts(initial.statistics); controller = new AbortController(); loadMap(currentParameters, version, controller.signal);
}());
</script>
