/* global document */
import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseUrl = process.env.GSS_BROWSER_BASE_URL ?? 'http://127.0.0.1:8010';
const email = process.env.GSS_BROWSER_EMAIL;
const password = process.env.GSS_BROWSER_PASSWORD;
const ceremonyId = process.argv[2];

if (!ceremonyId || !email || !password) {
    throw new Error('GSS browser email, password, and fixture ceremony ID are required.');
}

const browser = await chromium.launch({ headless: true });
const errors = [];

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
            && !message.text().includes('status of 422')
        ) {
            errors.push(`console: ${message.text()}`);
        }
    });
}

try {
    const desktop = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await desktop.newPage();
    watch(page);
    await signIn(page);
    await page.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}/announcement-settings`, { waitUntil: 'networkidle' });

    await page.getByRole('heading', { name: 'ENH TTS 6 Browser Ceremony' }).waitFor();
    await page.getByText('Fictional data only').waitFor();
    await page.getByText('Maria Santos', { exact: false }).waitFor();
    await page.getByText('Provider disabled').waitFor();
    assert.equal(await page.getByRole('button', { name: 'Generate temporary samples' }).isDisabled(), true);
    await page.evaluate(() => globalThis.scrollTo(0, 0));
    await page.screenshot({ path: '/tmp/enh-tts-6-desktop-initial.png' });

    const awardsToggle = page.getByLabel('Include awards');
    await awardsToggle.uncheck();
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/preview') && response.status() === 200),
        page.getByRole('button', { name: 'Preview fictional announcement' }).click(),
    ]);
    assert.equal((await page.locator('.announcement-preview-copy').innerText()).includes('Leadership Excellence Award'), false);
    await awardsToggle.check();

    await page.getByLabel('Template name').fill('Browser verified settings');
    await page.getByLabel('Closing text').fill('Congratulations from the browser rehearsal');
    await page.getByLabel('Default voice').selectOption('fil-PH-BlessicaNeural');
    await page.getByLabel('Speaking rate (%)').fill('-10');
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/preview') && response.status() === 200),
        page.getByRole('button', { name: 'Preview fictional announcement' }).click(),
    ]);
    await page.getByText('Congratulations from the browser rehearsal', { exact: false }).waitFor();
    await page.screenshot({ path: '/tmp/enh-tts-6-desktop-preview.png' });

    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Publish version 2' }).click();
    await page.waitForLoadState('networkidle');
    await page.getByRole('button', { name: 'Publish version 3' }).waitFor();
    await page.getByText('Browser verified settings · version 2').waitFor();
    await page.getByText('Browser baseline · version 1').waitFor();

    await page.getByLabel('Speaking rate (%)').fill('-21');
    await Promise.all([
        page.waitForResponse((response) => response.url().endsWith('/preview') && response.status() === 422),
        page.getByRole('button', { name: 'Preview fictional announcement' }).click(),
    ]);
    await page.getByRole('alert').filter({ hasText: 'speaking rate' }).waitFor();
    await page.getByLabel('Speaking rate (%)').fill('-10');

    await page.getByRole('heading', { name: 'Published versions' }).scrollIntoViewIfNeeded();
    await page.screenshot({ path: '/tmp/enh-tts-6-desktop-history.png' });
    const desktopFit = await page.evaluate(() => ({
        canScrollX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        width: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));
    assert.equal(desktopFit.canScrollX, false, JSON.stringify(desktopFit));
    await desktop.close();

    const mobile = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
    });
    const mobilePage = await mobile.newPage();
    watch(mobilePage);
    await signIn(mobilePage);
    await mobilePage.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}/announcement-settings`, { waitUntil: 'networkidle' });
    await mobilePage.getByRole('heading', { name: 'ENH TTS 6 Browser Ceremony' }).waitFor();
    await mobilePage.screenshot({ path: '/tmp/enh-tts-6-mobile.png' });
    const mobileFit = await mobilePage.evaluate(() => ({
        canScrollX: document.documentElement.scrollWidth > document.documentElement.clientWidth,
        width: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));
    assert.equal(mobileFit.canScrollX, false, JSON.stringify(mobileFit));
    await mobile.close();

    assert.deepEqual(errors, []);
    console.log(JSON.stringify({
        status: 'passed',
        checks: [
            'administrator login and settings access',
            'fixed fictional preview and toggle cycle',
            'voice, pacing, and closing-text draft preview',
            'version 2 publication with version 1 history retained',
            'invalid pacing feedback',
            'provider-disabled comparison guard',
            'desktop and mobile horizontal fit',
            'no unexpected browser errors',
        ],
    }, null, 2));
} finally {
    await browser.close();
}
