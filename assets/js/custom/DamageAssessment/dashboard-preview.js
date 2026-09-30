export function filterPreviewRecords(records, filters, statuses = {}) {
    const query = (filters.query || '').normalize('NFKC').trim().toLocaleLowerCase('ar');
    return records.filter(record => {
        const searchable = [record.code, record.sectorLabel, record.governorate, record.municipality, record.date, statuses[record.status]?.label || record.status].join(' ').normalize('NFKC').toLocaleLowerCase('ar');
        return (!filters.sector || filters.sector === 'all' || record.sector === filters.sector)
            && (!filters.governorate || record.governorate === filters.governorate)
            && (!filters.municipality || record.municipality === filters.municipality)
            && (!filters.from || record.date >= filters.from)
            && (!filters.to || record.date <= filters.to)
            && (!query || searchable.includes(query));
    });
}

export function summarizePreviewRecords(records) {
    return records.reduce((summary, record) => {
        summary.total += 1;
        if (Object.hasOwn(summary, record.status) && record.status !== 'total') {
            summary[record.status] += 1;
        }
        return summary;
    }, { total: 0, completed: 0, review: 0, blocked: 0 });
}

export function paginatePreviewRecords(records, page, pageSize = 8) {
    const pages = Math.max(1, Math.ceil(records.length / pageSize));
    const current = Math.max(1, Math.min(page, pages));
    const offset = (current - 1) * pageSize;
    return { rows: records.slice(offset, offset + pageSize), page: current, pages, start: records.length ? offset + 1 : 0, end: Math.min(offset + pageSize, records.length) };
}

export function previewMapFeatures(records, statuses) {
    return records.map(record => ({
        geometry: { type: 'point', longitude: record.longitude, latitude: record.latitude },
        symbol: { type: 'simple-marker', size: 11, color: statuses[record.status].color, outline: { color: '#ffffff', width: 1.5 } },
        attributes: { ...record, statusLabel: statuses[record.status].label },
        popupTemplate: {
            title: 'موقع افتراضي: {code}',
            content: [{ type: 'fields', fieldInfos: [
                { fieldName: 'sectorLabel', label: 'القطاع' },
                { fieldName: 'municipality', label: 'البلدية' },
                { fieldName: 'date', label: 'تاريخ التقييم' },
                { fieldName: 'statusLabel', label: 'الحالة' },
            ] }],
        },
    }));
}

function loadArcgis() {
    return new Promise((resolve, reject) => {
        const loadModules = () => window.require([
            'esri/Map', 'esri/views/MapView', 'esri/layers/GraphicsLayer', 'esri/Graphic',
        ], (...modules) => resolve(modules), reject);
        if (typeof window.require === 'function') {
            loadModules();
            return;
        }
        if (!document.querySelector('[data-preview-arcgis-style]')) {
            const stylesheet = document.createElement('link');
            stylesheet.rel = 'stylesheet';
            stylesheet.href = 'https://js.arcgis.com/4.22/esri/themes/light/main.css';
            stylesheet.dataset.previewArcgisStyle = '';
            document.head.append(stylesheet);
        }
        const script = document.createElement('script');
        script.src = 'https://js.arcgis.com/4.22/';
        const timeout = setTimeout(() => {
            script.remove();
            reject(new Error('Map loading timed out'));
        }, 20000);
        script.onload = () => {
            clearTimeout(timeout);
            loadModules();
        };
        script.onerror = () => {
            clearTimeout(timeout);
            script.remove();
            reject(new Error('Map library unavailable'));
        };
        document.head.append(script);
    });
}

