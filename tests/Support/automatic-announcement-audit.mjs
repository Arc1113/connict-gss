/* global document */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { chromium } from 'playwright';

const baseUrl = process.env.GSS_BROWSER_BASE_URL ?? 'http://127.0.0.1:8001';
const ceremonyId = process.env.GSS_AUDIT_CEREMONY_ID ?? '2';
const studentId = process.env.GSS_AUDIT_STUDENT_ID ?? '16';
const studentLabel = process.env.GSS_AUDIT_STUDENT_LABEL ?? 'Mobile Camera Graduate Two';
const email = process.env.GSS_BROWSER_EMAIL ?? 'admin@example.test';
const password = process.env.GSS_BROWSER_PASSWORD ?? readEnvironmentValue('GSS_SEED_PASSWORD');
const runFullFlow = process.env.GSS_AUDIT_FULL_FLOW === 'true';

if (!password) {
    throw new Error('GSS_SEED_PASSWORD or GSS_BROWSER_PASSWORD is required.');
}

const browser = await chromium.launch({ headless: true });
const browserErrors = [];
const networkErrors = [];

function readEnvironmentValue(name) {
    const line = readFileSync('.env', 'utf8')
        .split(/\r?\n/)
        .find((entry) => entry.startsWith(`${name}=`));

    return line?.slice(name.length + 1).replace(/^['"]|['"]$/g, '') ?? '';
}

function watch(page) {
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error' && !/WebSocket|Pusher/i.test(message.text())) {
            browserErrors.push(message.text());
        }
    });
    page.on('response', (response) => {
        if (response.status() >= 400) {
            networkErrors.push(`${response.status()} ${response.url()}`);
        }
    });
}

