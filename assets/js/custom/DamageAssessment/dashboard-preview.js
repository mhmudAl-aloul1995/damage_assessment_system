export function resolveGisField(fields, name) {
    return fields.find(field => field.name.toLowerCase() === name.toLowerCase()) || null;
}

export function buildGisWhere(fields, filters, dateFieldName, objectIdField, scopeObjectIds = null) {
    const clauses = [];
    const identifier = name => {
        if (!/^[a-z_][a-z0-9_]*$/i.test(name)) throw new Error('حقل GIS غير صالح.');
        return '"' + name + '"';
    };
    const locationFields = {
        governorate: ['governorate', 'unit_governorate'],
        municipalitie: ['municipalitie', 'unit_municipalitie'],
        neighborhood: ['neighborhood', 'unit_neighborhood'],
    };
    for (const [key, label] of [['governorate', 'المحافظة'], ['municipalitie', 'البلدية'], ['neighborhood', 'الحي']]) {
        if (!filters[key]) continue;
        const field = locationFields[key].map(name => resolveGisField(fields, name)).find(Boolean);
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

function hexToRgb(color) {
    const value = String(color || '').trim();
    const hex = value.match(/^#?([0-9a-f]{6})$/i);
    if (hex) {
        const number = Number.parseInt(hex[1], 16);
        return [(number >> 16) & 255, (number >> 8) & 255, number & 255];
    }
    const rgb = value.match(/^rgb\((\d+),\s*(\d+),\s*(\d+)\)$/i);
    return rgb ? [Number(rgb[1]), Number(rgb[2]), Number(rgb[3])] : [49, 95, 114];
}

function rgbToCss(rgb) {
    return 'rgb(' + rgb.map(value => Math.max(0, Math.min(255, Math.round(value)))).join(', ') + ')';
}

function mixWithWhite(color, amount) {
    const rgb = hexToRgb(color);
    return rgbToCss(rgb.map(value => value + ((255 - value) * amount)));
}

function drawRoundRect(context, x, y, width, height, radius, fillStyle, strokeStyle = null) {
    context.beginPath();
    context.moveTo(x + radius, y);
    context.arcTo(x + width, y, x + width, y + height, radius);
    context.arcTo(x + width, y + height, x, y + height, radius);
    context.arcTo(x, y + height, x, y, radius);
    context.arcTo(x, y, x + width, y, radius);
    context.closePath();
    context.fillStyle = fillStyle;
    context.fill();
    if (strokeStyle) {
        context.strokeStyle = strokeStyle;
        context.lineWidth = 2;
        context.stroke();
    }
}

function wrapRtlText(context, text, x, y, maxWidth, lineHeight, maxLines = 2) {
    const words = String(text || '').trim().split(/\s+/).filter(Boolean);
    if (!words.length) return y;
    let line = '';
    let lines = 0;
    for (let index = 0; index < words.length; index += 1) {
        const testLine = line ? line + ' ' + words[index] : words[index];
        if (context.measureText(testLine).width > maxWidth && line) {
            lines += 1;
            context.fillText(lines === maxLines && index < words.length ? line + '…' : line, x, y);
            y += lineHeight;
            line = words[index];
            if (lines >= maxLines) return y;
        } else {
            line = testLine;
        }
    }
    if (line && lines < maxLines) {
        context.fillText(line, x, y);
        y += lineHeight;
    }
    return y;
}

function pageFilterSummary(root) {
    const selectedText = selector => {
        const select = root.querySelector(selector);
        return select?.selectedOptions?.[0]?.textContent?.trim() || '';
    };
    const inputValue = selector => root.querySelector(selector)?.value || '';
    const from = inputValue('#cards-from-date');
    const to = inputValue('#cards-to-date');
    return [
        'المحافظة: ' + (selectedText('#cards-governorate') || 'كل المحافظات'),
        'البلدية: ' + (selectedText('#cards-municipality') || 'كل البلديات'),
        'الحي: ' + (selectedText('#cards-neighborhood') || 'كل الأحياء'),
        'الفترة: ' + (from || to ? [from || 'البداية', to || 'اليوم'].join(' إلى ') : 'كل الفترات'),
    ];
}

function collectExportCards(root) {
    return [...root.querySelectorAll('[data-dashboard-card]')].map(wrapper => {
        const article = wrapper.querySelector('.preview-summary-card');
        const itemNodes = [...wrapper.querySelectorAll('[data-dashboard-item]')];
        return {
            color: article?.style.getPropertyValue('--preview-card-color') || '#315f72',
            title: wrapper.querySelector('h3')?.textContent?.trim() || '',
            total: wrapper.querySelector('[data-dashboard-total]')?.textContent?.trim() || '0',
            subtitle: wrapper.querySelector('.card-body > p')?.textContent?.trim() || '',
            items: itemNodes.map(item => ({
                title: item.querySelector('.flex-grow-1')?.textContent?.trim() || '',
                value: item.querySelector('[data-dashboard-item-value]')?.textContent?.trim() || '',
            })).filter(item => item.title || item.value),
        };
    });
}

function exportFilename(now) {
    return 'damage-assessment-dashboard-cards-' + now.toISOString().slice(0, 10) + '.png';
}

function buildDashboardCardsImage(root, now = new Date()) {
    const cards = collectExportCards(root);
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');
    const fontFamily = window.getComputedStyle(root).fontFamily || 'Arial, sans-serif';
    const canvasFont = (weight, size) => weight + ' ' + size + 'px ' + fontFamily;
    const padding = 64;
    const gap = 24;
    const columns = Math.max(cards.length, 1);
    const cardWidth = 330;
    const width = Math.max(1600, (padding * 2) + (columns * cardWidth) + (gap * (columns - 1)));
    const cardHeights = cards.map(card => Math.max(285, 188 + (card.items.length * 42)));
    const rowHeights = [];
    for (let index = 0; index < cards.length; index += columns) {
        rowHeights.push(Math.max(...cardHeights.slice(index, index + columns)));
    }
    const height = Math.max(520, 250 + rowHeights.reduce((sum, rowHeight) => sum + rowHeight + gap, 0) + padding);
    const scale = 2;
    canvas.width = width * scale;
    canvas.height = height * scale;
    canvas.style.width = width + 'px';
    canvas.style.height = height + 'px';
    context.scale(scale, scale);
    context.direction = 'rtl';
    context.textAlign = 'right';
    context.fillStyle = '#f5f8fa';
    context.fillRect(0, 0, width, height);
    drawRoundRect(context, padding - 18, 44, width - ((padding - 18) * 2), height - 88, 18, '#ffffff', '#e4e6ef');
    context.fillStyle = '#1f2937';
    context.font = canvasFont(700, 34);
    context.fillText('لوحة متابعة تقييم الأضرار', width - padding, 104);
    context.font = canvasFont(600, 19);
    context.fillStyle = '#5e6278';
    context.fillText('المجلس الفلسطيني للإسكان | تصدير بطاقات لوحة التحكم', width - padding, 140);
    context.textAlign = 'left';
    context.font = canvasFont(500, 17);
    context.fillStyle = '#7e8299';
    context.fillText(now.toLocaleString('ar', {dateStyle: 'medium', timeStyle: 'short'}), padding, 114);
    context.textAlign = 'right';
    context.font = canvasFont(600, 18);
    const filters = pageFilterSummary(root);
    filters.forEach((filter, index) => {
        const chipWidth = Math.max(245, context.measureText(filter).width + 42);
        const x = width - padding - (index * 285);
        drawRoundRect(context, x - chipWidth, 166, chipWidth, 42, 21, '#f1f4f7', '#e4e6ef');
        context.fillStyle = '#3f4254';
        context.fillText(filter, x - 20, 193);
    });
    if (!cards.length) {
        context.font = canvasFont(700, 26);
        context.fillStyle = '#7e8299';
        context.textAlign = 'center';
        context.fillText('لا توجد بطاقات مفعّلة لعرضها.', width / 2, 310);
        return {dataUrl: canvas.toDataURL('image/png'), fileName: exportFilename(now)};
    }
    let y = 236;
    cards.forEach((card, index) => {
        const row = Math.floor(index / columns);
        const column = index % columns;
        if (column === 0 && index > 0) y += rowHeights[row - 1] + gap;
        const x = width - padding - ((column + 1) * cardWidth) - (column * gap);
        const cardHeight = rowHeights[row];
        const color = card.color || '#315f72';
        drawRoundRect(context, x, y, cardWidth, cardHeight, 14, mixWithWhite(color, 0.87), mixWithWhite(color, 0.76));
        drawRoundRect(context, x + cardWidth - 14, y, 14, cardHeight, 7, color);
        context.fillStyle = color;
        context.font = canvasFont(700, 22);
        wrapRtlText(context, card.title, x + cardWidth - 34, y + 42, cardWidth - 68, 28, 2);
        context.font = canvasFont(800, 46);
        context.fillStyle = '#1f2937';
        context.fillText(card.total, x + cardWidth - 34, y + 118);
        context.font = canvasFont(500, 17);
        context.fillStyle = '#6b7280';
        wrapRtlText(context, card.subtitle, x + cardWidth - 34, y + 151, cardWidth - 68, 23, 2);
        let itemY = y + 202;
        context.font = canvasFont(600, 16);
        card.items.forEach(item => {
            context.strokeStyle = 'rgba(126, 130, 153, .23)';
            context.beginPath();
            context.moveTo(x + 30, itemY - 18);
            context.lineTo(x + cardWidth - 34, itemY - 18);
            context.stroke();
            context.fillStyle = '#4b5563';
            wrapRtlText(context, item.title, x + cardWidth - 34, itemY + 4, cardWidth - 150, 20, 1);
            context.textAlign = 'left';
            context.fillStyle = color;
            context.fillText(item.value, x + 30, itemY + 4);
            context.textAlign = 'right';
            itemY += 42;
        });
    });
    context.textAlign = 'center';
    context.fillStyle = '#a1a5b7';
    context.font = canvasFont(500, 15);
    context.fillText('Damage Assessment System', width / 2, height - 42);
    return {dataUrl: canvas.toDataURL('image/png'), fileName: exportFilename(now)};
}

function initializeCardsExport(root) {
    const openButton = root.querySelector('[data-dashboard-export-open]');
    const modalElement = document.getElementById('dashboard_cards_export_modal');
    if (!openButton || !modalElement) return;
    const preview = modalElement.querySelector('[data-dashboard-export-preview]');
    const status = modalElement.querySelector('[data-dashboard-export-status]');
    const download = modalElement.querySelector('[data-dashboard-export-download]');
    const refresh = modalElement.querySelector('[data-dashboard-export-refresh]');
    const modal = window.bootstrap?.Modal ? new window.bootstrap.Modal(modalElement) : null;
    const render = () => {
        status.textContent = 'جارٍ إنشاء صورة البطاقات…';
        preview.classList.add('d-none');
        download.classList.add('disabled');
        download.setAttribute('aria-disabled', 'true');
        window.requestAnimationFrame(() => {
            try {
                const image = buildDashboardCardsImage(root);
                preview.src = image.dataUrl;
                preview.classList.remove('d-none');
                download.href = image.dataUrl;
                download.download = image.fileName;
                download.classList.remove('disabled');
                download.setAttribute('aria-disabled', 'false');
                status.textContent = 'الصورة جاهزة للتنزيل.';
            } catch (error) {
                status.textContent = 'تعذّر إنشاء صورة البطاقات. أعد المحاولة بعد تحديث الصفحة.';
            }
        });
    };
    openButton.addEventListener('click', () => {
        modal?.show();
        render();
    });
    refresh?.addEventListener('click', render);
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
    if (root) {
        initializeCardsExport(root);
        if (document.getElementById('preview-gis-data')) initializeGis(root);
    }
}
