(() => {
    'use strict';
    const workspace = document.getElementById('heks-workspace');
    if (!workspace) return;
    const dialog = document.getElementById('hs-detail');
    let opener = null;
    let changed = false;
    const message = (target, text, error = false) => {
        target.textContent = text;
        target.className = error ? 'alert alert-danger' : 'alert alert-success';
    };
    const loadCase = async (url) => {
        const body = document.getElementById('hs-detail-body');
        body.textContent = 'جاري تحميل الملف…';
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) throw new Error('تعذّر فتح الملف. تحقق من صلاحياتك وحاول مجدداً.');
            body.innerHTML = (await response.json()).html;
        } catch (error) { message(body, error.message, true); }
    };
    workspace.addEventListener('click', async (event) => {
        const link = event.target.closest('[data-case-link]');
        if (link && dialog && !event.ctrlKey && !event.metaKey && !event.shiftKey) {
            event.preventDefault();
            opener = link;
            dialog.showModal();
            await loadCase(link.href);
        }
        if (event.target.closest('[data-close-detail]')) dialog?.close();
        const tab = event.target.closest('[data-detail-tab]');
        if (tab) {
            const article = tab.closest('.hs-case');
            article.querySelectorAll('[data-detail-panel]').forEach(panel => { panel.hidden = panel.dataset.detailPanel !== tab.dataset.detailTab; });
            article.querySelectorAll('[data-detail-tab]').forEach(button => {
                button.classList.toggle('active', button === tab);
                button.setAttribute('aria-pressed', String(button === tab));
            });
        }
        const add = event.target.closest('[data-add-line]');
        if (add) {
            const lines = add.closest('form').querySelector('[data-boq-lines]');
            const row = lines.firstElementChild.cloneNode(true);
            row.querySelectorAll('input').forEach(input => { input.value = ''; });
            lines.appendChild(row);
            reindex(lines);
        }
        const remove = event.target.closest('[data-remove-line]');
        if (remove) {
            const lines = remove.closest('[data-boq-lines]');
            if (lines.children.length > 1) remove.closest('.hs-boq-row').remove();
            else lines.querySelectorAll('input').forEach(input => { input.value = ''; });
            reindex(lines);
        }
    });
    function reindex(lines) {
        Array.from(lines.children).forEach((row, index) => row.querySelectorAll('input').forEach(input => {
            input.name = input.name.replace(/lines\[\d+\]/, `lines[${index}]`);
        }));
    }
    dialog?.addEventListener('close', () => {
        if (changed) window.location.reload();
        else opener?.focus();
    });
    workspace.addEventListener('submit', async (event) => {
        const form = event.target.closest('[data-action-form]');
        if (!form) return;
        event.preventDefault();
        const article = form.closest('.hs-case');
        const notice = article.querySelector('[data-action-message]');
        const button = event.submitter;
        if (!form.querySelector('[name="action"]:checked') && !form.querySelector('input[type="hidden"][name="action"]')) {
            message(notice, 'اختر إجراءً أولاً.', true);
            return;
        }
        if (button) button.disabled = true;
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'تعذّر حفظ الإجراء.');
            if (dialog?.open) {
                changed = true;
                await loadCase(article.dataset.caseUrl);
                const updated = dialog.querySelector('[data-action-message]');
                if (updated) message(updated, data.message);
            } else window.location.reload();
        } catch (error) {
            message(notice, error.message, true);
            notice.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        } finally { if (button) button.disabled = false; }
    });
    const mapElement = document.getElementById('hs-map');
    if (mapElement && window.L) {
        const map = L.map(mapElement, { scrollWheelZoom: false }).setView([31.42, 34.36], 11);
        L.tileLayer('https://services.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Tiles © Esri' }).addTo(map);
        const points = JSON.parse(document.getElementById('hs-map-data').textContent);
        const bounds = [];
        points.forEach(point => {
            const popup = document.createElement('div');
            const link = document.createElement('a');
            link.href = point.url;
            link.textContent = `${point.name} · ${point.status}`;
            popup.appendChild(link);
            L.circleMarker([point.lat, point.lng], { radius: 7, color: '#5b3f8c', fillColor: '#a68bcb', fillOpacity: .9 }).addTo(map).bindPopup(popup);
            bounds.push([point.lat, point.lng]);
        });
        if (bounds.length) map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
    }
})();
