(() => {
    'use strict';
    const dialog = document.getElementById('reh-unit-delete-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const errorBox = dialog.querySelector('[data-delete-message]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
    const page = document.querySelector('.reh-maintenance');
    const checkboxes = [...page.querySelectorAll('[data-select-unit]')];
    const pageCheckbox = page.querySelector('[data-select-page]');
    const selectionBar = page.querySelector('[data-bulk-selection]');
    const operationPanel = page.querySelector('[data-bulk-operation]');
    const total = Number(page.dataset.totalUnits);
    let filteredSelection = false;
    const excluded = new Set();
    let bulkDialog = false;
    let operation = null;
    let pollTimer;
    let lastWorkerStart = Date.now();
    const impact = {
        database: 'تُحذف الوحدة وبياناتها المرتبطة من قاعدة البيانات فقط. تبقى في ArcGIS وقد تعود عند المزامنة التالية.',
        arcgis: 'تُحذف الوحدة من ArcGIS فقط، وتبقى محليًا بحالة غير موجودة في المصدر مع إعادة التدقيق والقرار إلى الانتظار.',
        both: 'تُحذف الوحدة من ArcGIS أولًا، ثم تُحذف وبياناتها المرتبطة من قاعدة البيانات بعد تأكيد نجاح حذف المصدر.'
    };
    let busy = false;
    function setBusy(value) {
        busy = value;
        dialog.querySelectorAll('button').forEach(button => { button.disabled = value; });
        dialog.querySelector('[data-confirm-delete]').textContent = value ? 'جارٍ تنفيذ الحذف…' : 'تأكيد الحذف';
    }
    async function request(url, body, method = 'POST') {
        const response = await fetch(url, {method, body, credentials:'same-origin', headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':csrf, ...(typeof body === 'string' ? {'Content-Type':'application/json'} : {})}});
        const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : data.message || 'تعذر إتمام العملية. حدّث الصفحة وتحقق من سجل الحذف قبل إعادة المحاولة.');
        return data;
    }
    function selectedCount() {
        return filteredSelection ? total - excluded.size : checkboxes.filter(checkbox => checkbox.checked).length;
    }
    function renderSelection() {
        const count = selectedCount();
        selectionBar.hidden = count === 0;
        selectionBar.querySelector('[data-selection-count]').textContent = `${count.toLocaleString('ar')} وحدة محددة`;
        selectionBar.querySelector('[data-selection-scope]').textContent = filteredSelection ? 'يشمل الاختيار كل نتائج الفلتر عبر الصفحات.' : 'من الصفحة الحالية · يُلغى التحديد عند تغيير الصفحة أو الفلاتر.';
        selectionBar.querySelector('[data-select-filtered]').hidden = filteredSelection || total <= checkboxes.length;
        selectionBar.querySelector('[data-delete-selected]').disabled = busy || Boolean(operation?.active);
        const checked = checkboxes.filter(checkbox => checkbox.checked).length;
        pageCheckbox.checked = checked > 0 && checked === checkboxes.length;
        pageCheckbox.indeterminate = checked > 0 && checked < checkboxes.length;
    }
    function clearSelection() {
        filteredSelection = false;
        excluded.clear();
        checkboxes.forEach(checkbox => { checkbox.checked = false; });
        renderSelection();
    }
    function showDialog() {
        dialog.querySelector('[data-delete-impact]').textContent = 'حدد مكان الحذف لمعرفة أثره.';
        errorBox.hidden = true;
        dialog.querySelector('[data-refresh-deletion]').hidden = true;
        dialog.querySelector('[data-bulk-caption]').hidden = !bulkDialog;
        dialog.querySelector('[data-delete-confirm-label]').textContent = bulkDialog ? 'راجعت عدد الوحدات ونطاق التحديد ووجهة الحذف وأؤكد حذف الوحدات المحددة.' : 'راجعت معرّف التأهيل ووجهة الحذف وأؤكد حذف هذا السجل.';
        dialog.showModal();
        form.querySelector('[name="destination"]').focus();
    }
    function renderOperation(value) {
        operation = value;
        if (!value) return;
        operationPanel.hidden = false;
        operationPanel.querySelector('[data-bulk-state]').textContent = {queued:'بانتظار التنفيذ', running:'قيد التنفيذ', completed:'مكتمل', partial:'نتيجة جزئية', failed:'توقفت المعالجة'}[value.status] || value.status;
        operationPanel.querySelector('[data-bulk-progress]').value = value.percent;
        operationPanel.querySelector('[data-bulk-summary]').textContent = `تمت معالجة ${value.processed} من ${value.total} · حُذفت: ${value.counts.completed} · متجاوزة: ${value.counts.skipped} · تعذّر حذفها: ${value.counts.failed} · تحتاج تحققًا: ${value.counts.needs_review}`;
        operationPanel.querySelector('[data-bulk-message]').textContent = value.message || '';
        operationPanel.querySelector('[data-start-bulk]').hidden = !value.can_start;
        const issues = operationPanel.querySelector('[data-bulk-issues]');
        issues.hidden = !value.issue_count;
        issues.querySelector('[data-bulk-issues-title]').textContent = value.issue_count > 50 ? `وحدات تحتاج المراجعة (أول 50 من ${value.issue_count})` : `وحدات تحتاج المراجعة (${value.issue_count || 0})`;
        const list = issues.querySelector('[data-bulk-issues-list]');
        list.replaceChildren();
        (value.issues || []).forEach(issue => {
            const item = document.createElement('li');
            item.textContent = `معرّف الحصر ${issue.reference} · معرّف التأهيل ${issue.object_id}: ${issue.message || 'راجع نتيجة الوحدة في سجل الحذف.'}`;
            list.appendChild(item);
        });
        renderSelection();
    }
    function schedulePoll(delay = 3000) {
        clearTimeout(pollTimer);
        if (operation?.active) pollTimer = setTimeout(poll, delay);
    }
    async function poll() {
        if (!operation?.active) return;
        try {
            const data = await request(page.dataset.bulkStatusUrl.replace('__operation__', operation.id), undefined, 'GET');
            const wasActive = operation.active;
            renderOperation(data.operation);
            if (wasActive && !operation.active) { location.reload(); return; }
            if (operation.can_start && Date.now() - lastWorkerStart >= 30000) {
                lastWorkerStart = Date.now();
                const restarted = await request(page.dataset.bulkStartUrl.replace('__operation__', operation.id), new FormData());
                renderOperation(restarted.operation);
            }
            schedulePoll();
        } catch (error) {
            operationPanel.querySelector('[data-bulk-message]').textContent = error.message;
            schedulePoll(15000);
        }
    }
    pageCheckbox?.addEventListener('change', () => {
        if (!pageCheckbox.checked) { clearSelection(); return; }
        checkboxes.forEach(checkbox => { if (!checkbox.disabled) checkbox.checked = true; });
        excluded.clear();
        renderSelection();
    });
    checkboxes.forEach(checkbox => checkbox.addEventListener('change', () => {
        if (filteredSelection) {
            if (checkbox.checked) excluded.delete(Number(checkbox.dataset.id));
            else excluded.add(Number(checkbox.dataset.id));
        }
        renderSelection();
    }));
    page.querySelector('[data-clear-selection]').addEventListener('click', clearSelection);
    page.querySelector('[data-select-filtered]').addEventListener('click', () => {
        filteredSelection = true; excluded.clear();
        checkboxes.forEach(checkbox => { if (!checkbox.disabled) checkbox.checked = true; });
        renderSelection();
    });
    page.querySelector('[data-delete-selected]').addEventListener('click', async () => {
        if (busy || !selectedCount() || operation?.active) return;
        const message = document.getElementById('reh-maintenance-message');
        busy = true; renderSelection();
        page.querySelector('[data-delete-selected]').textContent = 'جارٍ تجهيز التحديد…';
        checkboxes.forEach(checkbox => { checkbox.disabled = true; }); pageCheckbox.disabled = true;
        try {
            const data = await request(page.dataset.bulkPreviewUrl, JSON.stringify({scope:filteredSelection ? 'filtered' : 'selected', filters:JSON.parse(page.dataset.bulkFilters),
                units:checkboxes.filter(checkbox => checkbox.checked).map(checkbox => ({id:Number(checkbox.dataset.id), revision:checkbox.dataset.revision})), excluded:[...excluded]}));
            form.reset(); bulkDialog = true; form.action = page.dataset.bulkDeleteUrl; form.elements._method.value = 'POST';
            form.elements.selection_token.value = data.selection_token;
            dialog.querySelector('#reh-unit-delete-title').textContent = `حذف ${data.total} وحدة`;
            dialog.querySelector('#reh-unit-delete-caption').textContent = `${data.scope === 'filtered' ? 'كل النتائج المطابقة للفلتر' : 'الوحدات المختارة من الصفحة'} · العدد المؤكد: ${data.total} وحدة`;
            showDialog();
        } catch (error) { message.textContent = error.message; message.hidden = false; message.scrollIntoView({block:'center'}); }
        finally { busy = false; page.querySelector('[data-delete-selected]').textContent = 'حذف المحدد'; checkboxes.forEach(checkbox => { checkbox.disabled = false; }); pageCheckbox.disabled = false; renderSelection(); }
    });
    page.querySelector('[data-start-bulk]').addEventListener('click', async event => {
        if (!operation) return;
        event.currentTarget.disabled = true;
        try { const data = await request(page.dataset.bulkStartUrl.replace('__operation__', operation.id), new FormData()); renderOperation(data.operation); schedulePoll(); }
        catch (error) { operationPanel.querySelector('[data-bulk-message]').textContent = error.message; }
        finally { page.querySelector('[data-start-bulk]').disabled = false; }
    });
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-delete-unit],[data-close-delete],[data-verify-deletion]');
        if (!button || busy) return;
        if (button.hasAttribute('data-close-delete')) { dialog.close(); return; }
        if (button.hasAttribute('data-verify-deletion')) {
            button.disabled = true;
            try { await request(button.dataset.verifyDeletion, new FormData()); location.reload(); }
            catch (error) { const message = document.getElementById('reh-maintenance-message'); message.textContent = error.message; message.hidden = false; message.scrollIntoView({block:'center'}); button.disabled = false; }
            return;
        }
        bulkDialog = false;
        form.reset();
        form.action = button.dataset.url;
        form.elements._method.value = 'DELETE';
        form.elements.revision.value = button.dataset.revision;
        dialog.querySelector('#reh-unit-delete-title').textContent = 'حذف الوحدة';
        dialog.querySelector('#reh-unit-delete-caption').textContent = `معرّف الحصر: ${button.dataset.reference} · معرّف التأهيل: ${button.dataset.objectId}`;
        showDialog();
    });
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    form.querySelectorAll('[name="destination"]').forEach(input => input.addEventListener('change', () => { dialog.querySelector('[data-delete-impact]').textContent = impact[form.elements.destination.value] || 'حدد مكان الحذف لمعرفة أثره.'; }));
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const payload = new FormData(form);
        setBusy(true); errorBox.hidden = true;
        try {
            const data = await request(form.action, payload);
            if (!bulkDialog) { location.reload(); return; }
            setBusy(false); dialog.close(); clearSelection(); renderOperation(data.operation); schedulePoll();
            operationPanel.scrollIntoView({block:'center'});
        }
        catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; dialog.querySelector('[data-refresh-deletion]').hidden = false; errorBox.scrollIntoView({block:'center'}); setBusy(false); }
    });
    if (location.hash === '#reh-maintenance-history') document.getElementById('reh-maintenance-history').open = true;
    page.querySelector('[data-open-deletion-history]')?.addEventListener('click', () => { document.getElementById('reh-maintenance-history').open = true; });
    renderOperation(JSON.parse(operationPanel.dataset.initial || 'null'));
    renderSelection();
    schedulePoll();
})();
