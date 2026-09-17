import { test, type Frame, type Page } from '@playwright/test';
import {
    bulkAction,
    companyGatewayHasCredentials,
    ensureCompanyGatewayForKey,
    ensureCompanyGatewayTypeEnabled,
    getCompanyGateway,
    isGatewayMethodEnabled,
    listCompanyGateways,
    parseCompanyGatewayConfig,
    testCompanyGatewayWithRetry,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { decodePrimaryKey } from '../hash-helpers';
import {
    isolateCompanyGateway,
    isCompanyGatewayArchived,
    listAllCompanyGateways,
} from './gateway-isolation-helpers';
import { GatewayType, type GatewayAvailability } from './types';

/**
 * Stripe test mode helpers for ACH.
 */

let validatedSecret: Promise<string | null> | undefined;

export const stripeGatewayKey = 'd14dd26a37cecc30fdd65700bfb55b23';

export interface StripeAchGatewaySetupResult {
    availability: GatewayAvailability;
    restore?: () => Promise<void>;
}

export interface StripeAchSetupOptions {
    /** Archive every other active company gateway so checkout cannot fall back to PayPal. */
    isolate?: boolean;
}

/**
 * Documented test payment method that reaches `processing` and then fails.
 *
 * @see https://docs.stripe.com/payments/ach-direct-debit/accept-a-payment#test-account-numbers
 */
export const failingAchPaymentMethod = 'pm_usBankAccount_accountClosed';

function parseStripeTestSecret(): string | null {
    const raw = process.env.STRIPE_KEYS?.trim() ?? '';
    let secret = raw.startsWith('sk_') ? raw : '';

    if (!secret) {
        try {
            const parsed = JSON.parse(raw) as Record<string, unknown>;

            secret = String(
                parsed.apiKey ??
                    parsed.secretKey ??
                    parsed.secret ??
                    parsed.api_key ??
                    '',
            );
        } catch {
            secret = raw.match(/sk_(?:test|live)_[A-Za-z0-9]+/)?.[0] ?? '';
        }
    }

    return /^sk_test_/.test(secret) ? secret : null;
}

/** The Stripe test secret, or null when it is missing or Stripe rejects it. */
export function validatedStripeTestSecret(): Promise<string | null> {
    validatedSecret ??= (async () => {
        const secret = parseStripeTestSecret();

        if (!secret) {
            return null;
        }

        try {
            const response = await fetch('https://api.stripe.com/v1/account', {
                headers: { Authorization: `Bearer ${secret}` },
                signal: AbortSignal.timeout(10_000),
            });

            return response.ok ? secret : null;
        } catch {
            return null;
        }
    })();

    return validatedSecret;
}

async function stripeRequest<T>(
    secret: string,
    path: string,
    body?: Record<string, unknown>,
): Promise<T> {
    const response = await fetch(`https://api.stripe.com${path}`, {
        method: body ? 'POST' : 'GET',
        headers: {
            Authorization: `Bearer ${secret}`,
            ...(body
                ? { 'Content-Type': 'application/x-www-form-urlencoded' }
                : {}),
        },
        body: body ? encodeForm(body) : undefined,
    });

    const payload = (await response.json()) as T & {
        error?: { message?: string };
    };

    if (!response.ok) {
        throw new Error(
            `Stripe request failed (${response.status}): ${payload.error?.message ?? path}`,
        );
    }

    return payload;
}

/** Stripe takes nested parameters as `a[b]=c`. */
function encodeForm(
    body: Record<string, unknown>,
    prefix = '',
): string {
    return Object.entries(body)
        .flatMap(([key, value]) => {
            const name = prefix ? `${prefix}[${key}]` : key;

            if (Array.isArray(value)) {
                return value.map(
                    (item) =>
                        `${encodeURIComponent(`${name}[]`)}=${encodeURIComponent(String(item))}`,
                );
            }

            if (value && typeof value === 'object') {
                return [encodeForm(value as Record<string, unknown>, name)];
            }

            return [
                `${encodeURIComponent(name)}=${encodeURIComponent(String(value))}`,
            ];
        })
        .join('&');
}

export function stripeGet<T>(
    secret: string,
    path: string,
    params: Record<string, string> = {},
): Promise<T> {
    const url = new URL(`https://api.stripe.com${path}`);

    for (const [key, value] of Object.entries(params)) {
        url.searchParams.set(key, value);
    }

    return stripeRequest<T>(secret, url.pathname + url.search);
}

export async function stripeList<T>(
    secret: string,
    path: string,
    params: Record<string, string>,
): Promise<T[]> {
    const body = await stripeGet<{ data: T[] }>(secret, path, params);

    return body.data;
}

export function parseStripeKeysConfig(): Record<string, unknown> | null {
    const raw = process.env.STRIPE_KEYS?.trim() ?? '';

    if (!raw) {
        return null;
    }

    if (raw.startsWith('sk_')) {
        return null;
    }

    try {
        const parsed = JSON.parse(raw) as Record<string, unknown>;

        return companyGatewayHasCredentials(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

export function stripeGatewayConfigurationReady(
    gateway: CompanyGatewayEntity,
): boolean {
    return companyGatewayHasCredentials(parseCompanyGatewayConfig(gateway));
}

export function selectCanonicalStripeGateway(
    gateways: CompanyGatewayEntity[],
): CompanyGatewayEntity | undefined {
    const matches = gateways.filter(
        (gateway) => gateway.gateway_key === stripeGatewayKey,
    );

    if (matches.length === 0) {
        return undefined;
    }

    const active = matches.filter(
        (gateway) => !isCompanyGatewayArchived(gateway),
    );
    const pool = active.length > 0 ? active : matches;

    return [...pool].sort((left, right) => left.id.localeCompare(right.id))[0];
}

function describeStripeGatewayState(
    gateways: CompanyGatewayEntity[],
): string {
    const stripeGateways = gateways.filter(
        (gateway) => gateway.gateway_key === stripeGatewayKey,
    );

    if (stripeGateways.length === 0) {
        return 'no Stripe company gateways';
    }

    return stripeGateways
        .map((gateway) => {
            const achEnabled = isGatewayMethodEnabled(gateway, GatewayType.ACH);

            return `${decodePrimaryKey(gateway.id)} archived=${isCompanyGatewayArchived(gateway)} ach=${achEnabled}`;
        })
        .join('; ');
}

async function applyStripeConfig(
    api: ApiContext,
    gateway: CompanyGatewayEntity,
    envConfig: Record<string, unknown>,
): Promise<CompanyGatewayEntity> {
    const config = {
        ...parseCompanyGatewayConfig(gateway),
        ...envConfig,
    };

    const response = await api.request.put(
        `/api/v1/company_gateways/${gateway.id}`,
        {
            data: {
                gateway_key: gateway.gateway_key,
                config: JSON.stringify(config),
                fees_and_limits: gateway.fees_and_limits ?? {},
            },
        },
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to apply Stripe credentials to gateway ${decodePrimaryKey(gateway.id)} (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    return getCompanyGateway(api, gateway.id);
}

async function ensureStripeGatewayConfigured(
    api: ApiContext,
    gateway: CompanyGatewayEntity,
): Promise<CompanyGatewayEntity> {
    if (stripeGatewayConfigurationReady(gateway)) {
        return gateway;
    }

    const envConfig = parseStripeKeysConfig();

    if (!envConfig) {
        throw new Error(
            'STRIPE_KEYS must be JSON with apiKey and publishableKey when the company gateway has no Stripe credentials',
        );
    }

    return applyStripeConfig(api, gateway, envConfig);
}

/** Sync credentials, enable ACH, and verify auth on every active Stripe gateway. */
export async function syncActiveStripeAchGateways(
    api: ApiContext,
): Promise<CompanyGatewayEntity[]> {
    const activeGateways = (await listCompanyGateways(api)).filter(
        (gateway) => gateway.gateway_key === stripeGatewayKey,
    );

    if (activeGateways.length === 0) {
        return [];
    }

    const ready: CompanyGatewayEntity[] = [];

    for (const candidate of activeGateways) {
        let gateway = await ensureStripeGatewayConfigured(api, candidate);

        gateway = await ensureCompanyGatewayTypeEnabled(
            api,
            gateway,
            GatewayType.ACH,
        );

        if (!isGatewayMethodEnabled(gateway, GatewayType.ACH)) {
            throw new Error(
                `Stripe gateway ${decodePrimaryKey(gateway.id)} does not offer ACH in fees_and_limits`,
            );
        }

        const authTest = await testCompanyGatewayWithRetry(api, gateway.id);

        if (!authTest.ok) {
            throw new Error(
                `Stripe gateway ${decodePrimaryKey(gateway.id)} failed API auth test: ${authTest.message}`,
            );
        }

        ready.push(await getCompanyGateway(api, gateway.id));
    }

    return ready;
}

/**
 * Prepare a portal-ready Stripe ACH gateway: valid env, credentials, ACH fee slot,
 * driver auth, and optionally isolate the gateway from PayPal and others.
 */
export async function setupStripeAchExclusiveEnvironment(
    api: ApiContext,
    options: StripeAchSetupOptions = {},
): Promise<StripeAchGatewaySetupResult> {
    const stripeSecret = await validatedStripeTestSecret();

    if (!stripeSecret) {
        return {
            availability: {
                envConfigured: false,
                companyGatewayConfigured: false,
                skipReason:
                    'Set STRIPE_KEYS to a valid Stripe test-mode secret key to run live ACH tests.',
            },
        };
    }

    let gateway = selectCanonicalStripeGateway(await listAllCompanyGateways(api));

    if (!gateway) {
        gateway = await ensureCompanyGatewayForKey(
            api,
            stripeGatewayKey,
            'STRIPE_KEYS',
        );
    }

    if (!gateway) {
        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
                skipReason: `Stripe ACH: no company gateway for key ${stripeGatewayKey}. Set STRIPE_KEYS JSON with apiKey and publishableKey.`,
            },
        };
    }

    if (isCompanyGatewayArchived(gateway)) {
        await bulkAction(api, 'company_gateways', [gateway.id], 'restore');
        gateway = await getCompanyGateway(api, gateway.id);
    }

    try {
        await syncActiveStripeAchGateways(api);
        gateway = await ensureStripeGatewayConfigured(api, gateway);
        gateway = await ensureCompanyGatewayTypeEnabled(
            api,
            gateway,
            GatewayType.ACH,
        );
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);
        const state = describeStripeGatewayState(await listAllCompanyGateways(api));

        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
                skipReason: `Stripe ACH: ${message}. Gateways: ${state}`,
            },
        };
    }

    if (!isGatewayMethodEnabled(gateway, GatewayType.ACH)) {
        const state = describeStripeGatewayState(await listAllCompanyGateways(api));

        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
                companyGateway: gateway,
                skipReason: `Stripe ACH is not enabled in fees_and_limits[2]. Gateways: ${state}`,
            },
        };
    }

    const authTest = await testCompanyGatewayWithRetry(api, gateway.id);

    if (!authTest.ok) {
        const state = describeStripeGatewayState(await listAllCompanyGateways(api));

        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
                companyGateway: gateway,
                skipReason: `Stripe gateway ${decodePrimaryKey(gateway.id)} failed API auth test: ${authTest.message}. Gateways: ${state}`,
            },
        };
    }

    gateway = await getCompanyGateway(api, gateway.id);

    if (options.isolate === false) {
        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: true,
                companyGateway: gateway,
            },
        };
    }

    const { gateway: isolatedGateway, restore } = await isolateCompanyGateway(
        api,
        gateway,
        GatewayType.ACH,
    );

    return {
        availability: {
            envConfigured: true,
            companyGatewayConfigured: true,
            companyGateway: isolatedGateway,
        },
        restore,
    };
}

