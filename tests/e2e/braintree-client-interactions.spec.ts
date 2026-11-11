import { readFile } from 'node:fs/promises';
import { expect, test, type Page } from '@playwright/test';

// Browser integration tests exercise the production modules with controlled SDK
// responses. Processor integration is covered separately by the sandbox suite.
async function openCheckout(
    page: Page,
    method: string,
    options: Record<string, unknown> = {}
) {
    const common = (
        await readFile(
            'resources/js/clients/payments/braintree/payment.js',
            'utf8'
        )
    ).replaceAll('export ', '');
    const source = await readFile(
        method === 'card'
            ? 'resources/js/clients/payments/braintree-credit-card.js'
            : method === 'paypal'
              ? 'resources/js/clients/payments/braintree-paypal.js'
              : 'resources/js/clients/payment_methods/braintree-ach.js',
        'utf8'
    );
    const root_id =
        method === 'card'
            ? 'braintree-credit-card-payment'
            : method === 'paypal'
              ? 'braintree-paypal-payment'
              : method === 'ach-pay'
                ? 'braintree-ach-payment'
                : 'braintree-ach-authorize';
    await page.setContent(`<style>.hidden { display: none !important; } #braintree-hosted-fields, #paypal-button { min-height: 40px; }</style><div id="${root_id}" data-retry-message="Try again" data-error-message="Unable to initialize payment" data-verification-error="Card verification failed" data-mandate-error="Accept the mandate">
        <meta name="client-token" content="client-token">
        <div id="errors" role="alert" hidden></div>
        <form><input name="gateway_response"><input name="store_card"><input name="token"><input name="source"><input name="nonce"><input name="client-data">
        <input name="threeds_enable" value="${options.threeds ? 'true' : 'false'}">
        <input name="threeds" value='{"amount":"105.00","email":"buyer@example.com","billingAddress":{"countryCodeAlpha2":"US"}}'></form>
        ${options.saved ? '<input type="radio" class="toggle-payment-with-token" name="payment-type" data-token="saved-token" data-nonce="saved-nonce" data-bin="411111">' : ''}
        <input type="radio" name="payment-type" id="toggle-payment-with-credit-card" checked>
        <div id="save-card--container"><input type="checkbox" name="token-billing-checkbox" value="true" ${options.store ? 'checked' : ''}></div>
        <div id="braintree-hosted-fields"><label for="braintree-card-number">Card number</label><div id="braintree-card-number"></div><label for="braintree-expiration-date">Expiry</label><div id="braintree-expiration-date"></div><div id="braintree-cvv-container"><label for="braintree-cvv">CVV</label><div id="braintree-cvv"></div></div></div>
        <div id="braintree-postal-code-container"><label for="braintree-postal-code">Postal code</label><div id="braintree-postal-code"></div></div><div id="paypal-button"></div>
        <input id="accept-terms" type="checkbox"><label for="accept-terms">I authorize Test Merchant to debit my account.</label>
        <input name="ownership-type" type="radio" value="personal" checked><input name="ownership-type" type="radio" value="business">
        <input name="account-type" type="radio" value="checking" checked><input name="account-type" type="radio" value="savings">
        <input id="account-holder-name" value="Jane Mary Smith" required><input id="account-number" value="1000000000" required><input id="routing-number" value="021000021" required>
        <input id="billing-street-address" value="200 Market St" required><input id="billing-extended-address"><input id="billing-locality" value="San Francisco" required><input id="billing-region" value="CA" required><input id="billing-postal-code" value="94105" required>
        <button id="pay-now"><svg hidden></svg><span>Pay</span></button><button id="pay-now-with-token" class="hidden"><svg hidden></svg><span>Pay saved</span></button><button id="authorize-bank-account"><svg hidden></svg><span>Add account</span></button>
        </div>`);
    await page.evaluate((settings) => {
        const state = {
            options: settings,
            submissions: [] as Record<string, string>[],
            calls: [] as any[],
            errors: [] as string[],
            teardowns: 0,
            buttons: null as any,
        };
        (window as any).checkout = state;
        window.addEventListener('error', (event) =>
            state.errors.push(event.message)
        );
        HTMLFormElement.prototype.submit = function () {
            state.submissions.push(
                Object.fromEntries(new FormData(this)) as Record<string, string>
            );
        };
        const instance = (extra = {}) => ({
            teardown: async () => {
                state.teardowns++;
            },
            ...extra,
        });
        const fail = (stage: string) => {
            state.calls.push([stage]);
            if (state.options.failure === stage)
                throw new Error(`${stage} failed`);
        };
        const sdk = {
            client: {
                create: async () => {
                    fail('client');
                    return instance({
                        getConfiguration: () => ({
                            gatewayConfiguration: {
                                challenges: [
                                    ...(settings.cvv === false ? [] : ['cvv']),
                                    ...(settings.postal ? ['postal_code'] : []),
                                ],
                            },
                        }),
                    });
                },
            },
            dataCollector: {
                create: async () => {
                    fail('collector');
                    return instance({ deviceData: 'device-data' });
                },
            },
            hostedFields: {
                create: async (fields: any) => {
                    fail('fields');
                    state.calls.push([
                        'fields-options',
                        Object.keys(fields.fields),
                    ]);
                    return instance({
                        tokenize: async () => {
                            fail('tokenize');
                            await new Promise((resolve) =>
                                setTimeout(resolve, 40)
                            );
                            return {
                                nonce: 'card-nonce',
                                details: { bin: '411111' },
                            };
                        },
                    });
                },
            },
            threeDSecure: {
                create: async () => {
                    fail('3ds-init');
                    return instance({
                        verifyCard: async (parameters: any) => {
                            fail('verify');
                            state.calls.push(['verify-options', parameters]);
                            parameters.onLookupComplete({}, () =>
                                state.calls.push(['lookup-complete'])
                            );
                            return {
                                nonce: 'verified-nonce',
                                liabilityShifted:
                                    state.options.shifted !== false,
                            };
                        },
                    });
                },
            },
            paypalCheckout: {
                create: async () => {
                    fail('paypal-init');
                    return instance({
                        loadPayPalSDK: async () => {
                            fail('paypal-sdk');
                        },
                        createPayment: async (data: any) => {
                            fail('agreement');
                            state.calls.push(['agreement-options', data]);
                            return 'billing-agreement';
                        },
                        tokenizePayment: async () => {
                            fail('paypal-tokenize');
                            return { nonce: 'paypal-nonce' };
                        },
                    });
                },
            },
            usBankAccount: {
                create: async () => {
                    fail('bank-init');
                    return instance({
                        tokenize: async (data: any) => {
                            fail('bank-tokenize');
                            state.calls.push(['bank-options', data]);
                            await new Promise((resolve) =>
                                setTimeout(resolve, 40)
                            );
                            return { nonce: 'bank-nonce' };
                        },
                    });
                },
            },
        };
        (window as any).braintree = sdk;
        (window as any).paypal = {
            FUNDING: { PAYPAL: 'paypal' },
            Buttons: (callbacks: any) => {
                state.buttons = callbacks;
                return {
                    render: async () => {
                        fail('paypal-render');
                    },
                    close: async () => {
                        state.teardowns++;
                    },
                };
            },
        };
    }, options);
    await page.addScriptTag({
        type: 'module',
        content: `${common}\n${source.replace(/^import[\s\S]*?;\s*/m, '')}`,
    });
    if (
        options.failure &&
        [
            'client',
            'collector',
            'fields',
            '3ds-init',
            'paypal-init',
            'paypal-sdk',
            'paypal-render',
            'bank-init',
        ].includes(String(options.failure))
    ) {
        await expect(page.locator('#errors')).toBeVisible();
    } else {
        await expect(
            page.locator(
                method === 'ach'
                    ? '#authorize-bank-account'
                    : options.saved
                      ? '#pay-now-with-token'
                      : '#pay-now'
            )
        ).toBeEnabled();
    }
}

