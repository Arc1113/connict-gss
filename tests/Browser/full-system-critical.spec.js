import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import {
    prepareBrowserBackupFixture,
    preparePhaseThreeStageFixture,
} from '../Support/browserFixtures.js';

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

async function dispatchInstallPrompt(page) {
    await page.evaluate(() => {
        const event = new Event('beforeinstallprompt', { cancelable: true });
        Object.defineProperty(event, 'prompt', {
            configurable: true,
            value: () => Promise.resolve(),
        });
        Object.defineProperty(event, 'userChoice', {
            configurable: true,
            value: Promise.resolve({ outcome: 'dismissed', platform: 'web' }),
        });
        window.dispatchEvent(event);
    });
}

async function installWaitingServiceWorkerMock(context) {
    await context.addInitScript(() => {
        const registration = {
            waiting: {
                postMessage() {},
            },
            addEventListener() {},
        };

        Object.defineProperty(navigator, 'serviceWorker', {
            configurable: true,
            value: {
                controller: {},
                addEventListener() {},
                register: () => Promise.resolve(registration),
            },
        });
    });
}

async function expectNavigationGroups(page, present, absent = []) {
    const navigation = page.locator('.app-sidebar-nav');

    for (const label of present) {
        await expect(
            navigation.locator('h2', { hasText: label }),
        ).toBeVisible();
    }

    for (const label of absent) {
        await expect(
            navigation.locator('h2', { hasText: label }),
        ).toHaveCount(0);
    }
}

async function openFirstGraduateRecord(page) {
    await page
        .locator('.admin-table tbody tr')
        .first()
        .locator('th[scope="row"] a')
        .click();
}