/** Skip the current test when Stripe ACH is not portal-ready; return the gateway to use. */
export async function prepareStripeAchGateway(
    api: ApiContext,
    options: StripeAchSetupOptions = {},
): Promise<{
    companyGateway: CompanyGatewayEntity;
    restore: () => Promise<void>;
}> {
    const setup = await setupStripeAchExclusiveEnvironment(api, options);

    if (
        !setup.availability.envConfigured ||
        !setup.availability.companyGatewayConfigured ||
        !setup.availability.companyGateway
    ) {
        test.skip(
            true,
            setup.availability.skipReason ??
                'Stripe ACH is unavailable for this account.',
        );
    }

    return {
        companyGateway: setup.availability.companyGateway!,
        restore: setup.restore ?? (async () => {}),
    };
}

export interface StripeWebhookEndpoint {
    id: string;
    url: string;
    status: string;
}

/** Whether Stripe can reach this installation - async outcomes arrive only by webhook. */
export async function hasWebhookEndpoint(
    secret: string,
    webhookUrl: string,
): Promise<boolean> {
    const endpoints = await stripeGet<{ data: StripeWebhookEndpoint[] }>(
        secret,
        '/v1/webhook_endpoints?limit=100',
    );

    return endpoints.data.some(
        (endpoint) =>
            endpoint.url === webhookUrl && endpoint.status === 'enabled',
    );
}