async function submissions(page: Page) {
    return page.evaluate(() => (window as any).checkout.submissions);
}

for (const threeds of [false, true]) {
    for (const saved of [false, true]) {
        for (const store of [false, true]) {
            test(`cards: 3DS=${threeds}, saved=${saved}, store=${store}`, async ({
                page,
            }) => {
                await openCheckout(page, 'card', { threeds, saved, store });
                await page
                    .locator(saved ? '#pay-now-with-token' : '#pay-now')
                    .click();
                await expect
                    .poll(async () => (await submissions(page)).length)
                    .toBe(1);
                const [submission] = await submissions(page);
                expect(submission['client-data']).toBe('device-data');
                expect(submission.token).toBe(saved ? 'saved-token' : '');
                expect(submission.store_card).toBe(store ? 'true' : 'false');
                if (!saved || threeds)
                    expect(JSON.parse(submission.gateway_response).nonce).toBe(
                        threeds ? 'verified-nonce' : 'card-nonce'
                    );
                if (threeds) {
                    const calls = await page.evaluate(
                        () => (window as any).checkout.calls
                    );
                    expect(
                        calls.find(
                            (call: any[]) => call[0] === 'verify-options'
                        )[1]
                    ).toMatchObject({
                        amount: '105.00',
                        nonce: saved ? 'saved-nonce' : 'card-nonce',
                        bin: '411111',
                    });
                }
            });
        }
    }
}

