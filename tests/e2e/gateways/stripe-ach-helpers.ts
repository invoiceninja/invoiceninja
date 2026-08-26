import { type Frame, type Page } from '@playwright/test';

/**
 * Stripe test mode helpers for ACH.
 *
 * `stripe-ach-live.spec.ts` carries its own copies of the Financial Connections walk
 * through and the Stripe REST calls; these are the shared versions used by newer specs.
 */

let validatedSecret: Promise<string | null> | undefined;

export const stripeGatewayKey = 'd14dd26a37cecc30fdd65700bfb55b23';

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

export function stripeGet<T>(secret: string, path: string): Promise<T> {
    return stripeRequest<T>(secret, path);
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
