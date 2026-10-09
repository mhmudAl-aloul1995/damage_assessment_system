import assert from 'node:assert/strict';
import { test, before, after } from 'node:test';
import { createServer } from 'node:http';
import { readFile, mkdir } from 'node:fs/promises';
import { resolve, extname, sep } from 'node:path';
import puppeteer from 'puppeteer';

const root = resolve('mobile-shell');
let server;
let browser;
let origin;
const config = JSON.parse(await readFile(resolve(root, 'app-config.json'), 'utf8'));

before(async () => {
    server = createServer(async (request, response) => {
        const pathname = decodeURIComponent(new URL(request.url, 'http://localhost').pathname);
        const target = resolve(root, '.' + (pathname === '/' ? '/index.html' : pathname));
        if (!target.startsWith(root + sep)) {
            response.writeHead(403).end();
            return;
        }
        try {
            const types = { '.html': 'text/html', '.js': 'text/javascript', '.css': 'text/css', '.json': 'application/json', '.png': 'image/png', '.svg': 'image/svg+xml', '.woff': 'font/woff' };
            const content = await readFile(target);
            response.writeHead(200, { 'Content-Type': types[extname(target)] ?? 'application/octet-stream' });
            response.end(content);
        } catch {
            response.writeHead(404).end();
        }
    });
    await new Promise(done => server.listen(0, '127.0.0.1', done));
    origin = `http://127.0.0.1:${server.address().port}`;
    browser = await puppeteer.launch({ headless: true, args: ['--disable-features=HttpsUpgrades'] });
    await mkdir('tmp/mobile-preview', { recursive: true });
});

after(async () => {
    await browser?.close();
    server?.closeAllConnections();
    await new Promise(done => server?.close(done));
});

test('Arabic home renders all shortcuts and maps without horizontal overflow', async () => {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    for (const width of [320, 390, 768]) {
        await page.setViewport({ width, height: 844, deviceScaleFactor: 1 });
        await page.goto(origin, { waitUntil: 'networkidle0' });
        await page.waitForSelector('.module');
        assert.equal(await page.$eval('html', element => element.dir), 'rtl');
        assert.equal(await page.$$eval('.module', elements => elements.length), 4);
        assert.equal(await page.$$eval('.map-link', elements => elements.length), 3);
        assert.equal(await page.$eval('[data-system-link]', element => element.href), config.serverUrl);
        assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
        await page.screenshot({ path: `tmp/mobile-preview/home-${width}.png`, fullPage: true });
    }
    assert.deepEqual(errors, []);
    await page.close();
});

test('section search handles Arabic spelling, Latin case and empty results', async () => {
    const page = await browser.newPage();
    await page.goto(origin, { waitUntil: 'networkidle0' });
    await page.type('#module-search', 'heks');
    assert.equal(await page.$$eval('.module:not([hidden])', elements => elements.length), 1);
    await page.$eval('#module-search', element => { element.value = 'الادارة'; element.dispatchEvent(new Event('input')); });
    assert.equal(await page.$$eval('.module:not([hidden])', elements => elements.length), 1);
    await page.$eval('#module-search', element => { element.value = 'غير موجود'; element.dispatchEvent(new Event('input')); });
    assert.equal(await page.$eval('#empty-search', element => element.hidden), false);
    await page.close();
});

test('offline recovery navigates back to the real server instead of reloading the error page', async () => {
    const page = await browser.newPage();
    await page.setViewport({ width: 390, height: 844 });
    await page.goto(`${origin}/offline.html`, { waitUntil: 'networkidle0' });
    await page.screenshot({ path: 'tmp/mobile-preview/offline.png', fullPage: true });
    assert.equal(await page.$eval('[data-system-link]', element => element.href), config.serverUrl);
    await page.setRequestInterception(true);
    page.on('request', request => new URL(request.url()).hostname === new URL(config.serverUrl).hostname
        ? request.respond({ status: 200, contentType: 'text/html', body: '<h1>Server reached</h1>' })
        : request.continue());
    await Promise.all([page.waitForNavigation(), page.click('[data-system-link]')]);
    assert.equal(new URL(page.url()).hostname, new URL(config.serverUrl).hostname);
    assert.equal(new URL(page.url()).pathname, new URL(config.serverUrl).pathname);
    assert.equal(await page.$eval('h1', element => element.textContent), 'Server reached');
    await page.close();
});

test('configuration failure keeps server links disabled and shows recovery guidance', async () => {
    const page = await browser.newPage();
    await page.setRequestInterception(true);
    page.on('request', request => request.url().endsWith('app-config.json')
        ? request.respond({ status: 503, body: '' }) : request.continue());
    await page.goto(origin, { waitUntil: 'networkidle0' });
    assert.equal(await page.$eval('[data-system-link]', element => element.hasAttribute('href')), false);
    assert.match(await page.$eval('#connection-state', element => element.textContent), /تعذّر/);
    await page.close();
});