async function signIn(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Open assigned workspace' }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

async function fit(page) {
    return page.evaluate(() => ({
        width: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        height: document.documentElement.clientHeight,
        scrollHeight: document.documentElement.scrollHeight,
        horizontalOverflow: document.documentElement.scrollWidth > document.documentElement.clientWidth,
    }));
}

async function inspectAudio(audio) {
    await audio.waitFor();
    await audio.evaluate((element) => new Promise((resolve) => {
        if (element.readyState >= 1 || element.error) {
            resolve();
            return;
        }

        element.addEventListener('loadedmetadata', resolve, { once: true });
        element.addEventListener('error', resolve, { once: true });
        setTimeout(resolve, 5000);
    }));

    return audio.evaluate(async (element) => {
        let playError = null;
        try {
            element.muted = true;
            await element.play();
            await new Promise((resolve) => setTimeout(resolve, 250));
        } catch (error) {
            playError = error instanceof Error ? error.message : String(error);
        }

        const result = {
            currentSrc: element.currentSrc,
            duration: element.duration,
            errorCode: element.error?.code ?? null,
            networkState: element.networkState,
            paused: element.paused,
            playError,
            readyState: element.readyState,
        };
        element.pause();
        element.currentTime = 0;

        return result;
    });
}

async function inspectCandidateAudio(page) {
    const candidate = page.locator('.announcement-generation-list article').filter({
        has: page.locator('audio'),
    }).first();
    const audio = candidate.locator('audio');

    return inspectAudio(audio);
}

async function clickNativePlay(page, audio) {
    await audio.scrollIntoViewIfNeeded();
    const box = await audio.boundingBox();
    assert.ok(box, 'Audio controls must be visible.');
    await page.mouse.click(box.x + 18, box.y + (box.height / 2));
    await page.waitForFunction((element) => !element.paused, await audio.elementHandle());
    await page.mouse.click(box.x + 18, box.y + (box.height / 2));
    await page.waitForFunction((element) => element.paused, await audio.elementHandle());
}

try {
    const desktop = await browser.newContext({ viewport: { width: 1600, height: 900 } });
    const page = await desktop.newPage();
    watch(page);
    await signIn(page);

    await page.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}`, { waitUntil: 'networkidle' });
    await page.getByRole('link', { name: 'Announcement settings' }).waitFor();
    await page.getByRole('link', { name: 'Announcement batches' }).waitFor();
    const ceremonyFit = await fit(page);

    await page.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}/announcement-settings`, { waitUntil: 'networkidle' });
    await page.locator('legend').getByText('Template wording', { exact: true }).waitFor();
    await Promise.all([
        page.waitForResponse((response) => response.request().method() === 'POST'
            && response.url().endsWith('/announcement-settings/preview')),
        page.getByRole('button', { name: 'Preview fictional announcement' }).click(),
    ]);
    await page.getByRole('heading', { name: 'Announcement preview' }).waitFor();
    const settingsFit = await fit(page);
    await page.screenshot({ path: '/tmp/automatic-announcements-settings-desktop-viewport.png' });

    await page.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}/announcement-batches`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: /batch generation/i }).waitFor();
    await page.screenshot({ path: '/tmp/automatic-announcements-batches-desktop-viewport.png' });
    const initialBatchState = {
        completedLabels: await page.locator('.announcement-batch-card .admin-status').filter({ hasText: 'Preparation complete' }).count(),
        fit: await fit(page),
        selectionTableWidth: await page.locator('.announcement-batch-selection').evaluate((element) => ({
            clientWidth: element.clientWidth,
            scrollWidth: element.scrollWidth,
        })),
    };
    await page.screenshot({ path: '/tmp/automatic-announcements-batches-desktop.png', fullPage: true });

    let generatedBatchState = null;
    if (runFullFlow) {
        await page.getByLabel(`Select ${studentLabel}`).check();
        const generateBatch = page.getByRole('button', { name: 'Generate selected audio' });
        assert.equal(await generateBatch.isDisabled(), false);
        page.once('dialog', (dialog) => dialog.accept());
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST'
                && response.url().endsWith(`/ceremonies/${ceremonyId}/announcement-batches`)),
            generateBatch.click(),
        ]);
        await page.getByText('0 selected', { exact: true }).waitFor();
        const newestBatch = page.locator('.announcement-batch-card').first();
        for (let attempt = 0; attempt < 10; attempt += 1) {
            if (await newestBatch.getByText('Preparation complete', { exact: true }).isVisible()) break;
            await page.getByRole('button', { name: 'Refresh progress' }).click();
            await page.waitForTimeout(500);
        }
        await newestBatch.getByText('Preparation complete', { exact: true }).waitFor();
        generatedBatchState = await newestBatch.innerText();
    }

    await page.goto(`${baseUrl}/admin/students/${studentId}`, { waitUntil: 'networkidle' });
    await page.getByRole('heading', { name: 'Generation history' }).scrollIntoViewIfNeeded();
    const audioState = await inspectCandidateAudio(page);
    const candidateAudio = page.locator('.announcement-generation-list article audio').first();
    await clickNativePlay(page, candidateAudio);
    const reviewButtonState = {
        approveDisabled: await page.getByRole('button', { name: 'Approve candidate' }).first().isDisabled(),
        rejectDisabled: await page.getByRole('button', { name: 'Reject candidate' }).first().isDisabled(),
    };
    await page.screenshot({ path: '/tmp/automatic-announcements-review-desktop-viewport.png' });
    await page.screenshot({ path: '/tmp/automatic-announcements-review-desktop.png', fullPage: true });

    await page.route('**/announcement-candidates/*', (route) => route.fulfill({
        body: 'ID3-not-playable',
        contentType: 'audio/mpeg',
        status: 200,
    }));
    await page.reload({ waitUntil: 'networkidle' });
    await page.getByText('Audio unavailable. Regenerate or replace this clip before approval.').waitFor();
    const corruptAudioApproveDisabled = await page.getByRole('button', { name: 'Approve candidate' }).first().isDisabled();
    await page.unroute('**/announcement-candidates/*');
    await page.reload({ waitUntil: 'networkidle' });
    await page.locator('.announcement-generation-list .admin-audio-player-status').filter({ hasText: 'Ready to review' }).first().waitFor();

    let approvedAudioState = null;
    if (runFullFlow) {
        page.once('dialog', (dialog) => dialog.accept());
        await Promise.all([
            page.waitForResponse((response) => response.request().method() === 'POST'
                && response.url().endsWith('/approve')),
            page.getByRole('button', { name: 'Approve candidate' }).first().click(),
        ]);
        await page.locator('.admin-media-detail').getByText('Approved', { exact: true }).waitFor();
        const activeAudio = page.locator('.admin-media-detail audio');
        approvedAudioState = await inspectAudio(activeAudio);
        await clickNativePlay(page, activeAudio);
        await page.screenshot({ path: '/tmp/automatic-announcements-approved-desktop-viewport.png' });
    }
    const desktopStudentFit = await fit(page);
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
    await mobilePage.locator('legend').getByText('Template wording', { exact: true }).waitFor();
    await mobilePage.screenshot({ path: '/tmp/automatic-announcements-settings-mobile-viewport.png' });
    const mobileSettingsFit = await fit(mobilePage);
    await mobilePage.goto(`${baseUrl}/admin/ceremonies/${ceremonyId}/announcement-batches`, { waitUntil: 'networkidle' });
    await mobilePage.getByRole('heading', { name: /batch generation/i }).waitFor();
    await mobilePage.screenshot({ path: '/tmp/automatic-announcements-batches-mobile-viewport.png' });
    await mobilePage.screenshot({ path: '/tmp/automatic-announcements-batches-mobile.png', fullPage: true });
    const mobileBatchFit = await fit(mobilePage);
    await mobilePage.goto(`${baseUrl}/admin/students/${studentId}`, { waitUntil: 'networkidle' });
    await mobilePage.getByRole('heading', { name: 'Generation history' }).scrollIntoViewIfNeeded();
    await mobilePage.screenshot({ path: '/tmp/automatic-announcements-review-mobile-viewport.png' });
    await mobilePage.screenshot({ path: '/tmp/automatic-announcements-review-mobile.png', fullPage: true });
    const mobileStudentFit = await fit(mobilePage);
    await mobile.close();

    assert.equal(browserErrors.length, 0, browserErrors.join('\n'));
    assert.equal(networkErrors.length, 0, networkErrors.join('\n'));
    assert.equal(corruptAudioApproveDisabled, true);
    assert.equal(ceremonyFit.horizontalOverflow, false);
    assert.equal(settingsFit.horizontalOverflow, false);
    assert.equal(initialBatchState.fit.horizontalOverflow, false);
    assert.equal(desktopStudentFit.horizontalOverflow, false);
    assert.equal(mobileSettingsFit.horizontalOverflow, false);
    assert.equal(mobileBatchFit.horizontalOverflow, false);
    assert.equal(mobileStudentFit.horizontalOverflow, false);
    assert.equal(audioState.errorCode, null);
    assert.equal(audioState.playError, null);
    assert.ok(audioState.duration > 0);
    console.log(JSON.stringify({
        approvedAudioState,
        audioState,
        browserErrors,
        corruptAudioApproveDisabled,
        ceremonyFit,
        desktopStudentFit,
        generatedBatchState,
        initialBatchState,
        mobileBatchFit,
        mobileSettingsFit,
        mobileStudentFit,
        networkErrors,
        reviewButtonState,
        settingsFit,
    }, null, 2));
} finally {
    await browser.close();
}
