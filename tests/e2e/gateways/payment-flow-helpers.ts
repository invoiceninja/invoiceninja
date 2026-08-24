import { expect, test, type Page } from '@playwright/test';
import {
    getCompanyGateway,
    updateClient,
    updateCompanyGatewayRequirements,
    type CompanyGatewayEntity,
    type CompanyGatewayRequirementSettings,
} from '../api-helpers';
import {
    createAndLogInClient,
    dismissCookieConsent,
    waitForAlpine,
    waitForLivewire,
} from '../client-portal-helpers';
import { type ApiFixture } from '../fixtures';
import { decodePrimaryKey } from '../hash-helpers';
import {
    createSentInvoice,
    type PortalEntity,
} from '../portal-entity-helpers';
import { type PaymentGatewayContext } from './types';

export type PortalPaymentFlow = 'default' | 'smooth';

/** Default billing/shipping address used for payable checkout e2e. */
export const defaultClientAddress = {
    address1: '5 Wallaby Way',
    city: 'Perth',
    state: 'WA',
    postal_code: '90210',
    country_id: '840',
    shipping_address1: '5 Wallaby Way',
    shipping_city: 'Perth',
    shipping_state: 'WA',
    shipping_postal_code: '90210',
    shipping_country_id: '840',
    phone: '5555555555',
};

/** Clears billing/shipping fields so RFF gates checkout when required. */
export const emptyClientAddress = {
    address1: '',
    address2: '',
    city: '',
    state: '',
    postal_code: '',
    country_id: '',
    shipping_address1: '',
    shipping_address2: '',
    shipping_city: '',
    shipping_state: '',
    shipping_postal_code: '',
    shipping_country_id: '',
    phone: '',
};

const requiredClientInfoDefaults: Record<string, string> = {
    contact_first_name: 'Playwright',
    contact_last_name: 'Portal',
    contact_email: 'portal-rff@example.test',
    client_phone: '5555555555',
    client_address_line_1: '5 Wallaby Way',
    client_city: 'Perth',
    client_state: 'WA',
    client_postal_code: '90210',
    client_country_id: '840',
    client_shipping_address_line_1: '5 Wallaby Way',
    client_shipping_city: 'Perth',
    client_shipping_state: 'WA',
    client_shipping_postal_code: '90210',
    client_shipping_country_id: '840',
};

export function requiredClientInfoForm(page: Page) {
    return page.locator('#required-client-info-form');
}

export function gatewayCheckoutContainer(page: Page) {
    return page.locator('[data-ref="gateway-container"]');
}

const gatewayCheckoutReadySelectors = [
    '#paypal-credit-card-payment',
    '#checkout-form',
    '#card-element',
    '#pay-now',
    '#new-bank',
    '#authorize--credit-card-container',
    '#payment-form',
    '#paypal-payment',
    '#paypal-ppcp-payment',
    '#paypal-button-container',
] as const;

async function isPayPalCheckoutInteractive(page: Page): Promise<boolean> {
    const interactiveSelectors = [
        '#paypal-button-container iframe',
        '#paypal-button-container [data-funding-source]',
        '#card-number-field-container iframe',
        '#card-number-field-container input',
    ];

    for (const selector of interactiveSelectors) {
        const locator = page.locator(selector).first();

        if (await locator.isVisible().catch(() => false)) {
            return true;
        }
    }

    return false;
}

export async function isGatewayCheckoutReady(page: Page): Promise<boolean> {
    const paypalShell = page
        .locator('#paypal-payment, #paypal-ppcp-payment, #paypal-credit-card-payment')
        .first();

    if (await paypalShell.isVisible().catch(() => false)) {
        return isPayPalCheckoutInteractive(page);
    }

    for (const selector of gatewayCheckoutReadySelectors) {
        const locator = page.locator(selector).first();

        if (await locator.isVisible().catch(() => false)) {
            return true;
        }
    }

    return false;
}

export async function expectGatewayCheckoutReady(page: Page): Promise<void> {
    await expect
        .poll(() => isGatewayCheckoutReady(page), { timeout: 30_000 })
        .toBe(true);
}

