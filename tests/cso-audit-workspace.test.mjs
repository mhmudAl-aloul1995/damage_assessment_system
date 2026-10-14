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

before(async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/CsoAuditWorkspaceTest.php', '--filter=renders separate'], { cwd: root, timeout: 120000 });
    html = await readFile(join(tmpdir(), 'phc-cso-workspace-preview.html'), 'utf8');
    server = createServer(async (request, response) => {
        const url = new URL(request.url, origin);
        if (url.pathname === '/') {
            response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            response.end(html.replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin));
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

async function openWorkspace(width = 1600, height = 1800) {
    const page = await browser.newPage();
    page.setDefaultTimeout(7000);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    page.workspaceErrors = errors;
    await page.setViewport({ width, height });
    await page.setRequestInterception(true);
    page.on('request', request => request.url().startsWith(origin) ? request.continue() : request.abort());
    await page.goto(origin, { waitUntil: 'networkidle0' });
    await page.waitForFunction(() => document.querySelector('[data-filter-count="all"]')?.textContent !== '0');
    await page.evaluate(() => document.fonts.ready);
    await page.evaluate(() => {
        const modal = document.querySelector('#fieldEditModal');
        modal.addEventListener('shown.bs.modal', () => { modal.dataset.testOpen = 'true'; });
        modal.addEventListener('hide.bs.modal', () => { delete modal.dataset.testOpen; });
    });
    await page.$eval('.cso-detail-workspace', element => element.scrollIntoView({ block: 'start', inline: 'nearest' }));
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

test('record browsers keep independent selections and reading positions across tabs', async () => {
    const page = await openWorkspace();
    await openTab(page, 'organizations');
    assert.notEqual(await page.$eval('#cso_organizations_tab', element => getComputedStyle(element).borderBottomColor), await page.$eval('#cso_units_tab', element => getComputedStyle(element).borderBottomColor));
    assert.equal(await page.$$eval('#cso_organizations_pane [data-record-content]:not([hidden])', items => items.length), 1);
    await page.$eval('#cso_organizations_pane .cso-record-details', element => { element.scrollTop = 220; });
    await page.click('#cso_organizations_pane .cso-record-select:nth-child(2)');
    assert.match(await page.$eval('#cso_organizations_pane [data-record-content]:not([hidden]) .cso-child-title', element => element.textContent), /مركز الدعم/);
    await openTab(page, 'units');
    await page.click('#cso_units_pane .cso-record-step[data-step="1"]');
    assert.match(await page.$eval('#cso_units_pane [data-record-content]:not([hidden]) .cso-child-title', element => element.textContent), /غرفة الاستقبال/);
    await openTab(page, 'organizations');
    assert.match(await page.$eval('#cso_organizations_pane [data-record-content]:not([hidden]) .cso-child-title', element => element.textContent), /مركز الدعم/);
    await page.click('#cso_organizations_pane [data-record-content]:not([hidden]) .cso-record-step[data-step="-1"]');
    assert.equal(await page.$eval('#cso_organizations_pane .cso-record-details', element => element.scrollTop), 220);
    await page.$eval('#cso_organizations_pane .cso-record-details', element => { element.scrollTop = 0; });
    await page.$eval('.cso-detail-workspace', element => element.scrollIntoView({ block: 'start' }));
    await (await page.$('.cso-detail-workspace')).screenshot({ path: join(tmpdir(), 'phc-cso-workspace-desktop.png') });
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
    await page.select('#status_assignee', auditor);
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
