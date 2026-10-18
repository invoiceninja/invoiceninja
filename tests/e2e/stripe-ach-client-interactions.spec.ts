import { readFile } from 'node:fs/promises';
import path from 'node:path';
import { expect, test, type Page } from '@playwright/test';

interface BillingAddress {
    line1: string;
    line2: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
}

interface StripeCall {
    params?: {
        payment_method_data?: {
            billing_details?: {
                address?: Record<string, string>;
            };
        };
    };
    [key: string]: unknown;
}

const validAddress: BillingAddress = {
    line1: '200 Market St',
    line2: 'Suite 4',
    city: 'San Francisco',
    state: 'CA',
    postal_code: '94105',
    country: 'US',
};

const invalidAddressCases: Array<{
    label: string;
    field: keyof BillingAddress;
    value: string;
}> = [
    { label: 'line 1 is blank', field: 'line1', value: '   ' },
    { label: 'city is blank', field: 'city', value: '   ' },
    { label: 'state is blank', field: 'state', value: '   ' },
    { label: 'postal code is blank', field: 'postal_code', value: '   ' },
    { label: 'country is blank', field: 'country', value: '   ' },
    { label: 'country is one character', field: 'country', value: 'U' },
    { label: 'country is three characters', field: 'country', value: 'USA' },
];

let authorizeSource: string;
let paymentSource: string;
let waitSource: string;
let paymentLayoutListener: string;
let legacyPaymentSource: string;
let legacyPaymentBlade: string;
let livewirePaymentBlade: string;
let reauthorizationSource: string;

test.beforeAll(async () => {
    const root = process.cwd();

    [authorizeSource, paymentSource, waitSource] = await Promise.all([
        readFile(
            path.join(root, 'resources/js/clients/payments/stripe-ach.js'),
            'utf8'
        ),
        readFile(
            path.join(root, 'resources/js/clients/payments/stripe-ach-pay.js'),
            'utf8'
        ),
        readFile(path.join(root, 'resources/js/clients/wait.js'), 'utf8'),
    ]);

    const [
        layout,
        legacyPaymentView,
        livewirePaymentView,
        reauthorizationView,
    ] = await Promise.all([
        readFile(
            path.join(
                root,
                'resources/views/portal/ninja2020/layout/payments.blade.php'
            ),
            'utf8'
        ),
        readFile(
            path.join(
                root,
                'resources/views/portal/ninja2020/gateways/stripe/ach/pay.blade.php'
            ),
            'utf8'
        ),
        readFile(
            path.join(
                root,
                'resources/views/portal/ninja2020/gateways/stripe/ach/pay_livewire.blade.php'
            ),
            'utf8'
        ),
        readFile(
            path.join(
                root,
                'resources/views/portal/ninja2020/gateways/stripe/ach/reauthorize.blade.php'
            ),
            'utf8'
        ),
    ]);

    legacyPaymentBlade = legacyPaymentView;
    livewirePaymentBlade = livewirePaymentView;
    const scripts = [...layout.matchAll(/<script>([\s\S]*?)<\/script>/g)];

    paymentLayoutListener =
        scripts.find((match) =>
            match[1].includes("Livewire.on('passed-required-fields-check'")
        )?.[1] ?? '';

    if (!paymentLayoutListener) {
        throw new Error(
            'Unable to locate the payment layout Livewire listener.'
        );
    }

    legacyPaymentSource =
        [...legacyPaymentView.matchAll(/<script>([\s\S]*?)<\/script>/g)].find(
            (match) =>
                match[1].includes(
                    "let payNow = document.getElementById('pay-now')"
                )
        )?.[1] ?? '';

    if (!legacyPaymentSource) {
        throw new Error('Unable to locate the legacy ACH payment script.');
    }

    reauthorizationSource =
        [...reauthorizationView.matchAll(/<script>([\s\S]*?)<\/script>/g)].find(
            (match) =>
                match[1].includes("document.getElementById('authorize-button')")
        )?.[1] ?? '';

    if (!reauthorizationSource) {
        throw new Error('Unable to locate the ACH reauthorization script.');
    }

    reauthorizationSource = reauthorizationSource
        .replaceAll(
            "@json(ctrans('texts.ach_authorization_required'))",
            JSON.stringify('You must consent to ACH transactions.')
        )
        .replaceAll(
            "@json(ctrans('texts.unable_to_verify_payment_method'))",
            JSON.stringify('Unable to verify payment method.')
        );
});