async function clearStagePreflight(page) {
    const preflightHeading = page.getByRole('heading', {
        name: 'Check the screen before opening the curtain',
    });

    if (await preflightHeading.isVisible({ timeout: 500 }).catch(() => false)) {
        // The audience screen now runs its guarded preflight automatically as
        // soon as live state and audio are ready. Waiting for its completion
        // avoids racing that safe automatic transition with a second click.
        await expect(preflightHeading).toHaveCount(0, { timeout: 10_000 });
    }
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

test('stage suppresses workstation PWA install and update controls while other pages keep them', async ({
    browser,
}) => {
    test.setTimeout(45_000);

    const installContext = await browser.newContext();
    const updateContext = await browser.newContext();
    const installPage = await installContext.newPage();
    const updatePage = await updateContext.newPage();

    try {
        await signIn(installPage, 'stage@example.test');

        await installPage.goto('/dashboard');
        await dispatchInstallPrompt(installPage);
        await expect(
            installPage.getByText('Install this workstation app'),
        ).toBeVisible();

        await installPage.goto('/stage');
        await dispatchInstallPrompt(installPage);
        await expect(
            installPage.getByText('Install this workstation app'),
        ).toHaveCount(0);

        await installWaitingServiceWorkerMock(updateContext);

        await signIn(updatePage, 'stage@example.test');

        await updatePage.goto('/dashboard');
        await expect(
            updatePage.getByText('Application update ready'),
        ).toBeVisible();
        await expect(
            updatePage.getByRole('button', { name: 'Reload and update' }),
        ).toBeVisible();

        await updatePage.goto('/stage');
        await expect(
            updatePage.getByText('Application update ready'),
        ).toHaveCount(0);
        await expect(
            updatePage.getByRole('button', { name: 'Reload and update' }),
        ).toHaveCount(0);
    } finally {
        await installContext.close();
        await updateContext.close();
    }
});

test('working ceremony can differ from the live ceremony across admin navigation', async ({
    page,
}) => {
    const workingCeremonyName = `Browser Working Ceremony ${Date.now()}`;

    await signIn(page, 'admin@example.test');
    const liveCeremonyName = await page
        .locator('.app-toolbar-ceremony')
        .textContent();

    await page.goto('/admin/ceremonies/create');
    await page.getByLabel('Ceremony name').fill(workingCeremonyName);
    await page.getByLabel('Academic year').fill('2027-2028');
    await page.getByRole('button', { name: 'Create ceremony' }).click();

    await expect(
        page.getByRole('heading', { name: workingCeremonyName }),
    ).toBeVisible();

    const workingCeremonySelect = page.getByLabel('Working ceremony');

    await expect(workingCeremonySelect).toHaveValue(/^\d+$/);
    await expect(
        page.locator('.app-working-ceremony-switcher'),
    ).toContainText('Working ceremony');
    await expect(
        page.locator('.app-working-ceremony-switcher'),
    ).toContainText(workingCeremonyName);
    await expect(page.locator('.app-toolbar-ceremony')).toContainText(
        liveCeremonyName ?? '',
    );
    const selectedCeremonyId = await workingCeremonySelect.inputValue();

    await page.goto('/admin/students');

    await expect(page.getByLabel('Working ceremony')).toHaveValue(selectedCeremonyId);
    await expect(
        page.locator('.app-working-ceremony-switcher'),
    ).toContainText(workingCeremonyName);
    await expect(page.locator('.app-toolbar-ceremony')).toContainText(
        liveCeremonyName ?? '',
    );
});

test('unified overview keeps working preparation separate from the live ceremony', async ({
    page,
}) => {
    const workingCeremonyName = `Overview Working Ceremony ${Date.now()}`;

    await signIn(page, 'admin@example.test');
    const liveCeremonyName = await page
        .locator('.app-toolbar-ceremony')
        .textContent();

    await page.goto('/admin/ceremonies/create');
    await page.getByLabel('Ceremony name').fill(workingCeremonyName);
    await page.getByLabel('Academic year').fill('2027-2028');
    await page.getByRole('button', { name: 'Create ceremony' }).click();

    const workspaceNav = page.getByRole('navigation', {
        name: 'Ceremony workspace sections',
    });
    const workingCeremonyHref = await workspaceNav
        .getByRole('link', { name: 'Overview', exact: true })
        .getAttribute('href');

    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: 'Operations overview' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Working ceremony' })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Working ceremony' })).toContainText(
        workingCeremonyName,
    );
    await expect(page.getByRole('heading', { name: 'Live ceremony' })).toBeVisible();
    await expect(page.locator('.overview-live-ceremony')).toContainText(
        liveCeremonyName ?? '',
    );
    await expect(
        page.getByRole('link', { name: 'Continue preparation', exact: true }),
    ).toHaveAttribute('href', workingCeremonyHref ?? '');
    await expectNoAxeViolations(page);

    for (const viewport of [
        { width: 1366, height: 768 },
        { width: 1536, height: 864 },
        { width: 1920, height: 1080 },
        { width: 390, height: 844 },
    ]) {
        await page.setViewportSize(viewport);
        await expect(page.getByRole('heading', { name: 'Working ceremony' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Live ceremony' })).toBeVisible();
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
    }

    await page.goto('/admin');
    await expect(page).toHaveURL(/\/dashboard$/);
});

test('ceremony workspace opens with local navigation while working and live ceremony stay isolated', async ({
    page,
}) => {
    const workspaceCeremonyName = `Workspace Ceremony ${Date.now()}`;

    await signIn(page, 'admin@example.test');
    const liveCeremonyName = await page
        .locator('.app-toolbar-ceremony')
        .textContent();

    await page.goto('/admin/ceremonies/create');
    await page.getByLabel('Ceremony name').fill(workspaceCeremonyName);
    await page.getByLabel('Academic year').fill('2027-2028');
    await page.getByRole('button', { name: 'Create ceremony' }).click();

    await expect(
        page.getByRole('heading', { name: workspaceCeremonyName }),
    ).toBeVisible();
    await expect(page.locator('.ceremony-context-flags')).toContainText(
        'Working ceremony',
    );
    await expect(page.locator('.app-toolbar-ceremony')).toContainText(
        liveCeremonyName ?? '',
    );

    const workspaceNav = page.getByRole('navigation', {
        name: 'Ceremony workspace sections',
    });
    for (const label of [
        'Overview',
        'Setup',
        'Graduates',
        'Import',
        'QR & Media',
        'Announcements',
        'Stage Design',
        'Readiness',
    ]) {
        await expect(workspaceNav.getByRole('link', { name: label })).toBeVisible();
    }

    await workspaceNav.getByRole('link', { name: 'Setup' }).click();
    await expect(page).toHaveURL(/\/admin\/ceremonies\/\d+\/setup$/);
    await expect(page.getByRole('heading', { name: workspaceCeremonyName })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Setup', exact: true })).toBeVisible();

    await page.getByRole('link', { name: '← Ceremony overview' }).click();
    await expect(page).toHaveURL(/\/admin\/ceremonies\/\d+$/);
    await expect(page.getByRole('heading', { name: 'Preparation status' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('phase five preparation journey keeps the same working ceremony across workspace sections', async ({
    page,
}) => {
    const workingCeremonyName = `Phase Five Journey ${Date.now()}`;

    await signIn(page, 'admin@example.test');
    await page.goto('/admin/ceremonies/create');
    await page.getByLabel('Ceremony name').fill(workingCeremonyName);
    await page.getByLabel('Academic year').fill('2027-2028');
    await page.getByRole('button', { name: 'Create ceremony' }).click();

    const workspaceNav = () => page.getByRole('navigation', {
        name: 'Ceremony workspace sections',
    });
    await expect(page.getByRole('heading', { name: workingCeremonyName })).toBeVisible();
    await expect(workspaceNav()).toBeVisible();
    for (const label of ['Overview', 'Setup', 'Graduates', 'Import', 'QR & Media', 'Announcements', 'Stage Design', 'Readiness']) {
        await expect(workspaceNav().getByRole('link', { name: label, exact: true })).toBeVisible();
    }
    const workingCeremonyId = (await workspaceNav()
        .getByRole('link', { name: 'Overview' })
        .getAttribute('href'))?.match(/\/admin\/ceremonies\/(\d+)$/)?.[1];
    expect(workingCeremonyId).toBeTruthy();

    await workspaceNav().getByRole('link', { name: 'Graduates' }).click();
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}/graduates$`));
    await expect(page.getByRole('heading', { name: 'Graduates', exact: true })).toBeVisible();
    await expect(page.locator('.admin-readonly-field').first()).toContainText(
        workingCeremonyName,
    );
    await expectNoAxeViolations(page);

    const importHref = await page
        .getByRole('link', { name: 'Import graduates' })
        .getAttribute('href');
    expect(importHref).toMatch(new RegExp(`/admin/ceremonies/${workingCeremonyId}/import$`));
    await page.goto(importHref ?? '/admin/students/import');
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}/import$`));
    await expect(
        page.getByRole('heading', { name: 'Import graduate records' }),
    ).toBeVisible();
    await expect(page.locator('.admin-readonly-field').first()).toContainText(
        workingCeremonyName,
    );
    await expectNoAxeViolations(page);

    const graduatesHref = await page
        .getByRole('link', { name: 'Return to graduate records' })
        .getAttribute('href');
    expect(graduatesHref).toMatch(new RegExp(`/admin/ceremonies/${workingCeremonyId}/graduates$`));
    await page.goto(graduatesHref ?? '/admin/students');
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}/graduates$`));

    const qrHref = await page.getByRole('link', { name: 'QR preparation' }).getAttribute('href');
    expect(qrHref).toMatch(new RegExp(`/admin/ceremonies/${workingCeremonyId}/qr$`));
    await page.goto(qrHref ?? '/admin/students/qr-codes');
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}/qr`));
    await expect(page.getByRole('heading', { name: 'QR & Media', exact: true })).toBeVisible();
    await expect(page.locator('.admin-readonly-field').first()).toContainText(
        workingCeremonyName,
    );

    await page.goto(`/admin/ceremonies/${workingCeremonyId}/announcements`);
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}/announcements$`));
    await expect(page.getByRole('heading', { name: 'Announcements', exact: true })).toBeVisible();
    await expect(page.locator('.voice-workspace-summary')).toContainText(
        workingCeremonyName,
    );
    await expectNoAxeViolations(page);

    await page.goto(`/admin/ceremonies/${workingCeremonyId}/stage-design`);
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}/stage-design$`));
    await expect(page.getByRole('heading', { name: 'Current ceremony stage design' })).toBeVisible();

    await page.goto(`/admin/ceremonies/${workingCeremonyId}/readiness`);
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}/readiness$`));
    await expect(page.getByRole('heading', { name: 'Readiness sections' })).toBeVisible();
    await expectNoAxeViolations(page);

    await page.goto(`/admin/ceremonies/${workingCeremonyId}`);
    await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${workingCeremonyId}$`));
    await expect(page.getByRole('heading', { name: 'Preparation status' })).toBeVisible();
    await expectNoAxeViolations(page);
});