for (const failure of [
    'client',
    'collector',
    'fields',
    '3ds-init',
    'tokenize',
    'verify',
]) {
    test(`cards: ${failure} failure prevents submission`, async ({ page }) => {
        await openCheckout(page, 'card', { threeds: true, failure });
        if (['tokenize', 'verify'].includes(failure))
            await page.locator('#pay-now').click();
        await expect(page.locator('#errors')).toContainText(
            `${failure} failed`
        );
        expect(await submissions(page)).toHaveLength(0);
        if (['tokenize', 'verify'].includes(failure)) {
            await page.evaluate(() => {
                (window as any).checkout.options.failure = '';
            });
            await page.locator('#pay-now').click();
            await expect
                .poll(async () => (await submissions(page)).length)
                .toBe(1);
        }
    });
}

test('cards: failed liability shift prevents payment', async ({ page }) => {
    await openCheckout(page, 'card', { threeds: true, shifted: false });
    await page.locator('#pay-now').click();
    await expect(page.locator('#errors')).toHaveText(
        'Card verification failed'
    );
    expect(await submissions(page)).toHaveLength(0);
    await expect(page.locator('#pay-now')).toBeEnabled();
});

test('cards: suppresses duplicate clicks while tokenizing', async ({
    page,
}) => {
    await openCheckout(page, 'card');
    await page.locator('#pay-now').evaluate((button: HTMLButtonElement) => {
        button.click();
        button.click();
    });
    await expect.poll(async () => (await submissions(page)).length).toBe(1);
});

test('cards: optional CVV follows gateway configuration', async ({ page }) => {
    await openCheckout(page, 'card', { cvv: false });
    await expect(page.locator('#braintree-cvv-container')).toBeHidden();
    expect(
        await page.evaluate(
            () =>
                (window as any).checkout.calls.find(
                    (call: any[]) => call[0] === 'fields-options'
                )[1]
        )
    ).toEqual(['number', 'expirationDate']);
});

for (const method of ['card', 'paypal']) {
    test(`${method}: switches saved and new payment methods`, async ({
        page,
    }) => {
        await openCheckout(page, method, { saved: true });
        const widget = page.locator(
            method === 'card' ? '#braintree-hosted-fields' : '#paypal-button'
        );
        await expect(widget).toBeHidden();
        await page.locator('#toggle-payment-with-credit-card').check();
        await expect(widget).toBeVisible();
        await expect(page.locator('input[name=token]')).toHaveValue('');
        await page.locator('.toggle-payment-with-token').check();
        await expect(widget).toBeHidden();
        await expect(page.locator('input[name=token]')).toHaveValue(
            'saved-token'
        );
    });
}

for (const store of [false, true]) {
    test(`PayPal: approval and vault consent=${store}`, async ({ page }) => {
        await openCheckout(page, 'paypal', { store });
        await page.evaluate(async () => {
            await (window as any).checkout.buttons.createBillingAgreement();
            await (window as any).checkout.buttons.onApprove({
                orderID: 'order',
            });
        });
        const [submission] = await submissions(page);
        expect(JSON.parse(submission.gateway_response).nonce).toBe(
            'paypal-nonce'
        );
        expect(submission.store_card).toBe(store ? 'true' : 'false');
        expect(submission['client-data']).toBe('device-data');
    });
}

