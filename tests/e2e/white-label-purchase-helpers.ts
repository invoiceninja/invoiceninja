import { expect, type Page } from '@playwright/test';
import {
    getCompany,
    updateClient,
    type ApiContext,
    type CompanyGatewayEntity,
} from './api-helpers';
import {
    createAndLogInClient,
    dismissCookieConsent,
    portalContact,
    type PortalClient,
} from './client-portal-helpers';
import { type ApiFixture, uniqueName } from './fixtures';
import { decodePrimaryKey } from './hash-helpers';
import {
    completeRequiredClientInfoForm,
    fillStripeTestCard,
    gatewayCheckoutContainer,
    isRequiredClientInfoBlockingCheckout,
    requiredClientInfoForm,
} from './gateways/payment-flow-helpers';

/** RecurringInvoice::FREQUENCY_ANNUALLY */
export const WHITE_LABEL_ANNUAL_FREQUENCY_ID = '10';

export const WHITE_LABEL_PRODUCT_KEY = 'whitelabel';

export const WHITE_LABEL_SUBSCRIPTION_NAME = 'White Label License / year';

export interface WhiteLabelSubscription {
    product: { id: string; product_key?: string };
    subscription: { id: string; name?: string };
}

const defaultPurchaseClientAddress = {
    address1: '5 Wallaby Way',
    city: 'Perth',
    state: 'WA',
    postal_code: '90210',
    country_id: '840',
    phone: '5555555555',
    shipping_address1: '5 Wallaby Way',
    shipping_city: 'Perth',
    shipping_state: 'WA',
    shipping_postal_code: '90210',
    shipping_country_id: '840',
};

/**
 * A white label subscription is a subscription whose recurring product has
 * `product_key = whitelabel` and annual frequency.
 *
 * License fulfillment (`handleWhiteLabelPurchase`) additionally requires the
 * subscription company to match `NINJA_COMPANY_ID`.
 */
export async function isWhiteLabelFulfillmentConfigured(
    api: ApiContext,
): Promise<boolean> {
    const configuredCompanyId = process.env.NINJA_COMPANY_ID?.trim();

    const company = await getCompany(api);
    const rawCompanyId = decodePrimaryKey(company.id);

    if (!configuredCompanyId) {
        return false;
    }

    return configuredCompanyId === rawCompanyId;
}

export async function whiteLabelFulfillmentSkipReason(
    api: ApiContext,
): Promise<string | null> {
    const configuredCompanyId = process.env.NINJA_COMPANY_ID?.trim();

    if (!configuredCompanyId) {
        return 'Set NINJA_COMPANY_ID in .env to run white label license fulfillment';
    }

    const company = await getCompany(api);
    const rawCompanyId = decodePrimaryKey(company.id);

    if (configuredCompanyId === rawCompanyId) {
        return null;
    }

    return `NINJA_COMPANY_ID (${configuredCompanyId}) does not match the test company raw id (${rawCompanyId})`;
}

export async function createWhiteLabelSubscription(
    api: ApiFixture,
    options: { cost?: number; name?: string; cleanup?: boolean } = {},
): Promise<WhiteLabelSubscription> {
    const cleanup = options.cleanup ?? true;

    const product = await api.createEntity<{ id: string; product_key?: string }>(
        'products',
        {
            product_key: WHITE_LABEL_PRODUCT_KEY,
            notes: 'White Label License (Playwright)',
            cost: options.cost ?? 750,
            price: options.cost ?? 750,
            quantity: 1,
        },
        { cleanup },
    );

    const subscription = await api.createEntity<{ id: string; name?: string }>(
        'subscriptions',
        {
            name: options.name ?? uniqueName(WHITE_LABEL_SUBSCRIPTION_NAME),
            steps: 'cart,auth.login-or-register',
            recurring_product_ids: product.id,
            frequency_id: WHITE_LABEL_ANNUAL_FREQUENCY_ID,
            allow_cancellation: true,
            allow_plan_changes: false,
        },
        { cleanup },
    );

    return { product, subscription };
}

export async function prepareWhiteLabelPurchaseClient(
    api: ApiFixture,
    page: Page,
): Promise<PortalClient> {
    const client = await createAndLogInClient(api, page, {
        name: uniqueName('white-label-buyer'),
        contact: {
            first_name: 'White',
            last_name: 'Label',
            password: 'Portal123',
        },
    });

    return updateClient(api.context, client, defaultPurchaseClientAddress);
}

export async function openWhiteLabelPurchasePage(
    page: Page,
    subscriptionId: string,
): Promise<void> {
    const response = await page.goto(
        `/client/subscriptions/${subscriptionId}/purchase`,
    );

    expect(response?.ok()).toBe(true);
    await dismissCookieConsent(page);
    await expect(page.locator('#billing-page-company-logo')).toBeVisible({
        timeout: 15_000,
    });
}

