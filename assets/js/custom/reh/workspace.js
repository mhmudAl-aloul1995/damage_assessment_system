(() => {
    'use strict';
    const root = document.getElementById('reh-workspace');
    if (!root) return;
    const filters = document.getElementById('reh-filters');
    const results = document.getElementById('reh-results');
    const loading = document.getElementById('reh-loading');
    const detail = document.getElementById('reh-detail');
    const detailBody = document.getElementById('reh-detail-body');
    const message = document.getElementById('reh-message');
    const detailMessage = document.getElementById('reh-detail-message');
    const auditHistoryDialog = document.getElementById('reh-audit-history-dialog');
    const advancedFilters = document.getElementById('reh-advanced-filters');
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let state = { tab: 'overview', survey_status: 'completed', ...JSON.parse(root.dataset.initial) };
    let mapMode = 'survey';
    let exportFormat = 'excel';
    let exportColumnsExpanded = false;
    const exportColumnCatalog = JSON.parse(document.getElementById('reh-export-column-catalog')?.textContent || 'null');
    let listRequest, detailRequest, debounce, poll, map, dirty = false, saving = false;

    function notify(text, target = message, success = false) {
        target.textContent = text;
        target.dataset.success = String(success);
        target.hidden = false;
    }

    async function request(url, options = {}) {
        const response = await fetch(url, { credentials: 'same-origin', ...options, headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf, ...(options.headers || {}) } });
        if (response.redirected || !response.headers.get('content-type')?.includes('application/json')) {
            throw new Error('انتهت الجلسة أو تعذر قراءة الاستجابة. حدّث الصفحة وسجل الدخول مجددًا.');
        }
        const data = await response.json();
        if (!response.ok) {
            const error = new Error(data.errors ? Object.values(data.errors).flat().join('\n') : data.message || 'تعذر إتمام الطلب. حاول مجددًا.');
            error.status = response.status;
            throw error;
        }
        return data;
    }

    function values(value) { return (Array.isArray(value) ? value : [value]).filter(item => item !== '' && item !== null && item !== undefined); }
    function readQuery() {
        const parsed = {};
        for (const [name, value] of new URLSearchParams(location.search)) {
            if (name.endsWith('[]')) { const key = name.slice(0, -2); (parsed[key] ||= []).push(value); }
            else parsed[name] = value;
        }
        return parsed;
    }
    function query() {
        const params = new URLSearchParams();
        for (const [name, value] of Object.entries(state)) {
            if (name === 'column_set' && state.columns !== undefined) continue;
            if (name === 'columns' && exportColumnCatalog && Array.isArray(value)) {
                if (value.length) {
                    const digits = Array(exportColumnCatalog.length).fill(0);
                    value.forEach(key => { const index = exportColumnCatalog.indexes[key]; if (index !== undefined) digits[Math.floor(index / 4)] |= 1 << (index % 4); });
                    params.set('column_set', exportColumnCatalog.version + '.' + digits.map(digit => digit.toString(16)).join(''));
                }
            }
            else if (Array.isArray(value)) value.forEach(item => params.append(name + '[]', item));
            else if (value !== '' && value !== null && value !== undefined && value !== false) params.set(name, value);
        }
        return params;
    }
    function inputValues(input) { return input?.multiple ? [...input.selectedOptions].map(option => option.value) : values(input?.value); }
    function clearInput(input) {
        if (input.multiple) [...input.options].forEach(option => { option.selected = false; });
        else input.value = '';
        if (input.tagName === 'SELECT' && window.jQuery) window.jQuery(input).trigger('change.select2');
    }

    function reflectFilters() {
        if (state.tab === 'sync') state.priority = '';
        for (const input of [...filters.elements]) {
            if (!input.name) continue;
            if (input.type === 'checkbox') input.checked = String(state[input.name]) === '1';
            else if (input.multiple) [...input.options].forEach(option => { option.selected = values(state[input.name]).includes(option.value); });
            else input.value = state[input.name] ?? '';
            if (input.tagName === 'SELECT' && window.jQuery) window.jQuery(input).trigger('change.select2');
        }
        root.querySelectorAll('[data-tab]').forEach(link => {
            const active = link.dataset.tab === state.tab;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
        });
        for (const name of ['status']) filters.elements[name].disabled = state.tab === 'sync';
        const filterSummary = filters.querySelector('[data-filter-summary]');
        filterSummary.textContent = [...filters.elements.survey_status.selectedOptions].map(option => option.textContent).join('، ') || 'كل حالات الاستبيان';
        filterSummary.dataset.default = String(values(state.survey_status).length === 1 && values(state.survey_status).includes('completed'));
        if (filters.elements.priority) filters.elements.priority.disabled = state.tab === 'sync';
        filters.hidden = state.tab === 'sync';
        const chips = filters.querySelector('[data-active-filters]');
        chips.replaceChildren();
        let advancedCount = 0;
        for (const input of [...filters.elements]) {
            const selected = values(state[input.name]);
            if (!input.name || !selected.length || input.closest('[data-survey-condition]')) continue;
            if (advancedFilters.contains(input)) advancedCount += selected.length;
            const label = input.dataset.filterLabel || [input.closest('.reh-filter-range')?.querySelector('strong')?.textContent, filters.querySelector(`label[for="${CSS.escape(input.id)}"]`)?.textContent].filter(Boolean).join(' ') || input.name;
            selected.forEach(value => {
                const text = input.tagName === 'SELECT' ? [...input.options].find(option => option.value === String(value))?.textContent : value;
                const chip = document.createElement('button');
                chip.type = 'button'; chip.className = 'reh-filter-chip'; chip.dataset.removeFilter = input.name; chip.dataset.removeValue = value;
                chip.textContent = `${label}: ${text} ×`;
                chip.setAttribute('aria-label', `إزالة ${text} من فلتر ${label}`);
                chips.append(chip);
            });
        }
        advancedFilters.querySelectorAll('[data-survey-condition]').forEach(field => {
            const key = field.dataset.surveyCondition;
            const operator = state[key + '_op'] || field.dataset.conditionDefault;
            const presence = ['blank', 'not_blank'].includes(operator);
            const selected = presence ? [] : operator === 'between' ? [state[key + '_min'] || '', state[key + '_max'] || ''] : values(state[key]);
            if (!presence && !selected.some(value => value !== '' && value !== null && value !== undefined)) return;
            advancedCount++;
            const label = field.querySelector('.reh-condition-heading label').textContent;
            const operatorLabel = field.querySelector(`[data-condition-option="${CSS.escape(operator)}"]`)?.textContent || '';
            const input = filters.elements.namedItem(key);
            const texts = selected.map(value => value === '' ? '…' : input?.tagName === 'SELECT' ? [...input.options].find(option => option.value === String(value))?.textContent || value : value);
            const chip = document.createElement('button');
            chip.type = 'button'; chip.className = 'reh-filter-chip'; chip.dataset.removeCondition = key;
            chip.textContent = `${label}: ${operatorLabel}${texts.length ? ' · ' + texts.join(operator === 'between' ? ' — ' : '، ') : ''} ×`;
            chip.setAttribute('aria-label', `إزالة شرط ${label}`);
            chips.append(chip);
        });
        filters.querySelector('[data-advanced-count]').textContent = advancedCount;
        updateSurveyFilters();
    }

    async function load(push = true, quiet = false) {
        clearTimeout(poll);
        listRequest?.abort();
        const controller = new AbortController();
        listRequest = controller;
        reflectFilters();
        if (push) history.pushState({ filters: { ...state } }, '', root.dataset.indexUrl + '?' + query());
        results.querySelectorAll('[data-download-filtered]').forEach(button => { button.disabled = true; });
        if (!quiet) { results.setAttribute('aria-busy', 'true'); loading.hidden = false; message.hidden = true; }
        try {
            const data = await request(root.dataset.dataUrl + '?' + query(), { signal: controller.signal });
            if (controller.signal.aborted) return;
            if (map) { map.remove(); map = null; }
            const oldColumnSelect = results.querySelector('[data-export-columns].select2-hidden-accessible');
            if (oldColumnSelect && window.jQuery) window.jQuery(oldColumnSelect).off('.rehColumns').select2('destroy');
            results.innerHTML = data.html;
            filters.querySelector('[data-result-count]').textContent = `${data.total_units ?? 0} وحدة · ${data.total_buildings ?? 0} مبنى`;
            initializeExportColumns();
            updateExportFormat();
            renderMap();
            if (data.sync_active && state.tab === 'sync') poll = setTimeout(() => { if (!document.hidden) load(false, true); }, 5000);
            return true;
        } catch (error) {
            if (error.name !== 'AbortError') notify(error.message, advancedFilters.open ? advancedFilters.querySelector('[data-advanced-message]') : message);
            return false;
        } finally {
            if (listRequest === controller) { results.setAttribute('aria-busy', 'false'); loading.hidden = true; updateExportFormat(); }
        }
    }

    function canLeave() { return !saving && (!dirty || window.confirm('توجد تعديلات غير محفوظة. هل تريد مغادرة الملف؟')); }

    function closeDetail() {
        if (!canLeave()) return false;
        if (auditHistoryDialog.open) auditHistoryDialog.close();
        detailRequest?.abort();
        dirty = false;
        detail.close();
        return true;
    }

    function selectDetailTab(name) {
        detailBody.querySelectorAll('.reh-source-highlight').forEach(field => field.classList.remove('reh-source-highlight'));
        detailBody.querySelectorAll('[data-detail-tab]').forEach(tab => {
            const active = tab.dataset.detailTab === name;
            tab.classList.toggle('active', active);
            tab.setAttribute('aria-pressed', String(active));
        });
        detailBody.querySelectorAll('[data-detail-panel]').forEach(panel => { panel.hidden = panel.dataset.detailPanel !== name; });
    }

    function selectScoreTab(name) {
        detailBody.querySelectorAll('[data-score-tab]').forEach(tab => tab.setAttribute('aria-selected', String(tab.dataset.scoreTab === name)));
        detailBody.querySelectorAll('[data-score-panel]').forEach(panel => { panel.hidden = panel.dataset.scorePanel !== name; });
    }

    function selectScoreFamily(name) {
        detailBody.querySelectorAll('[data-score-family]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.scoreFamily === name)));
        detailBody.querySelectorAll('[data-score-family-panel]').forEach(panel => { panel.hidden = panel.dataset.scoreFamilyPanel !== name; });
    }

    function showScoreSource(button) {
        const panelName = button.dataset.sourcePanel;
        const fields = JSON.parse(button.dataset.scoreSource);
        selectDetailTab(panelName);
        const panel = detailBody.querySelector(`[data-detail-panel="${CSS.escape(panelName)}"]`);
        const attribute = panelName === 'quantities' ? 'data-quantity-field' : 'data-source-field';
        const targets = fields.map(field => panel?.querySelector(`[${attribute}="${CSS.escape(field)}"]`)).filter(Boolean);
        if (!targets.length) {
            notify('البيانات المرتبطة بهذا المؤشر غير متوفرة في آخر استقبال ضمن صلاحياتك.', detailMessage);
            detailMessage.scrollIntoView({ block: 'nearest' });
            return;
        }
        detailMessage.hidden = true;
        targets.forEach(target => {
            target.hidden = false;
            target.classList.add('reh-source-highlight');
            target.setAttribute('tabindex', '-1');
        });
        targets[0].focus({ preventScroll: true });
        targets[0].scrollIntoView({ block: 'center', behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }

    async function openDetail(url, push = true, section = 'summary') {
        if (!canLeave()) return;
        detailRequest?.abort();
        const controller = new AbortController();
        detailRequest = controller;
        dirty = false;
        detailMessage.hidden = true;
        detailBody.innerHTML = '<div class="reh-empty" role="status">جاري فتح ملف التقييم…</div>';
        if (!detail.open) detail.showModal();
        try {
            const data = await request(url, { signal: controller.signal });
            if (controller.signal.aborted) return;
            detailBody.innerHTML = data.html;
            selectDetailTab(section);
            if (push) history.pushState({ filters: { ...state }, detailUrl: url }, '', url);
        } catch (error) {
            if (error.name !== 'AbortError') notify(error.message, detailMessage);
        }
    }

    let auditOrigin = null;
    let auditHistoryOrigin = null;

    function openAuditHistory(button) {
        const field = button.dataset.auditFieldHistory;
        const entries = [...detailBody.querySelectorAll('[data-audit-log-field]')].filter(entry => entry.dataset.auditLogField === field);
        const body = auditHistoryDialog.querySelector('[data-audit-history-body]');
        body.replaceChildren();
        auditHistoryDialog.querySelector('#reh-audit-history-title').textContent = entries[0]?.querySelector('summary strong')?.textContent || 'سجل الحقل';
        auditHistoryDialog.querySelector('#reh-audit-history-caption').textContent = `${entries.length} تعديل · الأحدث أولًا · ضمن أحدث 100 تعديل متاح في الملف حسب صلاحيتك`;
        for (const entry of entries) {
            const copy = entry.cloneNode(true);
            copy.open = true;
            copy.querySelectorAll('[data-audit-verify]').forEach(control => control.remove());
            body.append(copy);
        }
        if (!entries.length) body.textContent = 'لا توجد تعديلات متاحة لهذا الحقل.';
        auditHistoryOrigin = button;
        auditHistoryDialog.showModal();
    }

    auditHistoryDialog.addEventListener('cancel', event => { event.preventDefault(); auditHistoryDialog.close(); });
    auditHistoryDialog.addEventListener('close', () => {
        auditHistoryDialog.querySelector('[data-audit-history-body]').replaceChildren();
        if (auditHistoryOrigin?.isConnected) auditHistoryOrigin.focus({ preventScroll: true });
        auditHistoryOrigin = null;
    });

    function openAudit(button) {
        if (saving || (dirty && !window.confirm('توجد تعديلات غير محفوظة. هل تريد تركها وفتح هذا الحقل؟'))) return;
        const field = button.dataset.auditField;
        const definition = JSON.parse(detailBody.querySelector('[data-audit-fields]').textContent)[field];
        if (!definition) return;
        const editor = detailBody.querySelector('[data-audit-editor]');
        const form = editor.querySelector('form');
        form.reset();
        form.elements.field.value = field;
        editor.querySelector('[data-audit-title]').textContent = definition.label;
        editor.querySelector('[data-audit-group]').textContent = definition.group;
        editor.querySelector('[data-audit-before]').textContent = definition.display;
        const input = document.createElement(definition.type.startsWith('select_') ? 'select' : 'input');
        input.id = 'reh-audit-value'; input.name = 'value'; input.required = true; input.className = 'form-control';
        if (input.tagName === 'SELECT') {
            input.dataset.dropdownParent = '#reh-detail';
            input.multiple = definition.type === 'select_multiple';
            if (input.multiple) input.size = Math.min(6, Object.keys(definition.choices).length);
            else { const empty = new Option('اختر الإجابة الصحيحة', ''); input.add(empty); }
            const selected = input.multiple ? String(definition.value || '').split(/[ ,]+/) : [String(definition.value ?? '')];
            Object.entries(definition.choices).forEach(([code, label]) => input.add(new Option(label, code, false, selected.includes(code))));
        } else {
            input.type = ['integer', 'decimal'].includes(definition.type) ? 'number' : definition.type === 'date' ? 'date' : 'text';
            if (input.type === 'number') { input.min = '0'; input.max = '1000000'; input.step = definition.type === 'integer' ? '1' : 'any'; }
            input.value = definition.value ?? '';
        }
        const previousInput = editor.querySelector('[data-audit-input] select');
        if (previousInput && window.jQuery?.fn.select2 && window.jQuery(previousInput).data('select2')) {
            window.jQuery(previousInput).select2('destroy');
        }
        editor.querySelector('[data-audit-input]').replaceChildren(input);
        if (input.tagName === 'SELECT' && window.jQuery) {
            window.jQuery(input).on('change.rehAudit', () => { dirty = true; });
        }
        editor.querySelector('[data-audit-feedback]').hidden = true;
        editor.hidden = false; auditOrigin = button; dirty = false;
        input.focus({ preventScroll: true }); editor.scrollIntoView({ block: 'start', behavior: 'smooth' });
    }

    function cancelAudit() {
        if (saving || (dirty && !window.confirm('هل تريد إلغاء التصحيح غير المحفوظ؟'))) return;
        detailBody.querySelector('[data-audit-editor]').hidden = true;
        dirty = false; auditOrigin?.focus({ preventScroll: true }); auditOrigin?.scrollIntoView({ block: 'center' });
    }

    detailBody.addEventListener('input', event => { if (event.target.closest('[data-audit-form]')) dirty = true; });
    detailBody.addEventListener('change', event => { if (event.target.closest('[data-audit-form]')) dirty = true; });
    detailBody.addEventListener('submit', async event => {
        const form = event.target.closest('[data-audit-form]');
        if (!form) return;
        event.preventDefault();
        if (saving || !form.reportValidity()) return;
        const payload = Object.fromEntries(new FormData(form));
        if (form.elements.value.multiple) payload.value = [...form.elements.value.selectedOptions].map(option => option.value).join(' ');
        const field = payload.field;
        const activeTab = detailBody.querySelector('[data-detail-tab].active')?.dataset.detailTab;
        const feedback = form.querySelector('[data-audit-feedback]');
        const submit = form.querySelector('[type="submit"]');
        const label = submit.textContent;
        saving = true; feedback.hidden = true; submit.disabled = true; submit.textContent = 'جاري التحقق والحفظ في المصدرين…';
        form.setAttribute('aria-busy', 'true');
        try {
            const data = await request(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            detailBody.innerHTML = data.html; dirty = false; auditOrigin = null;
            if (activeTab) selectDetailTab(activeTab);
            const target = detailBody.querySelector(`[data-source-field="${CSS.escape(field)}"], [data-quantity-field="${CSS.escape(field)}"]`);
            if (target) { target.hidden = false; target.classList.add('reh-source-highlight'); target.scrollIntoView({ block: 'center' }); target.querySelector('[data-audit-field]')?.focus({ preventScroll: true }); }
            notify('تم حفظ التصحيح في قاعدة البيانات وArcGIS وتحديث التقييم وسجل التعديلات.', detailMessage, true);
            await load(false, true);
        } catch (error) {
            notify(error.message, feedback); feedback.scrollIntoView({ block: 'nearest' });
            if (error.status === 502) {
                const reopen = document.createElement('button'); reopen.type = 'button'; reopen.className = 'btn btn-light-primary btn-sm'; reopen.textContent = 'فتح سجل التعديلات والتحقق';
                reopen.addEventListener('click', async () => {
                    dirty = false;
                    await openDetail(location.href, false, 'history');
                    detailBody.querySelector('.reh-audit-history')?.scrollIntoView({ block: 'start' });
                });
                feedback.append(document.createElement('br'), reopen);
            }
        } finally { saving = false; submit.disabled = false; submit.textContent = label; form.removeAttribute('aria-busy'); }
    });

    function renderMap() {
        const target = document.getElementById('reh-map');
        if (!target) return;
        if (!window.L) { target.textContent = 'تعذر تحميل الخريطة؛ يمكنك استخدام جدول المباني.'; return; }
        map = L.map(target, { scrollWheelZoom: false }).setView([31.42, 34.36], 11);
        L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
            maxZoom: 19,
            attribution: 'Tiles &copy; <a href="https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer" target="_blank" rel="noopener">Esri</a> &mdash; Source: Esri, Vantor, Earthstar Geographics, and the GIS User Community'
        }).addTo(map);
        const group = L.featureGroup().addTo(map);
        const data = document.getElementById('reh-map-data');
        const buildings = JSON.parse(data.dataset.buildings);
        const statuses = JSON.parse(data.dataset.statuses);
        const priorities = JSON.parse(data.dataset.priorities);
        const categories = {
            survey: { completed: ['مكتمل ميدانيًا', '#14846b'], not_completed: ['غير مكتمل ميدانيًا', '#d97706'], unrecorded: ['غير مسجل', '#64748b'] },
            status: Object.fromEntries(Object.entries(statuses).map(([key, label], index) => [key, [label, ['#2563eb', '#0891b2', '#dc2626', '#d97706', '#14846b', '#4338ca', '#be185d'][index]]])),
            priority: { very_high: [priorities.very_high, '#b91c1c'], high: [priorities.high, '#ea580c'], medium: [priorities.medium, '#2563eb'], low: [priorities.low, '#14846b'], unclassified: ['غير مصنفة', '#64748b'] }
        };
        const common = { mixed: ['حالات متعددة', '#7c3aed'], empty: ['لا توجد وحدات مطابقة', '#64748b'], missing: ['غير موجود في المصدر', '#475569'] };
        const layers = new Map();
        let selectedId = null;
        if (!results.querySelector(`[data-map-mode="${mapMode}"]`)) mapMode = 'survey';
        const category = building => {
            if (building.source_missing) return 'missing';
            if (mapMode === 'survey') return building.survey;
            if (mapMode === 'priority') return building.units.length ? building.priority || 'unclassified' : 'empty';
            const values = [...new Set(building.units.map(unit => unit.source_missing ? 'missing' : unit[mapMode]))];
            return values.length === 0 ? 'empty' : values.length > 1 ? 'mixed' : values[0];
        };
        const priorityRanges = { very_high: '76–100', high: '55–75', medium: '30–54', low: '0–29' };
        const definition = building => ({ ...categories[mapMode], ...common }[category(building)] || ['غير مسجل', '#64748b']);
        function element(tag, text, className) {
            const node = document.createElement(tag);
            if (text !== undefined) node.textContent = text;
            if (className) node.className = className;
            return node;
        }
        function badge(label, color) {
            const node = element('span', label, 'reh-map-badge');
            node.style.setProperty('--map-color', color);
            return node;
        }
        function selectBuilding(building, zoom = true) {
            selectedId = building.id;
            layers.forEach((layer, id) => layer.setStyle({ weight: id === selectedId ? 4 : 2, fillOpacity: id === selectedId ? .6 : .35 }));
            const layer = layers.get(building.id);
            if (zoom && layer) map.fitBounds(layer.getBounds(), { padding: [35, 35], maxZoom: 18 });
            results.querySelectorAll('[data-map-building-row]').forEach(row => {
                const selected = row.dataset.mapBuildingRow === String(building.id);
                row.classList.toggle('is-map-selected', selected);
                row.querySelector('[data-map-building]').setAttribute('aria-pressed', String(selected));
            });
            const panel = results.querySelector('[data-map-selection]');
            panel.replaceChildren(element('strong', building.name), element('p', `${building.units.length} وحدة مطابقة من ${building.total_units} وحدة`));
            if ('priority' in building) {
                panel.append(badge(`أعلى أولوية محسوبة: ${categories.priority[building.priority]?.[0] || 'غير مصنفة'}`, categories.priority[building.priority]?.[1] || '#64748b'));
                panel.append(element('p', `${building.unclassified_units} وحدة بانتظار اكتمال التقييم · ${building.missing_units} وحدة غير موجودة في المصدر`, 'text-muted'));
            }
            if (!layer) panel.append(element('p', 'لا توجد حدود جغرافية صالحة لهذا المبنى؛ وحداته متاحة أدناه.', 'text-muted'));
            const buildingLink = element('a', 'فتح ملف المبنى', 'btn btn-sm btn-light-primary');
            buildingLink.href = building.url; buildingLink.dataset.detail = '';
            panel.append(buildingLink);
            const list = element('div', undefined, 'reh-map-unit-list');
            building.units.forEach(unit => {
                const link = element('a', undefined, 'reh-map-unit');
                link.href = unit.url; link.dataset.detail = '';
                link.append(element('strong', `وحدة ${unit.old_objectid}`));
                const status = unit.source_missing ? common.missing : categories.status[unit.status] || ['غير مسجل', '#64748b'];
                link.append(badge(...status));
                if ('priority' in unit) link.append(badge(...(categories.priority[unit.priority] || categories.priority.unclassified)));
                link.append(element('small', 'فتح ملف التقييم ←'));
                list.append(link);
            });
            if (!building.units.length) list.append(element('p', 'لا توجد وحدات مطابقة للفلاتر داخل هذا المبنى.'));
            panel.append(list);
        }
        buildings.forEach(building => {
            const rings = building.geometry?.rings;
            if (!Array.isArray(rings) || !rings.length || !rings.every(ring => Array.isArray(ring) && ring.length >= 3 && ring.every(point => Array.isArray(point) && point.length >= 2 && Number.isFinite(point[0]) && Number.isFinite(point[1]) && Math.abs(point[0]) <= 180 && Math.abs(point[1]) <= 90))) return;
            const polygon = L.polygon(rings.map(ring => ring.map(point => [point[1], point[0]])), { weight: 2, fillOpacity: .35 }).addTo(group);
            polygon.bindTooltip(element('span', building.name), { direction: 'top' });
            polygon.on('click', () => selectBuilding(building, false));
            layers.set(building.id, polygon);
        });
        function recolor() {
            layers.forEach((layer, id) => layer.setStyle({ color: definition(buildings.find(building => building.id === id))[1] }));
            results.querySelectorAll('[data-map-mode]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.mapMode === mapMode)));
            results.querySelector('[data-map-explanation]').textContent = mapMode === 'priority'
                ? 'لون المبنى يعبّر عن أعلى أولوية محسوبة بين وحداته المطابقة للفلاتر، وليس درجة مستقلة للمبنى. الرمادي يعني عدم وجود أولوية محسوبة. تظهر الوحدات بانتظار الاستكمال عند اختيار المبنى. تعرض الخريطة مباني الصفحة الحالية فقط.'
                : 'الخريطة تعرض مباني الصفحة الحالية فقط.' + (mapMode === 'status' ? ' «حالات متعددة» تعني اختلاف حالات تدقيق الوحدات المطابقة داخل المبنى.' : '');
            const legend = results.querySelector('[data-map-legend]');
            legend.replaceChildren();
            Object.entries({ ...categories[mapMode], ...common }).forEach(([key, [label, color]]) => {
                const count = buildings.filter(building => category(building) === key).length;
                const range = mapMode === 'priority' && priorityRanges[key] ? ` (${priorityRanges[key]})` : '';
                if (count || (mapMode === 'priority' && priorityRanges[key])) legend.append(badge(`${label}${range} · ${count} مبنى`, color));
            });
        }
        results.querySelectorAll('[data-map-mode]').forEach(button => button.addEventListener('click', () => { mapMode = button.dataset.mapMode; recolor(); }));
        results.querySelectorAll('[data-map-building]').forEach(button => button.addEventListener('click', () => {
            const building = buildings.find(item => item.id === Number(button.dataset.mapBuilding));
            if (building) selectBuilding(building);
        }));
        results.querySelector('[data-map-coverage]').textContent = `${layers.size} مبنى على الخريطة من ${buildings.length} في هذه الصفحة · ${buildings.length - layers.size} دون حدود جغرافية صالحة`;
        recolor();
        if (group.getLayers().length) map.fitBounds(group.getBounds(), { padding: [25, 25], maxZoom: 16 });
    }

    function closeConditionMenus() {
        advancedFilters.querySelectorAll('[data-condition-trigger]').forEach(button => button.setAttribute('aria-expanded', 'false'));
        advancedFilters.querySelectorAll('.reh-condition-menu').forEach(menu => { menu.hidden = true; });
    }

    function updateConditionFields() {
        advancedFilters.querySelectorAll('[data-survey-condition]').forEach(field => {
            const operator = field.querySelector('[data-condition-operator]').value || field.dataset.conditionDefault;
            const presence = ['blank', 'not_blank'].includes(operator);
            const option = field.querySelector(`[data-condition-option="${CSS.escape(operator)}"]`);
            field.querySelector('[data-condition-label]').textContent = option?.textContent || 'الكل';
            field.querySelector('[data-condition-trigger]').dataset.active = String(operator !== field.dataset.conditionDefault);
            field.querySelectorAll('[data-condition-option]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.conditionOption === operator)));
            field.querySelectorAll('[data-condition-single], [data-condition-range]').forEach(part => {
                const visible = !presence && (part.hasAttribute('data-condition-range') ? operator === 'between' : operator !== 'between');
                part.hidden = !visible;
                part.querySelectorAll('[name]').forEach(input => { input.disabled = !visible; if (!visible) clearInput(input); });
            });
            const noValue = field.querySelector('[data-condition-no-value]');
            noValue.hidden = !presence && field.dataset.conditionType !== 'presence';
            noValue.textContent = presence ? 'لا يلزم اختيار إجابة' : 'اختر فارغ أو غير فارغ';
        });
    }

    function updateSurveyFilters() {
        updateConditionFields();
        const value = name => inputValues(filters.elements.namedItem(name));
        const workItem = filters.elements.namedItem('work_item');
        const workState = filters.elements.namedItem('work_state');
        if (value('work_item').length && !value('work_state').length) {
            [...workState.options].forEach(option => { option.selected = option.value === 'positive'; });
            if (window.jQuery) window.jQuery(workState).trigger('change.select2');
        }
        advancedFilters.querySelectorAll('[data-filter-parent], [data-work-condition]').forEach(field => {
            const eligible = field.dataset.filterParent
                ? value(field.dataset.filterParent).includes(field.dataset.filterParentValue)
                : value('work_item').length > 0 && (field.dataset.workCondition === 'item' || value('work_state').includes('positive'));
            field.dataset.inapplicable = String(!eligible);
            field.querySelectorAll('[name]').forEach(input => {
                input.disabled = !eligible;
                if (!eligible) clearInput(input);
            });
        });
        const normalize = text => text.normalize('NFKD').replace(/[\u064B-\u065F\u0670]/g, '').replace(/[أإآ]/g, 'ا').replace(/ء/g, '').replace(/ى/g, 'ي').trim().toLowerCase();
        const search = normalize(advancedFilters.querySelector('#reh-filter-search').value);
        let sections = 0;
        advancedFilters.querySelectorAll('[data-filter-section]').forEach(section => {
            const fields = [...new Set(section.querySelectorAll('[data-filter-field], .reh-advanced-grid > div, .reh-filter-range'))];
            const sectionMatch = search && normalize(section.querySelector('summary').textContent).includes(search);
            let matches = 0;
            fields.forEach(field => {
                const visible = field.dataset.inapplicable !== 'true' && (!search || sectionMatch || normalize(field.textContent).includes(search));
                field.hidden = !visible;
                if (visible) matches++;
            });
            section.hidden = Boolean(search) && matches === 0;
            if (!section.hidden) sections++;
            if (search && !section.hidden) section.open = true;
            const count = [...section.querySelectorAll('[name]')].filter(input => !input.closest('[data-survey-condition]')).reduce((sum, input) => sum + (input.disabled ? 0 : inputValues(input).length), 0)
                + [...section.querySelectorAll('[data-survey-condition]')].filter(field => ['blank', 'not_blank'].includes(field.querySelector('[data-condition-operator]').value) || [...field.querySelectorAll('[data-condition-value]')].some(input => !input.disabled && inputValues(input).length)).length;
            section.querySelector('[data-section-count]').textContent = count ? `${count} محدد` : '';
        });
        advancedFilters.querySelector('[data-filter-search-status]').textContent = search ? `${sections} أقسام مطابقة` : 'اختر قسمًا، أو ابحث عن سؤال أو خيار إجابة.';
    }

    function changeFilters() {
        updateSurveyFilters();
        const cleared = Object.fromEntries([...filters.elements].filter(input => input.name).map(input => [input.name, '']));
        const collected = {};
        for (const input of [...filters.elements]) {
            if (input.name && !input.disabled) collected[input.name] = input.multiple ? inputValues(input) : input.value;
        }
        advancedFilters.querySelectorAll('[data-survey-condition]').forEach(field => {
            const operator = field.querySelector('[data-condition-operator]');
            if (!['blank', 'not_blank'].includes(operator.value) && ![...field.querySelectorAll('[data-condition-value]')].some(input => !input.disabled && inputValues(input).length)) collected[operator.name] = '';
        });
        if (!values(collected.survey_status).length) collected.survey_status = ['all'];
        state = { ...state, ...cleared, ...collected, mine: '', page: 1 };
        return load();
    }
    filters.addEventListener('submit', async event => {
        event.preventDefault(); clearTimeout(debounce);
        const apply = advancedFilters.querySelector('[data-apply-filters]');
        apply.disabled = true;
        try { if (await changeFilters()) advancedFilters.close(); }
        finally { apply.disabled = false; }
    });
    filters.addEventListener('input', event => { if (advancedFilters.contains(event.target)) updateSurveyFilters(); if (event.target.name === 'q') { clearTimeout(debounce); debounce = setTimeout(changeFilters, 350); } });
    filters.addEventListener('change', event => { if (advancedFilters.contains(event.target)) updateSurveyFilters(); if (event.target.name !== 'q' && !advancedFilters.contains(event.target)) { clearTimeout(debounce); changeFilters(); } });
    if (window.jQuery) window.jQuery(filters).on('select2:select.reh select2:unselect.reh select2:clear.reh', 'select', event => { if (event.type === 'select2:select' && event.target.name === 'survey_status') {
            const selected = event.params?.data?.id;
            if (selected === 'all') [...event.target.options].forEach(option => { option.selected = option.value === 'all'; });
            else { const all = [...event.target.options].find(option => option.value === 'all'); if (all) all.selected = false; }
            window.jQuery(event.target).trigger('change.select2');
        } if (advancedFilters.contains(event.target)) updateSurveyFilters(); if (!advancedFilters.contains(event.target)) { clearTimeout(debounce); changeFilters(); } });
    filters.addEventListener('reset', () => { clearTimeout(debounce); state = { tab: state.tab, survey_status: 'completed', page: 1, ...(state.columns ? { columns: state.columns } : {}) }; setTimeout(() => load(), 0); });
    advancedFilters.addEventListener('close', () => { closeConditionMenus(); advancedFilters.querySelector('#reh-filter-search').value = ''; reflectFilters(); });
    advancedFilters.querySelector('.reh-advanced-body').addEventListener('scroll', closeConditionMenus, { passive: true });
    window.addEventListener('resize', closeConditionMenus);
    advancedFilters.addEventListener('keydown', event => {
        if (event.key === 'Escape' && advancedFilters.querySelector('.reh-condition-menu:not([hidden])')) { event.preventDefault(); closeConditionMenus(); }
    });
    document.addEventListener('click', event => { if (!event.target.closest('[data-condition-trigger], .reh-condition-menu')) closeConditionMenus(); });
    advancedFilters.querySelector('#reh-filter-search').addEventListener('keydown', event => { if (event.key === 'Enter') event.preventDefault(); });

    function initializeExportColumns() {
        const select = results.querySelector('[data-export-columns]');
        if (!select) return;
        const selected = state.columns ?? inputValues(select);
        [...select.options].forEach(option => { option.selected = selected.includes(option.value); });
        state.columns = selectedExportColumns(select);
        if (window.jQuery?.fn.select2) {
            window.jQuery(select).select2({ dir: 'rtl', width: '100%', closeOnSelect: false, placeholder: 'ابحث باسم السؤال أو العمود', dropdownParent: window.jQuery(select.closest('.reh-export-columns')) });
            window.jQuery(select).on('change.rehColumns', exportColumnsChanged);
        }
        updateExportColumns();
    }

    function selectedExportColumns(select) {
        const selected = inputValues(select);
        return exportColumnCatalog ? selected.sort((a, b) => exportColumnCatalog.indexes[a] - exportColumnCatalog.indexes[b]) : selected;
    }

    function updateExportColumns() {
        const select = results.querySelector('[data-export-columns]');
        if (!select) return;
        const selected = selectedExportColumns(select);
        results.querySelector('[data-export-column-count]').textContent = `${selected.length} من ${select.options.length} عمود`;
        results.querySelector('[data-export-column-warning]').hidden = selected.length > 0;
        results.querySelectorAll('[data-export-column-key]').forEach(cell => { cell.hidden = !selected.includes(cell.dataset.exportColumnKey); });
        results.querySelectorAll('.reh-table tr').forEach(row => {
            selected.forEach(key => {
                const cell = row.querySelector(`[data-export-column-key="${CSS.escape(key)}"]`);
                if (cell) row.append(cell);
            });
        });
        results.querySelector('[data-export-select-all]').disabled = selected.length === select.options.length;
        results.querySelector('[data-export-clear-columns]').disabled = !selected.length;
        const toggle = results.querySelector('[data-export-toggle-columns]');
        toggle.hidden = selected.length <= 8;
        toggle.setAttribute('aria-expanded', String(exportColumnsExpanded));
        toggle.textContent = exportColumnsExpanded ? 'طيّ الاختيارات' : `عرض بقية الاختيارات (${Math.max(0, selected.length - 8)})`;
        results.querySelector('#select2-reh-export-columns-container')?.classList.toggle('is-expanded', exportColumnsExpanded);
    }

    function exportColumnsChanged() {
        state.columns = selectedExportColumns(results.querySelector('[data-export-columns]'));
        history.replaceState({ filters: { ...state } }, '', root.dataset.indexUrl + '?' + query());
        updateExportColumns();
        updateExportFormat();
    }

    function updateExportFormat() {
        const panel = results.querySelector('[data-export-total]');
        if (!panel) return;
        const total = Number(panel.dataset.exportTotal);
        panel.querySelectorAll('[name="export_format"]').forEach(input => { input.checked = input.value === exportFormat; });
        const button = panel.querySelector('[data-download-filtered]');
        button.disabled = total === 0 || state.columns?.length === 0 || results.getAttribute('aria-busy') === 'true' || (exportFormat === 'pdf' && total > 500);
        button.textContent = exportFormat === 'excel' ? 'تنزيل Excel' : 'تنزيل PDF';
        panel.querySelector('[data-export-format-help]').textContent = exportFormat === 'excel' ? 'Excel يشمل جميع النتائج المطابقة، وليس المعاينة فقط.' : total > 500 ? 'النتائج أكثر من 500 وحدة؛ ضيّق الفلاتر أو اختر Excel لتصديرها جميعًا.' : 'PDF يشمل جميع النتائج المطابقة مع الفلاتر المعتمدة.';
    }
    results.addEventListener('change', event => {
        if (event.target.name === 'export_format') { exportFormat = event.target.value; updateExportFormat(); }
        if (event.target.matches('[data-export-columns]')) exportColumnsChanged();
    });

    async function downloadFile(button, url, format = 'pdf') {
        const label = button.textContent;
        const target = detail.open ? detailMessage : message;
        button.disabled = true; button.textContent = format === 'excel' ? 'جاري تجهيز Excel…' : 'جاري تجهيز PDF…';
        target.hidden = true;
        try {
            const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/pdf, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            if (!response.ok || !response.headers.get('content-type')?.includes(format === 'excel' ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'application/pdf')) {
                const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : null;
                throw new Error(data?.errors ? Object.values(data.errors).flat().join('\n') : data?.message || 'تعذر إنشاء التقرير. تحقق من الجلسة وحاول مجددًا.');
            }
            const blobUrl = URL.createObjectURL(await response.blob());
            const link = document.createElement('a'); link.href = blobUrl;
            const filename = response.headers.get('content-disposition')?.match(/filename="?([^";]+)"?/)?.[1];
            link.download = filename || (format === 'excel' ? 'REH-units.xlsx' : 'REH-report.pdf'); document.body.append(link); link.click(); link.remove();
            setTimeout(() => URL.revokeObjectURL(blobUrl), 10000);
            notify(format === 'excel' ? 'تم تجهيز ملف Excel وتنزيله.' : 'تم تجهيز تقرير PDF وتنزيله.', target, true);
        } catch (error) { notify(error.message, target); }
        finally { button.disabled = false; button.textContent = label; if (button.matches('[data-download-filtered]')) updateExportFormat(); }
    }
    detailBody.addEventListener('input', event => { if (event.target.closest('[data-review-form]')) dirty = true; });
    detailBody.addEventListener('change', event => {
        if (!event.target.matches('[data-action-form] input[name="action"]')) return;
        const form = event.target.closest('[data-action-form]');
        const label = event.target.dataset.actionLabel;
        form.querySelector('[data-action-selection]').textContent = 'الإجراء المختار: ' + label;
        const submit = form.querySelector('[data-action-submit]');
        submit.disabled = false;
        submit.textContent = 'حفظ: ' + label;
        submit.classList.toggle('btn-danger', event.target.value === 'exclude');
        submit.classList.toggle('btn-primary', event.target.value !== 'exclude');
        dirty = true;
    });
    detail.addEventListener('cancel', event => { event.preventDefault(); if (closeDetail()) history.pushState({ filters: { ...state } }, '', root.dataset.indexUrl + '?' + query()); });

    root.addEventListener('click', async event => {
        const button = event.target.closest('a, button, input');
        if (!button || button.disabled) return;
        if (button.matches('[data-open-export-page], [data-workspace-tab]')) {
            event.preventDefault();
            const params = query(); params.set('page', '1');
            if (button.dataset.workspaceTab) params.set('tab', button.dataset.workspaceTab);
            else params.set('tab', 'units');
            window.location.assign((button.dataset.workspaceTab ? root.dataset.workspaceUrl : root.dataset.exportPageUrl) + '?' + params);
        }
        else if (button.matches('[data-export-toggle-columns]')) {
            exportColumnsExpanded = !exportColumnsExpanded;
            updateExportColumns();
        }
        else if (button.matches('[data-export-select-all], [data-export-clear-columns], [data-export-basic-columns]')) {
            const select = results.querySelector('[data-export-columns]');
            [...select.options].forEach(option => { option.selected = button.matches('[data-export-select-all]') || button.matches('[data-export-basic-columns]') && option.hasAttribute('data-export-basic'); });
            exportColumnsExpanded = false;
            if (window.jQuery) window.jQuery(select).trigger('change.select2');
            exportColumnsChanged();
        }
        else if (button.matches('[data-download-filtered]')) {
            await downloadFile(button, (exportFormat === 'excel' ? root.dataset.excelUrl : root.dataset.pdfUrl) + '?' + query(), exportFormat);
        }
        else if (button.matches('[data-open-filters]')) {
            clearTimeout(debounce); reflectFilters();
            advancedFilters.querySelector('[data-advanced-message]').hidden = true;
            advancedFilters.showModal();
            advancedFilters.querySelectorAll('[data-filter-section]').forEach((section, index) => { section.open = index === 0 || Boolean(section.querySelector('[data-section-count]').textContent); });
        }
        else if (button.matches('[data-cancel-filters]')) { advancedFilters.close(); }
        else if (button.matches('[data-condition-trigger]')) {
            const field = button.closest('[data-survey-condition]');
            const menu = field.querySelector('.reh-condition-menu');
            const opening = menu.hidden;
            closeConditionMenus();
            if (opening) {
                menu.hidden = false; button.setAttribute('aria-expanded', 'true');
                const rect = button.getBoundingClientRect();
                const dialog = advancedFilters.getBoundingClientRect();
                const width = Math.min(230, dialog.width - 32);
                menu.style.width = width + 'px';
                menu.style.left = Math.max(dialog.left + 16, Math.min(rect.right - width, dialog.right - width - 16)) + 'px';
                menu.style.top = Math.max(8, Math.min(rect.bottom + 6, window.innerHeight - menu.offsetHeight - 12)) + 'px';
                menu.querySelector('[aria-pressed="true"]')?.focus();
            }
        }
        else if (button.matches('[data-condition-option]')) {
            const field = button.closest('[data-survey-condition]');
            field.querySelector('[data-condition-operator]').value = button.dataset.conditionOption;
            closeConditionMenus(); updateSurveyFilters(); field.querySelector('[data-condition-trigger]').focus();
        }
        else if (button.matches('[data-clear-advanced]')) {
            advancedFilters.querySelectorAll('[name]').forEach(input => {
                clearInput(input);
            });
            updateSurveyFilters();
        }
        else if (button.matches('[data-remove-condition]')) {
            const key = button.dataset.removeCondition;
            [key, key + '_op', key + '_min', key + '_max'].forEach(name => { delete state[name]; });
            state.page = 1; await load();
        }
        else if (button.matches('[data-remove-filter]')) {
            clearTimeout(debounce);
            const key = button.dataset.removeFilter;
            const remaining = values(state[key]).filter(value => String(value) !== button.dataset.removeValue);
            state[key] = remaining.length ? remaining : key === 'survey_status' ? ['all'] : '';
            advancedFilters.querySelectorAll('[data-filter-parent]').forEach(field => {
                if (field.dataset.filterParent === key && !remaining.includes(field.dataset.filterParentValue)) field.querySelectorAll('[name]').forEach(input => { state[input.name] = ''; });
            });
            if (['work_item', 'work_state'].includes(key) && !remaining.length) ['work_item', 'work_state', 'work_min', 'work_max'].forEach(name => { state[name] = ''; });
            state.page = 1; await load();
        }
        else if (button.matches('[data-export-pdf], [data-pdf-download]')) { await downloadFile(button, button.dataset.pdfDownload || root.dataset.pdfUrl + '?' + query()); }
        else if (button.matches('[data-audit-field]')) { openAudit(button); }
        else if (button.matches('[data-audit-field-history]')) {
            openAuditHistory(button);
        }
        else if (button.matches('[data-close-audit-history]')) { auditHistoryDialog.close(); }
        else if (button.matches('[data-audit-cancel]')) { cancelAudit(); }
        else if (button.matches('[data-audit-verify]')) {
            if (saving || !canLeave()) return;
            saving = true; button.disabled = true;
            try {
                const data = await request(button.dataset.auditVerify, { method: 'POST' });
                detailBody.innerHTML = data.html; dirty = false; selectDetailTab('history');
                notify('تم التحقق؛ راجع نتيجة المحاولة في سجل التعديلات.', detailMessage, true);
                detailBody.querySelector('.reh-audit-history')?.scrollIntoView({ block: 'start' });
                await load(false, true);
            } catch (error) { notify(error.message, detailMessage); detailMessage.scrollIntoView({ block: 'nearest' }); }
            finally { saving = false; button.disabled = false; }
        }
        else if (button.matches('[data-detail]')) { event.preventDefault(); event.stopPropagation(); await openDetail(button.href, true, button.dataset.detailSection || 'summary'); }
        else if (button.matches('[data-close-detail]')) { if (closeDetail()) history.pushState({ filters: { ...state } }, '', root.dataset.indexUrl + '?' + query()); }
        else if (button.matches('[data-tab]')) { event.preventDefault(); event.stopPropagation(); state.tab = button.dataset.tab; state.page = 1; await load(); }
        else if (button.matches('[data-metric-tab]')) { state.tab = button.dataset.metricTab; state.status = button.dataset.metricStatus; state.page = 1; await load(); }
        else if (button.matches('[data-page]')) { state.page = button.dataset.page; await load(); }
        else if (button.matches('[data-building-units]')) { if (detail.open && !closeDetail()) return; state = { tab: 'units', survey_status: state.survey_status, building_id: button.dataset.buildingUnits, page: 1 }; await load(); }
        else if (button.matches('[data-detail-tab]')) {
            selectDetailTab(button.dataset.detailTab);
        } else if (button.matches('[data-score-tab], [data-score-jump]')) {
            selectScoreTab(button.dataset.scoreTab || button.dataset.scoreJump);
            if (button.dataset.scoreSelectFamily) selectScoreFamily(button.dataset.scoreSelectFamily);
            if (button.dataset.scoreTarget) {
                const panels = [...detailBody.querySelectorAll('[data-score-panel]:not([hidden])')];
                const row = panels.flatMap(panel => [...panel.querySelectorAll(`[data-score-row="${CSS.escape(button.dataset.scoreTarget)}"]`)])
                    .find(candidate => !candidate.closest('[data-score-family-panel][hidden]'));
                if (row) { row.open = true; row.querySelector('summary').focus(); row.scrollIntoView({ block: 'nearest' }); }
            }
        } else if (button.matches('[data-score-family]')) {
            selectScoreFamily(button.dataset.scoreFamily);
        } else if (button.matches('[data-score-quantities], [data-score-quantity]')) {
            selectDetailTab('quantities');
            if (button.dataset.scoreQuantity) {
                const input = detailBody.querySelector(`[data-audit-field="${CSS.escape(button.dataset.scoreQuantity)}"]`);
                if (input) {
                    input.closest('[data-quantity-row]').hidden = false;
                    input.focus();
                    input.scrollIntoView({ block: 'center' });
                }
            }
        } else if (button.matches('[data-score-source]')) {
            showScoreSource(button);
        } else if (button.matches('[data-show-all-quantities]')) {
            detailBody.querySelectorAll('[data-quantity-row]').forEach(row => { row.hidden = !button.checked && row.dataset.hasQuantity !== '1' && !row.hasAttribute('data-score-missing-field'); });
        } else if (button.matches('[data-export]')) {
            window.location.assign(root.dataset.exportUrl + '?' + query());
        } else if (button.matches('[data-sync]')) {
            button.disabled = true;
            try { const data = await request(root.dataset.syncUrl, { method: 'POST' }); await load(false); notify(data.message, message, true); }
            catch (error) { await load(false); notify(error.message); }
            finally { button.disabled = false; }
        } else if (button.matches('[data-load-attachments]')) {
            const target = button.nextElementSibling;
            button.disabled = true; target.textContent = 'جاري تحميل المرفقات…';
            try {
                const data = await request(button.dataset.loadAttachments);
                target.replaceChildren();
                if (!data.attachments.length) target.textContent = 'لا توجد مرفقات متاحة ضمن صلاحياتك.';
                data.attachments.forEach(attachment => {
                    const link = document.createElement('a'); link.href = attachment.url; link.target = '_blank'; link.rel = 'noopener';
                    if (attachment.image) { const img = document.createElement('img'); img.src = attachment.url; img.alt = attachment.label || attachment.name; img.loading = 'lazy'; link.append(img); }
                    const label = document.createElement('span'); label.textContent = attachment.label || attachment.name; link.append(label); target.append(link);
                });
            } catch (error) { target.textContent = error.message; }
            finally { button.disabled = false; }
        }
    });

    detailBody.addEventListener('keydown', event => {
        if (!event.target.matches('[data-score-tab]') || !['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
        event.preventDefault();
        const tabs = [...detailBody.querySelectorAll('[data-score-tab]')];
        const index = tabs.indexOf(event.target);
        const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowLeft' ? 1 : -1) + tabs.length) % tabs.length;
        tabs[next].focus();
        selectScoreTab(tabs[next].dataset.scoreTab);
    });

    detailBody.addEventListener('submit', async event => {
        const form = event.target.closest('[data-review-form]');
        if (!form) return;
        event.preventDefault();
        if (saving) return;
        if (!form.reportValidity()) return;
        const payload = {};
        for (const [key, value] of new FormData(form)) {
            const quantity = key.match(/^quantities\[(.+)\]$/);
            if (quantity) { payload.quantities ??= {}; payload.quantities[quantity[1]] = value === '' ? null : value; }
            else if (key !== '_token') payload[key] = value;
        }
        const activeTab = detailBody.querySelector('[data-detail-tab].active')?.dataset.detailTab;
        const actionLabel = form.querySelector('input[name="action"]:checked')?.dataset.actionLabel || 'مراجعة الكميات';
        saving = true; detailMessage.hidden = true;
        const submit = form.querySelector('[type=submit]');
        const submitLabel = submit.textContent;
        submit.disabled = true; submit.textContent = 'جاري الحفظ…';
        try {
            const data = await request(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            detailBody.innerHTML = data.html;
            dirty = false;
            detailBody.querySelector(`[data-detail-tab="${activeTab}"]`)?.click();
            notify('تم حفظ «' + actionLabel + '» وتحديث سجل الوحدة.', detailMessage, true);
            await load(false, true);
        } catch (error) { notify(error.message, detailMessage); detailMessage.scrollIntoView({ block: 'nearest' }); }
        finally { saving = false; submit.disabled = false; submit.textContent = submitLabel; }
    });

    window.addEventListener('popstate', event => {
        state = event.state?.filters || { tab: 'overview', survey_status: 'completed', ...readQuery() };
        load(false);
        if (event.state?.detailUrl) openDetail(event.state.detailUrl, false);
        else if (detail.open) closeDetail();
    });
    window.addEventListener('beforeunload', event => { if (dirty || saving) { event.preventDefault(); event.returnValue = ''; } });
    document.addEventListener('visibilitychange', () => { if (!document.hidden && state.tab === 'sync') load(false, true); });
    history.replaceState({ filters: { ...state }, detailUrl: detailBody.innerHTML.trim() ? location.href : null }, '', location.href);
    if (detailBody.innerHTML.trim()) detail.showModal();
    load(false);
})();