function initializePreview(root) {
    const data = JSON.parse(document.getElementById('preview-dashboard-data').textContent);
    const find = selector => root.querySelector(selector);
    const form = find('#preview-filters');
    const governorate = find('#preview-governorate');
    const municipality = find('#preview-municipality');
    const fromDate = find('#preview-date-from');
    const toDate = find('#preview-date-to');
    const search = find('#preview-search');
    const mapMessage = find('#preview-map-message');
    const retryMap = find('#preview-map-retry');
    const mapContainer = find('#dashboard_preview_gis_map');
    let selectedSector = 'all';
    let selectedView = 'table';
    let filteredRecords = data.records;
    let page = 1;
    let mapState = null;
    let mapPromise = null;

    function renderTable() {
        const pagination = paginatePreviewRecords(filteredRecords, page);
        page = pagination.page;
        const rows = pagination.rows.map(record => {
            const row = document.createElement('tr');
            const values = [record.code, record.sectorLabel, record.governorate, record.municipality, record.date];
            values.forEach((value, index) => {
                const cell = document.createElement(index === 0 ? 'th' : 'td');
                cell.className = index === 0 ? 'fw-bold text-gray-800 fs-7' : 'text-gray-600 fs-7';
                if (index === 0) {
                    cell.scope = 'row';
                }
                const text = document.createElement('bdi');
                text.textContent = value;
                cell.append(text);
                row.append(cell);
            });
            const statusCell = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = 'badge badge-light-' + data.statuses[record.status].tone;
            badge.textContent = data.statuses[record.status].label;
            statusCell.append(badge);
            row.append(statusCell);
            return row;
        });
        find('#preview-records').replaceChildren(...rows);
        find('#preview-page-info').textContent = `${pagination.start}–${pagination.end} من ${filteredRecords.length}`;
        find('#preview-page-previous').disabled = page === 1;
        find('#preview-page-next').disabled = page === pagination.pages;
    }

    function fitMap() {
        if (!mapState || find('#preview-map-panel').hidden || !filteredRecords.length) {
            return;
        }
        const target = { target: mapState.layer.graphics.toArray() };
        if (filteredRecords.length === 1) {
            target.zoom = 13;
        }
        mapState.view.goTo(target, { animate: false }).catch(error => {
            if (!['AbortError', 'view:goto-interrupted'].includes(error.name)) {
                mapMessage.textContent = 'تعذّر تقريب الخريطة تلقائياً؛ استخدم أدوات التكبير لاستكشاف المواقع الافتراضية.';
            }
        });
    }

    function updateMap() {
        if (!mapState) {
            return;
        }
        mapState.view.popup.close();
        mapState.layer.removeAll();
        mapState.layer.addMany(previewMapFeatures(filteredRecords, data.statuses).map(feature => new mapState.Graphic(feature)));
        mapContainer.dataset.recordCount = String(filteredRecords.length);
        mapMessage.textContent = `${filteredRecords.length} موقع افتراضي يطابق النتائج المفلترة. المواقع تقريبية لأغراض التصميم فقط.`;
        fitMap();
    }

    async function ensureMap() {
        if (mapPromise) {
            return mapPromise;
        }
        mapMessage.textContent = 'جارٍ تحميل الخريطة…';
        retryMap.hidden = true;
        mapPromise = (async () => {
            let view;
            try {
                const [Map, MapView, GraphicsLayer, Graphic] = await loadArcgis();
                const layer = new GraphicsLayer();
                view = new MapView({ container: mapContainer, map: new Map({ basemap: 'osm', layers: [layer] }), center: [34.38, 31.43], zoom: 10 });
                await view.when();
                mapState = { view, layer, Graphic };
                updateMap();
            } catch (error) {
                if (view) {
                    view.destroy();
                }
                mapPromise = null;
                mapState = null;
                mapMessage.textContent = 'تعذّر تحميل الخريطة. النتائج ما زالت متاحة في الجدول؛ تحقق من الاتصال ثم أعد المحاولة.';
                retryMap.hidden = false;
            }
        })();
        return mapPromise;
    }

    function refreshResults() {
        const invalidDates = fromDate.value && toDate.value && fromDate.value > toDate.value;
        toDate.setCustomValidity(invalidDates ? 'تاريخ البداية يجب أن يسبق تاريخ النهاية.' : '');
        toDate.setAttribute('aria-invalid', String(Boolean(invalidDates)));
        find('#preview-date-error').hidden = !invalidDates;
        if (invalidDates) {
            return;
        }
        filteredRecords = filterPreviewRecords(data.records, { sector: selectedSector, governorate: governorate.value, municipality: municipality.value, from: fromDate.value, to: toDate.value, query: search.value }, data.statuses);
        const summary = summarizePreviewRecords(filteredRecords);
        root.querySelectorAll('[data-preview-stat]').forEach(element => {
            element.textContent = String(summary[element.dataset.previewStat]);
        });
        find('#preview-result-count').textContent = `${filteredRecords.length} نتيجة`;
        find('#preview-empty').hidden = filteredRecords.length > 0;
        page = 1;
        renderTable();
        updateMap();
    }

    function refreshMunicipalities() {
        const previous = municipality.value;
        const options = [...new Set(data.records.filter(record => !governorate.value || record.governorate === governorate.value).map(record => record.municipality))];
        municipality.replaceChildren(new Option('كل البلديات', ''), ...options.map(value => new Option(value, value)));
        municipality.value = options.includes(previous) ? previous : '';
    }

    form.addEventListener('submit', event => event.preventDefault());
    governorate.addEventListener('change', () => { refreshMunicipalities(); refreshResults(); });
    [municipality, fromDate, toDate].forEach(element => element.addEventListener('change', refreshResults));
    search.addEventListener('input', refreshResults);
    root.querySelectorAll('[data-preview-sector]').forEach(tab => tab.addEventListener('shown.bs.tab', () => {
        selectedSector = tab.dataset.previewSector;
        refreshResults();
    }));
    root.querySelectorAll('[data-preview-view]').forEach(button => button.addEventListener('click', () => {
        selectedView = button.dataset.previewView;
        root.querySelectorAll('[data-preview-view]').forEach(item => {
            const selected = item === button;
            item.setAttribute('aria-pressed', String(selected));
            item.classList.toggle('active', selected);
            item.classList.toggle('btn-light-primary', selected);
            item.classList.toggle('btn-light', !selected);
        });
        find('#preview-table-panel').hidden = selectedView !== 'table';
        find('#preview-map-panel').hidden = selectedView !== 'map';
        if (selectedView === 'map') {
            if (mapState) {
                fitMap();
            } else {
                ensureMap();
            }
        }
    }));
    retryMap.addEventListener('click', ensureMap);
    find('#preview-page-previous').addEventListener('click', () => { page -= 1; renderTable(); });
    find('#preview-page-next').addEventListener('click', () => { page += 1; renderTable(); });
    form.addEventListener('reset', event => {
        event.preventDefault();
        [governorate, municipality, fromDate, toDate, search].forEach(element => { element.value = ''; });
        selectedSector = 'all';
        refreshMunicipalities();
        bootstrap.Tab.getOrCreateInstance(find('#preview-tab-overview')).show();
        refreshResults();
    });
    refreshResults();
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('damage-dashboard-preview');
    if (root) {
        initializePreview(root);
    }
}