export async function authenticateExistingContactOnPurchasePage(
    page: Page,
    email: string,
    password: string,
): Promise<void> {
    await page.locator('input[wire\\:model="email"]').fill(email);
    await page.getByRole('button', { name: /Continue|Next|Sign in/i }).click();

    const passwordField = page.locator('input[wire\\:model="password"]');
    await expect(passwordField).toBeVisible({ timeout: 10_000 });
    await passwordField.fill(password);
    await page.getByRole('button', { name: /Continue|Next|Sign in/i }).click();
}

export async function waitForPurchasePaymentMethods(page: Page): Promise<void> {
    await expect(page.locator('#payment-method-form')).toBeAttached({
        timeout: 20_000,
    });
    await expect(purchasePaymentMethodButtons(page).first()).toBeVisible({
        timeout: 20_000,
    });
}

export function purchasePaymentMethodButtons(page: Page) {
    return page.locator('button[wire\\:click*="handleMethodSelectingEvent"]');
}

export function purchaseLoadingSpinner(page: Page) {
    return page.locator('#payment-method-form')
        .locator('xpath=..')
        .locator('.animate-spin');
}

export async function selectFirstPurchasePaymentMethod(
    page: Page,
): Promise<void> {
    const button = purchasePaymentMethodButtons(page).first();
    await expect(button).toBeVisible();
    await button.click();
}

export async function waitForSubscriptionCheckoutRedirect(
    page: Page,
): Promise<void> {
    // Billing portal v1/v2 intentionally wait 2.5s before submitting the form.
    await expect(page).toHaveURL(/\/client\/payments\/process/, {
        timeout: 20_000,
    });
}

export async function countClientInvoices(
    api: ApiContext,
    clientId: string,
): Promise<number> {
    const response = await api.request.get(
        `/api/v1/invoices?client_id=${clientId}&per_page=100&include=invitations`,
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to list invoices (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return body.meta?.pagination?.total ?? body.data?.length ?? 0;
}

export async function latestClientInvoice(
    api: ApiContext,
    clientId: string,
): Promise<{ id: string; footer?: string; status_id?: string | number }> {
    const response = await api.request.get(
        `/api/v1/invoices?client_id=${clientId}&sort=created_at|desc&per_page=1`,
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to fetch latest invoice (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();
    const invoice = body.data?.[0];

    if (!invoice?.id) {
        throw new Error(`Client ${clientId} has no invoices.`);
    }

    return invoice;
}

export async function completeSubscriptionCheckoutRequiredFields(
    page: Page,
    contactEmail?: string,
): Promise<void> {
    await completeRequiredClientInfoForm(page, contactEmail ? { contact_email: contactEmail } : {});
}

export async function waitForGatewayCheckoutReady(page: Page): Promise<void> {
    await expect
        .poll(() => isRequiredClientInfoBlockingCheckout(page), {
            timeout: 30_000,
        })
        .toBe(false);
    await expect(gatewayCheckoutContainer(page)).not.toHaveClass(
        /pointer-events-none/,
    );
    await expect(page.locator('#pay-now, #card-element').first()).toBeVisible({
        timeout: 30_000,
    });
}

export async function completeStripeSubscriptionCheckout(
    page: Page,
    contactEmail?: string,
): Promise<void> {
    await dismissCookieConsent(page);
    await completeSubscriptionCheckoutRequiredFields(page, contactEmail);
    await fillStripeTestCard(page);

    const consent = page.getByRole('button', { name: 'Got it!' });
    if (await consent.isVisible().catch(() => false)) {
        await consent.click();
    }

    await page.locator('#pay-now').click({ force: true });
    await page.waitForURL(/\/client\/payments\/(?!process)/, {
        timeout: 60_000,
    });
}

export async function logInAndOpenWhiteLabelPurchase(
    api: ApiFixture,
    page: Page,
    subscriptionId: string,
): Promise<PortalClient> {
    const client = await prepareWhiteLabelPurchaseClient(api, page);
    await openWhiteLabelPurchasePage(page, subscriptionId);
    await waitForPurchasePaymentMethods(page);

    return client;
}

export async function logOutAndReauthenticateOnPurchasePage(
    page: Page,
    client: PortalClient,
): Promise<void> {
    await page.goto('/client/logout');
    await openWhiteLabelPurchasePage(
        page,
        page.url().match(/subscriptions\/([^/]+)/)?.[1] ?? '',
    );

    const contact = portalContact(client);
    await authenticateExistingContactOnPurchasePage(
        page,
        contact.email,
        'Portal123',
    );
    await waitForPurchasePaymentMethods(page);
}

export function stripeGatewayConfigured(): boolean {
    return Boolean(process.env.STRIPE_KEYS?.trim());
}

export {
    completeRequiredClientInfoForm,
    gatewayCheckoutContainer,
    isRequiredClientInfoBlockingCheckout,
    requiredClientInfoForm,
};

export async function findStripeGatewayForPurchase(
    api: ApiContext,
): Promise<CompanyGatewayEntity | undefined> {
    const { listCompanyGateways, findCompanyGatewayByKey } = await import(
        './api-helpers'
    );
    const gateways = await listCompanyGateways(api);

    return findCompanyGatewayByKey(
        gateways,
        'd14dd26a37cecc30fdd65700bfb55b23',
        1,
    );
}
