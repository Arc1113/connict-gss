/* global document */
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseUrl = process.env.GSS_BROWSER_BASE_URL;
const password = process.env.GSS_BROWSER_PASSWORD;

if (!baseUrl || !password) {
    throw new Error('GSS_BROWSER_BASE_URL and GSS_BROWSER_PASSWORD are required.');
}

const browser = await chromium.launch({ headless: true });
const browserErrors = [];
const externalRequests = [];
const providerRequests = [];

function watch(page) {
    const localHost = new URL(baseUrl).hostname;
    page.on('pageerror', (error) => browserErrors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (
            message.type() === 'error'
            && !message.text().includes('WebSocket')
            && !message.text().includes('status of 422')
            && !message.text().includes('status of 409')
        ) {
            browserErrors.push(`console: ${message.text()}`);
        }
    });
    page.on('request', (request) => {
        const url = new URL(request.url());
        if (url.hostname !== localHost) externalRequests.push(request.url());
        if (/tts\.speech\.microsoft\.com|cognitiveservices\.azure\.com/i.test(request.url())) {
            providerRequests.push(request.url());
        }
    });
}

async function signIn(page, email) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Open assigned workspace' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

async function scan(page, token, expectedTitle) {
    await page.getByLabel('QR scan or manual token').fill(token);
    await page.getByRole('button', { name: 'Process scan' }).click();
    await page.getByRole('alert').getByText(expectedTitle, { exact: true }).waitFor();
}

async function assertHorizontalFit(page) {
    const fit = await page.evaluate(() => ({
        canScrollX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));
    assert.equal(fit.canScrollX, false, JSON.stringify(fit));
}

try {
    const scannerContext = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const scanner = await scannerContext.newPage();
    watch(scanner);
    await signIn(scanner, 'scanner@example.test');
    await scanner.goto(`${baseUrl}/scanner`, { waitUntil: 'networkidle' });
    await scanner.getByText('USB scanner ready', { exact: true }).waitFor();
    await scan(scanner, 'not-a-real-token', 'QR code not recognized');
    await scan(scanner, 'GSS-PHASE3-A7K9M2Q4', 'Graduate added to queue');
    await scan(scanner, 'GSS-PHASE3-A7K9M2Q4', 'Graduate is already in the queue');
    await scan(scanner, 'GSS-PHASE3-E2H6J8X4', 'Graduate added to queue');
    await scanner.screenshot({ path: '/tmp/enh-tts-8-offline-scanner.png' });
    await assertHorizontalFit(scanner);

    const controlContext = await browser.newContext({ viewport: { width: 1366, height: 768 } });
    const control = await controlContext.newPage();
    watch(control);
    await signIn(control, 'stage@example.test');
    await control.goto(`${baseUrl}/stage-control`, { waitUntil: 'networkidle' });
    await control.getByLabel('Waiting queue').getByText('Ana Marie dela Cruz', { exact: true }).waitFor();
    await control.getByRole('button', { name: 'Present next graduate' }).click();
    await control.getByLabel('Current graduate').getByText('Ana Marie dela Cruz', { exact: false }).waitFor();
    await control.getByRole('button', { name: 'Replay announcement' }).click();
    await control.screenshot({ path: '/tmp/enh-tts-8-offline-control-approved.png' });
    await assertHorizontalFit(control);

    const stageContext = await browser.newContext({ viewport: { width: 1920, height: 1080 } });
    const stage = await stageContext.newPage();
    watch(stage);
    await signIn(stage, 'stage@example.test');
    await stage.goto(`${baseUrl}/stage`, { waitUntil: 'networkidle' });
    await stage.getByText('Ana Marie dela Cruz', { exact: true }).waitFor();
    const audioResponse = stage.waitForResponse((response) => response.url().includes('/media/phase3/audio/student-01.wav'));
    await stage.getByRole('button', { name: 'Enable audio' }).click();
    assert.equal((await audioResponse).status(), 200);
    await stage.screenshot({ path: '/tmp/enh-tts-8-offline-stage-approved.png' });
    await assertHorizontalFit(stage);

    await control.getByRole('button', { name: 'Complete and present next' }).click();
    await control.getByText('Live announcer required', { exact: false }).waitFor();
    await control.screenshot({ path: '/tmp/enh-tts-8-offline-control-fallback.png' });
    await stage.reload({ waitUntil: 'networkidle' });
    await stage.getByText('Elena Alexandria-Cassandra Villanueva', { exact: true }).waitFor();
    assert.equal(await stage.locator('.stage-presentation').isVisible(), true);
    await stage.screenshot({ path: '/tmp/enh-tts-8-offline-stage-fallback.png' });
    await assertHorizontalFit(stage);

    await control.getByRole('button', { name: 'Complete and clear' }).click();
    await stage.reload({ waitUntil: 'networkidle' });
    await stage.getByRole('heading', { name: 'Welcome, graduates' }).waitFor();

    await scannerContext.close();
    await controlContext.close();
    await stageContext.close();

    assert.deepEqual(providerRequests, []);
    assert.deepEqual(externalRequests, []);
    assert.deepEqual(browserErrors, []);
    console.log(JSON.stringify({
        status: 'passed',
        checks: [
            'isolated-network role login and scanner readiness',
            'invalid and duplicate QR rejection',
            'HTTP queueing and presentation recovery without browser polling',
            'same-origin approved local audio fetch and replay command',
            'visual presentation with live-announcer fallback when audio is missing',
            'completion and idle-state recovery',
            'scanner, stage-control, and stage horizontal fit',
            'no Azure or external browser request',
        ],
    }, null, 2));
} finally {
    await browser.close();
}
