import assert from 'node:assert/strict';
import { test, before, after } from 'node:test';
import { createServer } from 'node:http';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { resolve, extname, sep, join } from 'node:path';
import { tmpdir } from 'node:os';
import puppeteer from 'puppeteer';

const root = resolve('.');
const fixturePath = join(tmpdir(), 'phc-cso-filter-preview.html');
const dataRequests = [];
let server;
let browser;
let origin;
let html;

const rows = [1001, 1002].map((id, index) => ({
    selection: `<input type="checkbox" class="form-check-input inf-audit-row-check" value="${id}">`,
    objectid: id,
    field_engineer: 'محمد عبد الرحمن',
    organization_name: index === 0 ? 'مؤسسة الأمل' : 'جمعية التنمية المجتمعية',
    building_name: 'المقر الرئيسي',
    municipalitie: 'غزة',
    neighborhood: 'الرمال',
    building_damage_status: 'ضرر جزئي',
    audit_status: `<span class="badge badge-light-primary">${index === 0 ? 'مقبول' : 'مسند'}</span>`,
    auditor: 'أحمد محمود',
    actions: '<button type="button" class="btn btn-sm btn-light-primary">فتح التدقيق</button>',
}));

before(async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/CsoAuditFiltersTest.php', '--filter=renders labeled'], { cwd: root, timeout: 120000 });
    html = await readFile(fixturePath, 'utf8');
    server = createServer(async (request, response) => {
        const url = new URL(request.url, origin);
        if (url.pathname === '/') {
            response.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            response.end(html.replaceAll('http://localhost', origin).replaceAll('http:\\/\\/localhost', origin));
            return;
        }
        if (url.pathname.endsWith('/inf-audit/cso/data')) {
            dataRequests.push(url.searchParams);
            const matches = rows.filter(row => (!url.searchParams.get('objectid') || String(row.objectid) === url.searchParams.get('objectid'))
                && (!url.searchParams.get('status') || (url.searchParams.get('status') === 'accepted' ? row.objectid === 1001 : row.objectid === 1002)));
            response.writeHead(200, { 'Content-Type': 'application/json' });
            response.end(JSON.stringify({ draw: Number(url.searchParams.get('draw')), recordsTotal: 2, recordsFiltered: matches.length, data: matches }));
            return;
        }
        if (url.pathname.startsWith('/assets/')) {
            const target = resolve(root, '.' + decodeURIComponent(url.pathname));
            if (!target.startsWith(resolve(root, 'assets') + sep)) {
                response.writeHead(403).end();
                return;
            }
            try {
                const types = { '.css': 'text/css', '.js': 'text/javascript', '.png': 'image/png', '.jpg': 'image/jpeg', '.svg': 'image/svg+xml', '.woff': 'font/woff', '.woff2': 'font/woff2', '.ttf': 'font/ttf' };
                response.writeHead(200, { 'Content-Type': types[extname(target)] ?? 'application/octet-stream' });
                response.end(await readFile(target));
            } catch {
                response.writeHead(404).end();
            }
            return;
        }
        response.writeHead(200, { 'Content-Type': 'application/json' });
        response.end(JSON.stringify({ data: [], message: 'تم الإسناد بنجاح.' }));
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

async function openAuditPage(width, height) {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setViewport({ width, height });
    await page.setRequestInterception(true);
    page.on('request', request => request.url().startsWith(origin) ? request.continue() : request.abort());
    await page.goto(origin, { waitUntil: 'networkidle0' });
    try {
        await page.waitForFunction(() => document.querySelectorAll('#inf_cso_table .inf-audit-row-check').length === 2, { timeout: 10000 });
    } catch {
        throw new Error('Table did not load: ' + JSON.stringify(errors));
    }
    await page.evaluate(() => document.fonts.ready);
    return page;
}

test('desktop filters apply on submit, show removable chips and reset the results', async () => {
    const page = await openAuditPage(1600, 1100);
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    assert.equal(await page.$eval('#cso_advanced_filters', element => getComputedStyle(element).display), 'none');
    await page.$eval('.cso-audit-workspace', element => element.scrollIntoView());
    await (await page.$('.cso-audit-workspace')).screenshot({ path: join(tmpdir(), 'phc-cso-filters-desktop.png') });
    const beforeTyping = dataRequests.length;
    await page.type('#filter_objectid', '1001');
    assert.equal(dataRequests.length, beforeTyping);
    await page.click('#cso_filters_form button[type="submit"]');
    await page.waitForFunction(() => document.querySelectorAll('#inf_cso_table .inf-audit-row-check').length === 1);
    assert.equal(dataRequests.at(-1).get('objectid'), '1001');
    assert.match(await page.$eval('#active_filters', element => element.textContent), /1001/);
    await page.click('#active_filters button');
    await page.waitForFunction(() => document.querySelectorAll('#inf_cso_table .inf-audit-row-check').length === 2);
    assert.equal(await page.$eval('#filter_objectid', element => element.value), '');

    await page.click('.cso-filter-toggle');
    await page.waitForFunction(() => document.querySelector('#cso_advanced_filters').classList.contains('show'));
    await page.select('#filter_auditor', await page.$eval('#filter_auditor option:nth-child(2)', element => element.value));
    await page.click('#cso_filters_form button[type="submit"]');
    await page.waitForFunction(() => document.querySelector('#advanced_filters_count').textContent === '1');
    await page.click('#reset_filters_btn');
    await page.waitForFunction(() => document.querySelector('#active_filters').classList.contains('d-none'));
    assert.equal(await page.$eval('#filter_auditor', element => element.value), '');
    assert.deepEqual(errors, []);
    await page.close();
});

test('assignment is contextual and requires a selected record and an auditor', async () => {
    const page = await openAuditPage(1600, 1100);
    assert.equal(await page.$eval('#cso_assignment_bar', element => getComputedStyle(element).display), 'none');
    await page.click('#inf_audit_select_all');
    assert.equal(await page.$eval('#selected_records_count', element => element.textContent), '2');
    assert.equal(await page.$eval('#bulk_assign_btn', element => element.disabled), true);
    await page.select('#bulk_assign_engineer', await page.$eval('#bulk_assign_engineer option:nth-child(2)', element => element.value));
    assert.equal(await page.$eval('#bulk_assign_btn', element => element.disabled), false);
    await page.click('#clear_selection_btn');
    assert.equal(await page.$eval('#cso_assignment_bar', element => getComputedStyle(element).display), 'none');
    assert.equal(await page.$eval('#inf_audit_select_all', element => element.checked), false);
    await page.close();
});

test('date range filters submit both dates and clear the picker with their chip', async () => {
    const page = await openAuditPage(1600, 1100);
    await page.click('.cso-filter-toggle');
    await page.waitForFunction(() => document.querySelector('#cso_advanced_filters').classList.contains('show'));
    await page.$eval('#cso_survey_date_range', element => element._flatpickr.setDate(['2026-10-01', '2026-10-14'], true));
    const response = page.waitForResponse(response => response.url().includes('/inf-audit/cso/data'));
    await page.click('#cso_filters_form button[type="submit"]');
    await response;
    assert.equal(dataRequests.at(-1).get('from_date'), '2026-10-01');
    assert.equal(dataRequests.at(-1).get('to_date'), '2026-10-14');
    assert.equal(await page.$$eval('#active_filters button', elements => elements.length), 1);
    await page.click('#active_filters button');
    assert.equal(await page.$eval('#filter_from_date', element => element.value), '');
    assert.equal(await page.$eval('#filter_to_date', element => element.value), '');
    assert.equal(await page.$eval('#cso_survey_date_range', element => element._flatpickr.selectedDates.length), 0);

    const lightBackground = await page.$eval('.cso-filter-panel', element => getComputedStyle(element).backgroundColor);
    await page.evaluate(() => document.documentElement.setAttribute('data-bs-theme', 'dark'));
    assert.notEqual(await page.$eval('.cso-filter-panel', element => getComputedStyle(element).backgroundColor), lightBackground);
    await page.close();
});

test('mobile filters fit the viewport and remain usable when expanded', async () => {
    const page = await openAuditPage(390, 1100);
    await page.click('.cso-filter-toggle');
    await page.waitForFunction(() => document.querySelector('#cso_advanced_filters').classList.contains('show'));
    const dimensions = await page.$eval('.cso-filter-panel', element => ({ width: element.clientWidth, scrollWidth: element.scrollWidth }));
    assert.ok(dimensions.scrollWidth <= dimensions.width + 1, JSON.stringify(dimensions));
    for (const id of ['filter_objectid', 'cso_survey_date_range']) {
        const bounds = await page.$eval('#' + id, element => ({ left: element.getBoundingClientRect().left, right: element.getBoundingClientRect().right }));
        assert.ok(bounds.left >= 0 && bounds.right <= 390, JSON.stringify(bounds));
    }
    await page.$eval('.cso-audit-workspace', element => element.scrollIntoView({ block: 'start', inline: 'nearest' }));
    await page.screenshot({ path: join(tmpdir(), 'phc-cso-filters-mobile.png') });
    assert.ok(await page.$eval('.cso-audit-workspace', element => element.scrollWidth <= element.clientWidth + 1), 'The audit card must not overflow horizontally.');
    await page.close();
});
