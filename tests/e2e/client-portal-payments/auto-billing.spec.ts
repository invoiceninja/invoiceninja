import { type Page } from '@playwright/test';
import { test as base, expect, type ApiFixture } from '../fixtures';
import {
    getEntity,
    updateCompanyGatewayRequirements,
    type CompanyGatewayEntity,
} from '../api-helpers';
import {
    createRecurringInvoice,
    createSentInvoice,
    type PortalEntity,
} from '../portal-entity-helpers';
import { dismissCookieConsent } from '../client-portal-helpers';
import { runArtisan } from '../artisan-helpers';
import { decodePrimaryKey } from '../hash-helpers';
import { StripePaymentGateway } from '../gateways/stripe-payment-gateway';
import {
    navigateToPortalGatewayCheckout,
    preparePortalPaymentContext,
} from '../gateways/payment-flow-helpers';

// Exercise the real checkout pages and Livewire requests without submitting a charge.
const test = base.extend<{ checkoutGateway: CompanyGatewayEntity }>({
    checkoutGateway: async ({ api }, use) => {
        const stripe = new StripePaymentGateway();
        const { availability } = await stripe.setupExclusiveTestEnvironment(
            api.context
        );
        try {
            stripe.skipUnlessAvailable(availability);
            const gateway = availability.companyGateway!;
            try {
                await use(gateway);
            } finally {
                await updateCompanyGatewayRequirements(api.context, gateway, {
                    token_billing: gateway.token_billing,
                });
            }
        } finally {
            await stripe.restoreExclusiveGateway();
        }
    },
});

const policies = [
    { policy: 'always', enabled: true, editable: false },
    { policy: 'off', enabled: false, editable: false },
    { policy: 'optin', enabled: false, editable: true },
    { policy: 'optout', enabled: true, editable: true },
] as const;

const radios = (page: Page) =>
    page.locator('input[name^="auto_bill_enabled_"]');

// recurring_id is not editable through the invoice API. Attach only our freshly
// created invoices, copying the template flag as the generation factory does.
function attachInvoices(
    recurring: PortalEntity,
    invoices: PortalEntity[]
): void {
    const ids = invoices.map((invoice) => Number(decodePrimaryKey(invoice.id)));
    runArtisan(
        `$recurring = \\App\\Models\\RecurringInvoice::findOrFail(${Number(decodePrimaryKey(recurring.id))});` +
            `\\App\\Models\\Invoice::whereIn('id', ${JSON.stringify(ids)})` +
            "->where('company_id', $recurring->company_id)->where('client_id', $recurring->client_id)" +
            "->update(['recurring_id' => $recurring->id, 'auto_bill_enabled' => $recurring->auto_bill_enabled]);"
    );
}

async function assertPreference(
    api: ApiFixture,
    recurring: PortalEntity,
    invoices: PortalEntity[],
    enabled: boolean
): Promise<void> {
    await expect
        .poll(async () => {
            const records = await Promise.all([
                getEntity<PortalEntity>(
                    api.context,
                    'recurring_invoices',
                    recurring.id
                ),
                ...invoices.map((invoice) =>
                    getEntity<PortalEntity>(api.context, 'invoices', invoice.id)
                ),
            ]);
            return records.map((record) => record.auto_bill_enabled);
        })
        .toEqual([enabled, ...invoices.map(() => enabled)]);

    // A subsequent generated invoice must inherit the persisted preference.
    // Construct it without saving/sending, so this check cannot trigger billing.
    const inherited = runArtisan(
        `$recurring = \\App\\Models\\RecurringInvoice::findOrFail(${Number(decodePrimaryKey(recurring.id))});` +
            'echo json_encode((bool) \\App\\Factory\\RecurringInvoiceToInvoiceFactory::create($recurring, $recurring->client)->auto_bill_enabled);'
    );
    expect(JSON.parse(inherited)).toBe(enabled);
}