test('graduate detail keeps overview, academic, media, announcement, and history workflows connected', async ({
    page,
}) => {
    preparePhaseThreeStageFixture();

    await signIn(page, 'admin@example.test');
    await page.goto('/admin/ceremonies');
    await page
        .getByRole('row', { name: /Sample Graduation Ceremony/i })
        .getByRole('link', { name: 'Open workspace' })
        .click();
    await page
        .getByRole('navigation', { name: 'Ceremony workspace sections' })
        .getByRole('link', { name: 'Graduates', exact: true })
        .click();
    await openFirstGraduateRecord(page);

    const detailNav = page.getByRole('navigation', {
        name: 'Graduate detail sections',
    });
    await expect(detailNav.getByRole('link', { name: 'Overview' })).toHaveAttribute(
        'aria-current',
        'page',
    );
    await expect(page.getByRole('heading', { name: 'Readiness' })).toBeVisible();
    await expectNoAxeViolations(page);

    await detailNav.getByRole('link', { name: 'Academic' }).click();
    await expect(page.getByRole('heading', { name: 'Academic' })).toBeVisible();

    await detailNav.getByRole('link', { name: 'Media & QR' }).click();
    await expect(page.getByRole('heading', { name: 'Portrait' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'QR' })).toBeVisible();

    await detailNav.getByRole('link', { name: 'Announcement' }).click();
    await expect(
        page.getByRole('heading', { name: 'Announcement', exact: true }),
    ).toBeVisible();
    await page.reload();
    await expect(page).toHaveURL(/section=announcement/);
    await expect(
        page.getByRole('heading', { name: 'Announcement', exact: true }),
    ).toBeVisible();

    await detailNav.getByRole('link', { name: 'History' }).click();
    await expect(page.getByRole('heading', { name: 'Stage history' })).toBeVisible();
    await page.locator('.graduate-detail-header').getByRole('link').click();
    await expect(page.getByRole('heading', { name: 'Graduates' })).toBeVisible();
});

