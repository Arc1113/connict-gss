import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

const password = process.env.GSS_E2E_PASSWORD;

test.skip(
    !password,
    'Set GSS_E2E_PASSWORD and point GSS_E2E_BASE_URL at a dedicated, freshly seeded QA database.',
);

test.describe.configure({ mode: 'serial' });

async function signIn(page, email) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(email);
    await page.getByLabel('Password').fill(password);
    await page.getByRole('button', { name: 'Open assigned workspace' }).click();
    await expect(page).not.toHaveURL(/\/login$/);
}

async function scanWithKeyboard(page, token) {
    const input = page.locator('#scan-token');

    await input.fill('');
    await input.pressSequentially(token, { delay: 4 });
    await input.press('Enter');
}

async function expectFocused(locator) {
    await expect(locator).toBeFocused();
}

async function expectNoAxeViolations(page) {
    const result = await new AxeBuilder({ page }).analyze();

    expect(
        result.violations.map(({ id, impact, nodes }) => ({
            id,
            impact,
            targets: nodes.map((node) => node.target),
        })),
    ).toEqual([]);
}

test('invalid credentials return keyboard focus to the email field', async ({
    page,
}) => {
    await page.goto('/login');
    const email = page.getByLabel('Email address');
    const passwordField = page.getByLabel('Password');

    await email.fill('admin@example.test');
    await passwordField.fill('not-the-qa-password');
    await Promise.all([
        page.waitForResponse(
            (response) =>
                response.url().endsWith('/login')
                && response.request().method() === 'POST',
        ),
        page.getByRole('button', { name: 'Open assigned workspace' }).click(),
    ]);

    await expect(page.getByRole('alert')).toContainText(
        'These credentials do not match our records.',
    );
    await expect(email).toBeFocused();
    await expect(passwordField).toHaveValue('');
    await expectNoAxeViolations(page);
});

test('administrator and data encoder receive only their assigned capabilities', async ({
    browser,
}) => {
    const adminContext = await browser.newContext();
    const encoderContext = await browser.newContext();
    const admin = await adminContext.newPage();
    const encoder = await encoderContext.newPage();

    try {
        await signIn(admin, 'admin@example.test');
        await signIn(encoder, 'encoder@example.test');

        for (const viewport of [
            { width: 1366, height: 768 },
            { width: 1536, height: 864 },
        ]) {
            await admin.setViewportSize(viewport);
            const sidebarFit = await admin.evaluate(() => {
                const navigation = document.querySelector('.app-sidebar-nav');
                const footer = document
                    .querySelector('.app-sidebar-footer')
                    .getBoundingClientRect();
                const lastLink = [...navigation.querySelectorAll('a')].at(-1);
                const lastBounds = lastLink.getBoundingClientRect();

                return {
                    allNavigationFits:
                        navigation.scrollHeight <= navigation.clientHeight,
                    lastDestination: lastLink.textContent.trim(),
                    lastDestinationVisible:
                        lastBounds.top >= 0 && lastBounds.bottom <= footer.top,
                };
            });

            expect(sidebarFit).toEqual({
                allNavigationFits: true,
                lastDestination: 'Connect devices',
                lastDestinationVisible: true,
            });
        }

        await admin.goto('/admin/users');
        await expect(admin.getByRole('heading', { name: 'Operator accounts' })).toBeVisible();

        await encoder.goto('/admin/students/import');
        await expect(
            encoder.getByRole('heading', { name: 'Import graduate records' }),
        ).toBeVisible();

        const forbidden = await encoder.goto('/admin/backups');
        expect(forbidden.status()).toBe(403);
        await expect(encoder.getByText('403')).toBeVisible();

        await admin.goto('/admin/students/1');
        await admin.getByLabel('Reviewer notes').fill(
            'Approved by the isolated Playwright rehearsal.',
        );
        admin.once('dialog', (dialog) => dialog.accept());
        await admin.getByRole('button', { name: 'Approve audio' }).click();
        await expect(admin.getByText('Approved audio ready')).toBeVisible();

        await admin.goto('/admin/backups');
        await admin.getByRole('button', { name: 'Create local backup' }).click();
        await expect(admin.getByText('Local backup completed and verified.')).toBeVisible();
        const latestBackup = admin.locator('.admin-table tbody tr').first();
        await expect(latestBackup).toContainText('completed');
        await expect(latestBackup).toContainText('passed');

        await latestBackup.getByRole('button', { name: 'Run verification' }).click();
        await expect(admin.getByText(/Disposable restore verified:/)).toBeVisible();
        await expect(latestBackup).toContainText('passed');
        await expectNoAxeViolations(admin);
    } finally {
        await adminContext.close();
        await encoderContext.close();
    }
});