test.describe('Stripe ACH billing-address bridge', () => {
    test('replaces every stale address meta value before unlocking checkout', async ({
        page,
    }) => {
        await openLayoutHarness(page, {
            line1: 'Old street',
            line2: 'Old suite',
            city: 'Old city',
            state: 'NY',
            postal_code: '10001',
            country: 'CA',
        });

        await dispatchRequiredFieldsPassed(page, validAddress);

        await expectAddressMeta(page, validAddress);
        await expect(
            page.locator('[data-ref="gateway-container"]')
        ).not.toHaveClass(/pointer-events-none/);
        await expect(
            page.locator('[data-ref="required-fields-container"]')
        ).toHaveClass(/pointer-events-none/);
    });

    test('clears an old line 2 when the newly saved address omits it', async ({
        page,
    }) => {
        await openLayoutHarness(page, validAddress);

        await dispatchRequiredFieldsPassed(page, {
            ...validAddress,
            line2: '',
        });

        await expect(page.locator('meta[name="address-2"]')).toHaveAttribute(
            'content',
            ''
        );
    });

    test('keeps existing metadata when a non-ACH event has no address payload', async ({
        page,
    }) => {
        await openLayoutHarness(page, validAddress);

        await page.evaluate(() => {
            window.__livewireListeners['passed-required-fields-check']({
                client_postal_code: '94105',
            });
        });

        await expectAddressMeta(page, validAddress);
    });

    test('passes newly dispatched values to Stripe rather than the initially rendered values', async ({
        page,
    }) => {
        await openAuthorizeHarness(page, {
            address: {
                line1: 'Old street',
                line2: 'Old suite',
                city: 'Old city',
                state: 'NY',
                postal_code: '10001',
                country: 'CA',
            },
            includeLayoutListener: true,
        });
        await setStripeBehavior(page, {
            collectSetup: {
                error: { message: 'Stop after collection' },
            },
        });

        await dispatchRequiredFieldsPassed(page, validAddress);
        await page.locator('#accept-terms').check();
        await page.locator('#save-button').click();

        const address = await firstStripeCallAddress(page, 'collectSetup');
        expect(address).toEqual(validAddress);
    });
});

