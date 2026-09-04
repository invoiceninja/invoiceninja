import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';

let browserPayPhpSource: string;
let browserPaySource: string;
let waitSource: string;

test.beforeAll(async () => {
    const root = process.cwd();

    [browserPayPhpSource, browserPaySource, waitSource] = await Promise.all([
        readFile(
            path.join(root, 'app/PaymentDrivers/Stripe/BrowserPay.php'),
            'utf8'
        ),
        readFile(
            path.join(
                root,
                'resources/js/clients/payments/stripe-browserpay.js'
            ),
            'utf8'
        ),
        readFile(path.join(root, 'resources/js/clients/wait.js'), 'utf8'),
    ]);
});

test('configures BrowserPay to disable Link and browser-saved cards only', () => {
    expect(configuredDisabledWallets()).toEqual(['link', 'browserCard']);
});

for (const checkout of [
    { label: 'classic checkout', instant: true },
    { label: 'Livewire checkout', instant: false },
]) {
    test(`passes wallet exclusions to Stripe in ${checkout.label}`, async ({
        page,
    }) => {
        const disabledWallets = configuredDisabledWallets();

        await openBrowserPayHarness(page, {
            disabledWallets,
            instant: checkout.instant,
        });

        await expect
            .poll(() =>
                page.evaluate(
                    () => window.__stripePaymentRequestCalls.length
                )
            )
            .toBe(1);

        const paymentRequestOptions = await page.evaluate(
            () => window.__stripePaymentRequestCalls[0]
        );

        expect(paymentRequestOptions).toMatchObject({
            country: 'US',
            currency: 'usd',
            disableWallets: ['link', 'browserCard'],
            requestPayerEmail: true,
            requestPayerName: true,
            total: {
                amount: 1250,
                label: 'Invoice payment',
            },
        });
        expect(paymentRequestOptions.disableWallets).not.toContain('applePay');
        expect(paymentRequestOptions.disableWallets).not.toContain('googlePay');
        await expect
            .poll(() =>
                page.evaluate(() => window.__stripePaymentRequestMounts)
            )
            .toEqual(['#payment-request-button']);
    });
}

function configuredDisabledWallets(): string[] {
    const match = browserPayPhpSource.match(
        /'disableWallets'\s*=>\s*\[((?:\s*'[^']+'\s*,?)+)\s*\]/
    );

    if (!match) {
        throw new Error(
            'Unable to locate the BrowserPay disableWallets configuration.'
        );
    }

    return [...match[1].matchAll(/'([^']+)'/g)].map(
        (wallet) => wallet[1]
    );
}

async function openBrowserPayHarness(
    page: Page,
    options: { disabledWallets: string[]; instant: boolean }
): Promise<void> {
    const paymentRequestData = escapeAttribute(
        JSON.stringify({
            country: 'US',
            currency: 'usd',
            total: {
                label: 'Invoice payment',
                amount: 1250,
            },
            requestPayerName: true,
            requestPayerEmail: true,
            disableWallets: options.disabledWallets,
        })
    );
    const instantPaymentMeta = options.instant
        ? '<meta name="instant-payment" content="yes">'
        : '';

    await page.route('http://browser-pay.test/**', async (route) => {
        const pathname = new URL(route.request().url()).pathname;

        if (pathname === '/__harness') {
            await route.fulfill({
                contentType: 'text/html',
                body: `<!doctype html>
                    <html>
                        <head>
                            <meta name="stripe-publishable-key" content="pk_test_browser_pay">
                            <meta name="stripe-pi-client-secret" content="pi_test_secret">
                            <meta name="payment-request-data" content="${paymentRequestData}">
                            <meta name="no-available-methods" content="&quot;No available methods&quot;">
                            ${instantPaymentMeta}
                            <script>${stripeStub()}</script>
                        </head>
                        <body>
                            <div id="stripe-browserpay-payment">
                                <div id="errors" hidden></div>
                                <div id="payment-request-button"></div>
                            </div>
                            <script type="module" src="/resources/js/clients/payments/stripe-browserpay.js"></script>
                        </body>
                    </html>`,
            });

            return;
        }

        if (
            pathname ===
            '/resources/js/clients/payments/stripe-browserpay.js'
        ) {
            await route.fulfill({
                contentType: 'text/javascript',
                body: browserPaySource,
            });

            return;
        }

        if (
            pathname === '/resources/js/clients/wait' ||
            pathname === '/resources/js/clients/wait.js'
        ) {
            await route.fulfill({
                contentType: 'text/javascript',
                body: waitSource,
            });

            return;
        }

        await route.abort();
    });

    await page.goto('http://browser-pay.test/__harness');
}

function escapeAttribute(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;');
}

function stripeStub(): string {
    return `window.__stripePaymentRequestCalls = [];
        window.__stripePaymentRequestMounts = [];
        window.Stripe = function () {
            return {
                elements() {
                    return {
                        create(type, options) {
                            if (type !== 'paymentRequestButton') {
                                throw new Error('Unexpected Stripe element: ' + type);
                            }

                            return {
                                mount(selector) {
                                    window.__stripePaymentRequestMounts.push(selector);
                                }
                            };
                        }
                    };
                },
                paymentRequest(options) {
                    window.__stripePaymentRequestCalls.push(options);

                    return {
                        canMakePayment: async function () {
                            return {googlePay: true};
                        },
                        on() {}
                    };
                }
            };
        };`;
}

declare global {
    interface Window {
        __stripePaymentRequestCalls: Array<{
            disableWallets?: string[];
            [key: string]: unknown;
        }>;
        __stripePaymentRequestMounts: string[];
    }
}