test('phase eight uses semantic parent links for direct-entry ceremony workspace pages', async ({
    browser,
}) => {
    preparePhaseThreeStageFixture();

    const discoveryContext = await browser.newContext();
    const discoveryPage = await discoveryContext.newPage();
    let ceremonyId;
    let studentId;

    try {
        await signIn(discoveryPage, 'admin@example.test');
        await discoveryPage.goto('/admin/ceremonies');
        await discoveryPage
            .getByRole('row', { name: /Sample Graduation Ceremony/i })
            .getByRole('link', { name: 'Open workspace' })
            .click();
        const graduatesHref = await discoveryPage
            .getByRole('navigation', { name: 'Ceremony workspace sections' })
            .getByRole('link', { name: 'Graduates', exact: true })
            .getAttribute('href');
        ceremonyId = graduatesHref?.match(/\/admin\/ceremonies\/(\d+)\/graduates$/)?.[1];
        await discoveryPage.goto(graduatesHref ?? '/admin/students');
        const graduateHref = await discoveryPage
            .locator('.admin-table tbody tr')
            .first()
            .locator('th[scope="row"] a')
            .getAttribute('href');
        studentId = graduateHref?.match(/\/graduates\/(\d+)$/)?.[1];
    } finally {
        await discoveryContext.close();
    }

    expect(ceremonyId).toBeTruthy();
    expect(studentId).toBeTruthy();

    const directEntryContext = await browser.newContext({
        viewport: { width: 1366, height: 768 },
    });
    const page = await directEntryContext.newPage();

    try {
        await signIn(page, 'admin@example.test');

        await page.goto(`/admin/ceremonies/${ceremonyId}/graduates/${studentId}/edit`);
        const editBack = page.getByRole('link', { name: 'Go to Graduate' });
        await expect(editBack).toHaveAttribute(
            'href',
            new RegExp(`/admin/ceremonies/${ceremonyId}/graduates/${studentId}$`),
        );
        await expectNoAxeViolations(page);
        await editBack.click();
        await expect(page).toHaveURL(
            new RegExp(`/admin/ceremonies/${ceremonyId}/graduates/${studentId}$`),
        );

        const detailBack = page.locator('.graduate-detail-header').getByRole('link', {
            name: '← Graduates',
        });
        await expect(detailBack).toHaveAttribute(
            'href',
            new RegExp(`/admin/ceremonies/${ceremonyId}/graduates$`),
        );
        await detailBack.click();
        await expect(page).toHaveURL(
            new RegExp(`/admin/ceremonies/${ceremonyId}/graduates$`),
        );

        await page.goto(`/admin/ceremonies/${ceremonyId}/setup`);
        const setupBack = page.getByRole('link', { name: '← Ceremony overview' });
        await expect(setupBack).toHaveAttribute(
            'href',
            new RegExp(`/admin/ceremonies/${ceremonyId}$`),
        );
        await expectNoAxeViolations(page);
        await setupBack.click();
        await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${ceremonyId}$`));

        await page.goto(`/admin/ceremonies/${ceremonyId}/qr/${studentId}`);
        await expect(page.getByRole('link', { name: 'Go to Graduate' })).toHaveAttribute(
            'href',
            new RegExp(`/admin/ceremonies/${ceremonyId}/graduates/${studentId}$`),
        );

        await page.goto(`/admin/ceremonies/${ceremonyId}/graduates/${studentId}?section=announcement`);
        const returnToQueue = page.getByRole('link', {
            name: 'Return to announcement queue',
        });
        await expect(returnToQueue).toHaveAttribute(
            'href',
            new RegExp(`/admin/ceremonies/${ceremonyId}/announcements#review-queue$`),
        );
        await returnToQueue.click();
        await expect(page).toHaveURL(
            new RegExp(`/admin/ceremonies/${ceremonyId}/announcements#review-queue$`),
        );

        await page.goto(`/admin/ceremonies/${ceremonyId}/import`);
        await page.locator('#csv_file').setInputFiles({
            name: 'semantic-parent.csv',
            mimeType: 'text/csv',
            buffer: Buffer.from([
                'student_number,first_name,middle_name,last_name,program,sequence_number,eligibility_status',
                'SEMANTIC-0001,Semantic,,Parent,BS Computer Science,99,eligible',
            ].join('\n')),
        });
        await page.getByRole('button', { name: 'Inspect import' }).click();
        const importBack = page.getByRole('link', { name: 'Go to Import source' });
        await expect(importBack).toHaveAttribute(
            'href',
            new RegExp(`/admin/ceremonies/${ceremonyId}/import$`),
        );
        await importBack.click();
        await expect(page).toHaveURL(new RegExp(`/admin/ceremonies/${ceremonyId}/import$`));

        await page.setViewportSize({ width: 390, height: 844 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
    } finally {
        await directEntryContext.close();
    }
});

test('phase five keeps live operations on the live ceremony while preparation stays on the working ceremony', async ({
    browser,
}) => {
    const workingCeremonyName = `Isolation Working ${Date.now()}`;
    const adminContext = await browser.newContext();
    const scannerContext = await browser.newContext();
    const stageContext = await browser.newContext();
    const admin = await adminContext.newPage();
    const scanner = await scannerContext.newPage();
    const stage = await stageContext.newPage();

    try {
        await signIn(admin, 'admin@example.test');
        const liveCeremonyName = await admin
            .locator('.app-toolbar-ceremony')
            .textContent();

        await admin.goto('/admin/ceremonies/create');
        await admin.getByLabel('Ceremony name').fill(workingCeremonyName);
        await admin.getByLabel('Academic year').fill('2027-2028');
        await admin.getByRole('button', { name: 'Create ceremony' }).click();

        const workspaceNav = admin.getByRole('navigation', {
            name: 'Ceremony workspace sections',
        });
        await expect(admin.getByRole('heading', { name: workingCeremonyName })).toBeVisible();
        const workingCeremonyId = (await workspaceNav
            .getByRole('link', { name: 'Overview' })
            .getAttribute('href'))?.match(/\/admin\/ceremonies\/(\d+)$/)?.[1];
        expect(workingCeremonyId).toBeTruthy();

        for (const path of [
            `/admin/ceremonies/${workingCeremonyId}/graduates`,
            `/admin/ceremonies/${workingCeremonyId}/qr`,
            `/admin/ceremonies/${workingCeremonyId}/announcements`,
            `/admin/ceremonies/${workingCeremonyId}/readiness`,
        ]) {
            await admin.goto(path);
            await expect(admin.locator('.app-working-ceremony-switcher')).toContainText(
                workingCeremonyName,
            );
            await expect(admin.locator('.app-toolbar-ceremony')).toContainText(
                liveCeremonyName ?? '',
            );
        }

        await signIn(scanner, 'scanner@example.test');
        await scanner.goto('/scanner');
        await expect(scanner.getByText(liveCeremonyName ?? '')).toBeVisible();
        await expect(scanner.getByText(workingCeremonyName)).toHaveCount(0);

        await signIn(stage, 'stage@example.test');
        await stage.goto('/stage-control');
        await expect(stage.getByText(liveCeremonyName ?? '')).toBeVisible();
        await expect(stage.getByText(workingCeremonyName)).toHaveCount(0);

        await stage.goto('/stage');
        await clearStagePreflight(stage);
        await expect(stage.getByText(liveCeremonyName ?? '')).toBeVisible();
        await expect(stage.getByText(workingCeremonyName)).toHaveCount(0);
    } finally {
        await adminContext.close().catch(() => {});
        await scannerContext.close().catch(() => {});
        await stageContext.close().catch(() => {});
    }
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
        await admin.getByRole('button', { name: 'Dismiss getting-started panel' }).click();

        await expectNavigationGroups(
            admin,
            ['OVERVIEW', 'CEREMONY WORKSPACE', 'LIVE OPERATIONS', 'REVIEW', 'SYSTEM'],
        );
        await expectNavigationGroups(
            encoder,
            ['OVERVIEW', 'CEREMONY WORKSPACE'],
            ['LIVE OPERATIONS', 'REVIEW', 'SYSTEM'],
        );

        for (const viewport of [
            { width: 1366, height: 768 },
            { width: 1536, height: 864 },
            { width: 1920, height: 1080 },
        ]) {
            await admin.setViewportSize(viewport);
            const sidebarFit = await admin.evaluate(() => {
                const navigation = document.querySelector('.app-sidebar-nav');
                const workspace = document.querySelector('.app-workspace');
                const footer = document
                    .querySelector('.app-sidebar-footer')
                    .getBoundingClientRect();
                const lastLink = [...navigation.querySelectorAll('a')].at(-1);
                const workspaceScrollTop = workspace.scrollTop;

                lastLink.scrollIntoView({ block: 'nearest' });
                lastLink.focus();

                const lastBounds = lastLink.getBoundingClientRect();

                return {
                    allNavigationFits:
                        navigation.scrollHeight <= navigation.clientHeight,
                    canReachLastDestination:
                        lastBounds.top >= 0 && lastBounds.bottom <= footer.top,
                    lastDestination: lastLink.textContent.trim(),
                    navigationScrolled:
                        navigation.scrollHeight > navigation.clientHeight
                            ? navigation.scrollTop > 0
                            : true,
                    mainWorkspaceStayedPut: workspace.scrollTop === workspaceScrollTop,
                    lastDestinationFocused: document.activeElement === lastLink,
                };
            });

            expect(sidebarFit.lastDestination).toBe('Audit Logs');
            expect(sidebarFit.canReachLastDestination).toBe(true);
            expect(sidebarFit.mainWorkspaceStayedPut).toBe(true);
            expect(sidebarFit.lastDestinationFocused).toBe(true);
            expect(
                sidebarFit.allNavigationFits || sidebarFit.navigationScrolled,
            ).toBe(true);
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
    } finally {
        await adminContext.close().catch(() => {});
        await encoderContext.close().catch(() => {});
    }
});

test('administrator admin-only workflows remain usable after the navigation refactor', async ({
    page,
}) => {
    test.setTimeout(120_000);
    prepareBrowserBackupFixture();

    await signIn(page, 'admin@example.test');

    await page.goto('/admin/students');
    await openFirstGraduateRecord(page);
    await page.getByRole('link', { name: 'Announcement', exact: true }).click();
    await expect(page).toHaveURL(/section=announcement/);
    await page.getByLabel('Reviewer notes').fill(
        'Approved by the isolated Playwright rehearsal.',
    );
    page.once('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Approve audio' }).click();
    await expect(page.getByText('Announcement audio approved.', { exact: true })).toBeVisible();

    await page.goto('/admin/backups');
    const createBackup = page.getByRole('button', { name: 'Create local backup' });
    const backupActionPosition = await createBackup.evaluate((button) => {
        const workspace = button.closest('.app-workspace');
        workspace?.scrollTo({ top: 0 });

        const buttonBounds = button.getBoundingClientRect();
        const toolbar = document.querySelector('.app-toolbar');
        const toolbarBounds = toolbar?.getBoundingClientRect();
        const hit = document.elementFromPoint(
            buttonBounds.left + (buttonBounds.width / 2),
            buttonBounds.top + (buttonBounds.height / 2),
        );

        return {
            isUnobstructed: hit === button || button.contains(hit),
            top: buttonBounds.top,
            toolbarBottom: toolbarBounds?.bottom ?? 0,
        };
    });
    expect(backupActionPosition.top).toBeGreaterThan(backupActionPosition.toolbarBottom);
    expect(backupActionPosition.isUnobstructed).toBe(true);
    await createBackup.click();
    await expect(page.getByText('Local backup completed and verified.')).toBeVisible();
    const latestBackup = page.locator('.admin-table tbody tr').first();
    await expect(latestBackup).toContainText('completed');
    await expect(latestBackup).toContainText('passed');

    await latestBackup.getByRole('button', { name: 'Run verification' }).click();
    await expect(page.getByText(/Disposable restore verified:/)).toBeVisible({
        timeout: 20_000,
    });
    await expect(latestBackup).toContainText('passed');
    await expectNoAxeViolations(page);
});