export interface StoredStripeBankAccount {
    customerId: string;
    paymentMethodId: string;
    last4: string;
}

/**
 * Builds a Stripe customer holding a mandated bank account that fails after processing.
 *
 * The mandate matters: the application charges a stored bank account off session, which
 * Stripe only allows when the payment method already carries one.
 */
export async function createMandatedAchPaymentMethod(
    secret: string,
    paymentMethod: string = failingAchPaymentMethod,
): Promise<StoredStripeBankAccount> {
    const customer = await stripeRequest<{ id: string }>(
        secret,
        '/v1/customers',
        { description: 'Playwright ACH gateway fee' },
    );

    const attached = await stripeRequest<{
        id: string;
        us_bank_account?: { last4?: string };
    }>(secret, `/v1/payment_methods/${paymentMethod}/attach`, {
        customer: customer.id,
    });

    const setupIntent = await stripeRequest<{ status: string }>(
        secret,
        '/v1/setup_intents',
        {
            customer: customer.id,
            payment_method: attached.id,
            payment_method_types: ['us_bank_account'],
            usage: 'off_session',
            confirm: 'true',
            mandate_data: { customer_acceptance: { type: 'offline' } },
        },
    );

    if (setupIntent.status !== 'succeeded') {
        throw new Error(
            `Stripe did not authorise the test bank account: ${setupIntent.status}`,
        );
    }

    return {
        customerId: customer.id,
        paymentMethodId: attached.id,
        last4: attached.us_bank_account?.last4 ?? '6789',
    };
}

