(() => {
    const element = id => document.getElementById(id);
    let token = null;
    let base;
    let page = 1;
    let lastPage = 1;
    let criteria;
    let busy = false;
    const labels = { fully_damaged: 'ضرر كلي', partially_damaged: 'ضرر جزئي', committee_review: 'بحاجة للجنة', no_damage: 'دون ضرر', unclassified: 'غير مصنف', destroyed: 'مدمر', severe: 'شديد', moderate: 'متوسط', minor: 'طفيف', pending: 'بانتظار التدقيق', not_completed: 'لم يكتمل', approved: 'معتمد', team_approved: 'معتمد من الفريق', undp_approved: 'معتمد من UNDP', needs_action: 'بحاجة لإجراء', rejected: 'مرفوض' };

    function status(message) { element('inquiry-status').textContent = message; }
    function reset() {
        token = null;
        criteria = null;
        element('inquiry-workspace').hidden = true;
        element('login-form').hidden = false;
        element('inquiry-results').replaceChildren();
        element('result-count').textContent = '';
        element('page-number').textContent = '';
    }
    async function api(path, method = 'GET', data) {
        if (!base) throw new Error('لم تكتمل تهيئة التطبيق. أعد فتح الصفحة.');
        if (!window.Capacitor?.nativePromise) throw new Error('افتح هذه الشاشة من تطبيق Android أو iPhone المثبت.');
        const response = await window.Capacitor.nativePromise('CapacitorHttp', 'request', {
            url: new URL(`api/v1/${path}`, base).href, method,
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
            ...(data ? { data } : {}), responseType: 'json', connectTimeout: 15000, readTimeout: 20000, disableRedirects: true,
        });
        if (response.status === 401) { reset(); throw new Error('انتهت الجلسة. سجّل الدخول مجددًا.'); }
        if (response.status === 403) throw new Error('حسابك لا يملك صلاحية الاستعلام عن هذا القطاع.');
        if (response.status === 404) throw new Error('خدمة الاستعلام غير متاحة على السيرفر. يلزم نشر تحديث API.');
        if (response.status === 422) throw new Error(method === 'POST' ? 'تحقق من البريد وكلمة المرور وحالة الحساب.' : 'تحقق من قيم البحث والفلاتر.');
        if (response.status === 429) throw new Error('طلبات كثيرة. انتظر دقيقة ثم حاول مجددًا.');
        if (response.status < 200 || response.status >= 300) throw new Error('تعذّر الوصول للخدمة. حاول مجددًا.');
        return typeof response.data === 'string' && response.data ? JSON.parse(response.data) : response.data;
    }
    async function run(action) {
        if (busy) return;
        busy = true;
        document.querySelectorAll('button').forEach(button => { button.disabled = true; });
        status('جارٍ الاتصال…');
        try { await action(); } catch (error) { status(error.message || 'تعذّر الاتصال بالسيرفر. تحقق من الإنترنت.'); }
        finally {
            busy = false;
            document.querySelectorAll('button').forEach(button => { button.disabled = false; });
            element('previous').disabled = !criteria || page <= 1;
            element('next').disabled = !criteria || page >= lastPage;
            element('search-submit').disabled = !element('sector').value;
        }
    }
    function renderRecord(record) {
        const card = document.createElement('article');
        card.className = 'inquiry-card';
        const title = document.createElement('h2');
        title.textContent = record.name || record.building_name || `سجل ${record.objectid ?? record.record_id}`;
        card.append(title);
        for (const text of [
            `رقم السجل: ${record.objectid ?? record.record_id}`,
            `الموقع: ${[record.municipality, record.neighborhood].filter(Boolean).join(' — ') || 'غير مسجل'}`,
            `الضرر: ${labels[record.damage_status] || record.damage_status}`,
            `العمل الميداني: ${record.field_completed ? 'مكتمل' : 'غير مكتمل'}`,
            `التدقيق: ${labels[record.audit_status] || record.audit_status}`,
        ]) { const line = document.createElement('p'); line.textContent = text; card.append(line); }
        const location = document.createElement('p');
        location.textContent = record.geometry ? 'موقع جغرافي مسجل في المنظومة' : 'لا يوجد موقع جغرافي مسجل';
        card.append(location);
        element('inquiry-results').append(card);
    }
    async function search(requestedPage) {
        element('inquiry-results').replaceChildren();
        element('result-count').textContent = '';
        const query = new URLSearchParams({ ...criteria.filters, page: requestedPage });
        const result = await api(`damage-assessment/${criteria.sector}?${query}`);
        page = result.current_page;
        lastPage = result.last_page;
        element('result-count').textContent = `${result.total} نتيجة`;
        element('page-number').textContent = `${page} / ${lastPage}`;
        result.data.forEach(renderRecord);
        status(result.total ? 'تم تحديث النتائج من السيرفر.' : 'لا توجد نتائج مطابقة ضمن صلاحيات حسابك.');
    }
    element('login-form').addEventListener('submit', event => {
        event.preventDefault();
        run(async () => {
            const form = new FormData(event.target);
            const result = await api('auth/login', 'POST', { email: form.get('email'), password: form.get('password'), device_name: 'PHC Mobile Inquiry' });
            token = result.access_token;
            event.target.reset();
            element('login-form').hidden = true;
            element('inquiry-workspace').hidden = false;
            const sectors = await api('damage-assessment/sectors');
            element('sector').replaceChildren();
            sectors.data.forEach(sector => { const option = document.createElement('option'); option.value = sector.key; option.textContent = sector.title; element('sector').append(option); });
            status(sectors.data.length ? 'اختر القطاع وأدخل الاسم أو الرقم ثم اضغط بحث.' : 'لا توجد قطاعات متاحة لحسابك.');
        });
    });
    element('search-form').addEventListener('submit', event => {
        event.preventDefault();
        if (busy) return;
        criteria = { sector: element('sector').value, filters: { search: element('record-search').value.trim(), municipality: element('municipality').value.trim(), neighborhood: element('neighborhood').value.trim() } };
        run(() => search(1));
    });
    element('previous').addEventListener('click', () => run(() => search(page - 1)));
    element('next').addEventListener('click', () => run(() => search(page + 1)));
    element('logout').addEventListener('click', () => run(async () => {
        try { await api('auth/logout', 'POST'); status('تم تسجيل الخروج.'); }
        finally { reset(); }
    }));
    fetch('app-config.json').then(response => { if (!response.ok) throw new Error(); return response.json(); }).then(config => {
        base = new URL(config.serverUrl);
        if (base.hostname !== '213.6.135.115' || !['http:', 'https:'].includes(base.protocol)) { base = null; throw new Error(); }
    }).catch(() => status('تعذّر تحميل إعدادات السيرفر. أعد فتح التطبيق.'));
})();