for (const failure of [
    'client',
    'collector',
    'paypal-init',
    'paypal-sdk',
    'paypal-render',
    'agreement',
    'paypal-tokenize',
]) {
    test(`PayPal: ${failure} failure`, async ({ page }) => {
        await openCheckout(page, 'paypal', { failure });
        if (failure === 'agreement')
            await page.evaluate(async () => {
                try {
                    await (
                        window as any
                    ).checkout.buttons.createBillingAgreement();
                } catch (error) {
                    (window as any).checkout.buttons.onError(error);
                }
            });
        if (failure === 'paypal-tokenize')
            await page.evaluate(() =>
                (window as any).checkout.buttons.onApprove({})
            );
        await expect(page.locator('#errors')).toContainText(
            `${failure} failed`
        );
        expect(await submissions(page)).toHaveLength(0);
    });
}

test('PayPal: cancellation permits another attempt; duplicate approval is ignored', async ({
    page,
}) => {
    await openCheckout(page, 'paypal');
    await page.evaluate(async () => {
        await (window as any).checkout.buttons.createBillingAgreement();
        (window as any).checkout.buttons.onCancel();
    });
    await expect(page.locator('#pay-now')).toBeEnabled();
    await page.evaluate(async () => {
        await (window as any).checkout.buttons.onApprove({});
        await (window as any).checkout.buttons.onApprove({});
    });
    expect(await submissions(page)).toHaveLength(1);
});

test('PayPal: saved token submits once without buyer approval', async ({
    page,
}) => {
    await openCheckout(page, 'paypal', { saved: true });
    await page
        .locator('#pay-now-with-token')
        .evaluate((button: HTMLButtonElement) => {
            button.click();
            button.click();
        });
    expect(await submissions(page)).toHaveLength(1);
    expect((await submissions(page))[0].token).toBe('saved-token');
});

for (const ownership of ['personal', 'business']) {
    for (const account_type of ['checking', 'savings']) {
        test(`ACH: ${ownership} ${account_type} authorization`, async ({
            page,
        }) => {
            await openCheckout(page, 'ach');
            await page.locator('#accept-terms').check();
            await page
                .locator(`input[name=ownership-type][value=${ownership}]`)
                .check();
            await page
                .locator(`input[name=account-type][value=${account_type}]`)
                .check();
            await page
                .locator('#authorize-bank-account')
                .evaluate((button: HTMLButtonElement) => {
                    button.click();
                    button.click();
                });
            await expect
                .poll(async () => (await submissions(page)).length)
                .toBe(1);
            expect((await submissions(page))[0].nonce).toBe('bank-nonce');
            const parameters = await page.evaluate(
                () =>
                    (window as any).checkout.calls.find(
                        (call: any[]) => call[0] === 'bank-options'
                    )[1]
            );
            expect(parameters.mandateText).toBe(
                'I authorize Test Merchant to debit my account.'
            );
            expect(parameters.bankDetails).toMatchObject({
                ownershipType: ownership,
                accountType: account_type,
            });
            if (ownership === 'personal')
                expect(parameters.bankDetails).toMatchObject({
                    firstName: 'Jane',
                    lastName: 'Mary Smith',
                });
            else
                expect(parameters.bankDetails.businessName).toBe(
                    'Jane Mary Smith'
                );
        });
    }
}

for (const failure of ['client', 'bank-init', 'bank-tokenize']) {
    test(`ACH: ${failure} failure is recoverable`, async ({ page }) => {
        await openCheckout(page, 'ach', { failure });
        if (failure === 'bank-tokenize') {
            await page.locator('#accept-terms').check();
            await page.locator('#authorize-bank-account').click();
        }
        await expect(page.locator('#errors')).toContainText(
            `${failure} failed`
        );
        expect(await submissions(page)).toHaveLength(0);
        if (failure === 'bank-tokenize') {
            await page.evaluate(() => {
                (window as any).checkout.options.failure = '';
            });
            await page.locator('#authorize-bank-account').click();
            await expect
                .poll(async () => (await submissions(page)).length)
                .toBe(1);
        }
    });
}

test('ACH: mandate acceptance is required', async ({ page }) => {
    await openCheckout(page, 'ach');
    await page.locator('#authorize-bank-account').click();
    await expect(page.locator('#errors')).toHaveText('Accept the mandate');
    expect(await submissions(page)).toHaveLength(0);
});

