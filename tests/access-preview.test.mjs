import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { readFile } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { extname, join, resolve, sep } from 'node:path';
import { after, before, test } from 'node:test';
import puppeteer from 'puppeteer';

const root = resolve('.');
const origin = 'http://localhost';
let browser;

before(async () => {
    execFileSync('php', ['artisan', 'test', '--compact', 'tests/Feature/AccessPreviewTest.php', '--filter=renders the access preview role control'], {
        cwd: root, timeout: 120000,
    });
    browser = await puppeteer.launch({ headless: true, args: ['--disable-features=HttpsUpgrades'] });
});

after(async () => { await browser?.close(); });

async function openPreview(mode) {
    const page = await browser.newPage();
    const html = await readFile(join(tmpdir(), `phc-access-preview-${mode}.html`), 'utf8');
    await page.setRequestInterception(true);
    page.on('request', async request => {
        const url = new URL(request.url());
        if (url.origin === origin && url.pathname === '/access-preview') {
            await request.respond({ status: 200, contentType: 'text/html; charset=utf-8', body: html });
            return;
        }
        if (url.origin === origin && url.pathname.startsWith('/assets/')) {
            const target = resolve(root, '.' + decodeURIComponent(url.pathname));
            if (target.startsWith(resolve(root, 'assets') + sep)) {
                try {
                    const types = { '.js': 'application/javascript', '.css': 'text/css', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff2': 'font/woff2', '.woff': 'font/woff' };
                    await request.respond({ status: 200, contentType: types[extname(target)] ?? 'application/octet-stream', body: await readFile(target) });
                    return;
                } catch {}
            }
        }
        await request.abort();
    });
    await page.goto(`${origin}/access-preview`, { waitUntil: 'networkidle0' });
    await page.waitForSelector('#select2-preview-mode-container');
    return page;
}

async function chooseOption(page, id, value) {
    const label = await page.$eval(`#${id}`, (element, selectedValue) => [...element.options]
        .find(option => selectedValue === null ? option.value !== '' : option.value === selectedValue).textContent.trim(), value);
    await page.click(`#select2-${id}-container`);
    await page.waitForSelector('.select2-container--open .select2-results__option');
    for (const option of await page.$$('.select2-container--open .select2-results__option')) {
        if (await option.evaluate(element => element.textContent.trim()) === label) {
            await option.click();
            return;
        }
    }
    assert.fail(`Select2 option not found: ${label}`);
}

test('Select2 mode changes enable role selection and restore user mode', async () => {
    const page = await openPreview('user');
    try {
        assert.equal(await page.$eval('#preview-role', element => element.disabled), true);
        await chooseOption(page, 'preview-mode', 'role');
        await page.waitForFunction(() => !document.getElementById('preview-role').disabled);
        assert.equal(await page.$eval('#preview-role', element => element.required), true);

        await chooseOption(page, 'preview-role', null);
        assert.notEqual(await page.$eval('#preview-role', element => element.value), '');

        await chooseOption(page, 'preview-mode', 'user');
        await page.waitForFunction(() => document.getElementById('preview-role').disabled);
        assert.equal(await page.$eval('#preview-role', element => element.required), false);
        assert.equal(await page.$eval('#preview-role', element => new FormData(element.form).has('role_id')), false);
    } finally {
        await page.close();
    }
});

test('opening role mode directly starts with an enabled selectable role', async () => {
    const page = await openPreview('role');
    try {
        assert.equal(await page.$eval('#preview-role', element => element.disabled), false);
        assert.equal(await page.$eval('#preview-role', element => element.required), true);
        await chooseOption(page, 'preview-role', null);
        assert.notEqual(await page.$eval('#preview-role', element => element.value), '');
    } finally {
        await page.close();
    }
});
