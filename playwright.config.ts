import { existsSync } from 'node:fs';
import { defineConfig, devices } from '@playwright/test';

// Match the UI project's environment conventions while allowing portal-only
// overrides. Earlier files take precedence because loadEnvFile does not
// overwrite variables that are already set.
for (const envFile of [
    '.env.playwright',
    '.env.testing',
    '.env.test',
    '.env',
]) {
    if (existsSync(envFile)) {
        process.loadEnvFile(envFile);
    }
}

const apiURL =
    process.env.VITE_API_URL ??
    process.env.APP_URL ??
    'http://localhost:8000';
const baseURL = process.env.CLIENT_PORTAL_BASE_URL ?? apiURL;
const accountCount = positiveInt(
    process.env.PLAYWRIGHT_ACCOUNT_COUNT ?? process.env.E2E_ACCOUNT_COUNT,
    8,
);
const workerCount = positiveInt(process.env.PLAYWRIGHT_WORKERS, accountCount);

function positiveInt(value: string | undefined, fallback: number): number {
    const parsed = Number.parseInt(value ?? '', 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './tests/e2e/test-results',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 1,
    workers: workerCount,
    reporter: [
        [process.env.CI ? 'line' : 'list'],
        ['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
    ],
    use: {
        baseURL,
        trace: 'on-first-retry',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                bypassCSP: true,
                launchOptions: {
                    args: ['--disable-web-security'],
                },
            },
        },
        {
            name: 'firefox',
            use: { ...devices['Desktop Firefox'] },
        },
    ],
});
