import { defineConfig, devices } from '@playwright/test';
import {
    loadPlaywrightEnvironment,
    resolvePlaywrightUrls,
} from './tests/e2e/environment';

loadPlaywrightEnvironment();

const { baseUrl: baseURL } = resolvePlaywrightUrls();
const accountCount = positiveInt(
    process.env.PLAYWRIGHT_ACCOUNT_COUNT ?? process.env.E2E_ACCOUNT_COUNT,
    8,
);
// Default to one worker. Parallel runs need PLAYWRIGHT_WORKERS and matching
// seeded account lanes; 8 headed Chromiums is what wedges `context` setup.
const workerCount = positiveInt(process.env.PLAYWRIGHT_WORKERS, 1);
const includeFirefox = Boolean(process.env.PLAYWRIGHT_FIREFOX);

function positiveInt(value: string | undefined, fallback: number): number {
    const parsed = Number.parseInt(value ?? '', 10);

    return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
}

export default defineConfig({
    testDir: './tests/e2e',
    outputDir: './tests/e2e/test-results',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 2 : 0,
    workers: Math.min(workerCount, accountCount),
    timeout: 60_000,
    reporter: [
        [process.env.CI ? 'line' : 'list'],
        ['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
    ],
    use: {
        baseURL,
        // PayPal's SDK blocks headless wallet checkout. Default to headed locally
        // (CLI and VS Code); set PLAYWRIGHT_HEADLESS=1 to force headless.
        headless:
            process.env.PLAYWRIGHT_HEADLESS === '1' ||
            process.env.CI === 'true',
        trace: process.env.CI ? 'on-first-retry' : 'off',
        screenshot: 'only-on-failure',
        // Video starts when the browser context is created and can push slow
        // boots over the fixture budget. Keep it for CI / opt-in.
        video:
            process.env.CI || process.env.PLAYWRIGHT_VIDEO
                ? 'retain-on-failure'
                : 'off',
    },
    projects: [
        {
            name: 'chromium',
            use: {
                ...devices['Desktop Chrome'],
                bypassCSP: true,
                launchOptions: {
                    args: [
                        '--disable-web-security',
                        // Headed runs otherwise keep spare renderer windows
                        // around after a timed-out context close.
                        '--disable-features=Translate,BackForwardCache',
                    ],
                },
            },
        },
        ...(includeFirefox
            ? [
                  {
                      name: 'firefox',
                      use: { ...devices['Desktop Firefox'] },
                  },
              ]
            : []),
    ],
});
