import assert from 'node:assert/strict';
import { test, before, after } from 'node:test';
import { createServer } from 'node:http';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep, join } from 'node:path';
import { tmpdir } from 'node:os';
import puppeteer from 'puppeteer';

const root = resolve('.');
const fieldRequests = [];
const statusRequests = [];
let failNextEdit = false;
let server;
let browser;
let origin;
let html;
let emptyHtml;
let filesHtml;
let singleHtml;

before(async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/CsoAuditWorkspaceTest.php'], { cwd: root, timeout: 120000 });
    html = await readFile(join(tmpdir(), 'phc-cso-workspace-preview.html'), 'utf8');
    emptyHtml = await readFile(join(tmpdir(), 'phc-cso-workspace-empty-preview.html'), 'utf8');
    filesHtml = await readFile(join(tmpdir(), 'phc-cso-workspace-files-preview.html'), 'utf8');
    singleHtml = await readFile(join(tmpdir(), 'phc-cso-workspace-single-preview.html'), 'utf8');
    server = createServer(async (request, response) => {
        const url = new URL(request.url, origin);
        if (['/', '/empty', '/files', '/single'].includes(url.pathname)) {
            response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            const fixture = ({ '/empty': emptyHtml, '/files': filesHtml, '/single': singleHtml })[url.pathname] ?? html;
            response.end(fixture.replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin));
            return;
        }
        const imageMatch = url.pathname.match(/^\/cso-test-media\/(\d+)\.svg$/);
        if (imageMatch) {
            if (imageMatch[1] === '12') { response.writeHead(404).end(); return; }
            response.writeHead(200, { 'Content-Type': 'image/svg+xml' });
            const color = Number(imageMatch[1]) % 2 ? '#d7e6ed' : '#eadfd0';
            response.end(`<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="900" viewBox="0 0 1200 900"><rect width="1200" height="900" fill="${color}"/><rect y="680" width="1200" height="220" fill="#c0cac5"/><rect x="220" y="210" width="760" height="480" fill="#e9eceb"/><path d="M190 210L600 70L1010 210Z" fill="#76868d"/><g fill="#879da9"><rect x="300" y="310" width="150" height="140"/><rect x="750" y="310" width="150" height="140"/><rect x="520" y="470" width="160" height="220"/></g><text x="60" y="830" font-family="Arial" font-size="42" fill="#435560">Survey attachment ${imageMatch[1]} · Test image</text></svg>`);
            return;
        }
        if (['/phc/pwa-install.js', '/phc/background-sync.js'].includes(url.pathname)) {
            response.writeHead(200, { 'Content-Type': 'text/javascript' });
            response.end(await readFile(join(root, 'public', url.pathname.split('/').at(-1))));
            return;
        }
        if (request.method === 'POST') {
            let body = '';
            for await (const chunk of request) { body += chunk; }
            const payload = Object.fromEntries(new URLSearchParams(body));
            if (url.pathname.endsWith('/field-update')) {
                fieldRequests.push(payload);
                if (failNextEdit) {
                    failNextEdit = false;
                    response.writeHead(409, { 'Content-Type': 'application/json' });
                    response.end(JSON.stringify({ message: 'القيمة تغيرت أثناء التدقيق. أعد تحميل السجل.' }));
                    return;
                }
                await new Promise(done => setTimeout(done, 180));
                response.writeHead(200, { 'Content-Type': 'application/json' });
                response.end(JSON.stringify({
                    message: 'تم حفظ التعديل', raw_value: payload.field_value,
                    display_value: payload.field_name === 'weather' ? (payload.field_value === '1' ? 'مشمس' : 'غائم') : payload.field_value,
                    history: payload.field_value === payload.expected_value ? [] : [{
                        field_value: payload.field_value, old_value: payload.expected_value,
                        user_name: 'مدقق الاختبار', created_at: '2026-10-14 10:30', notes: payload.notes,
                    }],
                }));
                return;
            }
            if (url.pathname.endsWith('/status')) {
                statusRequests.push(payload);
                response.writeHead(200, { 'Content-Type': 'application/json' });
                response.end(JSON.stringify({ message: 'تم تحديث الحالة', assignment: { user_name: 'أحمد محمود', manager_name: 'مدقق الاختبار', updated_at: '2026-10-14 10:30' } }));
                return;
            }
        }
        if (url.pathname.startsWith('/assets/')) {
            const target = resolve(root, '.' + decodeURIComponent(url.pathname));
            if (!target.startsWith(resolve(root, 'assets') + sep)) { response.writeHead(403).end(); return; }
            try {
                const types = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf' };
                response.writeHead(200, { 'Content-Type': types[extname(target)] ?? 'application/octet-stream' });
                response.end(await readFile(target));
            } catch { response.writeHead(404).end(); }
            return;
        }
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ data: [] }));
    });
    await new Promise(done => server.listen(0, '127.0.0.1', done));
    origin = `http://127.0.0.1:${server.address().port}`;
    browser = await puppeteer.launch({ headless: true, args: ['--disable-features=HttpsUpgrades'] });
});

