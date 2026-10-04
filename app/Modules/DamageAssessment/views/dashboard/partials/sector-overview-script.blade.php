<script>
(function () {
    'use strict';
    const root = document.getElementById('sector-overview');
    const initial = JSON.parse(document.getElementById('sector-overview-data').textContent);
    const labels = initial.labels, number = new Intl.NumberFormat(initial.locale);
    const form = document.getElementById('sector-overview-filters');
    const status = document.getElementById('sector-data-status'), error = document.getElementById('sector-data-error');
    const content = document.getElementById('sector-data-content'), mapStatus = document.getElementById('sector-map-status');
    const extentButton = document.getElementById('sector-map-extent'), extentFilter = document.getElementById('sector-extent-filter');
    const mapMode = document.getElementById('sector-map-mode');
    const colors = { fully_damaged: '#dc3545', partially_damaged: '#f59e0b', committee_review: '#8b5cf6', no_damage: '#059669', unclassified: '#64748b', destroyed: '#dc3545', severe: '#f97316', moderate: '#f59e0b', minor: '#3b82f6', mixed: '#db2777' };
    const auditColors = { pending: '#64748b', assigned: '#0284c7', assigned_engineer: '#0284c7', assigned_lawyer: '#6366f1', accepted: '#2563eb', accepted_engineer: '#2563eb', accepted_lawyer: '#7c3aed', needs_action: '#d97706', rejected: '#dc2626', team_approved: '#0d9488', undp_approved: '#059669', approved: '#059669', unclassified: '#475569', not_completed: '#94a3b8', mixed: '#db2777' };
    const bounds = ['west', 'south', 'east', 'north'];
    let version = 0, controller, sdkPromise, mapPromise, mapContext, mapLoading = false, extentTimer;
    let cachedFeatures = [], currentStatistics = initial.statistics, currentParameters = parameters();
    let recordsParameters, recordsController, recordsVersion = 0, recordPage = 1, recordLastPage = 1;
    const recordsModal = document.getElementById('sector-records-modal');
    const recordsBody = document.getElementById('sector-records-body'), recordsStatus = document.getElementById('sector-records-status');
    const previous = document.getElementById('sector-records-previous'), next = document.getElementById('sector-records-next');

    function parameters() {
        const result = new URLSearchParams(new FormData(form));
        for (const [key, value] of Array.from(result.entries())) if (!value) result.delete(key);
        return result;
    }
    function element(tag, className, text) {
        const item = document.createElement(tag); item.className = className || '';
        if (text !== undefined) item.textContent = text;
        return item;
    }
    function dot(color) {
        const item = element('span', 'sector-overview-legend-dot'); item.style.backgroundColor = color;
        item.setAttribute('aria-hidden', 'true'); return item;
    }
    function polygonColor(color) {
        return [parseInt(color.slice(1, 3), 16), parseInt(color.slice(3, 5), 16), parseInt(color.slice(5, 7), 16), 0.5];
    }
    function chartRow(label, count, denominator, color, filters) {
        const row = element(filters ? 'button' : 'div', 'sector-chart-link');
        if (filters) { row.type = 'button'; row.disabled = count === 0; row.addEventListener('click', () => openRecords(filters, label)); row.setAttribute('aria-label', `${label}: ${number.format(count)} — ${labels.view_records}`); }
        const heading = element('span', 'd-flex align-items-center gap-2 mb-2');
        heading.append(dot(color), element('span', 'flex-grow-1', label), element('strong', '', number.format(count)));
        const track = element('div', 'sector-overview-bar'), fill = element('div', 'sector-overview-bar-fill');
        fill.style.width = `${Math.min(100, count / Math.max(1, denominator) * 100)}%`; fill.style.backgroundColor = color;
        track.setAttribute('aria-hidden', 'true'); track.append(fill); row.append(heading, track); return row;
    }
    function renderLegend() {
        const legend = document.getElementById('sector-map-legend'); legend.replaceChildren();
        const audit = mapMode.value === 'audit';
        const keys = audit ? [...Object.keys(currentStatistics.audit), 'not_completed'] : Object.keys(currentStatistics.damage);
        if (root.dataset.sector === 'housing-units') keys.push('mixed');
        keys.forEach(key => {
            const label = key === 'mixed' ? labels.mixed : (audit ? labels.audit[key] : labels.damage[key]);
            const count = key === 'not_completed' ? currentStatistics.fieldwork.not_completed : (audit ? currentStatistics.audit[key] : currentStatistics.damage[key]);
            const row = element('span', 'd-inline-flex align-items-center gap-2', label + (count === undefined ? '' : ': ' + number.format(count)));
            row.prepend(dot((audit ? auditColors : colors)[key])); legend.append(row);
        });
    }
    function renderCharts(data) {
        root.querySelectorAll('[data-metric]').forEach(item => { item.textContent = number.format(data.summary[item.dataset.metric]); });
        root.querySelectorAll('[data-drill-metric]').forEach(item => { item.disabled = data.summary[item.dataset.drillMetric] === 0; });
        const field = document.getElementById('sector-fieldwork-chart'), audit = document.getElementById('sector-progress-chart');
        const damage = document.getElementById('sector-damage-chart'), specific = document.getElementById('sector-specific-chart');
        [field, audit, damage, specific].forEach(item => item.replaceChildren());
        const rate = data.summary.total ? Math.round(data.summary.completed / data.summary.total * 100) : 0;
        const heading = element('div', 'd-flex justify-content-between align-items-center mb-3');
        heading.append(element('span', 'text-muted fs-7', labels.completion_rate), element('strong', 'fs-2 text-success', number.format(rate) + '%')); field.append(heading);
        Object.entries(data.fieldwork).forEach(([key, count]) => field.append(chartRow(labels.fieldwork[key], count, data.summary.total, key === 'completed' ? '#059669' : '#94a3b8', { field_completion: key })));
        const auditTotal = Object.values(data.audit).reduce((sum, count) => sum + count, 0);
        if (!auditTotal) audit.append(element('p', 'text-muted py-3', labels.no_data));
        else Object.entries(data.audit).forEach(([key, count]) => audit.append(chartRow(labels.audit[key], count, auditTotal, auditColors[key], { audit_status: key, field_completion: 'completed' })));
        if (!data.summary.total) damage.append(element('p', 'text-muted py-3', labels.no_data));
        else Object.entries(data.damage).forEach(([key, count]) => damage.append(chartRow(labels.damage[key], count, data.summary.total, colors[key], { damage_status: key })));
        document.getElementById('sector-specific-title').textContent = data.chart.title;
        document.getElementById('sector-specific-note').textContent = data.chart.note;
        if (!data.chart.rows.length) specific.append(element('p', 'text-muted py-3', labels.no_data));
        else data.chart.rows.forEach(row => specific.append(chartRow(row.label, row.count, data.summary.total, '#2563eb', row.filter)));
        const calculated = new Date(data.calculated_at);
        document.getElementById('sector-calculated-at').textContent = labels.calculated_at + ': ' + calculated.toLocaleTimeString(initial.locale, {hour:'2-digit',minute:'2-digit'});
        renderLegend();
    }
    async function json(url, signal) {
        const response = await fetch(url, {signal, credentials:'same-origin', headers:{Accept:'application/json'}});
        if (!response.ok) throw new Error('Data unavailable'); return response.json();
    }
    function openRecords(filters, title) {
        recordsParameters = new URLSearchParams(currentParameters); recordsParameters.delete('after_id'); recordsParameters.delete('page');
        Object.entries(filters).forEach(([key, value]) => recordsParameters.set(key, value));
        const descriptions = [title];
        ['municipality', 'neighborhood', 'damage_status', 'audit_status', 'field_completion'].forEach(key => {
            const value = recordsParameters.get(key); if (!value) return;
            const label = key === 'damage_status' ? labels.damage[value] : key === 'audit_status' ? labels.audit[value] : key === 'field_completion' ? labels.fieldwork[value] : value;
            if (label && !descriptions.includes(label)) descriptions.push(label);
        });
        if (recordsParameters.has('west')) descriptions.push(labels.extent_filter);
        document.getElementById('sector-records-scope').textContent = descriptions.join(' · ');
        bootstrap.Modal.getOrCreateInstance(recordsModal).show(); loadRecords(1);
    }
    async function loadRecords(page) {
        const requestVersion = ++recordsVersion;
        if (recordsController) recordsController.abort(); recordsController = new AbortController();
        recordsBody.replaceChildren(); recordsStatus.textContent = labels.loading; previous.disabled = next.disabled = true;
        document.getElementById('sector-records-count').textContent = '';
        const query = new URLSearchParams(recordsParameters); query.set('page', page);
        try {
            const data = await json(root.dataset.recordsUrl + '?' + query, recordsController.signal);
            if (requestVersion !== recordsVersion) return;
            data.data.forEach(record => {
                const row = element('tr');
                [record.objectid ?? record.record_id, record.municipality || labels.not_recorded, record.neighborhood || labels.not_recorded, labels.damage[record.damage_status], labels.fieldwork[record.field_completed ? 'completed' : 'not_completed'], labels.audit[record.audit_status]].forEach(value => row.append(element('td', '', value)));
                recordsBody.append(row);
            });
            recordPage = data.current_page; recordLastPage = data.last_page;
            recordsStatus.textContent = data.total ? '' : labels.no_data;
            document.getElementById('sector-records-count').textContent = `${labels.total}: ${number.format(data.total)} · ${labels.page} ${number.format(recordPage)} / ${number.format(recordLastPage)}`;
            previous.disabled = recordPage <= 1; next.disabled = recordPage >= recordLastPage;
        } catch (reason) {
            if (reason.name === 'AbortError' || requestVersion !== recordsVersion) return;
            recordsStatus.replaceChildren(element('span', 'text-danger', labels.records_error));
            const retry = element('button', 'btn btn-sm btn-light-primary ms-3', labels.retry); retry.type = 'button'; retry.addEventListener('click', () => loadRecords(page)); recordsStatus.append(retry);
        }
    }
    function loadSdk() {
        if (sdkPromise) return sdkPromise;
        sdkPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script'); script.src = 'https://js.arcgis.com/4.22/';
            const fail = () => { clearTimeout(timeout); script.onload = script.onerror = null; script.remove(); reject(new Error('Map SDK unavailable')); };
            const timeout = setTimeout(fail, 30000); script.onerror = fail;
            script.onload = () => { clearTimeout(timeout); resolve(); }; document.head.append(script);
        }).catch(reason => { sdkPromise = null; throw reason; }); return sdkPromise;
    }
    function getMap() {
        if (mapPromise) return mapPromise;
        mapPromise = loadSdk().then(() => new Promise((resolve, reject) => {
            window.require(['esri/Map', 'esri/views/MapView', 'esri/layers/GraphicsLayer', 'esri/Graphic', 'esri/geometry/support/jsonUtils', 'esri/widgets/BasemapToggle', 'esri/geometry/support/webMercatorUtils'],
                (Map, MapView, GraphicsLayer, Graphic, geometryUtils, BasemapToggle, webMercatorUtils) => {
                    const layer = new GraphicsLayer(), view = new MapView({container:'sector-map', map:new Map({basemap:'osm',layers:[layer]}),center:[34.44,31.42],zoom:10});
                    const timeout = setTimeout(() => {view.destroy(); reject(new Error('Map loading timed out'));}, 30000);
                    view.ui.add(new BasemapToggle({view,nextBasemap:'satellite'}), 'top-left');
                    view.when(() => {
                        clearTimeout(timeout); mapContext = {layer,view,Graphic,geometryUtils,webMercatorUtils}; extentFilter.disabled = false;
                        view.watch('stationary', stationary => { if (stationary && extentFilter.checked && !mapLoading) scheduleExtentUpdate(); });
                        resolve(mapContext);
                    }, reason => {clearTimeout(timeout); view.destroy(); reject(reason);});
                }, reject);
        })).catch(reason => {mapPromise = null; throw reason;}); return mapPromise;
    }
    function setBounds() {
        if (!mapContext?.view.extent) return false;
        const extent = mapContext.view.extent;
        const geographic = extent.spatialReference.isWebMercator ? mapContext.webMercatorUtils.webMercatorToGeographic(extent) : extent;
        if (!geographic || (!geographic.spatialReference.isWGS84 && geographic.spatialReference.wkid !== 4326)) return false;
        const values = {west:Math.max(-180,geographic.xmin),south:Math.max(-90,geographic.ymin),east:Math.min(180,geographic.xmax),north:Math.min(90,geographic.ymax)};
        let changed = false;
        bounds.forEach(key => {const value = values[key].toFixed(6); if (form.elements[key].value !== value) changed = true; form.elements[key].value = value;});
        return changed;
    }
    function scheduleExtentUpdate() {
        clearTimeout(extentTimer);
        extentTimer = setTimeout(() => {if (extentFilter.checked && setBounds()) update();}, 650);
    }
    function clearBounds() { bounds.forEach(key => {form.elements[key].value = '';}); }
    function showMapError() {
        mapStatus.replaceChildren(element('span', '', labels.map_error));
        const retry = element('button', 'btn btn-sm btn-light-primary ms-3', labels.retry); retry.type = 'button';
        retry.addEventListener('click', () => loadMap(currentParameters, version, controller.signal)); mapStatus.append(retry);
    }
    function renderMap() {
        renderLegend();
        if (!mapContext) return;
        const audit = mapMode.value === 'audit', palette = audit ? auditColors : colors;
        const groups = new Map();
        cachedFeatures.forEach(feature => {
            const key = root.dataset.sector === 'housing-units' ? JSON.stringify([feature.attributes.parentglobalid, feature.geometry]) : feature.attributes.record_id;
            if (!groups.has(key)) groups.set(key, {feature, count:0, damage:new Set(), audits:new Set()});
            const group = groups.get(key); group.count++; group.damage.add(feature.attributes.damage_status); group.audits.add(feature.attributes.audit_status);
        });
        const graphics = [];
        groups.forEach(group => {
            const geometry = mapContext.geometryUtils.fromJSON(group.feature.geometry); if (!geometry) return;
            const values = audit ? group.audits : group.damage, key = values.size > 1 ? 'mixed' : Array.from(values)[0], color = palette[key] || '#64748b';
            const symbol = geometry.type === 'polygon' ? {type:'simple-fill',color:polygonColor(color),outline:{color,width:1}}
                : geometry.type === 'polyline' ? {type:'simple-line',color,width:3}
                : {type:'simple-marker',color,size:group.count > 1 ? 13 : 8,outline:{color:'#fff',width:1}};
            const fields = [ {fieldName:'municipality',label:labels.municipality}, {fieldName:'neighborhood',label:labels.neighborhood}, {fieldName:'damage_label',label:labels.damage_status}, {fieldName:'audit_label',label:labels.audit_chart} ];
            if (root.dataset.sector === 'housing-units') fields.push({fieldName:'unit_count',label:labels.unit_count});
            const labelFor = (values, dictionary) => values.size > 1 ? labels.mixed : dictionary[Array.from(values)[0]];
            graphics.push(new mapContext.Graphic({geometry,symbol,attributes:{...group.feature.attributes,unit_count:group.count,damage_label:labelFor(group.damage,labels.damage),audit_label:labelFor(group.audits,labels.audit)},
                popupTemplate:{title:root.dataset.sector === 'housing-units' ? labels.unit_count+': {unit_count}' : labels.objectid+': {objectid}',content:[{type:'fields',fieldInfos:fields}]}}));
        });
        mapContext.layer.removeAll(); mapContext.layer.addMany(graphics); renderLegend();
    }
    async function loadMap(parameters, requestVersion, signal) {
        mapStatus.textContent = labels.loading_map; extentButton.disabled = true; mapLoading = true;
        let scanned = 0;
        try {
            const context = await getMap(); if (requestVersion !== version || signal.aborted) return;
            cachedFeatures = []; context.layer.removeAll(); let cursor = 0;
            do {
                const query = new URLSearchParams(parameters); query.set('after_id',cursor);
                const data = await json(root.dataset.mapUrl+'?'+query,signal); if (requestVersion !== version) return;
                cachedFeatures.push(...data.features); scanned += data.scanned; cursor = data.next_cursor;
                mapStatus.textContent = `${labels.loading_map} ${number.format(scanned)} / ${number.format(currentStatistics.summary.total)}`;
            } while (cursor !== null);
            renderMap();
            mapStatus.textContent = currentStatistics.summary.total === 0 ? labels.no_data : `${labels.map_count}: ${number.format(cachedFeatures.length)} · ${labels.missing_locations}: ${number.format(scanned-cachedFeatures.length)}`;
            extentButton.disabled = cachedFeatures.length === 0 && !extentFilter.checked;
            if (parameters.has('west') && version === 0) {
                await context.view.goTo(context.geometryUtils.fromJSON({xmin:Number(parameters.get('west')),ymin:Number(parameters.get('south')),xmax:Number(parameters.get('east')),ymax:Number(parameters.get('north')),spatialReference:{wkid:4326}})).catch(() => {});
            } else if (cachedFeatures.length && !extentFilter.checked) zoomAll();
        } catch (reason) {
            if (reason.name !== 'AbortError' && requestVersion === version) {cachedFeatures=[]; if (mapContext) mapContext.layer.removeAll(); showMapError();}
        } finally {if (requestVersion === version) mapLoading = false;}
    }
    function zoomAll() {
        if (!mapContext || !mapContext.layer.graphics.length) return;
        const graphics = mapContext.layer.graphics.toArray(), first = graphics[0].geometry;
        const samePoint = first.type === 'point' && graphics.every(graphic => graphic.geometry.type === 'point' && graphic.geometry.x === first.x && graphic.geometry.y === first.y);
        mapContext.view.goTo(samePoint ? {target:first,zoom:15} : graphics).catch(() => {});
    }
    async function update() {
        const requestVersion = ++version; if (controller) controller.abort(); controller = new AbortController();
        const signal = controller.signal, query = parameters();
        content.setAttribute('aria-busy','true'); status.textContent = labels.loading; error.classList.add('d-none');
        try {
            const data = await json(root.dataset.statsUrl+'?'+query,signal); if (requestVersion !== version) return;
            currentStatistics=data; currentParameters=query; renderCharts(data);
            const neighborhoods=form.elements.neighborhood, selected=neighborhoods.value;
            neighborhoods.replaceChildren(new Option(labels.all,''),...data.neighborhoods.map(value=>new Option(value,value))); neighborhoods.value=selected;
            content.classList.remove('d-none');
            const url=new URL(window.location.href);
            ['municipality','neighborhood','damage_status','audit_status','field_completion','after_id','metric','page',...bounds].forEach(key=>url.searchParams.delete(key));
            query.forEach((value,key)=>url.searchParams.set(key,value)); window.history.replaceState({},'',url); status.textContent='';
            document.getElementById('sector-extent-note').textContent=extentFilter.checked ? labels.extent_note : labels.map_explanation;
            loadMap(query,requestVersion,signal);
        } catch (reason) {
            if (reason.name!=='AbortError' && requestVersion===version) {error.textContent=labels.data_error;error.classList.remove('d-none');content.classList.add('d-none');status.textContent='';}
        } finally {if(requestVersion===version)content.setAttribute('aria-busy','false');}
    }
    root.querySelectorAll('[data-drill-metric]').forEach(button=>button.addEventListener('click',()=>openRecords({metric:button.dataset.drillMetric},button.getAttribute('aria-label').split(' — ')[0])));
    previous.addEventListener('click',()=>loadRecords(recordPage-1)); next.addEventListener('click',()=>loadRecords(recordPage+1));
    recordsModal.addEventListener('hidden.bs.modal',()=>{recordsVersion++;if(recordsController)recordsController.abort();});
    form.addEventListener('submit',event=>{event.preventDefault();update();});
    form.elements.municipality.addEventListener('change',()=>{form.elements.neighborhood.value='';update();});
    document.getElementById('sector-reset').addEventListener('click',()=>{['municipality','neighborhood','damage_status','audit_status','field_completion'].forEach(key=>{form.elements[key].value='';});extentFilter.checked=false;clearTimeout(extentTimer);clearBounds();update();});
    mapMode.addEventListener('change',renderMap);
    extentFilter.addEventListener('change',()=>{clearTimeout(extentTimer);if(extentFilter.checked)setBounds();else clearBounds();update();});
    extentButton.addEventListener('click',()=>{if(extentFilter.checked){extentFilter.checked=false;clearBounds();update();}else zoomAll();});
    renderCharts(initial.statistics); controller=new AbortController(); loadMap(currentParameters,version,controller.signal);
}());
</script>