export function isDefaultPaymentProcessPage(page: Page): boolean {
    return page.url().includes('/client/payments/process');
}

export function isSmoothInvoicePaymentPage(page: Page): boolean {
    try {
        return /\/client\/invoices\/[^/?#]+$/i.test(new URL(page.url()).pathname);
    } catch {
        return false;
    }
}

export async function isRequiredClientInfoBlockingCheckout(
    page: Page
): Promise<boolean> {
    const form = requiredClientInfoForm(page);

    if (!(await form.isVisible().catch(() => false))) {
        return false;
    }

    const gateway = gatewayCheckoutContainer(page);

    if ((await gateway.count()) === 0) {
        return !(await isGatewayCheckoutReady(page));
    }

    return gateway.evaluate((element) =>
        element.classList.contains('pointer-events-none')
    );
}

export async function assertRequiredClientInfoBlocksCheckout(
    page: Page,
): Promise<void> {
    await expect(requiredClientInfoForm(page)).toBeVisible({
        timeout: 30_000,
    });

    if (isSmoothInvoicePaymentPage(page)) {
        await expect(
            page.locator('main').getByText('Required Fields', { exact: true }),
        ).toBeVisible();
    } else {
        await expect(
            page.getByRole('heading', { name: /Required payment details/i }),
        ).toBeVisible();
    }

    await expect
        .poll(() => isRequiredClientInfoBlockingCheckout(page), {
            timeout: 30_000,
        })
        .toBe(true);

    const gateway = gatewayCheckoutContainer(page);

    if ((await gateway.count()) > 0) {
        await expect(gateway).toHaveClass(/pointer-events-none/);
    } else {
        await expect
            .poll(() => isGatewayCheckoutReady(page), { timeout: 30_000 })
            .toBe(false);
    }
}

export async function assertRequiredClientInfoUnblocksCheckout(
    page: Page,
): Promise<void> {
    await expect
        .poll(() => isRequiredClientInfoBlockingCheckout(page), {
            timeout: 30_000,
        })
        .toBe(false);

    const gateway = gatewayCheckoutContainer(page);

    if ((await gateway.count()) > 0) {
        await expect(gateway).not.toHaveClass(/pointer-events-none/, {
            timeout: 30_000,
        });
    } else {
        await expectGatewayCheckoutReady(page);
    }
}

async function fillInputIfEmpty(
    input: ReturnType<Page['locator']>,
    value: string
): Promise<void> {
    if (!(await input.isVisible().catch(() => false))) {
        return;
    }

    const currentValue = await input.inputValue().catch(() => '');

    if (currentValue.trim().length > 0) {
        return;
    }

    await input.fill(value);
    await input.dispatchEvent('input');
    await input.dispatchEvent('change');
}

/**
 * Subscription checkout always renders the required-client-info step first.
 * The Stripe form exists in the DOM but stays disabled until Continue succeeds.
 */
export async function completeRequiredClientInfoForm(
    page: Page,
    overrides: Record<string, string> = {}
): Promise<void> {
    const form = requiredClientInfoForm(page);

    if (!(await form.isVisible().catch(() => false))) {
        return;
    }

    if (!(await isRequiredClientInfoBlockingCheckout(page))) {
        return;
    }

    await dismissCookieConsent(page);
    await waitForAlpine(page);

    const defaults = { ...requiredClientInfoDefaults, ...overrides };

    for (const [name, value] of Object.entries(defaults)) {
        const field = form.locator(`[name="${name}"]`);

        if ((await field.count()) === 0) {
            continue;
        }

        if (
            await field
                .evaluate((element) => element.tagName === 'SELECT')
                .catch(() => false)
        ) {
            continue;
        }

        await fillInputIfEmpty(field, value);
    }

    const selects = form.locator('select');
    const selectCount = await selects.count();

    for (let index = 0; index < selectCount; index += 1) {
        const select = selects.nth(index);
        const selected = await select.inputValue().catch(() => '');

        if (selected && selected !== 'none') {
            continue;
        }

        await select
            .selectOption({ label: /United States|US \(United States\)/i })
            .catch(() => select.selectOption('840'))
            .catch(() => null);
        await select.dispatchEvent('change');
    }

    const copyBilling = form.locator('#copy-billing-button');
    if (await copyBilling.isVisible().catch(() => false)) {
        await copyBilling.click({ force: true });
    }

    const terms = form.locator('input[name="terms_accepted"]');
    if (await terms.isVisible().catch(() => false)) {
        if (!(await terms.isChecked().catch(() => false))) {
            await terms.click({ force: true });
        }
    }

    const continueButton = form
        .locator('button.button-primary:not([disabled])')
        .last();

    const submitRequiredClientInfo = async (): Promise<void> => {
        if ((await continueButton.count()) === 0) {
            await form
                .locator('button.button-primary')
                .last()
                .click({ force: true });

            return;
        }

        /**
         * Prefer a real click - the PayPal flows depend on the actionability wait, and
         * forcing past it breaks vaulting and the smooth flow. Some layouts never yield
         * the pointer event though, so fall back to forcing rather than timing out the
         * whole test. The window is generous so a slow-but-real click still wins.
         */
        try {
            await continueButton.click({ timeout: 20_000 });
        } catch {
            await continueButton.click({ force: true });
        }
    };

    if (isSmoothInvoicePaymentPage(page)) {
        await waitForLivewire(page, submitRequiredClientInfo);
    } else {
        await submitRequiredClientInfo();
    }

    await expect
        .poll(() => isRequiredClientInfoBlockingCheckout(page), {
            timeout: 30_000,
        })
        .toBe(false);

    const gateway = gatewayCheckoutContainer(page);

    if ((await gateway.count()) > 0) {
        await expect(gateway).not.toHaveClass(/pointer-events-none/, {
            timeout: 30_000,
        });
    } else {
        await expectGatewayCheckoutReady(page);
    }
}

export async function expectSmoothPaymentStep(page: Page): Promise<void> {
    const smoothStep = page
        .locator('main')
        .getByText('Payment Methods', { exact: true })
        .or(page.locator('main').getByText('Required Fields', { exact: true }))
        .or(page.locator('main #card-element'))
        .or(page.locator('main #pay-now'))
        .or(page.locator('main #paypal-button-container'))
        .or(page.locator('main #paypal-credit-card-payment'))
        .or(
            page
                .locator('main')
                .getByRole('button', { name: /Credit Card|PayPal|Bank/i }),
        );

    await expect(smoothStep.first()).toBeVisible({ timeout: 30_000 });
}

export async function openSmoothInvoicePaymentPage(
    page: Page,
    invoice?: PortalEntity,
): Promise<void> {
    if (!invoice) {
        throw new Error('Smooth payment flow requires an invoice context');
    }

    await page.goto(`/client/invoices/${invoice.id}`);
    await dismissCookieConsent(page);
    await waitForAlpine(page);
    await expectSmoothPaymentStep(page);
}

export async function selectSmoothPaymentMethod(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    methodLabel?: string,
): Promise<void> {
    if (await isGatewayCheckoutReady(page)) {
        return;
    }

    if (await requiredClientInfoForm(page).isVisible().catch(() => false)) {
        return;
    }

    const rawId = decodePrimaryKey(companyGateway.id);
    const wireSelector = `button[wire\\:click*="handleSelect('${rawId}'"][wire\\:click*="'${gatewayTypeId}'"]`;
    const wireButton = page.locator(wireSelector).first();

    if ((await wireButton.count()) > 0) {
        await expect(wireButton).toBeVisible({ timeout: 15_000 });
        await wireButton.click();

        await expect
            .poll(async () => {
                if (await isGatewayCheckoutReady(page)) {
                    return true;
                }

                return requiredClientInfoForm(page)
                    .isVisible()
                    .catch(() => false);
            }, { timeout: 45_000 })
            .toBe(true);

        return;
    }

    if (methodLabel) {
        const labelButton = page
            .locator('main')
            .getByRole('button', { name: methodLabel, exact: true })
            .first();

        if ((await labelButton.count()) > 0) {
            await expect(labelButton).toBeVisible({ timeout: 15_000 });
            await labelButton.click();

            await expect
                .poll(async () => {
                    if (await isGatewayCheckoutReady(page)) {
                        return true;
                    }

                    return requiredClientInfoForm(page)
                        .isVisible()
                        .catch(() => false);
                }, { timeout: 45_000 })
                .toBe(true);

            return;
        }
    }

    await expect
        .poll(async () => {
            if (await isGatewayCheckoutReady(page)) {
                return true;
            }

            return requiredClientInfoForm(page).isVisible().catch(() => false);
        }, { timeout: 45_000 })
        .toBe(true);
}

export async function assertSmoothPaymentMethodOffered(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    methodLabel: string,
): Promise<void> {
    const rawId = decodePrimaryKey(companyGateway.id);
    const wireButton = page
        .locator(
            `button[wire\\:click*="handleSelect('${rawId}'"][wire\\:click*="'${gatewayTypeId}'"]`,
        )
        .first();
    const labelButton = page
        .locator('main')
        .getByRole('button', { name: methodLabel, exact: true })
        .first();

    if ((await wireButton.count()) > 0 || (await labelButton.count()) > 0) {
        await expect(wireButton.or(labelButton).first()).toBeVisible({
            timeout: 15_000,
        });

        return;
    }

    await expect
        .poll(async () => {
            if (await isGatewayCheckoutReady(page)) {
                return true;
            }

            return requiredClientInfoForm(page).isVisible().catch(() => false);
        }, { timeout: 30_000 })
        .toBe(true);
}

export async function navigateToSmoothGatewayCheckout(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    invoice?: PortalEntity,
    methodLabel?: string,
): Promise<void> {
    await openSmoothInvoicePaymentPage(page, invoice);
    await selectSmoothPaymentMethod(
        page,
        companyGateway,
        gatewayTypeId,
        methodLabel,
    );
    await fillRequiredPaymentInformationIfPresent(page);
}

export async function navigateToSmoothGatewayCheckoutWithoutRequiredClientInfo(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    invoice?: PortalEntity,
    methodLabel?: string,
): Promise<void> {
    await openSmoothInvoicePaymentPage(page, invoice);
    await selectSmoothPaymentMethod(
        page,
        companyGateway,
        gatewayTypeId,
        methodLabel,
    );
    await expect(page).toHaveURL(/\/client\/invoices\//, { timeout: 30_000 });
}

export async function navigateToPortalGatewayCheckout(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    paymentFlow: PortalPaymentFlow,
    invoice?: PortalEntity,
    methodLabel?: string,
): Promise<void> {
    if (paymentFlow === 'smooth') {
        await navigateToSmoothGatewayCheckout(
            page,
            companyGateway,
            gatewayTypeId,
            invoice,
            methodLabel,
        );

        return;
    }

    await navigateToGatewayCheckout(
        page,
        companyGateway,
        gatewayTypeId,
        invoice,
    );
}

export async function navigateToPortalGatewayCheckoutWithoutRequiredClientInfo(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    paymentFlow: PortalPaymentFlow,
    invoice?: PortalEntity,
    methodLabel?: string,
): Promise<void> {
    if (paymentFlow === 'smooth') {
        await navigateToSmoothGatewayCheckoutWithoutRequiredClientInfo(
            page,
            companyGateway,
            gatewayTypeId,
            invoice,
            methodLabel,
        );

        return;
    }

    await navigateToGatewayCheckoutWithoutRequiredClientInfo(
        page,
        companyGateway,
        gatewayTypeId,
        invoice,
    );
}

export async function preparePortalPaymentContext(
    api: ApiFixture,
    page: Page,
    companyGateway: CompanyGatewayEntity,
    paymentFlow: PortalPaymentFlow = 'default',
): Promise<PaymentGatewayContext> {
    let client = await createAndLogInClient(api, page, {
        settings: {
            ...paymentTestSettings,
            payment_flow: paymentFlow,
        },
        contact: {
            first_name: 'Playwright',
            last_name: 'Portal',
            email: `portal-pay-${Date.now()}@example.test`,
        },
    });
    client = await updateClient(api.context, client, {
        ...defaultClientAddress,
        phone: '5555555555',
    });
    const invoice = await createSentInvoice(api, client, {
        label: `gateway-${companyGateway.gateway_key}-${paymentFlow}`,
        cost: 42,
    });

    if ((invoice.balance ?? 0) <= 0) {
        throw new Error(
            `Expected a payable invoice for ${companyGateway.gateway_key}, got balance ${invoice.balance ?? 0}`,
        );
    }

    return { client, invoice, companyGateway };
}

export async function prepareDefaultPaymentContext(
    api: ApiFixture,
    page: Page,
    companyGateway: CompanyGatewayEntity
): Promise<PaymentGatewayContext> {
    return preparePortalPaymentContext(api, page, companyGateway, 'default');
}

export async function prepareSmoothPaymentContext(
    api: ApiFixture,
    page: Page,
    companyGateway: CompanyGatewayEntity,
): Promise<PaymentGatewayContext> {
    return preparePortalPaymentContext(api, page, companyGateway, 'smooth');
}

export interface IncompleteClientPaymentContextOptions {
    requireBillingAddress?: boolean;
    alwaysShowRequiredFields?: boolean;
    paymentFlow?: PortalPaymentFlow;
}

export interface IncompleteClientPaymentContext extends PaymentGatewayContext {
    restoreGatewayRequirements: () => Promise<void>;
}

export async function prepareIncompleteClientPaymentContext(
    api: ApiFixture,
    page: Page,
    companyGateway: CompanyGatewayEntity,
    options: IncompleteClientPaymentContextOptions = {},
): Promise<IncompleteClientPaymentContext> {
    const originalGateway = await getCompanyGateway(
        api.context,
        companyGateway.id,
    );
    const requirementSettings: CompanyGatewayRequirementSettings = {
        require_billing_address: options.requireBillingAddress ?? true,
        always_show_required_fields:
            options.alwaysShowRequiredFields ?? false,
    };

    const configuredGateway = await updateCompanyGatewayRequirements(
        api.context,
        companyGateway,
        requirementSettings,
    );

    let client = await createAndLogInClient(api, page, {
        settings: {
            ...paymentTestSettings,
            payment_flow: options.paymentFlow ?? 'default',
        },
        contact: {
            first_name: 'Playwright',
            last_name: 'Portal',
            email: `portal-rff-${Date.now()}@example.test`,
        },
    });

    if (options.alwaysShowRequiredFields) {
        client = await updateClient(api.context, client, {
            ...defaultClientAddress,
        });
    } else {
        client = await updateClient(api.context, client, {
            ...emptyClientAddress,
        });
    }

    const invoice = await createSentInvoice(api, client, {
        label: `rff-${companyGateway.gateway_key}-${options.paymentFlow ?? 'default'}`,
        cost: 42,
    });

    if ((invoice.balance ?? 0) <= 0) {
        throw new Error(
            `Expected a payable invoice for RFF tests on ${companyGateway.gateway_key}, got balance ${invoice.balance ?? 0}`,
        );
    }

    const restoreGatewayRequirements = async (): Promise<void> => {
        await updateCompanyGatewayRequirements(
            api.context,
            companyGateway,
            {
                require_billing_address: Boolean(
                    originalGateway.require_billing_address,
                ),
                require_postal_code: Boolean(
                    originalGateway.require_postal_code,
                ),
                require_shipping_address: Boolean(
                    originalGateway.require_shipping_address,
                ),
                always_show_required_fields: Boolean(
                    originalGateway.always_show_required_fields,
                ),
            },
        );
    };

    return {
        client,
        invoice,
        companyGateway: configuredGateway,
        restoreGatewayRequirements,
    };
}

export async function navigateToGatewayCheckoutWithoutRequiredClientInfo(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    invoice?: PortalEntity,
): Promise<void> {
    await openInvoicePaymentPage(page, invoice);
    await selectGatewayFromDropdown(page, companyGateway, gatewayTypeId);
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(/\/client\/payments\/process/, {
        timeout: 30_000,
    });
}

export async function openInvoicePaymentPage(
    page: Page,
    invoice?: PortalEntity,
): Promise<void> {
    if (invoice) {
        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        const dropdown = page.locator('[dusk="pay-now-dropdown"]');
        const payNowButton = page.getByRole('button', { name: /pay now/i });

        if (await dropdown.isVisible().catch(() => false)) {
            await dropdown.click();
            await expect(
                page.locator('[dusk="payment-methods-dropdown"]'),
            ).toBeVisible({ timeout: 15_000 });

            return;
        }

        if (await payNowButton.isVisible().catch(() => false)) {
            await payNowButton.click();
            await expect(page).toHaveURL(
                /\/client\/(?:invoices\/payment|payments\/process)/,
                { timeout: 30_000 },
            );
            await dismissCookieConsent(page);

            return;
        }

        throw new Error(
            `Invoice ${invoice.id} did not expose a Pay Now entry point`,
        );
    }

    await page.goto('/client/invoices');
    await dismissCookieConsent(page);
    await page.locator('[dusk="pay-now"]').first().click();
    await expect(page).toHaveURL(/\/client\/invoices\/payment/);
    await dismissCookieConsent(page);
    await expect(
        page.locator('[dusk="payment-methods-dropdown"]')
    ).toBeVisible();
}

export async function selectGatewayFromDropdown(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number
): Promise<void> {
    const dropdown = page.locator('[dusk="pay-now-dropdown"]');
    const methodsDropdown = page.locator('[dusk="payment-methods-dropdown"]');

    if (!(await methodsDropdown.isVisible().catch(() => false))) {
        await dropdown.click();
        await expect(methodsDropdown).toBeVisible({ timeout: 15_000 });
    }

    // Portal dropdowns use the raw company_gateway id; the API returns a hashed
    // id. Prefer `data-gateway-key` (after deploy), then decoded raw id, then
    // hashed id. Never fall back to type alone — multiple credit-card gateways
    // share gateway_type_id=1 and the wrong one was being selected.
    const rawId = decodePrimaryKey(companyGateway.id);
    const byKey = page.locator(
        `[dusk="payment-methods-dropdown"] [data-gateway-key="${companyGateway.gateway_key}"][data-gateway-type-id="${gatewayTypeId}"]`
    );
    const byRawId = page.locator(
        `[dusk="payment-methods-dropdown"] [data-company-gateway-id="${rawId}"][data-gateway-type-id="${gatewayTypeId}"]`
    );
    const byHashedId = page.locator(
        `[dusk="payment-methods-dropdown"] [data-company-gateway-id="${companyGateway.id}"][data-gateway-type-id="${gatewayTypeId}"]`
    );

    const gatewayOption =
        (await byKey.count()) > 0
            ? byKey.first()
            : (await byRawId.count()) > 0
              ? byRawId.first()
              : byHashedId.first();

    if ((await gatewayOption.count()) === 0) {
        test.skip(
            true,
            `Gateway ${companyGateway.gateway_key} is not offered in Pay Now — deploy the PaymentMethod multi-gateway fix or enable fees_and_limits for type ${gatewayTypeId}`
        );
    }

    await expect(gatewayOption).toBeVisible({ timeout: 15_000 });

    const companyGatewayId = await gatewayOption.getAttribute(
        'data-company-gateway-id'
    );
    const typeId = await gatewayOption.getAttribute('data-gateway-type-id');

    // Livewire can remount the dropdown and drop payment.js listeners; set the
    // form fields and submit directly so checkout still starts reliably.
    await page.locator('#company_gateway_id').evaluate((input, value) => {
        (input as HTMLInputElement).value = String(value ?? '');
    }, companyGatewayId);
    await page.locator('#payment_method_id').evaluate((input, value) => {
        (input as HTMLInputElement).value = String(value ?? '');
    }, typeId);
    await page.locator('#payment-form').evaluate((form) => {
        (form as HTMLFormElement).submit();
    });

    await expect(page).toHaveURL(/\/client\/payments\/process/, {
        timeout: 30_000,
    });
}

export async function fillRequiredPaymentInformationIfPresent(
    page: Page
): Promise<void> {
    await completeRequiredClientInfoForm(page);

    if (await isRequiredClientInfoBlockingCheckout(page)) {
        return;
    }

    const checkoutReady = page
        .locator('#paypal-credit-card-payment')
        .or(page.locator('#checkout-form'))
        .or(page.locator('#card-element'))
        .or(page.locator('#pay-now'))
        .or(page.locator('#new-bank'))
        .or(page.locator('#authorize--credit-card-container'))
        .or(page.locator('#payment-form'))
        .or(page.locator('#paypal-payment'))
        .or(page.locator('#paypal-button-container'))
        .or(page.locator('#paypal-ppcp-payment'));
    const billingAddress = page.locator('input[name="client_address_line_1"]');

    await Promise.race([
        expect
            .poll(() => isGatewayCheckoutReady(page), { timeout: 45_000 })
            .toBe(true)
            .catch(() => null),
        checkoutReady
            .first()
            .waitFor({ state: 'visible', timeout: 10_000 })
            .catch(() => null),
        billingAddress
            .waitFor({ state: 'visible', timeout: 10_000 })
            .catch(() => null),
    ]);

    if (await isGatewayCheckoutReady(page)) {
        return;
    }

    if (
        await checkoutReady
            .first()
            .isVisible()
            .catch(() => false)
    ) {
        return;
    }

    if (!(await billingAddress.isVisible().catch(() => false))) {
        return;
    }

    await dismissCookieConsent(page);

    await expect(
        page.getByRole('button', { name: /Next|Continue|Save/i })
    ).toBeEnabled({ timeout: 15_000 });

    await billingAddress.fill('5 Wallaby Way');

    const city = page.locator('input[name="client_city"]');
    if (await city.isVisible().catch(() => false)) {
        await city.fill('Los Angeles');
    }

    const state = page.locator('input[name="client_state"]');
    if (await state.isVisible().catch(() => false)) {
        await state.fill('CA');
    }

    const postal = page.locator('input[name="client_postal_code"]');
    if (await postal.isVisible().catch(() => false)) {
        await postal.fill('90210');
    }

    const countrySelect = page
        .locator('select[name="client_country_id"]')
        .first();
    if (await countrySelect.isVisible().catch(() => false)) {
        await countrySelect.selectOption('840');
    }

    const phone = page.locator('input[name="client_phone"]');
    if (await phone.isVisible().catch(() => false)) {
        await phone.fill('5555555555');
    }

    const email = page.locator('input[name="contact_email"]');
    if (await email.isVisible().catch(() => false)) {
        await email.fill(`portal-rff-${Date.now()}@example.test`);
    }

    const copyBilling = page.locator('#copy-billing-button');
    if (await copyBilling.isVisible().catch(() => false)) {
        await copyBilling.click();
    }

    await dismissCookieConsent(page);
    await page.getByRole('button', { name: /Continue|Save|Next/i }).click();

    if (
        !(await checkoutReady
            .first()
            .isVisible()
            .catch(() => false))
    ) {
        await checkoutReady
            .first()
            .waitFor({ state: 'visible', timeout: 30_000 })
            .catch(() => null);
    }

    if (
        !(await checkoutReady
            .first()
            .isVisible()
            .catch(() => false))
    ) {
        test.skip(
            true,
            'Required-fields step did not advance to a gateway checkout form'
        );
    }
}
/**
 * Fills and submits the required client information form when the portal renders it
 * alongside the gateway form.
 *
 * completeRequiredClientInfoForm() only acts while the form is blocking checkout. On
 * this layout it is not blocking - the gateway form is already on the page - but the
 * form still has to be filled and submitted, or Continue posts empty values and the
 * page reloads without a gateway form at all.
 */
export async function submitRequiredClientInfoIfPresent(
    page: Page,
): Promise<void> {
    const form = requiredClientInfoForm(page);

    if (!(await form.isVisible().catch(() => false))) {
        return;
    }

    await dismissCookieConsent(page);
    await waitForAlpine(page);

    for (const [name, value] of Object.entries(requiredClientInfoDefaults)) {
        const field = form.locator(`[name="${name}"]`);

        if ((await field.count()) === 0) {
            continue;
        }

        const isSelect = await field
            .first()
            .evaluate((element) => element.tagName === 'SELECT')
            .catch(() => false);

        if (isSelect) {
            const selected = await field.first().inputValue().catch(() => '');

            if (!selected || selected === 'none') {
                await field
                    .first()
                    .selectOption(value)
                    .catch(() => null);
            }

            continue;
        }

        await fillInputIfEmpty(field.first(), value);
    }

    const submit = form
        .getByRole('button', { name: /^(Continue|Next|Save)$/i })
        .first();

    if (!(await submit.isVisible().catch(() => false))) {
        return;
    }

    await submit.click({ force: true });

    /** Submitting re-renders the page, so the gateway form has to come back first. */
    await page
        .locator('#authorize--credit-card-container')
        .or(page.locator('#dropin-container'))
        .or(page.locator('#card-element'))
        .or(page.locator('#payment-form'))
        .first()
        .waitFor({ state: 'visible', timeout: 30_000 })
        .catch(() => null);

    await dismissCookieConsent(page);
}

export async function navigateToGatewayCheckout(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    invoice?: PortalEntity,
): Promise<void> {
    await openInvoicePaymentPage(page, invoice);
    await selectGatewayFromDropdown(page, companyGateway, gatewayTypeId);
    await fillRequiredPaymentInformationIfPresent(page);
}

export async function fillStripeTestCard(page: Page): Promise<void> {
    const cardholderName = page.locator('#cardholder-name');

    if (await cardholderName.isVisible()) {
        await cardholderName.fill('Playwright Test');
    }

    const frame = page.frameLocator('iframe').first();
    const cardNumber = frame.locator(
        'input[name="cardnumber"], input[placeholder*="Card number"]'
    );
    await expect(cardNumber).toBeVisible({ timeout: 15_000 });
    await cardNumber.fill('4242424242424242');
    await frame
        .locator('input[name="exp-date"], input[placeholder*="MM"]')
        .fill('1229');
    await frame
        .locator('input[name="cvc"], input[placeholder="CVC"]')
        .fill('123');

    const postal = frame.locator(
        'input[name="postal"], input[placeholder*="ZIP"], input[placeholder*="Postal"]'
    );
    if (await postal.isVisible({ timeout: 2_000 }).catch(() => false)) {
        await postal.fill('90210');
    }
}

/** Client portal settings that keep payment entry deterministic. */
export const paymentTestSettings = {
    client_portal_allow_under_payment: false,
    client_portal_allow_over_payment: false,
    require_invoice_signature: false,
    show_accept_invoice_terms: false,
    client_manual_payment_notification: false,
} as const;

export async function selectFirstAvailableGateway(page: Page): Promise<void> {
    const option = page
        .locator(
            '[dusk="payment-methods-dropdown"] .dropdown-gateway-button, [dusk="payment-methods-dropdown"] [data-company-gateway-id]'
        )
        .first();

    await expect(option).toBeVisible();
    await option.click();
}

export async function clickBulkPayNow(page: Page): Promise<void> {
    const bulkPay = page.locator('button[name="action"][value="payment"]');

    await expect(bulkPay).toBeEnabled({ timeout: 15_000 });
    await bulkPay.click();
}

export async function submitPrePayment(
    page: Page,
    amount: number,
    notes = 'Playwright pre-payment'
): Promise<void> {
    await page.goto('/client/pre_payments');
    await expect(page.locator('#payment-form')).toBeVisible();
    await page.locator('input[name="amount"]').fill(String(amount));
    await page.locator('textarea[name="notes"]').fill(notes);
    await page
        .locator('#payment-form')
        .getByRole('button', { name: 'Pay Now' })
        .click();
}