test('stage operator navigation stays limited to live operations and review destinations', async ({
    page,
}) => {
    await signIn(page, 'stage@example.test');

    await page.goto('/dashboard');
    await expect(
        page.getByRole('link', { name: 'Open Stage Control', exact: true }),
    ).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Working ceremony' })).toHaveCount(0);
    await expectNavigationGroups(
        page,
        ['OVERVIEW', 'LIVE OPERATIONS', 'REVIEW'],
        ['CEREMONY WORKSPACE', 'SYSTEM'],
    );
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Stage Control' }),
    ).toBeVisible();
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Stage Display' }),
    ).toBeVisible();
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Ceremony Overview' }),
    ).toHaveCount(0);

    await page.goto('/stage-control');
    await expect(page.locator('.app-sidebar-nav')).toHaveCount(0);
    await page.getByRole('button', { name: /Command/i }).click();
    await expect(page.getByRole('dialog', { name: 'Command palette' })).toBeVisible();
    await expect(
        page.getByRole('option', { name: /Stage Control/i }),
    ).toBeVisible();
    await expect(
        page.getByRole('option', { name: /Ceremony Overview/i }),
    ).toHaveCount(0);
});

test('navigation active states prefer the specific primary workspace', async ({
    page,
}) => {
    await signIn(page, 'admin@example.test');

    await page.goto('/admin/students');
    await openFirstGraduateRecord(page);
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Graduates' }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Import' }),
    ).not.toHaveAttribute('aria-current', 'page');

    await page.goto('/admin/students/import');
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Import' }),
    ).toHaveAttribute('aria-current', 'page');
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Graduates' }),
    ).not.toHaveAttribute('aria-current', 'page');

    await page.goto('/admin/voice-generation');
    await expect(
        page.locator('.app-sidebar-nav').getByRole('link', { name: 'Announcements' }),
    ).toHaveAttribute('aria-current', 'page');
});

