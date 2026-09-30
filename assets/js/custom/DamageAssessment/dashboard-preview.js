export function resolveGisField(fields, name) {
    return fields.find(field => field.name.toLowerCase() === name.toLowerCase()) || null;
}

export function buildGisWhere(fields, filters, dateFieldName, objectIdField, scopeObjectIds = null) {
    const clauses = [];
    const identifier = name => {
        if (!/^[a-z_][a-z0-9_]*$/i.test(name)) throw new Error('حقل GIS غير صالح.');
        return '"' + name + '"';
    };
    for (const [key, label] of [['governorate', 'المحافظة'], ['neighborhood', 'الحي']]) {
        if (!filters[key]) continue;
        const field = resolveGisField(fields, key);
        if (!field) throw new Error('طبقة GIS لا تدعم فلتر ' + label + ' المختار.');
        clauses.push(identifier(field.name) + " = '" + String(filters[key]).replace(/'/g, "''") + "'");
    }
    if (filters.from || filters.to) {
        const field = resolveGisField(fields, dateFieldName) || resolveGisField(fields, 'creationdate');
        if (!field || field.type !== 'date') throw new Error('طبقة GIS لا تدعم فلتر التاريخ المختار.');
        const date = value => {
            if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) throw new Error('تاريخ غير صالح.');
            const parsed = new Date(value + 'T00:00:00Z');
            if (Number.isNaN(parsed.valueOf()) || parsed.toISOString().slice(0, 10) !== value) throw new Error('تاريخ غير صالح.');
            return parsed;
        };
        if (filters.from) {
            date(filters.from);
            clauses.push(identifier(field.name) + " >= TIMESTAMP '" + filters.from + " 00:00:00'");
        }
        if (filters.to) {
            const nextDay = date(filters.to);
            nextDay.setUTCDate(nextDay.getUTCDate() + 1);
            clauses.push(identifier(field.name) + " < TIMESTAMP '" + nextDay.toISOString().slice(0, 10) + " 00:00:00'");
        }
        if (filters.from && filters.to && filters.from > filters.to) throw new Error('تاريخ البداية يجب أن يسبق تاريخ النهاية.');
    }
    if (scopeObjectIds !== null) {
        const ids = [...new Set(scopeObjectIds.filter(id => Number.isSafeInteger(id) && id >= 0))];
        if (!ids.length) return '1=0';
        const groups = [];
        for (let offset = 0; offset < ids.length; offset += 500) {
            groups.push(identifier(objectIdField) + ' IN (' + ids.slice(offset, offset + 500).join(',') + ')');
        }
        clauses.push('(' + groups.join(' OR ') + ')');
    }
    return clauses.length ? clauses.join(' AND ') : '1=1';
}

export function createDamageRenderer(geometryType, field) {
    if (!field) return null;
    const symbol = color => {
        if (geometryType === 'polygon') return {type: 'simple-fill', color: [...color, 0.55], outline: {color, width: 1}};
        if (geometryType === 'polyline') return {type: 'simple-line', color, width: 3};
        return {type: 'simple-marker', color, size: 9, outline: {color: 'white', width: 1}};
    };
    const statuses = [
        ['fully_damaged', 'ضرر كلي', [220, 53, 69]], ['destroyed', 'مدمر', [220, 53, 69]],
        ['severe', 'ضرر جسيم', [240, 100, 30]], ['partially_damaged', 'ضرر جزئي', [255, 193, 7]],
        ['partial_damage', 'ضرر جزئي', [255, 193, 7]], ['moderate', 'ضرر متوسط', [255, 193, 7]],
        ['committee_review', 'لجنة فنية', [150, 90, 220]], ['minor', 'ضرر خفيف', [40, 167, 69]],
        ['no_damage', 'لا يوجد ضرر', [40, 167, 69]], ['No_Damage', 'لا يوجد ضرر', [40, 167, 69]],
        ['no_damaged', 'لا يوجد ضرر', [40, 167, 69]],
    ];
    return {
        type: 'unique-value', field: field.name, defaultLabel: 'حالات أخرى / غير مصنف', defaultSymbol: symbol([120, 130, 140]),
        uniqueValueInfos: statuses.map(([value, label, color]) => ({value, label, symbol: symbol(color)})),
    };
}

let arcgisPromise;
function loadArcgis() {
    if (arcgisPromise) return arcgisPromise;
    arcgisPromise = new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('انتهت مهلة تحميل مكتبة GIS.')), 30000);
        const done = modules => { clearTimeout(timeout); resolve(modules); };
        const fail = error => { clearTimeout(timeout); reject(error); };
        const modules = () => window.require([
            'esri/Map', 'esri/views/MapView', 'esri/layers/FeatureLayer', 'esri/identity/IdentityManager',
            'esri/widgets/BasemapToggle', 'esri/widgets/Legend', 'esri/widgets/Expand', 'esri/widgets/ScaleBar',
            'esri/request', 'esri/geometry/Extent',
        ], (...loaded) => done(loaded), fail);
        if (!document.querySelector('[data-preview-arcgis-style]')) {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://js.arcgis.com/4.22/esri/themes/light/main.css';
            css.dataset.previewArcgisStyle = '';
            document.head.append(css);
        }
        if (typeof window.require === 'function') { modules(); return; }
        const script = document.createElement('script');
        script.src = 'https://js.arcgis.com/4.22/';
        script.onload = modules;
        script.onerror = () => { script.remove(); fail(new Error('تعذّر تحميل مكتبة GIS.')); };
        document.head.append(script);
    }).catch(error => { arcgisPromise = null; throw error; });
    return arcgisPromise;
}