async function choosePreference(page: Page, enabled: boolean): Promise<void> {
    const response = page.waitForResponse(
        (response) =>
            response.url().includes('/livewire/') &&
            response.request().method() === 'POST' &&
            response.request().postData()?.includes('setAutoBilling') === true
    );
    await radios(page)
        .locator(`xpath=self::input[@value="${enabled ? 1 : 0}"]`)
        .check();
    expect((await response).ok()).toBe(true);
    await expect(
        radios(page).locator(`xpath=self::input[@value="${enabled ? 1 : 0}"]`)
    ).toBeChecked();
}

for (const flow of ['default', 'smooth'] as const) {
    test.describe(`Checkout auto billing — ${flow}`, () => {
        for (const { policy, enabled, editable } of policies) {
            test(`${policy}: configured default, persistence and invoice propagation`, async ({
                api,
                page,
                checkoutGateway,
            }) => {
                test.setTimeout(120_000);
                const gateway = await updateCompanyGatewayRequirements(
                    api.context,
                    checkoutGateway,
                    { token_billing: editable ? 'optin' : policy }
                );
                const { client, invoice } = await preparePortalPaymentContext(
                    api,
                    page,
                    gateway,
                    flow
                );
                const recurring = await createRecurringInvoice(api, client, {
                    autoBill: policy,
                });
                expect(recurring.auto_bill_enabled).toBe(enabled);
                const sibling = await createSentInvoice(api, client);
                const unrelated = await createSentInvoice(api, client);
                attachInvoices(recurring, [invoice, sibling]);

                const openCheckout = async () => {
                    await navigateToPortalGatewayCheckout(
                        page,
                        gateway,
                        1,
                        flow,
                        invoice
                    );
                    await dismissCookieConsent(page);
                    await expect(
                        page.locator('#save-card--container')
                    ).toHaveCount(1);
                };
                await openCheckout();

                const saveCard = page.locator(
                    'input[name="token-billing-checkbox"]'
                );
                if (editable) {
                    await expect(
                        saveCard.locator(
                            `xpath=self::input[@value="${enabled}"]`
                        )
                    ).toBeChecked();
                    await expect(radios(page)).toHaveCount(2);
                    await expect(
                        radios(page).locator(
                            `xpath=self::input[@value="${enabled ? 1 : 0}"]`
                        )
                    ).toBeChecked();
                    await expect(
                        page.getByText(
                            'Automatically pay future invoices in this recurring series?',
                            { exact: false }
                        )
                    ).toBeVisible();

                    await expect(
                        page.locator('#save-payment-method--container')
                    ).toBeVisible({ visible: !enabled });
                    if (!enabled) {
                        // Saving a card alone does not opt the client in.
                        await saveCard
                            .locator('xpath=self::input[@value="true"]')
                            .check();
                    }
                    await assertPreference(
                        api,
                        recurring,
                        [invoice, sibling],
                        enabled
                    );
                    await choosePreference(page, !enabled);
                    await expect(
                        page.locator('#save-payment-method--container')
                    ).toBeVisible({ visible: enabled });
                    await expect(
                        page.locator(
                            'input[name="token-billing-checkbox"]:checked'
                        )
                    ).toHaveValue(String(!enabled));
                    if (enabled) {
                        await saveCard
                            .locator('xpath=self::input[@value="true"]')
                            .check();
                        await saveCard
                            .locator('xpath=self::input[@value="false"]')
                            .check();
                    }
                    await assertPreference(
                        api,
                        recurring,
                        [invoice, sibling],
                        !enabled
                    );
                    expect(
                        (
                            await getEntity<PortalEntity>(
                                api.context,
                                'invoices',
                                unrelated.id
                            )
                        ).auto_bill_enabled
                    ).toBe(unrelated.auto_bill_enabled);

                    // Consent inputs must not enter a gateway form submission.
                    expect(
                        await radios(page).evaluateAll((inputs) =>
                            inputs.every(
                                (input) =>
                                    (input as HTMLInputElement).form === null
                            )
                        )
                    ).toBe(true);
                    await openCheckout();
                    await expect(
                        radios(page).locator(
                            `xpath=self::input[@value="${!enabled ? 1 : 0}"]`
                        )
                    ).toBeChecked();
                    await choosePreference(page, enabled);
                    await expect(
                        page.locator('#save-payment-method--container')
                    ).toBeVisible({ visible: !enabled });
                    await expect(
                        page.locator(
                            'input[name="token-billing-checkbox"]:checked'
                        )
                    ).toHaveValue(String(enabled));
                    await assertPreference(
                        api,
                        recurring,
                        [invoice, sibling],
                        enabled
                    );
                } else {
                    await expect(
                        page.locator('#save-card--container')
                    ).toBeHidden();
                    await expect(saveCard).toHaveValue(String(enabled));
                    await expect(radios(page)).toHaveCount(0);
                    await assertPreference(
                        api,
                        recurring,
                        [invoice, sibling],
                        enabled
                    );
                }
            });
        }

        for (const tokenBilling of ['always', 'off'] as const) {
            test(`optin remains editable when saving cards is ${tokenBilling}`, async ({
                api,
                page,
                checkoutGateway,
            }) => {
                const gateway = await updateCompanyGatewayRequirements(
                    api.context,
                    checkoutGateway,
                    { token_billing: tokenBilling }
                );
                const { client, invoice } = await preparePortalPaymentContext(
                    api,
                    page,
                    gateway,
                    flow
                );
                const recurring = await createRecurringInvoice(api, client, {
                    autoBill: 'optin',
                });
                attachInvoices(recurring, [invoice]);
                await navigateToPortalGatewayCheckout(
                    page,
                    gateway,
                    1,
                    flow,
                    invoice
                );
                await dismissCookieConsent(page);
                await expect(
                    page.locator('#save-payment-method--container')
                ).toBeHidden();
                await expect(radios(page)).toHaveCount(2);
                await choosePreference(page, true);
                await expect(
                    page.locator('input[name="token-billing-checkbox"]:checked')
                ).toHaveValue('true');
                await assertPreference(api, recurring, [invoice], true);
            });
        }

        test('optout stays editable with a saved card and forces saving for a new card', async ({
            api,
            page,
            checkoutGateway,
        }, testInfo) => {
            test.setTimeout(120_000);
            const gateway = await updateCompanyGatewayRequirements(
                api.context,
                checkoutGateway,
                { token_billing: 'optin' }
            );
            const { client, invoice } = await preparePortalPaymentContext(
                api,
                page,
                gateway,
                flow
            );
            const recurring = await createRecurringInvoice(api, client, {
                autoBill: 'optout',
            });
            attachInvoices(recurring, [invoice]);

            // A display-only token exercises the new-card/token switch. Never charge it.
            const tokenId = runArtisan(
                `$client = \\App\\Models\\Client::findOrFail(${Number(decodePrimaryKey(client.id))});` +
                    '$token = new \\App\\Models\\ClientGatewayToken();' +
                    '$token->company_id = $client->company_id; $token->client_id = $client->id;' +
                    `$token->company_gateway_id = ${Number(decodePrimaryKey(gateway.id))};` +
                    '$token->gateway_type_id = 1; $token->token = "pm_playwright_display_only";' +
                    '$token->meta = (object) ["type" => 1, "brand" => "Visa", "last4" => "4242"];' +
                    '$token->saveQuietly(); echo $token->id;'
            );
            try {
                // Stub only the SDK's success result; use the application's actual
                // payment handler and intercept its POST before it reaches Laravel.
                await page.route(
                    /^https:\/\/js\.stripe\.com\/v3\/?(?:\?.*)?$/,
                    (route) =>
                        route.fulfill({
                            contentType: 'application/javascript',
                            body: `window.Stripe = () => ({
                        elements: () => ({ create: () => ({ mount() {} }) }),
                        handleCardPayment: async () => ({ paymentIntent: { id: 'pi_playwright_not_charged', status: 'succeeded' } })
                    });`,
                        })
                );
                await page.route('**/payments/process/response', (route) =>
                    route.fulfill({
                        status: 200,
                        body: 'Payment submission intercepted by Playwright',
                    })
                );
                await navigateToPortalGatewayCheckout(
                    page,
                    gateway,
                    1,
                    flow,
                    invoice
                );
                await dismissCookieConsent(page);
                await page.locator('.toggle-payment-with-token').check();
                await expect(
                    page.locator('#save-card--container')
                ).toBeHidden();
                await expect(radios(page).first()).toBeVisible();
                await choosePreference(page, false);
                await expect(
                    page.locator('#save-card--container')
                ).toBeHidden();
                await assertPreference(api, recurring, [invoice], false);
                await choosePreference(page, true);
                await expect(
                    page.locator('#save-card--container')
                ).toBeHidden();
                await assertPreference(api, recurring, [invoice], true);
                await page.locator('#toggle-payment-with-credit-card').check();
                await expect(radios(page)).toHaveCount(2);
                await expect(radios(page).first()).toBeVisible();
                await expect(
                    page.locator('#save-payment-method--container')
                ).toBeHidden();
                await expect(
                    page.locator('input[name="token-billing-checkbox"]:checked')
                ).toHaveValue('true');

                await choosePreference(page, false);
                for (const width of [390, 1280]) {
                    await page.setViewportSize({ width, height: 900 });
                    const auto = await radios(page).first().boundingBox();
                    const save = await page
                        .locator('#save-payment-method--container')
                        .boundingBox();
                    expect(auto).not.toBeNull();
                    expect(save).not.toBeNull();
                    expect(auto!.y).toBeLessThan(save!.y);
                    expect(
                        await page.evaluate(
                            () =>
                                document.documentElement.scrollWidth <=
                                window.innerWidth
                        )
                    ).toBe(true);
                    await page
                        .locator('#payment-method-preferences')
                        .screenshot({
                            path: testInfo.outputPath(
                                `preferences-${width}.png`
                            ),
                        });
                }
                await choosePreference(page, true);
                await page.locator('#payment-method-preferences').screenshot({
                    path: testInfo.outputPath('preferences-enabled.png'),
                });
                const submitted = page.waitForRequest(
                    (request) =>
                        request.url().includes('/payments/process/response') &&
                        request.method() === 'POST',
                    { timeout: 15_000 }
                );
                await page.locator('#pay-now').click();
                const payload = new URLSearchParams(
                    (await submitted).postData()!
                );
                expect(payload.get('store_card')).toBe('true');
                expect(payload.get('token')).toBe('');
            } finally {
                runArtisan(
                    `\\App\\Models\\ClientGatewayToken::where('id', ${Number(tokenId)})->where('client_id', ${Number(decodePrimaryKey(client.id))})->delete();`
                );
            }
        });

        test('choice belongs only to the first invoice even when later invoices are unpaid', async ({
            api,
            page,
            checkoutGateway,
        }) => {
            const { client, invoice } = await preparePortalPaymentContext(
                api,
                page,
                checkoutGateway,
                flow
            );
            const recurring = await createRecurringInvoice(api, client, {
                autoBill: 'optin',
            });
            const subsequent = await createSentInvoice(api, client);
            attachInvoices(recurring, [invoice, subsequent]);
            await navigateToPortalGatewayCheckout(
                page,
                checkoutGateway,
                1,
                flow,
                invoice
            );
            await expect(radios(page)).toHaveCount(2);

            for (const payable of [subsequent, invoice]) {
                await navigateToPortalGatewayCheckout(
                    page,
                    checkoutGateway,
                    1,
                    flow,
                    payable
                );
                await expect(page.locator('#save-card--container')).toHaveCount(
                    1
                );
                await expect(radios(page)).toHaveCount(
                    payable.id === invoice.id ? 2 : 0
                );
            }
            expect(
                (
                    await getEntity<PortalEntity>(
                        api.context,
                        'recurring_invoices',
                        recurring.id
                    )
                ).auto_bill_enabled
            ).toBe(false);
        });

        for (const tokenBilling of [
            'optin',
            'optout',
            'always',
            'off',
        ] as const) {
            for (const autoBill of ['optin', 'optout'] as const) {
                test(`${autoBill}, gateway ${tokenBilling}: save-method inputs update before Livewire starts`, async ({
                    api,
                    page,
                    checkoutGateway,
                }) => {
                    const gateway = await updateCompanyGatewayRequirements(
                        api.context,
                        checkoutGateway,
                        { token_billing: tokenBilling }
                    );
                    const { client, invoice } =
                        await preparePortalPaymentContext(
                            api,
                            page,
                            gateway,
                            flow
                        );
                    const recurring = await createRecurringInvoice(
                        api,
                        client,
                        {
                            autoBill,
                        }
                    );
                    attachInvoices(recurring, [invoice]);
                    await navigateToPortalGatewayCheckout(
                        page,
                        gateway,
                        1,
                        flow,
                        invoice
                    );
                    await dismissCookieConsent(page);

                    // Observe the DOM in the same change event, before any network round trip.
                    await page.evaluate(() => {
                        document.addEventListener('change', (event) => {
                            const target = event.target as HTMLInputElement;
                            if (!target.name?.startsWith('auto_bill_enabled_'))
                                return;
                            (window as any).__saveMethodAtChange = (
                                document.querySelector(
                                    'input[name="token-billing-checkbox"]:checked'
                                ) as HTMLInputElement
                            )?.value;
                        });
                    });

                    for (const enabled of [
                        autoBill === 'optin',
                        autoBill !== 'optin',
                    ]) {
                        const expectedSave =
                            enabled ||
                            ['always', 'optout'].includes(tokenBilling);
                        const optionalVisible =
                            !enabled &&
                            ['optin', 'optout'].includes(tokenBilling);
                        let releaseRequest!: () => void;
                        let releaseResponse!: () => void;
                        let signalRequested!: () => void;
                        let signalSaved!: () => void;
                        const requestGate = new Promise<void>((resolve) => {
                            releaseRequest = resolve;
                        });
                        const responseGate = new Promise<void>((resolve) => {
                            releaseResponse = resolve;
                        });
                        const requested = new Promise<void>((resolve) => {
                            signalRequested = resolve;
                        });
                        const saved = new Promise<void>((resolve) => {
                            signalSaved = resolve;
                        });
                        await page.route('**/livewire/**', async (route) => {
                            const body = route.request().postDataJSON() as {
                                components?: { calls?: { method: string }[] }[];
                            };
                            if (
                                !body.components?.some((component) =>
                                    component.calls?.some(
                                        (call) =>
                                            call.method === 'setAutoBilling'
                                    )
                                )
                            ) {
                                await route.continue();
                                return;
                            }
                            signalRequested();
                            await requestGate;
                            const response = await route.fetch();
                            expect(response.ok()).toBe(true);
                            signalSaved();
                            await responseGate;
                            await route.fulfill({ response }).catch(() => {});
                        });
                        try {
                            if (autoBill === 'optout') {
                                await page
                                    .locator(
                                        'input[name^="auto_bill_enabled_"]:checked'
                                    )
                                    .focus();
                                await page.keyboard.press(
                                    enabled ? 'ArrowLeft' : 'ArrowRight'
                                );
                            } else {
                                await radios(page)
                                    .locator(
                                        `xpath=self::input[@value="${enabled ? 1 : 0}"]`
                                    )
                                    .check();
                            }
                            expect(
                                await page.evaluate(
                                    () => (window as any).__saveMethodAtChange
                                )
                            ).toBe(String(expectedSave));
                            await requested;
                            await expect(radios(page).first()).toBeDisabled();
                            await expect(
                                page.locator(
                                    'input[name="token-billing-checkbox"]:checked'
                                )
                            ).toHaveValue(String(expectedSave));
                            await expect(
                                page.locator('#save-payment-method--container')
                            ).toBeVisible({ visible: optionalVisible });
                            await expect(
                                page.locator('#pay-now')
                            ).toBeEnabled();
                            releaseRequest();
                            await saved;
                            expect(
                                (
                                    await getEntity<PortalEntity>(
                                        api.context,
                                        'recurring_invoices',
                                        recurring.id
                                    )
                                ).auto_bill_enabled
                            ).toBe(enabled);
                            await expect(
                                page.locator(
                                    'input[name="token-billing-checkbox"]:checked'
                                )
                            ).toHaveValue(String(expectedSave));
                            await expect(
                                page.locator('#pay-now')
                            ).toBeEnabled();
                            // A later Livewire response must not undo a save-method choice
                            // made while the auto-billing preference was being persisted.
                            if (optionalVisible) {
                                await page
                                    .locator(
                                        `input[name="token-billing-checkbox"][value="${!expectedSave}"]`
                                    )
                                    .check();
                            }
                            releaseResponse();
                            await expect(radios(page).first()).toBeEnabled();
                            await expect(
                                page.locator(
                                    'input[name="token-billing-checkbox"]:checked'
                                )
                            ).toHaveValue(
                                String(
                                    optionalVisible
                                        ? !expectedSave
                                        : expectedSave
                                )
                            );
                        } finally {
                            releaseRequest();
                            releaseResponse();
                            await page.unrouteAll({ behavior: 'wait' });
                        }
                    }
                });
            }
        }

        test('failed preference request permits a successful retry', async ({
            api,
            page,
            checkoutGateway,
        }) => {
            const gateway = await updateCompanyGatewayRequirements(
                api.context,
                checkoutGateway,
                { token_billing: 'optin' }
            );
            const { client, invoice } = await preparePortalPaymentContext(
                api,
                page,
                gateway,
                flow
            );
            const recurring = await createRecurringInvoice(api, client, {
                autoBill: 'optin',
            });
            attachInvoices(recurring, [invoice]);
            await navigateToPortalGatewayCheckout(
                page,
                gateway,
                1,
                flow,
                invoice
            );
            await dismissCookieConsent(page);
            let failed = false;
            await page.route('**/livewire/**', async (route) => {
                const body = route.request().postDataJSON();
                if (
                    !failed &&
                    body.components?.some(
                        (component: { calls?: { method: string }[] }) =>
                            component.calls?.some(
                                (call) => call.method === 'setAutoBilling'
                            )
                    )
                ) {
                    failed = true;
                    await route.abort('failed');
                } else {
                    await route.continue();
                }
            });
            await radios(page).locator('xpath=self::input[@value="1"]').check();
            await expect.poll(() => failed).toBe(true);
            await expect(radios(page).first()).toBeEnabled();
            await expect(
                page.locator('input[name="token-billing-checkbox"]:checked')
            ).toHaveValue('true');
            expect(
                (
                    await getEntity<PortalEntity>(
                        api.context,
                        'recurring_invoices',
                        recurring.id
                    )
                ).auto_bill_enabled
            ).toBe(false);
            // A local update is optimistic; persistence still requires a successful request.
            await choosePreference(page, false);
            await choosePreference(page, true);
            expect(
                (
                    await getEntity<PortalEntity>(
                        api.context,
                        'recurring_invoices',
                        recurring.id
                    )
                ).auto_bill_enabled
            ).toBe(true);
            await expect(
                page.locator('input[name="token-billing-checkbox"]:checked')
            ).toHaveValue('true');
        });

        test('ordinary invoice has no recurring auto-billing choice', async ({
            api,
            page,
            checkoutGateway,
        }) => {
            const { invoice } = await preparePortalPaymentContext(
                api,
                page,
                checkoutGateway,
                flow
            );
            await navigateToPortalGatewayCheckout(
                page,
                checkoutGateway,
                1,
                flow,
                invoice
            );
            await expect(page.locator('#save-card--container')).toHaveCount(1);
            await expect(radios(page)).toHaveCount(0);
        });
    });
}