test('command palette lists the new authorized navigation destinations', async ({
    page,
}) => {
    await signIn(page, 'admin@example.test');

    await page.keyboard.press('Control+K');
    await expect(page.getByLabel('Search commands')).toBeVisible();
    await page.getByLabel('Search commands').fill('announce');
    await expect(page.getByRole('option', { name: /Announcements/i })).toBeVisible();
    await expect(page.getByRole('option', { name: /Announcements/i })).toContainText(
        'CEREMONY WORKSPACE',
    );
    await page.getByRole('option', { name: /Announcements/i }).click();
    await expect(
        page.getByRole('heading', { name: 'Announcements' }),
    ).toBeVisible();
});

test('mobile navigation drawer keeps the workflow groups reachable and closes after navigation', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await signIn(page, 'admin@example.test');

    await page.getByRole('button', { name: 'Toggle navigation' }).click();
    await expect(page.locator('.app-shell')).toHaveAttribute('data-mobile-open', 'true');
    await expectNavigationGroups(
        page,
        ['OVERVIEW', 'CEREMONY WORKSPACE', 'LIVE OPERATIONS', 'REVIEW', 'SYSTEM'],
    );
    await expect(page.locator('#working-ceremony-select')).toBeVisible();

    await page
        .locator('.app-sidebar-nav')
        .getByRole('link', { name: 'Import' })
        .click();

    await expect(page.locator('.app-shell')).toHaveAttribute('data-mobile-open', 'false');
    await expect(
        page.getByRole('heading', { name: 'Import graduate records' }),
    ).toBeVisible();
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true);
});

