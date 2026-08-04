/* global document */
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseUrl = process.env.GSS_BROWSER_BASE_URL ?? 'http://127.0.0.1:8010';
const email = process.env.GSS_BROWSER_EMAIL;
const password = process.env.GSS_BROWSER_PASSWORD;
const [ceremonyId, completedBatchId, pausedBatchId, queuedBatchId] = process.argv.slice(2);

if (!ceremonyId || !completedBatchId || !pausedBatchId || !queuedBatchId || !email || !password) {
    throw new Error('Browser credentials and all ENH-TTS-7 fixture identifiers are required.');
}

const browser = await chromium.launch({ headless: true });
const errors = [];
const providerRequests = [];

async function signIn(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Open assigned workspace' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

function watch(page) {
    page.on('pageerror', (error) => errors.push(`pageerror: ${error.message}`));
    page.on('console', (message) => {
        if (
            message.type() === 'error'
            && !message.text().includes('WebSocket')
            && !message.text().includes('Pusher')
        ) {
            errors.push(`console: ${message.text()}`);
        }
    });
    page.on('request', (request) => {
        if (/tts\.speech\.microsoft\.com|cognitiveservices\.azure\.com/i.test(request.url())) {
            providerRequests.push(request.url());
        }
    });
}

function batchCard(page, batchId) {
    return page.locator('.announcement-batch-card').filter({ hasText: `Batch #${batchId}` });
}

async function assertHorizontalFit(page) {
    const fit = await page.evaluate(() => ({
        canScrollX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        width: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));
    assert.equal(fit.canScrollX, false, JSON.stringify(fit));
}

try {
    const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await desktop.newPage();
    watch(page);
    await signIn(page);
    await page.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}/announcement-batches`, { waitUntil: 'networkidle' });

    await page.getByRole('heading', { name: 'ENH TTS 7 Browser Ceremony batch generation' }).waitFor();
    await page.getByRole('heading', { name: 'Preparation boundary' }).waitFor();
    await page.getByText('Provider disabled').waitFor();
    await page.getByText('Generated candidates are never approved automatically', { exact: false }).waitFor();
    assert.equal(await page.getByRole('button', { name: 'Queue selected candidates' }).isDisabled(), true);
    assert.equal(await page.getByLabel('Select Blocked Browser').isDisabled(), true);
    await page.evaluate(() => globalThis.scrollTo(0, 0));
    await page.screenshot({ path: '/tmp/enh-tts-7-desktop-initial.png' });

    await page.getByRole('button', { name: 'Select first 3 ready' }).click();
    await page.getByText('3 selected', { exact: true }).waitFor();
    assert.match(await page.locator('.announcement-batch-estimate').innerText(), /proposed characters/);
    assert.equal(await page.getByRole('button', { name: 'Queue selected candidates' }).isDisabled(), true);
    await page.getByRole('button', { name: 'Clear' }).click();
    await page.getByText('0 selected', { exact: true }).waitFor();

    await page.getByLabel('Select Ana Browser').check();
    await page.getByLabel('Select Ben Browser').check();
    await page.getByText('2 selected', { exact: true }).waitFor();
    await page.screenshot({ path: '/tmp/enh-tts-7-desktop-selection.png' });

    const completed = batchCard(page, completedBatchId);
    const paused = batchCard(page, pausedBatchId);
    const queued = batchCard(page, queuedBatchId);
    await completed.getByText('3/3', { exact: true }).waitFor();
    await completed.getByText('completed', { exact: true }).waitFor();
    await paused.getByText('paused', { exact: true }).waitFor();
    assert.equal(await paused.getByRole('button', { name: 'Resume batch' }).isDisabled(), true);
    await completed.getByText('View deterministic item order').click();
    await completed.getByText('1. Ana Browser').waitFor();
    await completed.getByText('2. Ben Browser').waitFor();
    await completed.getByText('3. Cara Browser').waitFor();

    page.once('dialog', (dialog) => dialog.accept());
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST'
            && response.url().endsWith(`/announcement-batches/${queuedBatchId}/cancel`)),
        queued.getByRole('button', { name: 'Cancel queued items' }).click(),
    ]);
    await batchCard(page, queuedBatchId).locator('.admin-status').getByText('cancelled', { exact: true }).waitFor();
    await page.getByRole('button', { name: 'Refresh progress' }).click();
    await batchCard(page, queuedBatchId).locator('.admin-status').getByText('cancelled', { exact: true }).waitFor();
    await page.getByRole('heading', { name: 'Persistent batch history' }).scrollIntoViewIfNeeded();
    await page.screenshot({ path: '/tmp/enh-tts-7-desktop-history.png' });
    await assertHorizontalFit(page);
    await desktop.close();

    const mobile = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
    });
    const mobilePage = await mobile.newPage();
    watch(mobilePage);
    await signIn(mobilePage);
    await mobilePage.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}/announcement-batches`, { waitUntil: 'networkidle' });
    await mobilePage.getByRole('heading', { name: 'ENH TTS 7 Browser Ceremony batch generation' }).waitFor();
    await mobilePage.screenshot({ path: '/tmp/enh-tts-7-mobile-initial.png' });
    await mobilePage.getByRole('heading', { name: 'Persistent batch history' }).scrollIntoViewIfNeeded();
    await mobilePage.screenshot({ path: '/tmp/enh-tts-7-mobile-history.png' });
    await assertHorizontalFit(mobilePage);
    await mobile.close();

    assert.deepEqual(providerRequests, []);
    assert.deepEqual(errors, []);
    console.log(JSON.stringify({
        status: 'passed',
        checks: [
            'administrator login and batch workspace access',
            'preparation-only and no-auto-approval boundary',
            'provider-disabled start and resume guards',
            'ready-only select, clear, and individual selection cycles',
            'blocked graduate guard and character estimates',
            'completed, paused, and queued persistent histories',
            'deterministic item order and safe failure display',
            'queued-item cancellation and HTTP recovery refresh',
            'desktop and mobile horizontal fit',
            'no Azure request or unexpected browser error',
        ],
    }, null, 2));
} finally {
    await browser.close();
}
