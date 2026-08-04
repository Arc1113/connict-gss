import { chromium } from 'playwright';
import process from 'node:process';

const baseUrl = process.env.GSS_APP_URL ?? 'http://127.0.0.1:8000';
const password = process.env.GSS_BROWSER_PASSWORD;
const studentId = process.env.GSS_BROWSER_STUDENT_ID;
const generationId = process.env.GSS_BROWSER_GENERATION_ID;
const desktopScreenshot = process.env.GSS_DESKTOP_SCREENSHOT ?? '/tmp/enh-tts4-desktop.png';
const mobileScreenshot = process.env.GSS_MOBILE_SCREENSHOT ?? '/tmp/enh-tts4-mobile.png';

if (!password || !studentId || !generationId) {
    throw new Error('GSS browser password, student ID, and generation ID are required.');
}

const browser = await chromium.launch({
    executablePath: '/usr/bin/google-chrome',
    headless: true,
});
const browserErrors = [];

async function login(page) {
    await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
    await page.getByLabel('Email').fill('tts4.encoder@example.test');
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: /open assigned workspace/i }).click();
    await page.waitForURL((url) => !url.pathname.endsWith('/login'));
}

function observe(page) {
    page.on('pageerror', (error) => browserErrors.push(error.message));
    page.on('console', (message) => {
        if (message.type() === 'error') browserErrors.push(message.text());
    });
}

const context = await browser.newContext({
    viewport: { width: 1440, height: 900 },
    ignoreHTTPSErrors: true,
});
const page = await context.newPage();
observe(page);

try {
    await login(page);
    await page.goto(`${baseUrl}/admin/students/${studentId}`, {
        waitUntil: 'networkidle',
    });

    await page.getByRole('heading', { name: 'Automated announcement candidate' }).waitFor();
    await page.getByText('Provider disabled').waitFor();
    await page.getByRole('heading', { name: 'Announcement audio' }).waitFor();
    await page.getByText('Private candidates remain separate from active ceremony audio.').waitFor();

    const generateButton = page.getByRole('button', { name: 'Generate candidate' });
    if (!(await generateButton.isDisabled())) {
        throw new Error('Generate candidate should be disabled while Azure is disabled.');
    }

    await page.getByRole('button', { name: 'Preview announcement' }).click();
    await page.getByText('Fictional Browser Graduate', { exact: false }).first().waitFor();

    await page.locator('#award_name').fill('Browser Leadership Award');
    await page.locator('#spoken_text').fill('the Browser Leadership Award');
    await page.getByRole('button', { name: 'Add award' }).click();
    const awardRow = page.locator('.announcement-award-list > li').filter({
        hasText: 'Browser Leadership Award',
    });
    await awardRow.waitFor();
    await awardRow.getByRole('button', { name: 'Edit' }).click();
    await page.locator('#award_name').fill('Browser Leadership Distinction');
    await page.getByLabel('Include in spoken announcement').uncheck();
    await page.getByRole('button', { name: 'Save award' }).click();
    const editedAward = page.locator('.announcement-award-list > li').filter({
        hasText: 'Browser Leadership Distinction',
    });
    await editedAward.getByText('Not spoken').waitFor();
    await editedAward.getByRole('button', { name: /move .* up/i }).click();

    const candidateResponse = await page.request.get(
        `${baseUrl}/admin/students/${studentId}/announcement-candidates/${generationId}`,
    );
    if (!candidateResponse.ok()) {
        throw new Error(`Protected candidate returned ${candidateResponse.status()}.`);
    }
    if (!candidateResponse.headers()['cache-control']?.includes('no-store')) {
        throw new Error('Protected candidate response is missing no-store caching.');
    }

    await page.locator('.announcement-workspace').screenshot({
        path: desktopScreenshot,
    });
    const desktopFit = await page.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        workspaceWidth: document.querySelector('.announcement-workspace')?.getBoundingClientRect().width,
    }));
    if (desktopFit.scrollWidth > desktopFit.clientWidth) {
        throw new Error(`Desktop horizontal overflow: ${JSON.stringify(desktopFit)}`);
    }

    page.once('dialog', (dialog) => dialog.accept());
    await editedAward.getByRole('button', { name: 'Remove' }).click();
    await editedAward.waitFor({ state: 'detached' });

    const mobileContext = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true,
        ignoreHTTPSErrors: true,
    });
    const mobilePage = await mobileContext.newPage();
    observe(mobilePage);
    await login(mobilePage);
    await mobilePage.goto(`${baseUrl}/admin/students/${studentId}`, {
        waitUntil: 'networkidle',
    });
    await mobilePage.getByRole('heading', { name: 'Automated announcement candidate' }).scrollIntoViewIfNeeded();
    await mobilePage.locator('.announcement-workspace').screenshot({ path: mobileScreenshot });
    const mobileFit = await mobilePage.evaluate(() => ({
        clientWidth: document.documentElement.clientWidth,
        scrollWidth: document.documentElement.scrollWidth,
        awardActionsWidth: document.querySelector('.announcement-award-actions')?.getBoundingClientRect().width,
    }));
    await mobileContext.close();

    if (mobileFit.scrollWidth > mobileFit.clientWidth) {
        throw new Error(`Mobile horizontal overflow: ${JSON.stringify(mobileFit)}`);
    }

    if (browserErrors.length > 0) {
        throw new Error(`Browser errors: ${JSON.stringify(browserErrors)}`);
    }

    process.stdout.write(JSON.stringify({
        status: 'passed',
        desktopFit,
        mobileFit,
        desktopScreenshot,
        mobileScreenshot,
    }));
} finally {
    await context.close();
    await browser.close();
}