after(async () => {
    await browser?.close();
    server?.closeAllConnections();
    await new Promise(done => server?.close(done));
});

async function openWorkspace(width = 1600, height = 1800, path = '/') {
    const page = await browser.newPage();
    page.setDefaultTimeout(7000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.workspaceErrors = errors;
    await page.setViewport({ width, height });
    await page.setRequestInterception(true);
    page.on('request', request => request.url().startsWith(origin) ? request.continue() : request.abort());
    await page.goto(origin + path, { waitUntil: 'networkidle0' });
    await page.waitForFunction(() => document.querySelector('[data-filter-count="all"]')?.textContent !== '0');
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => {
        const modal = document.querySelector('#fieldEditModal');
        modal.addEventListener('shown.bs.modal', () => { modal.dataset.testOpen = 'true'; });
        modal.addEventListener('hide.bs.modal', () => { delete modal.dataset.testOpen; });
        const viewer = document.querySelector('#cso_attachment_viewer');
        viewer.addEventListener('shown.bs.modal', () => { viewer.dataset.testOpen = 'true'; });
        viewer.addEventListener('hide.bs.modal', () => { delete viewer.dataset.testOpen; });
    });
    await page.$eval('.cso-detail-workspace', element => window.scrollTo(0, element.getBoundingClientRect().top + window.scrollY - (document.querySelector('#kt_app_header')?.getBoundingClientRect().bottom ?? 0) - 8));
    return page;
}

async function openTab(page, kind) {
    await page.click(`#cso_${kind}_tab`);
    await page.waitForFunction(kind => document.querySelector(`#cso_${kind}_pane`).classList.contains('active'), {}, kind);
}

async function editField(page, selector, value) {
    await page.click(selector);
    await page.waitForFunction(() => document.querySelector('#fieldEditModal').dataset.testOpen === 'true');
    await page.$eval('#edit_field_value', (element, value) => { element.value = value; }, value);
    await page.click('#saveFieldEdit');
    await page.waitForFunction(() => getComputedStyle(document.querySelector('#fieldEditModal')).display === 'none' && !document.querySelector('.modal-backdrop'));
}

async function scrollRecord(page, selector, offset) {
    await page.$eval(selector, (element, offset) => {
        const readingTop = document.querySelector('#cso_decision_header').getBoundingClientRect().bottom + document.querySelector('.cso-workspace-tabs').getBoundingClientRect().height + 24;
        window.scrollTo(0, window.scrollY + element.getBoundingClientRect().top + offset - readingTop);
    }, offset);
}

async function recordReading(page, selector) {
    return page.$eval(selector, element => Math.max(0, document.querySelector('#cso_decision_header').getBoundingClientRect().bottom + document.querySelector('.cso-workspace-tabs').getBoundingClientRect().height + 24 - element.getBoundingClientRect().top));
}