test('USB scan, queue, stage commands, realtime updates, and refresh recovery remain atomic', async ({
    browser,
}) => {
    const scannerContext = await browser.newContext();
    const controlContext = await browser.newContext();
    const stageContext = await browser.newContext();
    const scanner = await scannerContext.newPage();
    const control = await controlContext.newPage();
    const stage = await stageContext.newPage();

    try {
        await signIn(scanner, 'scanner@example.test');
        await signIn(control, 'stage@example.test');
        await signIn(stage, 'stage@example.test');

        await Promise.all([
            scanner.goto('/scanner'),
            control.goto('/stage-control'),
            stage.goto('/stage'),
        ]);

        await expect(scanner.getByText('Live updates connected')).toBeVisible();
        await expect(control.getByText('Live updates connected')).toBeVisible();
        await expect(stage.getByText('Live', { exact: true })).toBeVisible();
        await expect(stage.getByRole('heading', { name: 'Welcome, graduates' })).toBeVisible();
        await expect(stage).toHaveScreenshot('stage-idle-1366x768.png', {
            animations: 'disabled',
        });

        const tokenInput = scanner.locator('#scan-token');
        await expectFocused(tokenInput);

        const fallback = scanner.locator('.scanner-fallback');
        const documentHeightBeforeFallback = await scanner.evaluate(
            () => document.documentElement.scrollHeight,
        );
        await fallback.locator('summary').click();
        await expect(scanner.getByLabel('Graduate name or student number')).toBeFocused();
        expect(
            await scanner.evaluate(
                () => document.documentElement.scrollHeight,
            ),
        ).toBe(documentHeightBeforeFallback);
        expect(
            await scanner.locator('.scanner-fallback-body').evaluate((tray) => {
                const bounds = tray.getBoundingClientRect();

                return bounds.top >= 0 && bounds.bottom <= window.innerHeight;
            }),
        ).toBe(true);
        await scanner.getByLabel('Graduate name or student number').fill('zz-no-match');
        await scanner.getByRole('button', { name: 'Search' }).click();
        await expect(
            scanner.getByText('No graduate matched this active-ceremony search.'),
        ).toBeVisible();
        await fallback.locator('summary').click();
        await expectFocused(tokenInput);

        await tokenInput.press('Enter');
        await expect(scanner.getByText('Nothing scanned')).toBeVisible();
        await expectFocused(tokenInput);

        await scanWithKeyboard(scanner, 'NOT-A-GRADUATE-TOKEN');
        await expect(scanner.getByText('QR code not recognized').first()).toBeVisible();
        await expect(tokenInput).toHaveValue('');
        await expectFocused(tokenInput);

        await scanWithKeyboard(scanner, 'GSS-PHASE3-A7K9M2Q4');
        await expect(scanner.getByText('Ana Marie dela Cruz').first()).toBeVisible();
        await expect(control.getByText('Ana Marie dela Cruz').first()).toBeVisible();
        await expect(stage.getByRole('heading', { name: 'Welcome, graduates' })).toBeVisible();

        let betaPosts = 0;
        scanner.on('request', (request) => {
            if (request.method() === 'POST' && request.url().endsWith('/scans')) {
                betaPosts += 1;
            }
        });
        await tokenInput.pressSequentially('GSS-PHASE3-B8L3N5R7', { delay: 2 });
        await tokenInput.press('Enter');
        await tokenInput.press('Enter');
        await tokenInput.press('Enter');
        await expect(control.getByText('Christopher Emmanuel Villanueva-Santos').first()).toBeVisible();
        expect(betaPosts).toBe(1);

        await scanWithKeyboard(scanner, 'GSS-PHASE3-A7K9M2Q4');
        await expect(scanner.getByText(/already.*queue/i).first()).toBeVisible();

        await scanWithKeyboard(scanner, 'GSS-PHASE3-C4P6S8V2');
        const gammaRow = control
            .locator('.stage-control-table-wrap tbody tr')
            .filter({ hasText: 'Ma. Lourdes P. Reyes' });
        await gammaRow.getByRole('button', { name: 'Skip' }).click();
        await expect(control.getByText('Skipped graduates')).toBeVisible();

        await scanWithKeyboard(scanner, 'GSS-PHASE3-C4P6S8V2');
        await expect(
            scanner.getByText('Graduate is on the skipped list').first(),
        ).toBeVisible();
        await control
            .locator('.stage-control-support li')
            .filter({ hasText: 'Ma. Lourdes P. Reyes' })
            .getByRole('button', { name: 'Return to queue' })
            .click();

        await control.getByRole('button', { name: 'Present next graduate' }).click();
        await expect(stage.getByRole('heading', { name: 'Ana Marie dela Cruz' })).toBeVisible();

        let replayPosts = 0;
        control.on('request', (request) => {
            if (request.method() === 'POST' && request.url().endsWith('/stage/replay')) {
                replayPosts += 1;
            }
        });
        await control.getByRole('button', { name: 'Replay announcement' }).dblclick();
        expect(replayPosts).toBe(1);

        await control.getByRole('button', { name: 'Show holding screen' }).click();
        await expect(stage.getByRole('heading', { name: 'Ceremony program continues' })).toBeVisible();
        await stage.reload();
        await expect(stage.getByRole('heading', { name: 'Ceremony program continues' })).toBeVisible();
        await control.getByRole('button', { name: 'Restore live display' }).click();
        await expect(stage.getByRole('heading', { name: 'Ana Marie dela Cruz' })).toBeVisible();

        const correctionTrigger = control.getByRole('button', {
            name: 'Correct wrong graduate',
        });
        await correctionTrigger.focus();
        await correctionTrigger.click();
        const cancel = control.getByRole('button', { name: 'Cancel' });
        const confirm = control.getByRole('button', {
            name: 'Return graduate to queue',
        });
        await expectFocused(cancel);
        await cancel.press('Tab');
        await expectFocused(confirm);
        await confirm.press('Tab');
        await expectFocused(cancel);
        await cancel.press('Escape');
        await expectFocused(correctionTrigger);

        let completeNextPosts = 0;
        control.on('request', (request) => {
            if (
                request.method() === 'POST' &&
                request.url().endsWith('/stage/complete-and-present-next')
            ) {
                completeNextPosts += 1;
            }
        });
        await control
            .getByRole('button', { name: 'Complete and present next' })
            .dblclick();
        await expect(
            stage.getByRole('heading', {
                name: 'Christopher Emmanuel Villanueva-Santos',
            }),
        ).toBeVisible();
        expect(completeNextPosts).toBe(1);

        await Promise.all([control.reload(), stage.reload(), scanner.reload()]);
        await expect(
            stage.getByRole('heading', {
                name: 'Christopher Emmanuel Villanueva-Santos',
            }),
        ).toBeVisible();
        await expect(control.getByText('Christopher Emmanuel Villanueva-Santos').first()).toBeVisible();
        await expectFocused(scanner.locator('#scan-token'));

        await expectNoAxeViolations(scanner);
        await expectNoAxeViolations(control);
        await expectNoAxeViolations(stage);
    } finally {
        await scannerContext.close();
        await controlContext.close();
        await stageContext.close();
    }
});

