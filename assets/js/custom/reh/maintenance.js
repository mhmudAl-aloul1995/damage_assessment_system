(() => {
    'use strict';
    const dialog = document.getElementById('reh-unit-delete-dialog');
    if (!dialog) return;
    const form = dialog.querySelector('form');
    const errorBox = dialog.querySelector('[data-delete-message]');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
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
    async function request(url, body) {
        const response = await fetch(url, {method:'POST', body, credentials:'same-origin', headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN':csrf}});
        const data = response.headers.get('content-type')?.includes('application/json') ? await response.json() : {};
        if (!response.ok) throw new Error(data.errors ? Object.values(data.errors).flat().join('\n') : data.message || 'تعذر إتمام العملية. حدّث الصفحة وتحقق من سجل الحذف قبل إعادة المحاولة.');
        return data;
    }
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
        form.reset();
        form.action = button.dataset.url;
        form.elements.revision.value = button.dataset.revision;
        dialog.querySelector('#reh-unit-delete-caption').textContent = `معرّف الحصر: ${button.dataset.reference} · معرّف التأهيل: ${button.dataset.objectId}`;
        dialog.querySelector('[data-delete-impact]').textContent = 'حدد مكان الحذف لمعرفة أثره.';
        errorBox.hidden = true;
        dialog.querySelector('[data-refresh-deletion]').hidden = true;
        dialog.showModal();
        form.querySelector('[name="destination"]').focus();
    });
    dialog.addEventListener('cancel', event => { if (busy) event.preventDefault(); });
    form.querySelectorAll('[name="destination"]').forEach(input => input.addEventListener('change', () => { dialog.querySelector('[data-delete-impact]').textContent = impact[form.elements.destination.value] || 'حدد مكان الحذف لمعرفة أثره.'; }));
    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (busy || !form.reportValidity()) return;
        const payload = new FormData(form);
        setBusy(true); errorBox.hidden = true;
        try { await request(form.action, payload); location.reload(); }
        catch (error) { errorBox.textContent = error.message; errorBox.hidden = false; dialog.querySelector('[data-refresh-deletion]').hidden = false; errorBox.scrollIntoView({block:'center'}); setBusy(false); }
    });
    if (location.hash === '#reh-maintenance-history') document.getElementById('reh-maintenance-history').open = true;
})();