test('role guidance pauses, resumes, traps focus, and stays manually available during a live ceremony', async ({
    browser,
}) => {
    const adminContext = await browser.newContext();
    const scannerContext = await browser.newContext();
    const admin = await adminContext.newPage();
    const scanner = await scannerContext.newPage();

    try {
        await signIn(admin, 'admin@example.test');
        await admin.goto('/dashboard');
        await admin.getByRole('button', { name: 'Help and guidance' }).click();
        await admin.getByRole('button', { name: /(?:Start|Resume) role guide/ }).click();

        const coachmark = admin.locator('.tour-coachmark');
        await expect(coachmark).toBeVisible();
        await expect(coachmark).toBeFocused();
        await admin.keyboard.press('Shift+Tab');
        await expect(coachmark.getByRole('button', { name: 'Next' })).toBeFocused();
        await admin.keyboard.press('Escape');
        await expect(coachmark).toBeHidden();

        await admin.getByRole('button', { name: 'Help and guidance' }).click();
        await admin.getByRole('button', { name: 'Resume role guide' }).click();
        await expect(coachmark).toBeVisible();
        await coachmark.getByRole('button', { name: 'Pause' }).click();
        await expect(coachmark).toBeHidden();

        await signIn(scanner, 'scanner@example.test');
        await scanner.goto('/scanner');
        await expect(scanner.locator('.onboarding-welcome')).toHaveCount(0);
        await expect(scanner.getByRole('button', { name: 'Help and guidance' })).toBeVisible();
        await expectNoAxeViolations(scanner);
    } finally {
        await adminContext.close();
        await scannerContext.close();
    }
});

test('USB scan, queue, stage commands, realtime updates, and refresh recovery remain atomic', async ({
    browser,
}) => {
    test.setTimeout(180_000);
    preparePhaseThreeStageFixture();

    const scannerContext = await browser.newContext();
    const controlContext = await browser.newContext();
    const stageContext = await browser.newContext();
    await stageContext.addInitScript(() => {
        HTMLMediaElement.prototype.play = () => Promise.resolve();
    });
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
        await clearStagePreflight(stage);
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
        await Promise.all([
            control.waitForResponse(
                (response) => response.request().method() === 'POST'
                    && response.url().endsWith('/stage/replay'),
            ),
            control.getByRole('button', { name: 'Replay announcement' }).click(),
        ]);
        expect(replayPosts).toBe(1);

        await control.getByRole('button', { name: 'Show holding screen' }).click();
        await expect(stage.getByRole('heading', { name: 'Ceremony program continues' })).toBeVisible();
        await stage.reload();
        await clearStagePreflight(stage);
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
        await clearStagePreflight(stage);
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
        await scannerContext.close().catch(() => {});
        await controlContext.close().catch(() => {});
        await stageContext.close().catch(() => {});
    }
});

