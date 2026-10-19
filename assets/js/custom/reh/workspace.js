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
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    let state = { tab: 'overview', ...JSON.parse(root.dataset.initial) };
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
        for (const name of ['status', 'mine']) filters.elements[name].disabled = state.tab === 'buildings' || state.tab === 'sync';
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
        detailRequest?.abort();
        dirty = false;
        detail.close();
        return true;
    }

    function selectDetailTab(name) {
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

    function renderMap() {
        const target = document.getElementById('reh-map');
        if (!target) return;
        if (!window.L) { target.textContent = 'تعذر تحميل الخريطة؛ يمكنك استخدام جدول المباني.'; return; }
        map = L.map(target, { scrollWheelZoom: false }).setView([31.42, 34.36], 11);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
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
        state = { ...state, ...Object.fromEntries(new FormData(filters)), mine: filters.elements.mine.checked ? '1' : '', page: 1 };
        if (state.tab === 'buildings') { state.status = ''; state.mine = ''; }
        load();
    }
    filters.addEventListener('submit', event => { event.preventDefault(); clearTimeout(debounce); changeFilters(); });
    filters.addEventListener('input', event => { if (event.target.name === 'q') { clearTimeout(debounce); debounce = setTimeout(changeFilters, 350); } });
    filters.addEventListener('change', event => { if (event.target.name !== 'q') { clearTimeout(debounce); changeFilters(); } });
    if (window.jQuery) window.jQuery(filters).on('select2:select.reh select2:clear.reh', 'select', () => { clearTimeout(debounce); changeFilters(); });
    filters.addEventListener('reset', () => { clearTimeout(debounce); state = { tab: state.tab, page: 1 }; setTimeout(() => load(), 0); });
    detailBody.addEventListener('input', event => { if (event.target.closest('[data-review-form]')) dirty = true; });
    detail.addEventListener('cancel', event => { event.preventDefault(); if (closeDetail()) history.pushState({ filters: { ...state } }, '', root.dataset.indexUrl + '?' + query()); });

    root.addEventListener('click', async event => {
        const button = event.target.closest('a, button, input');
        if (!button || button.disabled) return;
        if (button.matches('[data-detail]')) { event.preventDefault(); event.stopPropagation(); await openDetail(button.href, true, button.dataset.detailSection || 'summary'); }
        else if (button.matches('[data-close-detail]')) { if (closeDetail()) history.pushState({ filters: { ...state } }, '', root.dataset.indexUrl + '?' + query()); }
        else if (button.matches('[data-tab]')) { event.preventDefault(); event.stopPropagation(); state.tab = button.dataset.tab; state.page = 1; if (state.tab === 'buildings') { state.status = ''; state.mine = ''; } await load(); }
        else if (button.matches('[data-metric-tab]')) { state.tab = button.dataset.metricTab; state.status = button.dataset.metricStatus; state.page = 1; await load(); }
        else if (button.matches('[data-page]')) { state.page = button.dataset.page; await load(); }
        else if (button.matches('[data-building-units]')) { if (detail.open && !closeDetail()) return; state = { tab: 'units', building_object_id: button.dataset.buildingUnits, page: 1 }; await load(); }
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
                const input = detailBody.querySelector(`[name="quantities[${CSS.escape(button.dataset.scoreQuantity)}]"]`);
                if (input) {
                    input.closest('[data-quantity-row]').hidden = false;
                    input.focus();
                    input.scrollIntoView({ block: 'center' });
                }
            }
        } else if (button.matches('[data-score-source]')) {
            selectDetailTab('data');
            detailBody.querySelector(`[data-source-field="${CSS.escape(button.dataset.scoreSource)}"]`)?.scrollIntoView({ block: 'center' });
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
        const payload = {};
        for (const [key, value] of new FormData(form)) {
            const quantity = key.match(/^quantities\[(.+)\]$/);
            if (quantity) { payload.quantities ??= {}; payload.quantities[quantity[1]] = value === '' ? null : value; }
            else if (key !== '_token') payload[key] = value;
        }
        const activeTab = detailBody.querySelector('[data-detail-tab].active')?.dataset.detailTab;
        saving = true; detailMessage.hidden = true;
        const submit = form.querySelector('[type=submit]'); submit.disabled = true; submit.textContent = 'جاري الحفظ…';
        try {
            const data = await request(form.action, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
            detailBody.innerHTML = data.html;
            dirty = false;
            detailBody.querySelector(`[data-detail-tab="${activeTab}"]`)?.click();
            notify('تم حفظ الإجراء وتحديث النقاط المحسومة وسجل الوحدة.', detailMessage, true);
            await load(false, true);
        } catch (error) { notify(error.message, detailMessage); detailMessage.scrollIntoView({ block: 'nearest' }); }
        finally { saving = false; submit.disabled = false; submit.textContent = payload.action === 'quantities' ? 'حفظ مراجعة الكميات' : 'حفظ الإجراء'; }
    });

    window.addEventListener('popstate', event => {
        state = event.state?.filters || { tab: 'overview', ...Object.fromEntries(new URLSearchParams(location.search)) };
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
