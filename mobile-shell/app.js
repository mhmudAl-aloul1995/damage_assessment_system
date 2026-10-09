async function initialize() {
    const status = document.getElementById('connection-state');
    try {
        const response = await fetch('app-config.json');
        if (!response.ok) throw new Error('Configuration unavailable');
        const config = await response.json();
        const base = new URL(config.serverUrl);
        if (!['http:', 'https:'].includes(base.protocol)) throw new Error('Invalid server URL');
        const systemUrl = (path = '') => {
            const url = new URL(path, base);
            if (url.origin !== base.origin || !url.pathname.startsWith(base.pathname)) throw new Error('Invalid destination');
            return url.href;
        };
        document.querySelectorAll('[data-system-link]').forEach(link => {
            link.href = systemUrl();
            link.removeAttribute('aria-disabled');
        });
        const version = document.getElementById('version');
        if (version) version.textContent = `الإصدار ${config.version}`;
        const modules = document.getElementById('modules');
        if (modules) {
            for (const module of config.modules) {
                const link = document.createElement('a');
                link.className = 'module';
                link.href = systemUrl(module.path);
                link.dataset.search = `${module.title} ${module.description}`;
                const symbol = document.createElement('span');
                symbol.className = `module-symbol ${module.color}`;
                symbol.setAttribute('aria-hidden', 'true');
                symbol.textContent = module.symbol;
                const copy = document.createElement('span');
                copy.className = 'module-copy';
                const title = document.createElement('strong');
                title.textContent = module.title;
                const description = document.createElement('small');
                description.textContent = module.description;
                copy.append(title, description);
                const arrow = document.createElement('span');
                arrow.className = 'arrow';
                arrow.textContent = '‹';
                arrow.setAttribute('aria-hidden', 'true');
                link.append(symbol, copy, arrow);
                modules.append(link);
            }
            const count = document.getElementById('module-count');
            count.textContent = `${config.modules.length} أقسام`;
            const normalize = value => value.normalize('NFKC').toLowerCase().replace(/[أإآ]/g, 'ا').replace(/[\u064B-\u065F\u0640]/g, '').trim();
            document.getElementById('module-search').addEventListener('input', event => {
                let visible = 0;
                for (const link of modules.children) {
                    link.hidden = !normalize(link.dataset.search).includes(normalize(event.target.value));
                    if (!link.hidden) visible++;
                }
                count.textContent = `${visible} أقسام`;
                document.getElementById('empty-search').hidden = visible !== 0;
            });
            for (const map of config.maps) {
                const link = document.createElement('a');
                link.className = 'map-link';
                link.href = systemUrl(map.path);
                link.textContent = `${map.title} ↗`;
                document.getElementById('maps').append(link);
            }
        }
        const showConnectivity = () => {
            if (status) status.textContent = navigator.onLine ? 'يلزم اتصال بالسيرفر لعرض البيانات.' : 'الجهاز غير متصل بالإنترنت. تحقق من الشبكة ثم أعد المحاولة.';
        };
        window.addEventListener('online', showConnectivity);
        window.addEventListener('offline', showConnectivity);
        showConnectivity();
    } catch {
        if (status) {
            status.textContent = 'تعذّر تحميل إعدادات التطبيق. أغلق التطبيق وافتحه مجددًا.';
            status.classList.add('error');
        }
    }
}
initialize();