test('critical screens remain usable at supported desktop viewports', async ({ browser }) => {
    const scannerContext = await browser.newContext();
    const controlContext = await browser.newContext();
    const stageContext = await browser.newContext();
    const scanner = await scannerContext.newPage();
    const control = await controlContext.newPage();
    const stage = await stageContext.newPage();

    try {
        await signIn(scanner, 'scanner@example.test');
        await signIn(control, 'stage@example.test');
        await signIn(stage, 'stage@example.test');
        await Promise.all([
            scanner.goto('/scanner'),
            control.goto('/stage-control'),
            stage.goto('/stage'),
        ]);

        for (const viewport of [
            { width: 1366, height: 768 },
            { width: 1536, height: 864 },
            { width: 1920, height: 1080 },
        ]) {
            await Promise.all([
                scanner.setViewportSize(viewport),
                control.setViewportSize(viewport),
                stage.setViewportSize(viewport),
            ]);

            await expect(scanner.locator('#scan-token')).toBeInViewport();
            await expect(scanner.getByRole('button', { name: 'Process scan' })).toBeInViewport();
            await expect(
                control.getByRole('button', { name: 'Complete and present next' }),
            ).toBeInViewport();
            await expect(stage.locator('.stage-connection-status')).toBeInViewport();
            expect(
                await stage.evaluate(
                    () => document.documentElement.scrollHeight <= window.innerHeight,
                ),
            ).toBe(true);
        }
    } finally {
        await scannerContext.close();
        await controlContext.close();
        await stageContext.close();
    }
});

