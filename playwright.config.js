import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './tests/Browser',
    outputDir: 'test-results/playwright',
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: [
        ['list'],
        ['html', { open: 'never', outputFolder: 'playwright-report' }],
    ],
    snapshotPathTemplate: '{testDir}/__screenshots__/{arg}{ext}',
    use: {
        baseURL: process.env.GSS_E2E_BASE_URL ?? 'http://127.0.0.1:8002',
        viewport: { width: 1366, height: 768 },
        colorScheme: 'light',
        reducedMotion: 'reduce',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
});