test('ACH: required bank fields prevent tokenization', async ({ page }) => {
    await openCheckout(page, 'ach');
    await page.locator('#accept-terms').check();
    await page.locator('#account-number').fill('');
    await page.locator('#authorize-bank-account').click();
    expect(await submissions(page)).toHaveLength(0);
    expect(
        await page.evaluate(() =>
            (window as any).checkout.calls.some(
                (call: any[]) => call[0] === 'bank-tokenize'
            )
        )
    ).toBe(false);
});

test('ACH: saved account payment submits once', async ({ page }) => {
    await openCheckout(page, 'ach-pay', { saved: true });
    await page.locator('#pay-now').evaluate((button: HTMLButtonElement) => {
        button.click();
        button.click();
    });
    expect(await submissions(page)).toHaveLength(1);
    expect((await submissions(page))[0].source).toBe('saved-token');
});

for (const method of ['card', 'paypal', 'ach']) {
    test(`${method}: tears down removed roots and initializes replacements once`, async ({
        page,
    }) => {
        await openCheckout(page, method);
        await page.evaluate(() => {
            const root = document.querySelector('[id^=braintree-]')!;
            const replacement = root.cloneNode(true);
            root.remove();
            document.body.append(replacement);
        });
        await expect
            .poll(() => page.evaluate(() => (window as any).checkout.teardowns))
            .toBeGreaterThan(0);
        await expect(
            page.locator(
                method === 'ach' ? '#authorize-bank-account' : '#pay-now'
            )
        ).toBeEnabled();
        expect(
            await page.evaluate(
                () =>
                    (window as any).checkout.calls.filter(
                        (call: any[]) => call[0] === 'client'
                    ).length
            )
        ).toBe(2);
        expect(
            await page.evaluate(() => (window as any).checkout.errors)
        ).toEqual([]);
    });
}

for (const method of ['card', 'paypal', 'ach']) {
    test(`${method}: retries SDK initialization without duplicate listeners`, async ({
        page,
    }) => {
        await openCheckout(page, method, { failure: 'client' });
        await page.evaluate(() => {
            (window as any).checkout.options.failure = '';
        });
        await page.locator('#errors button').click();
        await expect(
            page.locator(
                method === 'ach' ? '#authorize-bank-account' : '#pay-now'
            )
        ).toBeEnabled();
        expect(
            await page.evaluate(
                () =>
                    (window as any).checkout.calls.filter(
                        (call: any[]) => call[0] === 'client'
                    ).length
            )
        ).toBe(2);
    });
}

test('cards: includes postal code when the gateway requires it', async ({
    page,
}) => {
    await openCheckout(page, 'card', { postal: true });
    await expect(
        page.locator('#braintree-postal-code-container')
    ).toBeVisible();
    expect(
        await page.evaluate(
            () =>
                (window as any).checkout.calls.find(
                    (call: any[]) => call[0] === 'fields-options'
                )[1]
        )
    ).toContain('postalCode');
});

test('cards: removing the checkout during tokenization prevents a late submission', async ({
    page,
}) => {
    await openCheckout(page, 'card');
    await page.locator('#pay-now').evaluate((button: HTMLButtonElement) => {
        button.click();
        document.querySelector('#braintree-credit-card-payment')!.remove();
    });
    await expect
        .poll(() => page.evaluate(() => (window as any).checkout.teardowns))
        .toBeGreaterThan(0);
    expect(await submissions(page)).toHaveLength(0);
});

test('PayPal: concurrent approval callbacks only tokenize once', async ({
    page,
}) => {
    await openCheckout(page, 'paypal');
    await page.evaluate(async () => {
        await Promise.all([
            (window as any).checkout.buttons.onApprove({}),
            (window as any).checkout.buttons.onApprove({}),
        ]);
    });
    expect(await submissions(page)).toHaveLength(1);
});

test('cards: failed saved-card authentication requests a fresh nonce before retry', async ({
    page,
}) => {
    await openCheckout(page, 'card', {
        saved: true,
        threeds: true,
        shifted: false,
    });
    await page.locator('#pay-now-with-token').click();
    await expect(page.locator('#errors')).toContainText(
        'Card verification failed'
    );
    await expect(page.locator('#errors button')).toHaveText('Try again');
    await expect(page.locator('#pay-now-with-token')).toBeDisabled();
    expect(await submissions(page)).toHaveLength(0);
});
