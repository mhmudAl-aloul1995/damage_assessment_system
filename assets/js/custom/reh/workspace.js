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
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let state = { tab: 'overview', survey_status: 'completed', ...JSON.parse(root.dataset.initial) };
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

    function query() {
        return new URLSearchParams(Object.entries(state).filter(([, value]) => value !== '' && value !== null && value !== false));
    }

    function reflectFilters() {
        if (state.tab === 'buildings' || state.tab === 'sync') state.priority = '';
        for (const input of filters.elements) {
            if (!input.name) continue;
            if (input.type === 'checkbox') input.checked = String(state[input.name]) === '1';
            else input.value = state[input.name] || '';
            if (input.tagName === 'SELECT' && window.jQuery) window.jQuery(input).trigger('change.select2');
        }
        root.querySelectorAll('[data-tab]').forEach(link => {
            const active = link.dataset.tab === state.tab;
            link.classList.toggle('active', active);
            if (active) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
        });
        for (const name of ['status']) filters.elements[name].disabled = state.tab === 'buildings' || state.tab === 'sync';
        const filterSummary = filters.querySelector('[data-filter-summary]');
        filterSummary.textContent = filters.elements.survey_status.selectedOptions[0]?.textContent || '';
        filterSummary.dataset.default = String(state.survey_status === 'completed');
        if (filters.elements.priority) filters.elements.priority.disabled = state.tab === 'buildings' || state.tab === 'sync';
        filters.hidden = state.tab === 'sync';
    }

    async function load(push = true, quiet = false) {
        clearTimeout(poll);
        listRequest?.abort();
        const controller = new AbortController();
        listRequest = controller;
        reflectFilters();
        if (push) history.pushState({ filters: { ...state } }, '', root.dataset.indexUrl + '?' + query());
        if (!quiet) { results.setAttribute('aria-busy', 'true'); loading.hidden = false; message.hidden = true; }
        try {
            const data = await request(root.dataset.dataUrl + '?' + query(), { signal: controller.signal });
            if (controller.signal.aborted) return;
            if (map) { map.remove(); map = null; }
            results.innerHTML = data.html;
            renderMap();
            if (data.sync_active && state.tab === 'sync') poll = setTimeout(() => { if (!document.hidden) load(false, true); }, 5000);
        } catch (error) {
            if (error.name !== 'AbortError') notify(error.message);
        } finally {
            if (listRequest === controller) { results.setAttribute('aria-busy', 'false'); loading.hidden = true; }
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
        const buildings = JSON.parse(document.getElementById('reh-map-data').dataset.buildings);
        buildings.forEach(building => {
            const rings = building.geometry?.rings;
            if (!rings?.length) return;
            const polygon = L.polygon(rings.map(ring => ring.map(point => [point[1], point[0]])), { color: '#167b7c', weight: 2, fillOpacity: .22 }).addTo(group);
            const link = document.createElement('a');
            link.textContent = building.name;
            link.href = building.url;
            link.dataset.detail = '';
            polygon.bindPopup(link);
        });
        if (group.getLayers().length) map.fitBounds(group.getBounds(), { padding: [25, 25], maxZoom: 16 });
    }

    function changeFilters() {
        state = { ...state, ...Object.fromEntries(new FormData(filters)), mine: '', page: 1 };
        if (state.tab === 'buildings') { state.status = ''; state.mine = ''; state.priority = ''; }
        load();
    }
    filters.addEventListener('submit', event => { event.preventDefault(); clearTimeout(debounce); changeFilters(); });
    filters.addEventListener('input', event => { if (event.target.name === 'q') { clearTimeout(debounce); debounce = setTimeout(changeFilters, 350); } });
    filters.addEventListener('change', event => { if (event.target.name !== 'q') { clearTimeout(debounce); changeFilters(); } });
    if (window.jQuery) window.jQuery(filters).on('select2:select.reh select2:clear.reh', 'select', () => { clearTimeout(debounce); changeFilters(); });
    filters.addEventListener('reset', () => { clearTimeout(debounce); state = { tab: state.tab, survey_status: 'completed', page: 1 }; setTimeout(() => load(), 0); });
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
        if (button.matches('[data-audit-field]')) { openAudit(button); }
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
        else if (button.matches('[data-tab]')) { event.preventDefault(); event.stopPropagation(); state.tab = button.dataset.tab; state.page = 1; if (state.tab === 'buildings') { state.status = ''; state.mine = ''; state.priority = ''; } await load(); }
        else if (button.matches('[data-metric-tab]')) { state.tab = button.dataset.metricTab; state.status = button.dataset.metricStatus; state.page = 1; await load(); }
        else if (button.matches('[data-page]')) { state.page = button.dataset.page; await load(); }
        else if (button.matches('[data-building-units]')) { if (detail.open && !closeDetail()) return; state = { tab: 'units', survey_status: state.survey_status, building_object_id: button.dataset.buildingUnits, page: 1 }; await load(); }
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
            detailBody.querySelectorAll('[data-quantity-row]').forEach(row => { row.hidden = !button.checked && row.dataset.hasQuantity !== '1'; });
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
        state = event.state?.filters || { tab: 'overview', survey_status: 'completed', ...Object.fromEntries(new URLSearchParams(location.search)) };
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