async function initializeGis(root) {
    const data = JSON.parse(document.getElementById('preview-gis-data').textContent);
    const find = selector => root.querySelector(selector);
    const message = find('#preview-gis-message');
    const retry = find('#preview-gis-retry');
    const fit = find('#preview-gis-fit');
    const container = find('#dashboard_preview_gis_map');
    let selected = Object.keys(data.sectors)[0];
    let revision = 0;
    let map;
    let view;
    let legend;
    let activeExtent;
    let modules;
    const layers = new Map();
    const popupNames = ['objectid', 'building_name', 'str_name', 'governorate', 'municipalitie', 'neighborhood', 'building_damage_status', 'road_damage_level', 'field_status'];

    async function zoomToExtent(extent) {
        if (!extent) return;
        try { await view.goTo(extent.expand(1.15), {animate: false}); }
        catch (error) {
            if (!['AbortError', 'view:goto-interrupted'].includes(error.name)) message.textContent += ' استخدم أدوات التكبير لاستعراض الطبقة.';
        }
    }

    async function showSector(key) {
        selected = key;
        const currentRevision = ++revision;
        const sector = data.sectors[key];
        find('#preview-gis-title').textContent = sector.title;
        find('#preview-gis-records').href = sector.listUrl;
        root.querySelectorAll('[data-gis-sector]').forEach(button => {
            const active = button.dataset.gisSector === key;
            button.setAttribute('aria-pressed', String(active));
            button.classList.toggle('btn-primary', active);
            button.classList.toggle('btn-light', !active);
        });
        fit.disabled = true;
        retry.hidden = true;
        activeExtent = null;
        if (map) map.removeAll();
        if (view) view.popup.close();
        container.hidden = true;
        delete container.dataset.featureCount;
        message.textContent = 'جارٍ تحميل طبقة ' + sector.title + ' من GIS…';
        if (data.error || !data.token) { message.textContent = data.error || 'جلسة GIS غير متاحة؛ أعد تحميل الصفحة.'; return; }
        if (!sector.url) { message.textContent = 'رابط طبقة GIS لهذا القطاع غير مُعدّ في النظام.'; return; }
        try {
            modules = await loadArcgis();
            if (currentRevision !== revision) return;
            const [ArcgisMap, MapView, FeatureLayer, identity, BasemapToggle, Legend, Expand, ScaleBar, esriRequest, Extent] = modules;
            if (!view) {
                map = new ArcgisMap({basemap: 'satellite'});
                view = new MapView({container, map, center: [34.38, 31.43], zoom: 10});
                legend = new Legend({view});
                view.ui.add(new Expand({view, content: legend, expandTooltip: 'مفتاح الخريطة'}), 'bottom-right');
                view.ui.add(new BasemapToggle({view, nextBasemap: 'osm'}), 'top-left');
                view.ui.add(new ScaleBar({view, unit: 'metric'}), 'bottom-left');
            }
            identity.registerToken({server: sector.url.replace(/\/\d+$/, ''), token: data.token});
            if (!layers.has(key)) {
                const layer = new FeatureLayer({url: sector.url, title: sector.title, definitionExpression: '1=0', minScale: 0, maxScale: 0});
                layers.set(key, layer.load().then(() => layer).catch(error => { layers.delete(key); throw error; }));
            }
            const layer = await layers.get(key);
            if (currentRevision !== revision) return;
            if (!layer.geometryType) {
                message.textContent = 'بيانات هذا القطاع جدول GIS مرتبط بالمباني، ولا تحتوي على مواقع جغرافية مستقلة. افتح سجلات القطاع لعرض التفاصيل.';
                return;
            }
            layer.definitionExpression = buildGisWhere(layer.fields, data.filters, sector.dateField, layer.objectIdField, sector.scopeObjectIds);
            const popupFields = popupNames.map(name => resolveGisField(layer.fields, name)).filter(Boolean);
            layer.outFields = [...new Set([layer.objectIdField, ...popupFields.map(field => field.name)])];
            layer.popupTemplate = {title: sector.title, content: [{type: 'fields', fieldInfos: popupFields.map(field => ({fieldName: field.name, label: field.alias || field.name}))}]};
            const damageField = resolveGisField(layer.fields, 'building_damage_status') || resolveGisField(layer.fields, 'road_damage_level');
            const renderer = createDamageRenderer(layer.geometryType, damageField);
            if (renderer) layer.renderer = renderer;
            map.add(layer);
            legend.layerInfos = [{layer, title: sector.title}];
            container.hidden = false;
            await view.when();
            await view.whenLayerView(layer);
            const query = layer.createQuery();
            const [count, response] = await Promise.all([
                layer.queryFeatureCount(query),
                esriRequest(sector.url + '/query', {
                    query: {f: 'json', where: layer.definitionExpression, returnExtentOnly: true, outSR: 4326, token: data.token},
                    responseType: 'json', method: 'post',
                }),
            ]);
            if (currentRevision !== revision) return;
            if (response.data.error) throw new Error('تعذّر حساب نطاق طبقة GIS.');
            activeExtent = response.data.extent ? new Extent(response.data.extent) : null;
            fit.disabled = !activeExtent;
            container.dataset.featureCount = String(count);
            message.textContent = count ? count.toLocaleString('ar') + ' عنصر جغرافي من طبقة ' + sector.title + '. اضغط على العنصر لعرض تفاصيله.' : 'لا توجد عناصر جغرافية مطابقة للفلاتر المختارة.';
            await zoomToExtent(activeExtent);
        } catch (error) {
            if (currentRevision !== revision) return;
            if (map) map.removeAll();
            container.hidden = true;
            message.textContent = error.message?.startsWith('طبقة GIS') || error.message?.startsWith('تاريخ') ? error.message : 'تعذّر تحميل طبقة GIS. تحقق من الاتصال وصلاحية جلسة GIS، ثم أعد المحاولة أو حدّث الصفحة.';
            retry.hidden = false;
        }
    }
    root.querySelectorAll('[data-gis-sector]').forEach(button => button.addEventListener('click', () => showSector(button.dataset.gisSector)));
    retry.addEventListener('click', () => showSector(selected));
    fit.addEventListener('click', () => zoomToExtent(activeExtent));
    await showSector(selected);
}

if (typeof document !== 'undefined') {
    const root = document.getElementById('damage-dashboard-preview');
    if (root && document.getElementById('preview-gis-data')) initializeGis(root);
}