/**
 * Walks the Financial Connections modal to the end, linking a test bank account.
 *
 * The flow is Stripe hosted and changes without notice, so this clicks whatever it
 * recognises rather than following a fixed script.
 */
export async function completeFinancialConnections(
    page: Page,
    completedUrlPattern: RegExp = /\/client\/payment_methods\/(?!create(?:\?|$))[^/?]+/,
    timeout = 60_000,
): Promise<void> {
    const deadline = Date.now() + timeout;
    let lastSnapshot = '';

    while (Date.now() < deadline) {
        if (completedUrlPattern.test(page.url())) {
            return;
        }

        for (const frame of page.frames().reverse()) {
            if (
                frame === page.mainFrame() ||
                !frame.url().includes('stripe.com')
            ) {
                continue;
            }

            const text = await frame
                .locator('body')
                .innerText()
                .catch(() => '');

            if (!text.trim()) {
                continue;
            }

            lastSnapshot = text.replace(/\s+/g, ' ').slice(0, 1_000);

            if (await clickVisible(frame, /Test \(Non-OAuth\)/i)) {
                break;
            }

            if (await clickVisible(frame, /Finish without saving/i)) {
                break;
            }

            if (
                await clickVisible(
                    frame,
                    /Agree and continue|Continue|Get started/i,
                )
            ) {
                break;
            }

            const account = frame
                .getByText(/Checking|Savings/i, { exact: false })
                .first();

            if (await account.isVisible().catch(() => false)) {
                const clicked = await account
                    .click({ force: true, timeout: 2_000 })
                    .then(() => true)
                    .catch(() => false);

                if (clicked) {
                    break;
                }
            }

            if (
                await clickVisible(frame, /Connect account|Link account|Done/i)
            ) {
                break;
            }
        }

        await page.waitForTimeout(500);
    }

    throw new Error(
        `Stripe Financial Connections did not complete. Last visible content: ${lastSnapshot}`,
    );
}

async function clickVisible(frame: Frame, name: RegExp): Promise<boolean> {
    const button = frame.getByRole('button', { name }).first();

    if (
        (await button.isVisible().catch(() => false)) &&
        (await button.isEnabled().catch(() => false))
    ) {
        const clicked = await button
            .click({ force: true, timeout: 2_000 })
            .then(() => true)
            .catch(() => false);

        if (clicked) {
            return true;
        }
    }

    const link = frame.getByRole('link', { name }).first();

    if (await link.isVisible().catch(() => false)) {
        const clicked = await link
            .click({ force: true, timeout: 2_000 })
            .then(() => true)
            .catch(() => false);

        if (clicked) {
            return true;
        }
    }

    const text = frame.getByText(name, { exact: false }).first();

    if (await text.isVisible().catch(() => false)) {
        return text
            .click({ force: true, timeout: 2_000 })
            .then(() => true)
            .catch(() => false);
    }

    return false;
}