test('record browsers keep independent selections and reading positions across tabs', async () => {
    const page = await openWorkspace();
    await openTab(page, 'organizations');
    assert.notEqual(await page.$eval('#cso_organizations_tab', element => getComputedStyle(element).borderBottomColor), await page.$eval('#cso_units_tab', element => getComputedStyle(element).borderBottomColor));
    assert.equal(await page.$$eval('#cso_organizations_pane [data-record-content]:not([hidden])', items => items.length), 1);
    await scrollRecord(page, '#cso_organizations_pane [data-record-content]:not([hidden])', 220);
    await page.click('#cso_organizations_pane .cso-record-select:nth-child(2)');
    assert.match(await page.$eval('#cso_organizations_pane [data-record-content]:not([hidden]) .cso-child-title', element => element.textContent), /مركز الدعم/);
    await openTab(page, 'units');
    await page.click('#cso_units_pane .cso-record-step[data-step="1"]');
    assert.match(await page.$eval('#cso_units_pane [data-record-content]:not([hidden]) .cso-child-title', element => element.textContent), /غرفة الاستقبال/);
    await openTab(page, 'organizations');
    assert.match(await page.$eval('#cso_organizations_pane [data-record-content]:not([hidden]) .cso-child-title', element => element.textContent), /مركز الدعم/);
    await page.click('#cso_organizations_pane [data-record-content]:not([hidden]) .cso-record-step[data-step="-1"]');
    assert.ok(Math.abs(await recordReading(page, '#cso_organizations_pane [data-record-content]:not([hidden])') - 220) < 2);
    await scrollRecord(page, '#cso_organizations_pane [data-record-content]:not([hidden])', 0);
    await page.$eval('.cso-detail-workspace', element => element.scrollIntoView({ block: 'start' }));
    await page.screenshot({ path: join(tmpdir(), 'phc-cso-workspace-desktop.png') });
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('search, edited and unanswered filters apply only to the open record and show an empty result state', async () => {
    const page = await openWorkspace();
    await page.click('.cso-field-filter[data-filter="edited"]');
    assert.equal(await page.$$eval('#cso_survey_pane [data-field-row]:not([hidden])', items => items.length), 1);
    const history = '#cso_survey_pane [data-field-row]:not([hidden]) .cso-history-toggle';
    const historyTarget = await page.$eval(history, element => element.dataset.bsTarget);
    await page.click(history);
    await page.waitForFunction(target => document.querySelector(target).classList.contains('show'), {}, historyTarget);
    assert.match(await page.$eval('#cso_survey_pane [data-field-row]:not([hidden]) .cso-field-history', element => element.textContent), /المقر السابق/);
    await openTab(page, 'units');
    assert.equal(await page.$$eval('#cso_units_pane [data-record-content]:not([hidden]) [data-field-row]:not([hidden])', items => items.length), 0);
    assert.equal(await page.$eval('#cso_units_pane [data-record-content]:not([hidden]) .cso-no-fields', element => element.hidden), false);
    await page.click('.cso-field-filter[data-filter="unanswered"]');
    const answeredVisible = await page.$$eval('#cso_units_pane [data-record-content]:not([hidden]) [data-field-row]:not([hidden])', items => items.some(item => item.dataset.hasAnswer === '1'));
    assert.equal(answeredVisible, false);
    await page.click('.cso-field-filter[data-filter="all"]');
    await page.type('#cso_field_search', 'قاعة التدريب');
    assert.equal(await page.$$eval('#cso_units_pane [data-record-content]:not([hidden]) [data-field-row]:not([hidden])', items => items.length), 1);
    await page.click('#cso_units_pane .cso-record-step[data-step="1"]');
    assert.equal(await page.$eval('#cso_units_pane [data-record-content]:not([hidden]) .cso-no-fields', element => element.hidden), false);
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('editing targets the selected child with its expected value and preserves zero as an answer', async () => {
    const page = await openWorkspace();
    await openTab(page, 'units');
    await page.click('#cso_units_pane .cso-record-step[data-step="1"]');
    const selector = '#cso_units_pane [data-record-content]:not([hidden]) [data-field-name="unit_name"]';
    const recordId = await page.$eval(selector, element => element.dataset.recordId);
    await editField(page, selector, '0');
    const request = fieldRequests.at(-1);
    assert.equal(request.table_type, 'cso_survey_unit_table');
    assert.equal(request.auditable_id, recordId);
    assert.equal(request.expected_value, 'غرفة الاستقبال');
    assert.equal(request.field_value, '0');
    assert.equal(await page.$eval(selector, element => element.dataset.rawValue), '0');
    assert.equal(await page.$eval(selector, element => element.closest('[data-field-row]').dataset.hasAnswer), '1');
    assert.equal(await page.$eval('#cso_units_pane [data-record-content]:not([hidden]) .cso-child-title', element => element.textContent), '0');
    const selectedName = await page.$eval('#cso_units_pane .cso-record-select.active .cso-record-select-name', element => element.textContent);
    assert.equal(selectedName, '0');
    await editField(page, selector, '<اسم جديد>');
    assert.equal(fieldRequests.at(-1).expected_value, '0');
    assert.equal(await page.$eval(selector, element => element.closest('[data-field-row]').querySelector('.cso-current-value').textContent), '<اسم جديد>');
    assert.equal(await page.$eval(selector, element => element.closest('[data-field-row]').querySelector('.cso-current-value').childElementCount), 0);
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('failed edits keep the original answer and notes available for correction and retry', async () => {
    const page = await openWorkspace();
    const selector = '#cso_survey_pane [data-field-name="building_name"]';
    await page.click(selector);
    await page.waitForFunction(() => document.querySelector('#fieldEditModal').dataset.testOpen === 'true');
    await page.$eval('#edit_field_value', element => { element.value = 'اسم مصحح'; });
    await page.type('#edit_notes', 'ملاحظة للمراجعة');
    failNextEdit = true;
    await page.click('#saveFieldEdit');
    await page.waitForFunction(() => !document.querySelector('#cso_edit_error').classList.contains('d-none'));
    assert.match(await page.$eval('#cso_edit_error', element => element.textContent), /القيمة تغيرت/);
    assert.equal(await page.$eval(selector, element => element.dataset.rawValue), 'المقر الرئيسي');
    assert.equal(await page.$eval('#edit_notes', element => element.value), 'ملاحظة للمراجعة');
    assert.equal(await page.$eval('#saveFieldEdit', element => element.disabled), false);
    await page.click('#saveFieldEdit');
    await page.waitForFunction(() => getComputedStyle(document.querySelector('#fieldEditModal')).display === 'none' && !document.querySelector('.modal-backdrop'));
    assert.equal(await page.$eval(selector, element => element.dataset.rawValue), 'اسم مصحح');
    assert.equal(fieldRequests.at(-1).notes, 'ملاحظة للمراجعة');
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('choice fields use their raw option and unchanged saves do not mark a field as edited', async () => {
    const page = await openWorkspace();
    const selector = '#cso_survey_pane [data-field-name="weather"]';
    await page.click(selector);
    await page.waitForFunction(() => document.querySelector('#fieldEditModal').dataset.testOpen === 'true');
    assert.equal(await page.$eval('#edit_field_select', element => element.value), '1');
    assert.equal(await page.$eval('#edit_current_value', element => element.textContent), 'مشمس');
    await page.click('#saveFieldEdit');
    await page.waitForFunction(() => getComputedStyle(document.querySelector('#fieldEditModal')).display === 'none' && !document.querySelector('.modal-backdrop'));
    assert.equal(await page.$eval(selector, element => element.closest('[data-field-row]').dataset.isEdited), '0');
    await page.click(selector);
    await page.waitForFunction(() => document.querySelector('#fieldEditModal').dataset.testOpen === 'true');
    await page.select('#edit_field_select', '2');
    await page.click('#saveFieldEdit');
    await page.waitForFunction(() => getComputedStyle(document.querySelector('#fieldEditModal')).display === 'none' && !document.querySelector('.modal-backdrop'));
    assert.equal(fieldRequests.at(-1).expected_value, '1');
    assert.equal(fieldRequests.at(-1).field_value, '2');
    assert.equal(await page.$eval(selector, element => element.dataset.value), 'غائم');
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('status decisions preserve assignment unless assigning and update the summary and button state', async () => {
    const page = await openWorkspace();
    await page.click('[data-status="accepted"]');
    await page.waitForFunction(() => document.querySelector('#cso_current_status').textContent === 'مقبول');
    assert.equal(statusRequests.at(-1).status, 'accepted');
    assert.equal(Object.hasOwn(statusRequests.at(-1), 'assigned_to'), false);
    assert.equal(await page.$eval('[data-status="accepted"]', element => element.disabled), true);
    const auditor = await page.$eval('#status_assignee option:nth-child(2)', element => element.value);
    await page.click('#cso_assignment_toggle');
    await page.waitForSelector('#cso_assignment_menu.show');
    await page.click('#cso_assignment_menu .select2-selection');
    const auditorOption = '#cso_assignment_menu .select2-results__option[role="option"][id$="-' + auditor + '"]';
    try {
        await page.waitForSelector(auditorOption, { visible: true });
    } catch (error) {
        await page.screenshot({ path: join(tmpdir(), 'phc-cso-assignment-debug.png') });
        const options = await page.$$eval('.select2-results__option', elements => elements.map(element => element.outerHTML));
        throw new Error('Auditor options unavailable: ' + JSON.stringify(options), { cause: error });
    }
    await page.click(auditorOption);
    assert.equal(await page.$eval('#status_assignee', element => element.value), auditor);
    await page.click('[data-status="assigned"]');
    await page.waitForFunction(() => document.querySelector('#cso_current_status').textContent === 'مسند');
    assert.equal(statusRequests.at(-1).assigned_to, auditor);
    assert.equal(await page.$eval('[data-status="assigned"]', element => element.disabled), true);
    assert.match(await page.$eval('#assignment_user_name', element => element.textContent), /أحمد محمود/);
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('mobile record browsing fits the viewport and dark mode follows the application theme', async () => {
    const page = await openWorkspace(390, 1100);
    await openTab(page, 'organizations');
    await page.click('#cso_organizations_pane .cso-record-select:nth-child(2)');
    for (const selector of ['.cso-detail-workspace', '#cso_organizations_pane .cso-record-details', '.cso-workspace-tabs', '.cso-field-toolbar']) {
        const dimensions = await page.$eval(selector, element => ({ width: element.clientWidth, scrollWidth: element.scrollWidth }));
        assert.ok(dimensions.scrollWidth <= dimensions.width + 1, selector + ': ' + JSON.stringify(dimensions));
    }
    const bounds = await page.$eval('#cso_organizations_pane .cso-record-details', element => ({ left: element.getBoundingClientRect().left, right: element.getBoundingClientRect().right }));
    assert.ok(bounds.left >= 0 && bounds.right <= 390, JSON.stringify(bounds));
    const lightBackground = await page.$eval('.cso-field-toolbar', element => getComputedStyle(element).backgroundColor);
    await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'dark'));
    assert.notEqual(await page.$eval('.cso-field-toolbar', element => getComputedStyle(element).backgroundColor), lightBackground);
    await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'light'));
    await page.$eval('.cso-detail-workspace', element => element.scrollIntoView({ block: 'start', inline: 'nearest' }));
    await page.screenshot({ path: join(tmpdir(), 'phc-cso-workspace-mobile.png') });
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('sticky decision and gallery remain visible without overlapping while the survey scrolls', async () => {
    const page = await openWorkspace(1600, 1000);
    await page.waitForFunction(() => document.querySelector('#cso_gallery_image').naturalWidth > 0);
    const alignment = await page.evaluate(() => {
        const data = document.querySelector('.cso-audit-data').getBoundingClientRect();
        const photos = document.querySelector('.cso-attachment-column').getBoundingClientRect();
        return { dataLeft: data.left, photoRight: photos.right };
    });
    assert.ok(alignment.photoRight < alignment.dataLeft, JSON.stringify(alignment));
    assert.equal(await page.$eval('#cso_decision_header', element => getComputedStyle(element).position), 'sticky');
    await page.evaluate(() => window.scrollBy(0, 40));
    await page.waitForFunction(() => Math.abs(document.querySelector('#cso_decision_header').getBoundingClientRect().top - parseFloat(getComputedStyle(document.querySelector('#cso_decision_header')).top)) < 2);
    const positions = await page.evaluate(() => ({
        headerBottom: document.querySelector('#cso_decision_header').getBoundingClientRect().bottom,
        photoTop: document.querySelector('.cso-attachment-column').getBoundingClientRect().top,
        photoBottom: document.querySelector('#cso_gallery_panel').getBoundingClientRect().bottom,
    }));
    assert.ok(positions.photoTop >= positions.headerBottom + 10, JSON.stringify(positions));
    assert.ok(positions.photoBottom <= 1000, JSON.stringify(positions));
    await scrollRecord(page, '#cso_survey_pane > [data-record-content]', 450);
    assert.ok(await recordReading(page, '#cso_survey_pane > [data-record-content]') > 400);
    const scroll = await page.$eval('.cso-gallery-scroll', element => ({ height: element.clientHeight, contentHeight: element.scrollHeight }));
    assert.ok(scroll.contentHeight > scroll.height, JSON.stringify(scroll));
    const topBeforeThumbScroll = await page.$eval('#cso_gallery_image', element => element.getBoundingClientRect().top);
    await page.$eval('.cso-gallery-scroll', element => { element.scrollTop = 100; });
    assert.equal(await page.$eval('#cso_gallery_image', element => element.getBoundingClientRect().top), topBeforeThumbScroll);
    await page.click('#cso_gallery_panel [data-cso-image-step="1"]');
    assert.match(await page.$eval('#cso_gallery_name', element => element.textContent), /2\.jpg/);
    await openTab(page, 'units');
    assert.match(await page.$eval('#cso_gallery_name', element => element.textContent), /2\.jpg/);
    await page.screenshot({ path: join(tmpdir(), 'phc-cso-sticky-gallery-desktop.png') });
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('sticky gaps hide underlying fields in light and dark themes on desktop and mobile', async () => {
    for (const width of [1600, 390]) {
        const page = await openWorkspace(width, 1000);
        await page.evaluate(() => window.scrollBy(0, 1000));
        for (const theme of ['light', 'dark']) {
            await page.evaluate(theme => document.documentElement.setAttribute('data-bs-theme', theme), theme);
            const coverage = await page.evaluate(() => {
                const header = document.querySelector('#cso_decision_header');
                const tabs = document.querySelector('.cso-workspace-tabs');
                const headerRect = header.getBoundingClientRect();
                const tabsRect = tabs.getBoundingClientRect();
                const points = [
                    { owner: header, x: tabsRect.left + tabsRect.width / 2, y: headerRect.top - 4 },
                    { owner: tabs, x: tabsRect.left + tabsRect.width / 2, y: (headerRect.bottom + tabsRect.top) / 2 },
                    { owner: header, x: headerRect.right - 2, y: headerRect.bottom - 2 },
                ];
                return points.map(({ owner, x, y }) => {
                    const hit = document.elementFromPoint(x, y);
                    const background = getComputedStyle(owner, '::before').backgroundColor;
                    return { covered: hit === owner || owner.contains(hit), opaque: !['transparent', 'rgba(0, 0, 0, 0)'].includes(background), hit: hit?.className, x, y };
                });
            });
            assert.ok(coverage.every(point => point.covered && point.opaque), JSON.stringify({ width, theme, coverage }));
        }
        await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'light'));
        await page.screenshot({ path: join(tmpdir(), `phc-cso-sticky-gaps-${width}.png`) });
        assert.deepEqual(page.workspaceErrors, []);
        await page.close();
    }
});

test('gallery viewer zooms, navigates, handles failed images and restores the reading position', async () => {
    const page = await openWorkspace(1600, 1000);
    await page.click('#cso_gallery_panel [data-cso-image-index="1"]');
    await scrollRecord(page, '#cso_survey_pane > [data-record-content]', 240);
    const position = await page.evaluate(() => window.scrollY);
    await page.click('#cso_gallery_panel [data-cso-open-gallery]');
    await page.waitForFunction(() => document.querySelector('#cso_attachment_viewer').dataset.testOpen === 'true');
    await page.waitForFunction(() => document.querySelector('#cso_viewer_image').naturalWidth > 0);
    assert.match(await page.$eval('#cso_viewer_name', element => element.textContent), /2\.jpg/);
    const originalWidth = await page.$eval('#cso_viewer_image', element => element.clientWidth);
    await page.click('#cso_zoom_in');
    assert.equal(await page.$eval('#cso_zoom_reset', element => element.textContent), '150%');
    assert.ok(await page.$eval('#cso_viewer_image', element => element.clientWidth) > originalWidth);
    await page.click('#cso_zoom_reset');
    assert.equal(await page.$eval('#cso_zoom_out', element => element.disabled), true);
    await page.keyboard.press('ArrowLeft');
    assert.match(await page.$eval('#cso_viewer_name', element => element.textContent), /3\.jpg/);
    await page.click('#cso_attachment_viewer [data-cso-image-index="11"]');
    await page.waitForFunction(() => !document.querySelector('#cso_viewer_error').hidden);
    await page.waitForFunction(() => document.querySelector('#cso_attachment_viewer [data-cso-image-index="11"] img').hidden);
    assert.equal(await page.$eval('#cso_attachment_viewer [data-cso-image-step="1"]', element => element.disabled), true);
    assert.equal(await page.$eval('#cso_zoom_in', element => element.disabled), true);
    await page.click('#cso_attachment_viewer [data-cso-image-step="-1"]');
    await page.waitForFunction(() => document.querySelector('#cso_viewer_image').complete && document.querySelector('#cso_viewer_image').naturalWidth > 0);
    assert.equal(await page.$eval('#cso_viewer_error', element => element.hidden), true);
    await page.click('#cso_attachment_viewer .btn-close');
    await page.waitForFunction(() => getComputedStyle(document.querySelector('#cso_attachment_viewer')).display === 'none' && !document.querySelector('.modal-backdrop'));
    const returned = await page.evaluate(() => window.scrollY);
    assert.deepEqual(returned, position);
    assert.equal(await page.$eval('#cso_gallery_panel [data-cso-open-gallery]', element => element === document.activeElement), true);
    assert.match(await page.$eval('#cso_gallery_name', element => element.textContent), /11\.jpg/);
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('mobile gallery opens full screen and the decision stays below the system header while scrolling', async () => {
    const page = await openWorkspace(390, 844);
    await page.evaluate(() => window.scrollBy(0, 900));
    const dimensions = await page.evaluate(() => {
        const header = document.querySelector('#cso_decision_header').getBoundingClientRect();
        const system = document.querySelector('#kt_app_header').getBoundingClientRect();
        return { headerTop: header.top, headerBottom: header.bottom, headerHeight: header.height, systemBottom: system.bottom, width: document.querySelector('#cso_audit_workspace').clientWidth, scrollWidth: document.querySelector('#cso_audit_workspace').scrollWidth };
    });
    assert.ok(dimensions.headerTop >= dimensions.systemBottom, JSON.stringify(dimensions));
    assert.ok(dimensions.headerBottom < 844 && dimensions.headerHeight < 190, JSON.stringify(dimensions));
    assert.ok(dimensions.scrollWidth <= dimensions.width + 1, JSON.stringify(dimensions));
    assert.equal(await page.$eval('.cso-attachment-column', element => getComputedStyle(element).display), 'none');
    const scrollPosition = await page.evaluate(() => window.scrollY);
    await page.screenshot({ path: join(tmpdir(), 'phc-cso-sticky-gallery-mobile.png') });
    await page.click('.cso-mobile-gallery-trigger');
    await page.waitForFunction(() => document.querySelector('#cso_attachment_viewer').dataset.testOpen === 'true');
    const viewerSize = await page.$eval('#cso_attachment_viewer .modal-dialog', element => ({ width: element.getBoundingClientRect().width, height: element.getBoundingClientRect().height }));
    assert.equal(viewerSize.width, 390);
    assert.equal(viewerSize.height, 844);
    await page.click('#cso_attachment_viewer [data-cso-image-step="1"]');
    assert.match(await page.$eval('#cso_viewer_name', element => element.textContent), /2\.jpg/);
    await page.screenshot({ path: join(tmpdir(), 'phc-cso-image-viewer-mobile.png') });
    await page.click('#cso_attachment_viewer .btn-close');
    await page.waitForFunction(() => getComputedStyle(document.querySelector('#cso_attachment_viewer')).display === 'none' && !document.querySelector('.modal-backdrop'));
    assert.equal(await page.evaluate(() => window.scrollY), scrollPosition);
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});

test('gallery empty and file-only states remain usable on mobile without image controls', async () => {
    for (const path of ['/empty', '/files']) {
        const page = await openWorkspace(390, 844, path);
        assert.equal(await page.$('#cso_gallery_image'), null);
        await page.click('.cso-mobile-gallery-trigger');
        await page.waitForFunction(() => document.querySelector('#cso_attachment_viewer').dataset.testOpen === 'true');
        assert.equal(await page.$('#cso_viewer_image'), null);
        assert.match(await page.$eval('#cso_attachment_viewer', element => element.textContent), /لا توجد صور مرفقة/);
        if (path === '/files') {
            assert.match(await page.$eval('#cso_attachment_viewer .cso-viewer-files a', element => element.href), /100\.pdf$/);
        }
        assert.deepEqual(page.workspaceErrors, []);
        await page.close();
    }
});

test('single image gallery keeps navigation disabled in both the sidebar and viewer', async () => {
    const page = await openWorkspace(1600, 1000, '/single');
    assert.equal(await page.$$eval('[data-cso-image-step]', buttons => buttons.every(button => button.disabled)), true);
    await page.click('#cso_gallery_panel [data-cso-open-gallery]');
    await page.waitForFunction(() => document.querySelector('#cso_attachment_viewer').dataset.testOpen === 'true');
    await page.keyboard.press('ArrowLeft');
    assert.equal(await page.$eval('#cso_attachment_viewer [data-cso-image-counter]', element => element.textContent), '1 من 1');
    assert.equal(await page.$$eval('[data-cso-image-step]', buttons => buttons.every(button => button.disabled)), true);
    assert.deepEqual(page.workspaceErrors, []);
    await page.close();
});
