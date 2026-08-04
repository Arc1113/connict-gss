/* global document */

import { chromium } from 'playwright';
import process from 'node:process';

const baseUrl = process.env.GSS_APP_URL ?? 'https://127.0.0.1';
const email = process.env.GSS_BROWSER_EMAIL;
const password = process.env.GSS_BROWSER_PASSWORD;
const studentId = process.env.GSS_BROWSER_STUDENT_ID;
const approveId = process.env.GSS_BROWSER_APPROVE_ID;
const rejectId = process.env.GSS_BROWSER_REJECT_ID;

if (!email || !password || !studentId || !approveId || !rejectId) {
    throw new Error('ENH-TTS-5 browser fixture environment is incomplete.');
}

const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    headless: true,
});
const errors = [];
const realtimeWarnings = [];
const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
});
const page = await context.newPage();
page.on('pageerror', (error) => errors.push(error.message));
page.on('console', (message) => {
    if (message.type() !== 'error') return;
    if (message.text().includes('WebSocket connection')) {
        realtimeWarnings.push(message.text());
        return;
    }
    errors.push(message.text());
});

try {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /open assigned workspace/i }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
    await page.goto(`${baseUrl}/admin/students/${studentId}`, { waitUntil: 'networkidle' });

    const approvalCard = page.locator('.announcement-generation-list article').filter({
        hasText: `Candidate #${approveId}`,
    });
    await approvalCard.getByRole('button', { name: 'Approve candidate' }).waitFor();
    await approvalCard.locator('textarea').fill('Browser approval verified.');
    page.once('dialog', (dialog) => dialog.accept());
    await approvalCard.getByRole('button', { name: 'Approve candidate' }).click();
    await page.getByText('Candidate approved and promoted to active stage audio.').waitFor();
    await page.locator('.announcement-generation-list article').filter({
        hasText: `Candidate #${approveId}`,
    }).getByText('Current active audio').waitFor();

    const rejectionCard = page.locator('.announcement-generation-list article').filter({
        hasText: `Candidate #${rejectId}`,
    });
    await rejectionCard.locator('textarea').fill('Browser rejection verified.');
    await rejectionCard.getByRole('button', { name: 'Reject candidate' }).click();
    await page.getByText('Candidate rejected. Active audio was not changed.').waitFor();
    await page.locator('.announcement-generation-list article').filter({
        hasText: `Candidate #${rejectId}`,
    }).getByText('Review note: Browser rejection verified.').waitFor();

    const fit = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
    }));
    if (fit.scrollWidth > fit.clientWidth) {
        throw new Error(`Horizontal overflow: ${JSON.stringify(fit)}`);
    }
    if (errors.length > 0) {
        throw new Error(`Browser errors: ${JSON.stringify(errors)}`);
    }

    await page.locator('.announcement-workspace').screenshot({
        path: '/tmp/enh-tts5-review.png',
    });
    process.stdout.write(JSON.stringify({
        status: 'passed',
        fit,
        realtimeWarningCount: realtimeWarnings.length,
    }));
} finally {
    await context.close();
    await browser.close();
}