test('critical screens remain usable at supported desktop viewports', async ({ browser }) => {
    test.setTimeout(60_000);
    preparePhaseThreeStageFixture();

    const scannerContext = await browser.newContext();
    const controlContext = await browser.newContext();
    const stageContext = await browser.newContext();
    await stageContext.addInitScript(() => {
        HTMLMediaElement.prototype.play = () => Promise.resolve();
    });
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
        await clearStagePreflight(stage);

        await scanWithKeyboard(scanner, 'GSS-PHASE3-A7K9M2Q4');
        await scanWithKeyboard(scanner, 'GSS-PHASE3-B8L3N5R7');
        await expect(
            control.getByText('Ana Marie dela Cruz').first(),
        ).toBeVisible();
        await expect(
            control.getByText('Christopher Emmanuel Villanueva-Santos').first(),
        ).toBeVisible();
        await control.getByRole('button', { name: 'Present next graduate' }).click();
        await expect(
            control.getByRole('button', { name: 'Complete and present next' }),
        ).toBeVisible();

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
            await expect(stage.getByRole('heading').first()).toBeInViewport();
            expect(
                await scanner.evaluate(
                    () => document.documentElement.scrollWidth <= window.innerWidth,
                ),
            ).toBe(true);
            expect(
                await control.evaluate(
                    () => document.documentElement.scrollWidth <= window.innerWidth,
                ),
            ).toBe(true);
            expect(
                await stage.evaluate(
                    () => document.documentElement.scrollWidth <= window.innerWidth,
                ),
            ).toBe(true);
        }
    } finally {
        await scannerContext.close().catch(() => {});
        await controlContext.close().catch(() => {});
        await stageContext.close().catch(() => {});
    }
});

test('pronunciation dictionary keeps common corrections simple and comfortably spaced', async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();

    try {
        await signIn(page, 'admin@example.test');
        await page.goto('/admin/pronunciation-rules');
        expect(
            await page.evaluate(() => ({
                accent: getComputedStyle(document.documentElement)
                    .getPropertyValue('--color-accent')
                    .trim(),
                canvas: getComputedStyle(document.documentElement)
                    .getPropertyValue('--color-bg-app')
                    .trim(),
                selected: getComputedStyle(document.documentElement)
                    .getPropertyValue('--color-surface-selected')
                    .trim(),
            })),
        ).toEqual({
            accent: '#1769e0',
            canvas: '#f4f5f7',
            selected: '#eaf2ff',
        });
        await expect(page.getByRole('heading', { name: 'Pronunciation dictionary' })).toBeVisible();
        await expect(page.getByText(/enabled rules/)).toBeVisible();

        for (const viewport of [
            { width: 1024, height: 768 },
            { width: 1366, height: 768 },
            { width: 1920, height: 1080 },
        ]) {
            await page.setViewportSize(viewport);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            await expect(page.getByRole('button', { name: 'Add pronunciation' })).toBeInViewport();
        }

        await page.getByRole('button', { name: 'Add pronunciation' }).click();
        await expect(page.getByLabel('Phrase as written')).toBeVisible();
        await expect(page.getByLabel('How it should sound')).toBeVisible();
        await expect(page.locator('.pronunciation-advanced')).not.toHaveAttribute('open', '');
        await expect(page.getByRole('button', { name: 'Save pronunciation' })).toHaveCSS('min-height', '44px');

        const spacing = await page.locator('.pronunciation-editor').evaluate((editor) => {
            const style = getComputedStyle(editor);
            const rule = editor.closest('.app-workspace').querySelector('.pronunciation-rule-list');

            return {
                editorPadding: Number.parseFloat(style.paddingLeft),
                ruleListFits: !rule || rule.scrollWidth <= rule.clientWidth,
            };
        });
        expect(spacing).toEqual({ editorPadding: 24, ruleListFits: true });

        await page.getByText('Advanced settings', { exact: true }).click();
        await expect(page.getByLabel('Apply to')).toBeVisible();
        await expect(page.getByLabel('Announcement section')).toBeVisible();
        await expectNoAxeViolations(page);
    } finally {
        await context.close();
    }
});

test('assisted automatic state remains readable, locked, and persistent', async ({
    browser,
}) => {
    test.setTimeout(60_000);

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
        await admin
            .locator('.admin-table tbody tr')
            .filter({ hasText: 'Active' })
            .first()
            .getByRole('link', { name: 'Setup' })
            .click();
        await admin.locator('#presentation_mode').selectOption(mode);
        await admin.getByRole('button', { name: 'Save ceremony' }).click();
        await expect(admin).not.toHaveURL(/\/setup$/);
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
        await adminContext.close().catch(() => {});
        await controlContext.close().catch(() => {});
    }
});
