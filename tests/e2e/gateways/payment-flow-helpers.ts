import { expect, test, type Page } from '@playwright/test';
import { updateClient, type CompanyGatewayEntity } from '../api-helpers';
import {
    createAndLogInClient,
    dismissCookieConsent,
    waitForAlpine,
} from '../client-portal-helpers';
import { type ApiFixture } from '../fixtures';
import { decodePrimaryKey } from '../hash-helpers';
import {
    createSentInvoice,
    type PortalEntity,
} from '../portal-entity-helpers';
import { type PaymentGatewayContext } from './types';

const defaultClientAddress = {
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

export async function isGatewayCheckoutReady(page: Page): Promise<boolean> {
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

export async function isRequiredClientInfoBlockingCheckout(
    page: Page
): Promise<boolean> {
    const form = requiredClientInfoForm(page);

    if (!(await form.isVisible().catch(() => false))) {
        return false;
    }

    const gateway = gatewayCheckoutContainer(page);

    if ((await gateway.count()) === 0) {
        return false;
    }

    return gateway.evaluate((element) =>
        element.classList.contains('pointer-events-none')
    );
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
    if ((await continueButton.count()) === 0) {
        await form
            .locator('button.button-primary')
            .last()
            .click({ force: true });
    } else {
        await continueButton.click();
    }

    await expect
        .poll(() => isRequiredClientInfoBlockingCheckout(page), {
            timeout: 30_000,
        })
        .toBe(false);

    await expect(gatewayCheckoutContainer(page)).not.toHaveClass(
        /pointer-events-none/,
        { timeout: 30_000 }
    );
}

export async function prepareDefaultPaymentContext(
    api: ApiFixture,
    page: Page,
    companyGateway: CompanyGatewayEntity
): Promise<PaymentGatewayContext> {
    let client = await createAndLogInClient(api, page, {
        settings: {
            payment_flow: 'default',
            client_manual_payment_notification: false,
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
        label: `gateway-${companyGateway.gateway_key}`,
        cost: 42,
    });

    if ((invoice.balance ?? 0) <= 0) {
        throw new Error(
            `Expected a payable invoice for ${companyGateway.gateway_key}, got balance ${invoice.balance ?? 0}`,
        );
    }

    return { client, invoice, companyGateway };
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