test('assisted automatic state remains readable, locked, and persistent', async ({
    browser,
}) => {
    const adminContext = await browser.newContext({
        viewport: { width: 1366, height: 768 },
    });
    const controlContext = await browser.newContext({
        viewport: { width: 1366, height: 768 },
    });
    const admin = await adminContext.newPage();
    const control = await controlContext.newPage();

    async function setPresentationMode(mode) {
        await admin.goto('/admin/ceremonies');
        await admin.getByRole('link', { name: 'Edit' }).first().click();
        await admin.locator('#presentation_mode').selectOption(mode);
        await admin.getByRole('button', { name: 'Save ceremony' }).click();
        await expect(admin).not.toHaveURL(/\/edit$/);
    }

    try {
        await signIn(admin, 'admin@example.test');
        await signIn(control, 'stage@example.test');
        await control.goto('/stage-control');
        await control.locator('.stage-control-loading').waitFor({
            state: 'detached',
        });

        const completeAndClear = control.getByRole('button', {
            name: 'Complete and clear',
        });
        if (await completeAndClear.isVisible()) {
            await completeAndClear.click();
            await expect(
                control.getByText('No graduate is currently presented'),
            ).toBeVisible();
        }

        await setPresentationMode('automatic');
        await control.reload();
        const automatic = control.locator('.stage-control-automatic-command');
        await expect(automatic).toContainText('Running — idle scans present');
        expect(
            await automatic.evaluate((cell) => {
                const bounds = cell.getBoundingClientRect();
                const status = cell.querySelector('span');
                const button = cell.querySelector('button').getBoundingClientRect();

                return (
                    bounds.top >= 0
                    && bounds.bottom <= window.innerHeight
                    && button.right <= window.innerWidth
                    && status.scrollWidth <= status.clientWidth
                );
            }),
        ).toBe(true);

        let automaticPosts = 0;
        control.on('request', (request) => {
            if (
                request.method() === 'POST'
                && request.url().endsWith('/stage/automatic/pause')
            ) {
                automaticPosts += 1;
            }
        });
        await control
            .getByRole('button', { name: 'Pause assisted automatic' })
            .dblclick();
        await expect(automatic).toContainText('Paused — scans wait in queue');
        expect(automaticPosts).toBe(1);

        await control.reload();
        await expect(automatic).toContainText('Paused — scans wait in queue');
        await control
            .getByRole('button', { name: 'Resume assisted automatic' })
            .click();
        await expect(automatic).toContainText('Running — idle scans present');
        await expectNoAxeViolations(control);
    } finally {
        if (!admin.isClosed()) {
            await setPresentationMode('controlled').catch(() => {});
        }
        await adminContext.close();
        await controlContext.close();
    }
});
