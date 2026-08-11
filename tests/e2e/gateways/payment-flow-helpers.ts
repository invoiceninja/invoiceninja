import { expect, type Page } from '@playwright/test';
import { updateClient } from '../api-helpers';
import { createAndLogInClient } from '../client-portal-helpers';
import { type ApiFixture } from '../fixtures';
import {
    createSentInvoice,
    type PortalEntity,
} from '../portal-entity-helpers';
import { type CompanyGatewayEntity } from '../api-helpers';
import { type PaymentGatewayContext } from './types';

const defaultClientAddress = {
    address1: '5 Wallaby Way',
    city: 'Perth',
    state: 'WA',
    postal_code: '6000',
    country_id: '840',
    shipping_address1: '5 Wallaby Way',
    shipping_city: 'Perth',
    shipping_state: 'WA',
    shipping_postal_code: '6000',
    shipping_country_id: '840',
};

export async function prepareDefaultPaymentContext(
    api: ApiFixture,
    page: Page,
    companyGateway: CompanyGatewayEntity,
): Promise<PaymentGatewayContext> {
    let client = await createAndLogInClient(api, page, {
        settings: {
            payment_flow: 'default',
            client_manual_payment_notification: false,
        },
    });
    client = await updateClient(api.context, client, defaultClientAddress);
    const invoice = await createSentInvoice(api, client, {
        label: `gateway-${companyGateway.gateway_key}`,
        cost: 42,
    });

    return { client, invoice, companyGateway };
}

export async function openInvoicePaymentPage(page: Page): Promise<void> {
    await page.goto('/client/invoices');
    await page.locator('[dusk="pay-now"]').first().click();
    await expect(page).toHaveURL(/\/client\/invoices\/payment/);
    await expect(page.locator('[dusk="payment-methods-dropdown"]')).toBeVisible();
}

export async function selectGatewayFromDropdown(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): Promise<void> {
    await page.locator('[dusk="pay-now-dropdown"]').click();

    const gatewayOption = page.locator(
        `[dusk="payment-methods-dropdown"] [data-company-gateway-id="${companyGateway.id}"][data-gateway-type-id="${gatewayTypeId}"]`,
    );

    await expect(gatewayOption).toBeVisible();
    await gatewayOption.click();
    await expect(page).toHaveURL(/\/client\/payments\/process/, {
        timeout: 30_000,
    });
}

export async function fillRequiredPaymentInformationIfPresent(
    page: Page,
): Promise<void> {
    const billingAddress = page.locator('input[name="client_address_line_1"]');

    if (!(await billingAddress.isVisible({ timeout: 2_000 }).catch(() => false))) {
        return;
    }

    await billingAddress.fill('5 Wallaby Way');
    await page.locator('input[name="client_city"]').fill('Perth');
    await page.locator('input[name="client_state"]').fill('WA');

    const countrySelect = page.locator('#client_country');
    if (await countrySelect.isVisible()) {
        await countrySelect.selectOption('840');
    }

    const shippingAddress = page.locator(
        'input[name="client_shipping_address_line_1"]',
    );
    if (await shippingAddress.isVisible()) {
        await shippingAddress.fill('5 Wallaby Way');
        await page.locator('input[name="client_shipping_city"]').fill('Perth');
        await page.locator('input[name="client_shipping_state"]').fill('WA');
    }

    await page.getByRole('button', { name: /Continue|Save/i }).click();
}

export async function navigateToGatewayCheckout(
    page: Page,
    companyGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): Promise<void> {
    await openInvoicePaymentPage(page);
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
        'input[name="cardnumber"], input[placeholder*="Card number"]',
    );
    await cardNumber.fill('4242424242424242');
    await frame
        .locator('input[name="exp-date"], input[placeholder*="MM"]')
        .fill('1229');
    await frame
        .locator('input[name="cvc"], input[placeholder="CVC"]')
        .fill('123');
}

/** Client portal settings that keep payment entry deterministic. */
export const paymentTestSettings = {
    client_portal_allow_under_payment: false,
    client_portal_allow_over_payment: false,
    require_invoice_signature: false,
    show_accept_invoice_terms: false,
    client_manual_payment_notification: false,
} as const;

export async function openInvoiceDetailPayNow(
    page: Page,
    invoiceId: string,
): Promise<void> {
    await page.goto(`/client/invoices/${invoiceId}`);
    await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
        'View Invoice',
    );

    const payDropdown = page.locator('[dusk="pay-now-dropdown"]');

    if ((await payDropdown.count()) === 0) {
        throw new Error(
            'Pay Now dropdown is missing; no payment gateway is available for this client.',
        );
    }

    await payDropdown.click();
}

export async function selectFirstAvailableGateway(page: Page): Promise<void> {
    const option = page
        .locator('[dusk="payment-methods-dropdown"] [dusk="payment-method"]')
        .first();

    await expect(option).toBeVisible();
    await option.click();
}

export async function submitPrePayment(
    page: Page,
    amount: number,
    notes = 'Playwright pre-payment',
): Promise<void> {
    await page.goto('/client/pre_payments');
    await expect(page.locator('#payment-form')).toBeVisible();
    await page.locator('input[name="amount"]').fill(String(amount));
    await page.locator('textarea[name="notes"]').fill(notes);
    await page.locator('#payment-form').getByRole('button', { name: 'Pay Now' }).click();
}
