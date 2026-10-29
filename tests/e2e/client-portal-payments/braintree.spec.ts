import { test, expect } from '../fixtures';
import {
    getCompany,
    getEntity,
    getCompanyGateway,
    parseCompanyGatewayConfig,
    updateCompanyGatewayRequirements,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { BraintreePaymentGateway } from '../gateways/braintree-payment-gateway';
import {
    navigateToPortalGatewayCheckout,
    preparePortalPaymentContext,
    submitRequiredClientInfoIfPresent,
} from '../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../gateways/types';
import { createSentInvoice } from '../portal-entity-helpers';
import { dismissCookieConsent } from '../client-portal-helpers';
import {
    completePayPalSandboxPayment,
    payPalSandboxBuyerCredentials,
} from './paypal/flow-helpers';
import { parsePayPalRestKeys } from './paypal/env';
import { PAYPAL_REST_PAYMENT_METHODS } from './paypal/payment-methods';

test.use({
    headless:
        process.env.PLAYWRIGHT_HEADLESS === '1' || process.env.CI === 'true',
});

let gateway = new BraintreePaymentGateway();
let availability: GatewayAvailability;
let original_gateway: CompanyGatewayEntity | undefined;

test.describe('Braintree supported SDK sandbox checkout', () => {
    test.beforeEach(async ({ api, companyGuard, notificationGuard }) => {
        test.setTimeout(240_000);
        original_gateway = undefined;
        const gateway_type_id = Number(
            test
                .info()
                .annotations.find(
                    (annotation) => annotation.type === 'braintree-method'
                )?.description || 1
        );
        gateway = new BraintreePaymentGateway(
            gateway_type_id === 2 && process.env.BRAINTREE_ACH_KEYS
                ? 'BRAINTREE_ACH_KEYS'
                : 'BRAINTREE_KEYS'
        );
        const keys = JSON.parse(gateway.getEnvValue() || '{}');
        test.skip(
            keys.testMode !== true,
            `${gateway.envVar} must explicitly enable sandbox testMode`
        );
        const setup = await gateway.setupExclusiveTestEnvironment(api.context, {
            gatewayTypeId: gateway_type_id,
        });
        expect(
            setup.skipReason,
            'Braintree sandbox initialization'
        ).toBeUndefined();
        availability = setup.availability;
        original_gateway = await getCompanyGateway(
            api.context,
            availability.companyGateway!.id
        );
        const company = await getCompany(api.context);
        await companyGuard.update({
            settings: {
                ...company.settings,
                enable_client_portal_password: false,
            },
        });
        await notificationGuard.suppressPaymentEmails();
    });
    test.afterEach(async ({ api }) => {
        try {
            if (original_gateway) {
                const response = await api.context.request.put(
                    `/api/v1/company_gateways/${original_gateway.id}`,
                    {
                        data: {
                            gateway_key: original_gateway.gateway_key,
                            config: JSON.stringify(
                                parseCompanyGatewayConfig(original_gateway)
                            ),
                            token_billing: original_gateway.token_billing,
                            fees_and_limits: original_gateway.fees_and_limits,
                        },
                    }
                );
                expect(response.ok()).toBe(true);
            }
        } finally {
            await gateway.restoreExclusiveGateway();
        }
    });

    for (const flow of ['default', 'smooth'] as const) {
        for (const store of [false, true]) {
            test(`${flow}: new card, save=${store}, and saved-card continuity`, async ({
                api,
                page,
            }) => {
                const company_gateway = await updateCompanyGatewayRequirements(
                    api.context,
                    availability.companyGateway!,
                    { token_billing: store ? 'always' : 'off' }
                );
                const context = await preparePortalPaymentContext(
                    api,
                    page,
                    company_gateway,
                    flow
                );
                await navigateToPortalGatewayCheckout(
                    page,
                    company_gateway,
                    1,
                    flow,
                    context.invoice
                );
                await gateway.assertCheckoutReady(page);
                await gateway.completePayment(page);
                await gateway.assertPaymentSucceeded(page);
                const invoice = await getEntity(
                    api.context,
                    'invoices',
                    context.invoice.id
                );
                expect(Number(invoice.balance)).toBe(0);
                const client = await getEntity(
                    api.context,
                    'clients',
                    context.client.id
                );
                const tokens = (
                    (client.gateway_tokens as Array<{
                        gateway_type_id: number | string;
                    }>) || []
                ).filter((token) => Number(token.gateway_type_id) === 1);
                expect(tokens.length).toBe(store ? 1 : 0);
                if (store) {
                    const second_invoice = await createSentInvoice(
                        api,
                        context.client,
                        { cost: 31, label: 'braintree-saved-card' }
                    );
                    await navigateToPortalGatewayCheckout(
                        page,
                        company_gateway,
                        1,
                        flow,
                        second_invoice
                    );
                    await submitRequiredClientInfoIfPresent(page);
                    await dismissCookieConsent(page);
                    await expect(
                        page.locator('#pay-now-with-token')
                    ).toBeEnabled();
                    await page.locator('#pay-now-with-token').click();
                    await gateway.assertPaymentSucceeded(page);
                    expect(
                        Number(
                            (
                                await getEntity(
                                    api.context,
                                    'invoices',
                                    second_invoice.id
                                )
                            ).balance
                        )
                    ).toBe(0);
                }
            });
        }

        for (const scenario of [
            ...['personal', 'business'].flatMap((ownership_type) =>
                ['checking', 'savings'].map((account_type) => ({
                    ownership_type,
                    account_type,
                    account_number: '1000000000',
                }))
            ),
            ...[
                '1000000001',
                '1000000002',
                '1000000004',
                '1000000005',
                '1000000006',
            ].map((account_number) => ({
                ownership_type: 'personal',
                account_type: 'checking',
                account_number,
            })),
        ]) {
            const { ownership_type, account_type, account_number } = scenario;
            test(
                `${flow}: ACH ${ownership_type} ${account_type} ${account_number} verification and payments`,
                {
                    annotation: {
                        type: 'braintree-method',
                        description: '2',
                    },
                },
                async ({ api, page }) => {
                    const company_gateway = availability.companyGateway!;
                    const context = await preparePortalPaymentContext(
                        api,
                        page,
                        company_gateway,
                        flow
                    );
                    await navigateToPortalGatewayCheckout(
                        page,
                        company_gateway,
                        2,
                        flow,
                        context.invoice
                    );
                    await dismissCookieConsent(page);
                    await page.waitForFunction(() => {
                        const button =
                            document.querySelector<HTMLButtonElement>(
                                '#authorize-bank-account'
                            );
                        const errors =
                            document.querySelector<HTMLElement>('#errors');
                        return (
                            (button && !button.disabled) ||
                            (errors && !errors.hidden)
                        );
                    });
                    const initialization_error = await page
                        .locator('#errors')
                        .textContent();
                    test.skip(
                        initialization_error?.includes(
                            'US bank account is not enabled'
                        ) === true,
                        'Blocked: the configured Braintree sandbox does not have ACH enabled'
                    );
                    await expect(
                        page.locator('#authorize-bank-account')
                    ).toBeEnabled();
                    await page
                        .locator('#account-holder-name')
                        .fill('Jane Smith');
                    await page
                        .locator(
                            `input[name="ownership-type"][value="${ownership_type}"]`
                        )
                        .check();
                    await page
                        .locator(
                            `input[name="account-type"][value="${account_type}"]`
                        )
                        .check();
                    await page.locator('#account-number').fill(account_number);
                    await page.locator('#routing-number').fill('021000021');
                    await page
                        .locator('#billing-street-address')
                        .fill('200 Market St');
                    await page
                        .locator('#billing-locality')
                        .fill('San Francisco');
                    await page.locator('#billing-region').selectOption('CA');
                    await page.locator('#billing-postal-code').fill('94105');
                    await page.locator('#accept-terms').check();
                    await page.locator('#authorize-bank-account').click();
                    if (account_number !== '1000000000') {
                        await expect(
                            page
                                .locator(
                                    '#braintree-ach-authorize .alert-failure'
                                )
                                .filter({ hasText: /\S/ })
                                .first()
                        ).toBeVisible({ timeout: 60_000 });
                        const client = await getEntity(
                            api.context,
                            'clients',
                            context.client.id
                        );
                        expect(
                            (
                                (client.gateway_tokens as Array<{
                                    gateway_type_id: number | string;
                                }>) || []
                            ).filter(
                                (token) => Number(token.gateway_type_id) === 2
                            )
                        ).toHaveLength(0);
                        const invoice = await getEntity(
                            api.context,
                            'invoices',
                            context.invoice.id
                        );
                        expect(Number(invoice.balance)).toBe(
                            Number(context.invoice.balance)
                        );
                        await expect(
                            page.locator('#braintree-ach-payment')
                        ).toHaveCount(0);
                        return;
                    }
                    await expect
                        .poll(
                            async () => {
                                const client = await getEntity(
                                    api.context,
                                    'clients',
                                    context.client.id
                                );
                                return (
                                    (client.gateway_tokens as Array<{
                                        gateway_type_id: number | string;
                                    }>) || []
                                ).filter(
                                    (token) =>
                                        Number(token.gateway_type_id) === 2
                                ).length;
                            },
                            { timeout: 60_000 }
                        )
                        .toBe(1);
                    // Authorization returns to the invoice payment summary before checkout.
                    await navigateToPortalGatewayCheckout(
                        page,
                        company_gateway,
                        2,
                        flow,
                        context.invoice
                    );
                    await submitRequiredClientInfoIfPresent(page);
                    await dismissCookieConsent(page);
                    await expect(
                        page.locator('#braintree-ach-payment #pay-now')
                    ).toBeEnabled({ timeout: 60_000 });
                    await page.locator('#pay-now').click();
                    await gateway.assertPaymentSucceeded(page);
                    expect(
                        Number(
                            (
                                await getEntity(
                                    api.context,
                                    'invoices',
                                    context.invoice.id
                                )
                            ).balance
                        )
                    ).toBe(0);
                    const client = await getEntity(
                        api.context,
                        'clients',
                        context.client.id
                    );
                    const tokens = (
                        (client.gateway_tokens as Array<{
                            gateway_type_id: number | string;
                        }>) || []
                    ).filter((token) => Number(token.gateway_type_id) === 2);
                    expect(tokens).toHaveLength(1);
                    const second_invoice = await createSentInvoice(
                        api,
                        context.client,
                        {
                            cost: 19,
                            label: 'braintree-saved-bank',
                        }
                    );
                    await navigateToPortalGatewayCheckout(
                        page,
                        company_gateway,
                        2,
                        flow,
                        second_invoice
                    );
                    await submitRequiredClientInfoIfPresent(page);
                    await dismissCookieConsent(page);
                    await expect(
                        page.locator('#braintree-ach-payment #pay-now')
                    ).toBeEnabled();
                    await page
                        .locator('#braintree-ach-payment #pay-now')
                        .click();
                    await gateway.assertPaymentSucceeded(page);
                    expect(
                        Number(
                            (
                                await getEntity(
                                    api.context,
                                    'invoices',
                                    second_invoice.id
                                )
                            ).balance
                        )
                    ).toBe(0);
                }
            );
        }

        test(
            `${flow}: PayPal approval and payment`,
            { annotation: { type: 'braintree-method', description: '3' } },
            async ({ api, page }) => {
                const paypal_keys = parsePayPalRestKeys();
                const buyer = paypal_keys
                    ? payPalSandboxBuyerCredentials(paypal_keys)
                    : null;
                test.skip(
                    !buyer,
                    'Add sandbox buyer credentials to PAYPAL_REST_KEYS'
                );
                const company_gateway = await updateCompanyGatewayRequirements(
                    api.context,
                    availability.companyGateway!,
                    { token_billing: 'always' }
                );
                const context = await preparePortalPaymentContext(
                    api,
                    page,
                    company_gateway,
                    flow
                );
                await navigateToPortalGatewayCheckout(
                    page,
                    company_gateway,
                    3,
                    flow,
                    context.invoice
                );
                await completePayPalSandboxPayment(
                    page,
                    PAYPAL_REST_PAYMENT_METHODS[0],
                    buyer!
                );
                await gateway.assertPaymentSucceeded(page);
                expect(
                    Number(
                        (
                            await getEntity(
                                api.context,
                                'invoices',
                                context.invoice.id
                            )
                        ).balance
                    )
                ).toBe(0);
                const client = await getEntity(
                    api.context,
                    'clients',
                    context.client.id
                );
                expect(
                    (
                        client.gateway_tokens as Array<{
                            gateway_type_id: string;
                        }>
                    ).filter((token) => Number(token.gateway_type_id) === 3)
                ).toHaveLength(1);
                const second_invoice = await createSentInvoice(
                    api,
                    context.client,
                    { cost: 17, label: 'braintree-saved-paypal' }
                );
                await navigateToPortalGatewayCheckout(
                    page,
                    company_gateway,
                    3,
                    flow,
                    second_invoice
                );
                await submitRequiredClientInfoIfPresent(page);
                await dismissCookieConsent(page);
                await expect(page.locator('#pay-now-with-token')).toBeEnabled();
                await page.locator('#pay-now-with-token').click();
                await gateway.assertPaymentSucceeded(page);
                expect(
                    Number(
                        (
                            await getEntity(
                                api.context,
                                'invoices',
                                second_invoice.id
                            )
                        ).balance
                    )
                ).toBe(0);
            }
        );
    }
    for (const scenario of [
        {
            label: 'successful authentication',
            card: '4000000000002701',
            success: true,
        },
        {
            label: 'failed authentication',
            card: '4000000000002925',
            success: false,
        },
    ]) {
        test(`3DS: ${scenario.label}`, async ({ api, page }) => {
            const company_gateway = availability.companyGateway!;
            const response = await api.context.request.put(
                `/api/v1/company_gateways/${company_gateway.id}`,
                {
                    data: {
                        gateway_key: company_gateway.gateway_key,
                        config: JSON.stringify({
                            ...parseCompanyGatewayConfig(company_gateway),
                            threeds: true,
                        }),
                    },
                }
            );
            expect(response.ok()).toBe(true);
            if (scenario.success) {
                await updateCompanyGatewayRequirements(
                    api.context,
                    company_gateway,
                    { token_billing: 'always' }
                );
            }
            const context = await preparePortalPaymentContext(
                api,
                page,
                company_gateway
            );
            await navigateToPortalGatewayCheckout(
                page,
                company_gateway,
                1,
                'default',
                context.invoice
            );
            await gateway.assertCheckoutReady(page);
            await submitRequiredClientInfoIfPresent(page);
            await dismissCookieConsent(page);
            await page
                .frameLocator('iframe[name="braintree-hosted-field-number"]')
                .locator('input')
                .first()
                .fill(scenario.card);
            await page
                .frameLocator(
                    'iframe[name="braintree-hosted-field-expirationDate"]'
                )
                .locator('input')
                .first()
                .fill(`01/${new Date().getFullYear() + 3}`);
            const cvv = page
                .frameLocator('iframe[name="braintree-hosted-field-cvv"]')
                .locator('input')
                .first();
            if (await cvv.isVisible().catch(() => false)) await cvv.fill('123');
            await page.locator('#pay-now').click();
            if (scenario.success) {
                await gateway.assertPaymentSucceeded(page);
                expect(
                    Number(
                        (
                            await getEntity(
                                api.context,
                                'invoices',
                                context.invoice.id
                            )
                        ).balance
                    )
                ).toBe(0);
                const second_invoice = await createSentInvoice(
                    api,
                    context.client,
                    { cost: 31, label: 'braintree-saved-card-3ds' }
                );
                await navigateToPortalGatewayCheckout(
                    page,
                    company_gateway,
                    1,
                    'default',
                    second_invoice
                );
                await submitRequiredClientInfoIfPresent(page);
                await submitRequiredClientInfoIfPresent(page);
                await dismissCookieConsent(page);
                await expect(page.locator('#pay-now-with-token')).toBeEnabled({
                    timeout: 30_000,
                });
                await page.locator('#pay-now-with-token').click();
                await gateway.assertPaymentSucceeded(page);
                expect(
                    Number(
                        (
                            await getEntity(
                                api.context,
                                'invoices',
                                second_invoice.id
                            )
                        ).balance
                    )
                ).toBe(0);
            } else {
                await expect(page.locator('#errors')).toBeVisible({
                    timeout: 60_000,
                });
                await expect(page.locator('#pay-now')).toBeEnabled();
                expect(
                    Number(
                        (
                            await getEntity(
                                api.context,
                                'invoices',
                                context.invoice.id
                            )
                        ).balance
                    )
                ).toBeGreaterThan(0);
            }
        });
    }
});