test.describe('Stripe ACH add-payment-method interactions', () => {
    test('shows the mandate acceptance error without calling Stripe', async ({
        page,
    }) => {
        await openAuthorizeHarness(page);

        await page.locator('#save-button').click();

        await expectVisibleError(
            page,
            'You must accept the mandate terms prior to adding this payment method.'
        );
        await expectStripeCallCount(page, 'collectSetup', 0);
        await expectButtonReady(page, '#save-button');
    });

    for (const scenario of invalidAddressCases) {
        test(`shows a visible address error when ${scenario.label}`, async ({
            page,
        }) => {
            await openAuthorizeHarness(page, {
                address: {
                    ...validAddress,
                    [scenario.field]: scenario.value,
                },
            });
            await page.locator('#accept-terms').check();

            await page.locator('#save-button').click();

            await expectVisibleError(
                page,
                'A complete billing address is required to add a bank account.'
            );
            await expectStripeCallCount(page, 'collectSetup', 0);
            await expectButtonReady(page, '#save-button');
        });
    }

    test('accepts an address without line 2 and omits the empty field sent to Stripe', async ({
        page,
    }) => {
        await openAuthorizeHarness(page, {
            address: { ...validAddress, line2: '' },
        });
        await setStripeBehavior(page, {
            collectSetup: {
                error: { message: 'Collection stopped for assertion' },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#save-button').click();

        expect(await firstStripeCallAddress(page, 'collectSetup')).toEqual({
            line1: validAddress.line1,
            city: validAddress.city,
            state: validAddress.state,
            postal_code: validAddress.postal_code,
            country: validAddress.country,
        });
    });

    test('trims values and normalizes the country code before calling Stripe', async ({
        page,
    }) => {
        await openAuthorizeHarness(page, {
            address: {
                line1: '  200 Market St  ',
                line2: '  Suite 4  ',
                city: '  San Francisco  ',
                state: '  CA  ',
                postal_code: '  94105  ',
                country: ' us ',
            },
        });
        await setStripeBehavior(page, {
            collectSetup: {
                error: { message: 'Collection stopped for assertion' },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#save-button').click();

        expect(await firstStripeCallAddress(page, 'collectSetup')).toEqual(
            validAddress
        );
    });

    const collectionFailures = [
        {
            label: 'Stripe returns a collection error',
            behavior: {
                collectSetup: { error: { message: 'Collection failed' } },
            },
            message: 'Collection failed',
        },
        {
            label: 'the client closes Financial Connections',
            behavior: {
                collectSetup: {
                    setupIntent: { status: 'requires_payment_method' },
                },
            },
            message: 'Please complete the bank account verification process.',
        },
        {
            label: 'Stripe throws during collection',
            behavior: { collectSetup: { throws: 'Network unavailable' } },
            message: 'Network unavailable',
        },
        {
            label: 'Stripe returns a malformed response',
            behavior: { collectSetup: {} },
            message: 'An unexpected error occurred.',
        },
    ];

    for (const scenario of collectionFailures) {
        test(`restores the button and exposes an error when ${scenario.label}`, async ({
            page,
        }) => {
            await openAuthorizeHarness(page);
            await setStripeBehavior(page, scenario.behavior);
            await page.locator('#accept-terms').check();

            await page.locator('#save-button').click();

            await expectVisibleError(page, scenario.message);
            await expectButtonReady(page, '#save-button');
            await expect(page.locator('#server_response')).not.toHaveAttribute(
                'data-submitted',
                'true'
            );
        });
    }

    test('exposes confirmation errors and restores the button', async ({
        page,
    }) => {
        await openAuthorizeHarness(page);
        await setStripeBehavior(page, {
            collectSetup: {
                setupIntent: { status: 'requires_confirmation' },
            },
            confirmSetup: { error: { message: 'Confirmation failed' } },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#save-button').click();

        await expectVisibleError(page, 'Confirmation failed');
        await expectButtonReady(page, '#save-button');
    });

    const successfulSetupStatuses = [
        'requires_action',
        'succeeded',
        'processing',
    ];

    for (const status of successfulSetupStatuses) {
        test(`submits the SetupIntent when collection returns ${status}`, async ({
            page,
        }) => {
            await openAuthorizeHarness(page);
            await setStripeBehavior(page, {
                collectSetup: {
                    setupIntent: { id: `seti_${status}`, status },
                },
            });
            await page.locator('#accept-terms').check();

            await page.locator('#save-button').click();

            await expectSubmitted(page, '#server_response');
            await expect(page.locator('#gateway_response')).toHaveValue(
                JSON.stringify({ id: `seti_${status}`, status })
            );
        });
    }

    test('submits the confirmed SetupIntent after requires_confirmation', async ({
        page,
    }) => {
        await openAuthorizeHarness(page);
        await setStripeBehavior(page, {
            collectSetup: {
                setupIntent: {
                    id: 'seti_pending',
                    status: 'requires_confirmation',
                },
            },
            confirmSetup: {
                setupIntent: { id: 'seti_confirmed', status: 'succeeded' },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#save-button').click();

        await expectSubmitted(page, '#server_response');
        await expect(page.locator('#gateway_response')).toHaveValue(
            JSON.stringify({ id: 'seti_confirmed', status: 'succeeded' })
        );
        await expectStripeCallCount(page, 'confirmSetup', 1);
    });
});

test.describe('Stripe ACH new-bank payment interactions', () => {
    test('shows the mandate acceptance error without calling Stripe', async ({
        page,
    }) => {
        await openPaymentHarness(page);

        await page.locator('#new-bank').click();

        await expectVisibleError(
            page,
            'You must accept the mandate terms prior to making payment.'
        );
        await expectStripeCallCount(page, 'collectPayment', 0);
        await expectButtonReady(page, '#new-bank');
    });

    for (const scenario of invalidAddressCases) {
        test(`shows a visible address error when ${scenario.label}`, async ({
            page,
        }) => {
            await openPaymentHarness(page, {
                address: {
                    ...validAddress,
                    [scenario.field]: scenario.value,
                },
            });
            await page.locator('#accept-terms').check();

            await page.locator('#new-bank').click();

            await expectVisibleError(
                page,
                'A complete billing address is required to pay by bank account.'
            );
            await expectStripeCallCount(page, 'collectPayment', 0);
            await expectButtonReady(page, '#new-bank');
        });
    }

    test('accepts an address without line 2 and sends the remaining fields', async ({
        page,
    }) => {
        await openPaymentHarness(page, {
            address: { ...validAddress, line2: '' },
        });
        await setStripeBehavior(page, {
            collectPayment: {
                error: { message: 'Collection stopped for assertion' },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#new-bank').click();

        expect(await firstStripeCallAddress(page, 'collectPayment')).toEqual({
            line1: validAddress.line1,
            city: validAddress.city,
            state: validAddress.state,
            postal_code: validAddress.postal_code,
            country: validAddress.country,
        });
    });

    test('trims values and normalizes the country code', async ({ page }) => {
        await openPaymentHarness(page, {
            address: {
                line1: '  200 Market St  ',
                line2: '  Suite 4  ',
                city: '  San Francisco  ',
                state: '  CA  ',
                postal_code: '  94105  ',
                country: ' us ',
            },
        });
        await setStripeBehavior(page, {
            collectPayment: {
                error: { message: 'Collection stopped for assertion' },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#new-bank').click();

        expect(await firstStripeCallAddress(page, 'collectPayment')).toEqual(
            validAddress
        );
    });

    const collectionFailures = [
        {
            label: 'Stripe returns a collection error',
            behavior: {
                collectPayment: {
                    error: { message: 'Payment collection failed' },
                },
            },
            message: 'Payment collection failed',
        },
        {
            label: 'the client closes Financial Connections',
            behavior: {
                collectPayment: {
                    paymentIntent: { status: 'requires_payment_method' },
                },
            },
            message:
                'We were unable to process the payment with this account, please try another one.',
        },
        {
            label: 'Stripe rejects the collection request',
            behavior: {
                collectPayment: { throws: 'Payment collection unavailable' },
            },
            message: 'Payment collection unavailable',
        },
        {
            label: 'Stripe returns a malformed collection response',
            behavior: { collectPayment: {} },
            message: 'An unexpected error occurred.',
        },
        {
            label: 'Stripe returns an unexpected collection status',
            behavior: {
                collectPayment: {
                    paymentIntent: { status: 'processing' },
                },
            },
            message: 'We were unable to process this payment.',
        },
        {
            label: 'Stripe.js cannot be initialized',
            behavior: { initializeThrows: 'Stripe.js unavailable' },
            message: 'Stripe.js unavailable',
        },
    ];

    for (const scenario of collectionFailures) {
        test(`restores the button and exposes an error when ${scenario.label}`, async ({
            page,
        }) => {
            await openPaymentHarness(page);
            await setStripeBehavior(page, scenario.behavior);
            await page.locator('#accept-terms').check();

            await page.locator('#new-bank').click();

            await expectVisibleError(page, scenario.message);
            await expectButtonReady(page, '#new-bank');
            await expect(page.locator('#server-response')).not.toHaveAttribute(
                'data-submitted',
                'true'
            );
        });
    }

    const confirmationResults = [
        {
            label: 'processing',
            paymentIntent: { id: 'pi_processing', status: 'processing' },
            submitted: true,
        },
        {
            label: 'microdeposit verification',
            paymentIntent: {
                id: 'pi_microdeposits',
                status: 'requires_action',
                next_action: { type: 'verify_with_microdeposits' },
            },
            submitted: true,
        },
        {
            label: 'source action',
            paymentIntent: {
                id: 'pi_source_action',
                status: 'requires_action',
                next_action: { type: 'requires_source_action' },
            },
            submitted: true,
        },
        {
            label: 'another payment method',
            paymentIntent: {
                id: 'pi_requires_payment_method',
                status: 'requires_payment_method',
            },
            submitted: false,
        },
    ];

    for (const scenario of confirmationResults) {
        test(`handles a confirmed PaymentIntent requiring ${scenario.label}`, async ({
            page,
        }) => {
            await openPaymentHarness(page);
            await setStripeBehavior(page, {
                collectPayment: {
                    paymentIntent: { status: 'requires_confirmation' },
                },
                confirmPayment: { paymentIntent: scenario.paymentIntent },
            });
            await page.locator('#accept-terms').check();

            await page.locator('#new-bank').click();

            if (scenario.submitted) {
                await expectSubmitted(page, '#server-response');
                await expect(page.locator('#gateway_response')).toHaveValue(
                    JSON.stringify(scenario.paymentIntent)
                );
            } else {
                await expectVisibleError(
                    page,
                    'We were unable to process the payment with this account, please try another one.'
                );
                await expectButtonReady(page, '#new-bank');
            }
        });
    }

    const confirmationFailures = [
        {
            label: 'Stripe returns a confirmation error',
            result: { error: { message: 'Payment confirmation failed' } },
            message: 'Payment confirmation failed',
        },
        {
            label: 'Stripe rejects the confirmation request',
            result: { throws: 'Payment confirmation unavailable' },
            message: 'Payment confirmation unavailable',
        },
        {
            label: 'Stripe returns a malformed confirmation response',
            result: {},
            message: 'An unexpected error occurred.',
        },
        {
            label: 'Stripe returns an unexpected confirmation status',
            result: {
                paymentIntent: { status: 'requires_capture' },
            },
            message: 'We were unable to process this payment.',
        },
    ];

    for (const scenario of confirmationFailures) {
        test(`restores the button and exposes an error when ${scenario.label}`, async ({
            page,
        }) => {
            await openPaymentHarness(page);
            await setStripeBehavior(page, {
                collectPayment: {
                    paymentIntent: { status: 'requires_confirmation' },
                },
                confirmPayment: scenario.result,
            });
            await page.locator('#accept-terms').check();

            await page.locator('#new-bank').click();

            await expectVisibleError(page, scenario.message);
            await expectButtonReady(page, '#new-bank');
            await expect(page.locator('#server-response')).not.toHaveAttribute(
                'data-submitted',
                'true'
            );
        });
    }
});

test.describe('Stripe ACH stored-token payment interactions', () => {
    test('renders pending tokens as disabled and labelled in both payment templates', () => {
        for (const template of [legacyPaymentBlade, livewirePaymentBlade]) {
            expect(template).toContain("@disabled($tokenState === 'pending')");
            expect(template).toContain(
                "ctrans('texts.stripe_ach_verifiation_pending')"
            );
            expect(template).toContain('ach-token-status');
            expect(template).toContain("['disabled' => ! $hasSelectableToken]");
        }
    });

    for (const implementation of ['module', 'legacy'] as const) {
        for (const tokenPrefix of ['pm', 'ba'] as const) {
            test(`${implementation} blocks a pending ${tokenPrefix}_ token without submitting`, async ({
                page,
            }) => {
                await openPaymentHarness(page, {
                    implementation,
                    tokenPrefix,
                    tokenStates: ['pending'],
                });

                const pendingToken = page.locator('[data-state="pending"]');

                await expect(pendingToken).toBeDisabled();
                await expect(pendingToken).not.toBeChecked();
                await expect(page.locator('.ach-token-status')).toHaveText(
                    'This payment method is not ready for use yet. Verification is pending.'
                );
                await expect(page.locator('#pay-now')).toBeDisabled();
                await expect(page.locator('input[name="source"]')).toHaveValue(
                    ''
                );

                await page
                    .locator('#pay-now')
                    .evaluate((button) =>
                        (button as HTMLButtonElement).click()
                    );

                await expect(
                    page.locator('#server-response')
                ).not.toHaveAttribute('data-submitted', 'true');
                await expectStripeCallCount(page, 'confirmSetup', 0);
            });
        }

        test(`${implementation} skips a pending token and selects the first payable token`, async ({
            page,
        }) => {
            await openPaymentHarness(page, {
                implementation,
                tokenStates: ['pending', 'authorized'],
            });

            await expect(page.locator('[data-state="pending"]')).toBeDisabled();
            await expect(
                page.locator('[data-state="authorized"]')
            ).toBeChecked();
            await expect(page.locator('input[name="source"]')).toHaveValue(
                'token-authorized'
            );
            await expect(page.locator('#pay-now')).toBeEnabled();

            await page.locator('#pay-now').click();

            await expectSubmitted(page, '#server-response');
            await expectStripeCallCount(page, 'confirmSetup', 0);
        });

        test(`${implementation} skips a pending token and selects an inactive token for mandate renewal`, async ({
            page,
        }) => {
            await openPaymentHarness(page, {
                implementation,
                tokenStates: ['pending', 'inactive'],
            });

            await expect(page.locator('[data-state="pending"]')).toBeDisabled();
            await expect(page.locator('[data-state="inactive"]')).toBeChecked();
            await expect(page.locator('input[name="source"]')).toHaveValue(
                'token-inactive'
            );
            await expect(page.locator('#mandate-authorization')).toBeVisible();
            await expect(page.locator('#pay-now')).toBeEnabled();

            await expectStripeCallCount(page, 'confirmSetup', 0);
        });
    }

    test('submits an authorized token without invoking Stripe.js', async ({
        page,
    }) => {
        await openPaymentHarness(page, { tokenStates: ['authorized'] });

        await page.locator('#pay-now').click();

        await expectSubmitted(page, '#server-response');
        await expect(page.locator('input[name="source"]')).toHaveValue(
            'token-authorized'
        );
        await expectStripeCallCount(page, 'confirmSetup', 0);
    });

    test('shows and hides mandate acceptance when switching token state', async ({
        page,
    }) => {
        await openPaymentHarness(page, {
            tokenStates: ['authorized', 'inactive'],
        });

        await page.locator('[data-state="inactive"]').check();
        await expect(page.locator('#mandate-authorization')).toBeVisible();
        await expect(page.locator('input[name="source"]')).toHaveValue(
            'token-inactive'
        );

        await page.locator('[data-state="authorized"]').check();
        await expect(page.locator('#mandate-authorization')).toBeHidden();
        await expect(page.locator('input[name="source"]')).toHaveValue(
            'token-authorized'
        );
    });

    test('requires mandate acceptance for an inactive token', async ({
        page,
    }) => {
        await openPaymentHarness(page, { tokenStates: ['inactive'] });

        await page.locator('#pay-now').click();

        await expectVisibleError(
            page,
            'You must accept the mandate terms prior to making payment.'
        );
        await expectStripeCallCount(page, 'confirmSetup', 0);
        await expectButtonReady(page, '#pay-now');
    });

    test('shows an error when the mandate client secret is unavailable', async ({
        page,
    }) => {
        await openPaymentHarness(page, {
            tokenStates: ['inactive'],
            mandateClientSecret: '',
        });
        await page.locator('#accept-mandate').check();

        await page.locator('#pay-now').click();

        await expectVisibleError(
            page,
            'We were unable to renew the bank account authorization.'
        );
        await expectStripeCallCount(page, 'confirmSetup', 0);
        await expectButtonReady(page, '#pay-now');
    });

    const mandateFailures = [
        {
            label: 'Stripe returns an error',
            result: { error: { message: 'Mandate confirmation failed' } },
            message: 'Mandate confirmation failed',
        },
        {
            label: 'the SetupIntent does not succeed',
            result: { setupIntent: { status: 'requires_action' } },
            message: 'We were unable to renew the bank account authorization.',
        },
        {
            label: 'Stripe rejects the mandate request',
            result: { throws: 'Mandate service unavailable' },
            message: 'Mandate service unavailable',
        },
    ];

    for (const scenario of mandateFailures) {
        test(`restores the button and exposes an error when ${scenario.label}`, async ({
            page,
        }) => {
            await openPaymentHarness(page, { tokenStates: ['inactive'] });
            await setStripeBehavior(page, { confirmSetup: scenario.result });
            await page.locator('#accept-mandate').check();

            await page.locator('#pay-now').click();

            await expectVisibleError(page, scenario.message);
            await expectButtonReady(page, '#pay-now');
            await expect(page.locator('#server-response')).not.toHaveAttribute(
                'data-submitted',
                'true'
            );
        });
    }

    test('submits the renewed mandate and selected token in one client action', async ({
        page,
    }) => {
        await openPaymentHarness(page, { tokenStates: ['inactive'] });
        await setStripeBehavior(page, {
            confirmSetup: {
                setupIntent: { id: 'seti_renewed', status: 'succeeded' },
            },
        });
        await page.locator('#accept-mandate').check();

        await page.locator('#pay-now').click();

        await expectSubmitted(page, '#server-response');
        await expect(page.locator('#setup_intent_id')).toHaveValue(
            'seti_renewed'
        );
        await expect(page.locator('input[name="source"]')).toHaveValue(
            'token-inactive'
        );
        await expectStripeCallCount(page, 'confirmSetup', 1);
    });
});

test.describe('Legacy Stripe ACH payment-view parity', () => {
    for (const scenario of invalidAddressCases) {
        test(`shows the address error when ${scenario.label}`, async ({
            page,
        }) => {
            await openPaymentHarness(page, {
                implementation: 'legacy',
                address: {
                    ...validAddress,
                    [scenario.field]: scenario.value,
                },
            });
            await page.locator('#accept-terms').check();

            await page.locator('#new-bank').click();

            await expectVisibleError(
                page,
                'A complete billing address is required to pay by bank account.'
            );
            await expectStripeCallCount(page, 'collectPayment', 0);
            await expectButtonReady(page, '#new-bank');
        });
    }

    test('accepts an address without line 2 and sends the complete remainder', async ({
        page,
    }) => {
        await openPaymentHarness(page, {
            implementation: 'legacy',
            address: { ...validAddress, line2: '' },
        });
        await setStripeBehavior(page, {
            collectPayment: {
                error: { message: 'Collection stopped for assertion' },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#new-bank').click();

        expect(await firstStripeCallAddress(page, 'collectPayment')).toEqual({
            line1: validAddress.line1,
            city: validAddress.city,
            state: validAddress.state,
            postal_code: validAddress.postal_code,
            country: validAddress.country,
        });
    });

    test('exposes rejected Stripe collection requests and restores the button', async ({
        page,
    }) => {
        await openPaymentHarness(page, { implementation: 'legacy' });
        await setStripeBehavior(page, {
            collectPayment: { throws: 'Legacy collection unavailable' },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#new-bank').click();

        await expectVisibleError(page, 'Legacy collection unavailable');
        await expectButtonReady(page, '#new-bank');
    });

    test('exposes Stripe confirmation errors and restores the button', async ({
        page,
    }) => {
        await openPaymentHarness(page, { implementation: 'legacy' });
        await setStripeBehavior(page, {
            collectPayment: {
                paymentIntent: { status: 'requires_confirmation' },
            },
            confirmPayment: {
                error: { message: 'Legacy confirmation failed' },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#new-bank').click();

        await expectVisibleError(page, 'Legacy confirmation failed');
        await expectButtonReady(page, '#new-bank');
    });

    test('submits an authorized stored token without Stripe.js', async ({
        page,
    }) => {
        await openPaymentHarness(page, {
            implementation: 'legacy',
            tokenStates: ['authorized'],
        });

        await page.locator('#pay-now').click();

        await expectSubmitted(page, '#server-response');
        await expectStripeCallCount(page, 'confirmSetup', 0);
    });

    test('renews and submits an inactive stored token', async ({ page }) => {
        await openPaymentHarness(page, {
            implementation: 'legacy',
            tokenStates: ['inactive'],
        });
        await setStripeBehavior(page, {
            confirmSetup: {
                setupIntent: { id: 'seti_legacy_renewed', status: 'succeeded' },
            },
        });
        await page.locator('#accept-mandate').check();

        await page.locator('#pay-now').click();

        await expectSubmitted(page, '#server-response');
        await expect(page.locator('#setup_intent_id')).toHaveValue(
            'seti_legacy_renewed'
        );
    });
});

test.describe('Stripe ACH standalone mandate reauthorization', () => {
    test('requires mandate acceptance without calling Stripe', async ({
        page,
    }) => {
        await openReauthorizationHarness(page);

        await page.locator('#authorize-button').click();

        await expectVisibleError(page, 'You must consent to ACH transactions.');
        await expectStripeCallCount(page, 'confirmSetup', 0);
        await expectButtonReady(page, '#authorize-button');
    });

    const failures = [
        {
            label: 'Stripe.js cannot initialize',
            behavior: { initializeThrows: 'Stripe.js unavailable' },
            message: 'Stripe.js unavailable',
        },
        {
            label: 'Stripe returns an error',
            behavior: {
                confirmSetup: { error: { message: 'Authorization failed' } },
            },
            message: 'Authorization failed',
        },
        {
            label: 'Stripe rejects confirmation',
            behavior: { confirmSetup: { throws: 'Network unavailable' } },
            message: 'Network unavailable',
        },
        {
            label: 'Stripe returns a malformed response',
            behavior: { confirmSetup: {} },
            message: 'Unable to verify payment method.',
        },
        ...['requires_action', 'processing', 'requires_payment_method'].map(
            (status) => ({
                label: `Stripe returns ${status}`,
                behavior: { confirmSetup: { setupIntent: { status } } },
                message: 'Unable to verify payment method.',
            })
        ),
    ];

    for (const scenario of failures) {
        test(`restores the button when ${scenario.label}`, async ({ page }) => {
            await openReauthorizationHarness(page);
            await setStripeBehavior(page, scenario.behavior);
            await page.locator('#accept-terms').check();

            await page.locator('#authorize-button').click();

            await expectVisibleError(page, scenario.message);
            await expectButtonReady(page, '#authorize-button');
            await expect(page.locator('#server-response')).not.toHaveAttribute(
                'data-submitted',
                'true'
            );
        });
    }

    test('submits the succeeded SetupIntent in the same client action', async ({
        page,
    }) => {
        await openReauthorizationHarness(page);
        await setStripeBehavior(page, {
            confirmSetup: {
                setupIntent: {
                    id: 'seti_standalone_renewed',
                    status: 'succeeded',
                },
            },
        });
        await page.locator('#accept-terms').check();

        await page.locator('#authorize-button').click();

        await expectSubmitted(page, '#server-response');
        await expect(page.locator('#setup-intent-id')).toHaveValue(
            'seti_standalone_renewed'
        );
        await expectStripeCallCount(page, 'confirmSetup', 1);
    });
});

async function openAuthorizeHarness(
    page: Page,
    options: {
        address?: BillingAddress;
        includeLayoutListener?: boolean;
    } = {}
): Promise<void> {
    const address = options.address ?? validAddress;
    const layout = options.includeLayoutListener
        ? `<script>${livewireStub()}</script><script>${paymentLayoutListener}</script>`
        : '';

    await openModuleHarness(
        page,
        '/resources/js/clients/payments/stripe-ach.js',
        `${layout}
        ${addressMeta(address)}
        <div data-ref="required-fields-container"></div>
        <div data-ref="gateway-container" class="pointer-events-none opacity-25"></div>
        <div id="errors" hidden></div>
        <input id="account-holder-name" value="Jane Doe">
        <input type="radio" name="account-holder-type" value="individual" checked>
        <input type="checkbox" id="accept-terms">
        <button id="save-button"><svg class="hidden"></svg><span>Add bank</span></button>
        <form id="server_response">
            <input id="gateway_response">
        </form>`
    );

    if (options.includeLayoutListener) {
        await initializeLivewireListener(page);
    }
}

async function openReauthorizationHarness(page: Page): Promise<void> {
    await page.setContent(`<!doctype html>
        <html>
            <head>
                <meta name="stripe-publishable-key" content="pk_test_browser">
                <meta name="stripe-client-secret" content="seti_secret_reauthorize">
                <script>${stripeStub()}</script>
            </head>
            <body>
                <div id="errors" hidden></div>
                <input type="checkbox" id="accept-terms">
                <button id="authorize-button"><svg class="hidden"></svg><span>Authorize</span></button>
                <form id="server-response">
                    <input id="setup-intent-id">
                </form>
                <script>${reauthorizationSource}</script>
            </body>
        </html>`);
}

async function openPaymentHarness(
    page: Page,
    options: {
        address?: BillingAddress;
        tokenStates?: Array<'authorized' | 'inactive' | 'pending'>;
        tokenPrefix?: 'pm' | 'ba';
        mandateClientSecret?: string;
        implementation?: 'module' | 'legacy';
    } = {}
): Promise<void> {
    const address = options.address ?? validAddress;
    const tokenStates = options.tokenStates ?? [];
    const tokenPrefix = options.tokenPrefix ?? 'pm';
    const mandateClientSecret =
        options.mandateClientSecret === undefined
            ? 'seti_secret_renew'
            : options.mandateClientSecret;
    const tokenInputs = tokenStates
        .map(
            (state) => `<label>
                <input
                    type="radio"
                    class="toggle-payment-with-token"
                    name="payment-type"
                    data-token="token-${state}"
                    data-payment-method="${tokenPrefix}-${state}"
                    data-state="${state}"
                    ${state === 'pending' ? 'disabled' : ''}
                >
                <span>Bank account</span>
                ${state === 'pending' ? '<span class="ach-token-status">This payment method is not ready for use yet. Verification is pending.</span>' : ''}
            </label>`
        )
        .join('');
    const hasSelectableToken = tokenStates.some((state) => state !== 'pending');
    const storedTokenControls = tokenStates.length
        ? `${tokenInputs}
            <div id="mandate-authorization" hidden>
                <input type="checkbox" id="accept-mandate">
            </div>
            <button id="pay-now" ${hasSelectableToken ? '' : 'disabled'}><svg class="hidden"></svg><span>Pay now</span></button>`
        : `<input type="checkbox" id="accept-terms">
            <input id="account-holder-name-field" value="Jane Doe">
            <input id="email-field" value="jane@example.test">
            <button id="new-bank"><svg class="hidden"></svg><span>New bank</span></button>`;

    const body = `<div id="stripe-ach-payment">
            ${addressMeta(address)}
            <meta name="client_secret" content="pi_secret_test">
            <meta name="mandate_client_secret" content="${escapeAttribute(mandateClientSecret)}">
            <div id="errors" hidden></div>
            <form id="server-response">
                <input name="source">
                <input id="gateway_response">
                <input id="bank_account_response">
                <input id="setup_intent_id">
            </form>
            ${storedTokenControls}
        </div>`;

    if (options.implementation === 'legacy') {
        await page.setContent(`<!doctype html>
            <html>
                <head>
                    <meta name="stripe-publishable-key" content="pk_test_browser">
                    <script>${stripeStub()}</script>
                </head>
                <body>
                    ${body}
                    <script>${legacyPaymentSource}</script>
                </body>
            </html>`);

        return;
    }

    await openModuleHarness(
        page,
        '/resources/js/clients/payments/stripe-ach-pay.js',
        body
    );
}

async function openLayoutHarness(
    page: Page,
    address: BillingAddress
): Promise<void> {
    await page.setContent(`<!doctype html>
        <html>
            <head>${addressMeta(address)}</head>
            <body>
                <script>${livewireStub()}</script>
                <div data-ref="required-fields-container"></div>
                <div data-ref="gateway-container" class="pointer-events-none opacity-25"></div>
                <script>${paymentLayoutListener}</script>
            </body>
        </html>`);

    await initializeLivewireListener(page);
}

async function openModuleHarness(
    page: Page,
    scriptPath: string,
    body: string
): Promise<void> {
    await page.route('http://ach.test/**', async (route) => {
        const pathname = new URL(route.request().url()).pathname;

        if (pathname === '/__harness') {
            await route.fulfill({
                contentType: 'text/html',
                body: `<!doctype html>
                    <html>
                        <head>
                            <meta name="stripe-publishable-key" content="pk_test_browser">
                            <script>${stripeStub()}</script>
                        </head>
                        <body>
                            ${body}
                            <script type="module" src="${scriptPath}" onload="document.body.dataset.scriptLoaded = 'true'"></script>
                        </body>
                    </html>`,
            });

            return;
        }

        if (pathname === '/resources/js/clients/payments/stripe-ach.js') {
            await route.fulfill({
                contentType: 'text/javascript',
                body: authorizeSource,
            });

            return;
        }

        if (pathname === '/resources/js/clients/payments/stripe-ach-pay.js') {
            await route.fulfill({
                contentType: 'text/javascript',
                body: paymentSource,
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

    await page.goto('http://ach.test/__harness');
    await expect(page.locator('body')).toHaveAttribute(
        'data-script-loaded',
        'true'
    );
}

function addressMeta(address: BillingAddress): string {
    return `<meta name="contact-email" content="jane@example.test">
        <meta name="stripe-client-secret" content="seti_secret_test">
        <meta name="address-1" content="${escapeAttribute(address.line1)}">
        <meta name="address-2" content="${escapeAttribute(address.line2)}">
        <meta name="city" content="${escapeAttribute(address.city)}">
        <meta name="state" content="${escapeAttribute(address.state)}">
        <meta name="postal_code" content="${escapeAttribute(address.postal_code)}">
        <meta name="country" content="${escapeAttribute(address.country)}">`;
}

function escapeAttribute(value: string): string {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('"', '&quot;')
        .replaceAll('<', '&lt;');
}

function livewireStub(): string {
    return `window.__livewireListeners = {};
        window.Livewire = {
            on(name, callback) {
                window.__livewireListeners[name] = callback;
            }
        };`;
}

function stripeStub(): string {
    return `window.__stripeCalls = {
            collectSetup: [],
            collectPayment: [],
            confirmSetup: [],
            confirmPayment: []
        };
        window.__stripeBehavior = {};
        window.__stripeResult = async function (name, fallback) {
            const result = window.__stripeBehavior[name];
            if (result && result.throws) {
                throw new Error(result.throws);
            }
            return result === undefined ? fallback : result;
        };
        window.Stripe = function () {
            if (window.__stripeBehavior.initializeThrows) {
                throw new Error(window.__stripeBehavior.initializeThrows);
            }
            return {
                collectBankAccountForSetup(options) {
                    window.__stripeCalls.collectSetup.push(options);
                    return window.__stripeResult('collectSetup', {
                        setupIntent: {id: 'seti_default', status: 'succeeded'}
                    });
                },
                collectBankAccountForPayment(options) {
                    window.__stripeCalls.collectPayment.push(options);
                    return window.__stripeResult('collectPayment', {
                        paymentIntent: {id: 'pi_default', status: 'requires_confirmation'}
                    });
                },
                confirmUsBankAccountSetup(clientSecret, options) {
                    window.__stripeCalls.confirmSetup.push({clientSecret, options});
                    return window.__stripeResult('confirmSetup', {
                        setupIntent: {id: 'seti_confirmed', status: 'succeeded'}
                    });
                },
                confirmUsBankAccountPayment(clientSecret) {
                    window.__stripeCalls.confirmPayment.push({clientSecret});
                    return window.__stripeResult('confirmPayment', {
                        paymentIntent: {id: 'pi_processing', status: 'processing'}
                    });
                }
            };
        };
        HTMLFormElement.prototype.submit = function () {
            this.dataset.submitted = 'true';
        };`;
}

async function initializeLivewireListener(page: Page): Promise<void> {
    await page.evaluate(() => {
        document.dispatchEvent(new Event('livewire:init'));
    });

    await expect
        .poll(() =>
            page.evaluate(
                () =>
                    typeof window.__livewireListeners[
                        'passed-required-fields-check'
                    ] === 'function'
            )
        )
        .toBe(true);
}

async function dispatchRequiredFieldsPassed(
    page: Page,
    address: BillingAddress
): Promise<void> {
    await page.evaluate((billingAddress) => {
        window.__livewireListeners['passed-required-fields-check']({
            client_postal_code: billingAddress.postal_code,
            billingAddress,
        });
    }, address);
}

async function setStripeBehavior(
    page: Page,
    behavior: Record<string, unknown>
): Promise<void> {
    await page.evaluate((value) => {
        window.__stripeBehavior = value;
    }, behavior);
}

async function firstStripeCallAddress(
    page: Page,
    call: 'collectSetup' | 'collectPayment'
): Promise<Record<string, string>> {
    return page.evaluate((callName) => {
        const address =
            window.__stripeCalls[callName][0]?.params?.payment_method_data
                ?.billing_details?.address;

        if (!address) {
            throw new Error(
                `Stripe call ${callName} did not include an address.`
            );
        }

        return address;
    }, call);
}

async function expectStripeCallCount(
    page: Page,
    call: 'collectSetup' | 'collectPayment' | 'confirmSetup' | 'confirmPayment',
    count: number
): Promise<void> {
    await expect
        .poll(() =>
            page.evaluate(
                (callName) => window.__stripeCalls[callName].length,
                call
            )
        )
        .toBe(count);
}

async function expectAddressMeta(
    page: Page,
    address: BillingAddress
): Promise<void> {
    const mapping: Record<keyof BillingAddress, string> = {
        line1: 'address-1',
        line2: 'address-2',
        city: 'city',
        state: 'state',
        postal_code: 'postal_code',
        country: 'country',
    };

    for (const [field, meta] of Object.entries(mapping)) {
        await expect(page.locator(`meta[name="${meta}"]`)).toHaveAttribute(
            'content',
            address[field as keyof BillingAddress]
        );
    }
}

async function expectVisibleError(page: Page, message: string): Promise<void> {
    await expect(page.locator('#errors')).toBeVisible();
    await expect(page.locator('#errors')).toHaveText(message);
}

async function expectButtonReady(page: Page, selector: string): Promise<void> {
    const button = page.locator(selector);

    await expect(button).toBeEnabled();
    await expect(button.locator('svg')).toHaveClass(/hidden/);
    await expect(button.locator('span')).not.toHaveClass(/hidden/);
}

async function expectSubmitted(page: Page, selector: string): Promise<void> {
    await expect(page.locator(selector)).toHaveAttribute(
        'data-submitted',
        'true'
    );
}

declare global {
    interface Window {
        __livewireListeners: Record<
            string,
            (event: Record<string, unknown>) => void
        >;
        __stripeBehavior: Record<string, unknown>;
        __stripeCalls: Record<string, StripeCall[]>;
    }
}
