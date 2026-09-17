import { expect, type BrowserContext, type Frame, type Page } from '@playwright/test';
import { dismissCookieConsent, clearPortalOverlays } from '../../client-portal-helpers';
import {
    completeRequiredClientInfoForm,
    isRequiredClientInfoBlockingCheckout,
} from '../../gateways/payment-flow-helpers';
import { type PayPalRestKeys } from './env';
import {
    PAYPAL_FUNDING_BUTTON_LABELS,
    type PayPalRestPaymentMethod,
} from './payment-methods';

export interface PayPalSandboxBuyerCredentials {
    email: string;
    password: string;
}

export function payPalSandboxBuyerCredentials(
    keys: PayPalRestKeys,
): PayPalSandboxBuyerCredentials | null {
    const email = keys.buyerEmail?.trim() ?? '';
    const password = keys.buyerPassword?.trim() ?? '';

    if (!email || !password) {
        return null;
    }

    return { email, password };
}

const PAYPAL_US_FUNDING_SANDBOX_GEOLOCATION = {
    latitude: 40.7128,
    longitude: -74.006,
};

/**
 * PayPal sandbox simulates US-only funding sources when buyer-country=US is
 * present on the JS SDK script URL.
 *
 * @see https://developer.paypal.com/docs/checkout/pay-with-venmo/test/
 */
export function withPayPalSandboxBuyerCountry(
    url: string,
    country = 'US',
): string {
    const parsed = new URL(url);

    if (!parsed.searchParams.has('buyer-country')) {
        parsed.searchParams.set('buyer-country', country);
    }

    return parsed.toString();
}

export async function configurePayPalUsFundingSandboxContext(
    context: BrowserContext,
): Promise<void> {
    await context.setGeolocation(PAYPAL_US_FUNDING_SANDBOX_GEOLOCATION);
    await context.grantPermissions(['geolocation']);

    await context.route(/paypal\.com\/sdk\/js/, async (route) => {
        await route.continue({
            url: withPayPalSandboxBuyerCountry(route.request().url()),
        });
    });
}

/** @deprecated Use configurePayPalUsFundingSandboxContext */
export async function configurePayPalVenmoSandboxContext(
    context: BrowserContext,
): Promise<void> {
    await configurePayPalUsFundingSandboxContext(context);
}

export function requiresPayPalUsFundingSandboxContext(
    method: PayPalRestPaymentMethod,
): boolean {
    return method.gatewayTypeId === 25 || method.gatewayTypeId === 28;
}

/** @deprecated Use requiresPayPalUsFundingSandboxContext */
export function requiresPayPalVenmoSandboxContext(
    method: PayPalRestPaymentMethod,
): boolean {
    return requiresPayPalUsFundingSandboxContext(method);
}

/** Official PayPal static sandbox Visa from developer.paypal.com/tools/sandbox/card-testing */
export const PAYPAL_SANDBOX_TEST_CARD = {
    number: '4012888888881881',
    expiry: '12/30',
    cvv: '123',
} as const;

/** Passes Luhn validation but is rejected by PayPal Advanced Cards sandbox. */
export const PAYPAL_INVALID_SANDBOX_CARD_NUMBER = '4242424242424242';

const PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS = {
    cardNumber: '#credit-card-number',
    expiry: '#expiry-date',
    security: '#credit-card-security',
    email: '#email',
    phone: '#phone',
    submit: '#submit-button',
} as const;

const PAYPAL_SANDBOX_GUEST_PHONE = '4155550132';

const PAYPAL_GUEST_CARD_BILLING_ADDRESS = {
    givenName: 'Playwright',
    familyName: 'Portal',
    line1: '510 Townsend Street',
    city: 'San Francisco',
    state: 'CA',
    postcode: '94103',
    country: 'US',
} as const;

const PAYPAL_GUEST_CARD_BILLING_SELECTORS = {
    section: '.billingAddress',
    country:
        '[id="billingAddress.country"], select.country[name="billingAddress.country"]',
    givenName:
        '[id="billingAddress.givenName"], input[name="givenName"][autocomplete="given-name"]',
    familyName:
        '[id="billingAddress.familyName"], input[name="familyName"][autocomplete="family-name"]',
    line1:
        '[id="billingAddress.line1"], input[name="line1"][autocomplete="billing street-address"]',
    line2:
        '[id="billingAddress.line2"], input[name="line2"][autocomplete="billing street-address2"]',
    city:
        '[id="billingAddress.city"], input[name="city"][autocomplete="billing address-level2"]',
    state:
        '[id="billingAddress.state"], select[name="state"][autocomplete="billing address-level1"]',
    postcode:
        '[id="billingAddress.postcode"], input[name="postcode"][autocomplete="billing postal-code"]',
} as const;

function usesPayPalGuestCardCheckout(method: PayPalRestPaymentMethod): boolean {
    return method.checkoutKind === 'buttons' && method.fundingSource === 'card';
}

function payPalGuestCardExpiryDigits(): string {
    return PAYPAL_SANDBOX_TEST_CARD.expiry.replace(/\D/g, '');
}

export function payPalAdvancedCardErrorsLocator(page: Page) {
    return page
        .locator('#paypal-credit-card-payment #errors')
        .or(page.locator('main #errors'))
        .first();
}

const PAYPAL_ADVANCED_CARD_FIELD_TARGETS = {
    number: {
        container: '#card-number-field-container',
        selectors: [
            'input.card-field-number',
            'input[name="number"]',
            'input[autocomplete="cc-number"]',
            'input[placeholder="Card number"]',
        ],
    },
    expiry: {
        container: '#card-expiry-field-container',
        selectors: [
            'input.card-field-expiry',
            'input[name="expiry"]',
            'input[autocomplete="cc-exp"]',
            'input[placeholder*="MM"]',
            'input[placeholder*="Expiry"]',
        ],
    },
    cvv: {
        container: '#card-cvv-field-container',
        selectors: [
            'input.card-field-cvv',
            'input[name="cvv"]',
            'input[autocomplete="cc-csc"]',
            'input[placeholder="CVV"]',
            'input[placeholder*="CVC"]',
        ],
    },
} as const;

async function typePayPalHostedCardInput(
    page: Page,
    input: ReturnType<Page['locator']>,
    value: string,
): Promise<void> {
    await input.click({ timeout: 5_000 });
    await input.press('ControlOrMeta+a').catch(() => null);
    await input.press('Backspace').catch(() => null);
    await input.pressSequentially(value, { delay: 60 });
    await input.press('Tab').catch(() => null);
    await page.waitForTimeout(400);
}

async function assertPayPalAdvancedCardCheckoutHasNoErrors(
    page: Page,
): Promise<void> {
    const errors = payPalAdvancedCardErrorsLocator(page);

    if (!(await errors.isVisible().catch(() => false))) {
        return;
    }

    const message = (await errors.textContent())?.trim();

    throw new Error(
        message
            ? `PayPal advanced card checkout failed: ${message}`
            : 'PayPal advanced card checkout failed with a validation error',
    );
}

function isPayPal3dsChallengeFrameUrl(url: string): boolean {
    return /cardinalcommerce|three-d-secure|\/3ds\/|\/challenge\//i.test(url);
}

async function completePayPalAdvancedCard3dsIfPresent(page: Page): Promise<boolean> {
    for (const frame of page.frames()) {
        if (!isPayPal3dsChallengeFrameUrl(frame.url())) {
            continue;
        }

        for (const pattern of [
            /complete authentication/i,
            /^submit$/i,
            /^continue$/i,
            /^approve$/i,
        ]) {
            const button = frame.getByRole('button', { name: pattern }).first();

            if (await button.isVisible({ timeout: 250 }).catch(() => false)) {
                await button.click({ timeout: 15_000, force: true });

                return true;
            }
        }

        for (const selector of [
            'input[type="submit"]',
            'button[type="submit"]',
            '#submit',
        ]) {
            const button = frame.locator(selector).first();

            if (await button.isVisible({ timeout: 250 }).catch(() => false)) {
                await button.click({ timeout: 15_000, force: true });

                return true;
            }
        }
    }

    return false;
}

async function waitForPayPalAdvancedCardPaymentResult(
    page: Page,
): Promise<void> {
    await expect
        .poll(async () => {
            if (isPayPalPaymentConfirmationUrl(page.url())) {
                return 'done';
            }

            await completePayPalAdvancedCard3dsIfPresent(page);

            if (isPayPalPaymentConfirmationUrl(page.url())) {
                return 'done';
            }

            await assertPayPalAdvancedCardCheckoutHasNoErrors(page);

            return null;
        }, { timeout: 180_000 })
        .toBe('done');
}

async function followPayPalAdvancedCardPaymentRedirect(
    page: Page,
    response: import('@playwright/test').Response,
): Promise<void> {
    if (!response.ok()) {
        return;
    }

    const body = (await response.json().catch(() => null)) as {
        redirect?: string;
    } | null;

    if (!body?.redirect || isPayPalPaymentConfirmationUrl(page.url())) {
        return;
    }

    await page.goto(body.redirect);
}

async function fillPayPalHostedCardInput(
    page: Page,
    field: keyof typeof PAYPAL_ADVANCED_CARD_FIELD_TARGETS,
    value: string,
): Promise<void> {
    const target = PAYPAL_ADVANCED_CARD_FIELD_TARGETS[field];

    await expect(page.locator(target.container)).toBeVisible({
        timeout: 45_000,
    });

    let input: ReturnType<Page['locator']> | null = null;

    await expect
        .poll(async () => {
            input = await findPayPalHostedCardInput(page, field);

            return input !== null;
        }, { timeout: 90_000 })
        .toBe(true);

    if (!input) {
        throw new Error(
            `PayPal advanced card field "${field}" was not found in ${target.container}`,
        );
    }

    await typePayPalHostedCardInput(page, input, value);
}

async function findPayPalHostedCardInput(
    page: Page,
    field: keyof typeof PAYPAL_ADVANCED_CARD_FIELD_TARGETS,
): Promise<ReturnType<Page['locator']> | null> {
    const target = PAYPAL_ADVANCED_CARD_FIELD_TARGETS[field];
    const containerIframe = page.frameLocator(`${target.container} iframe`).first();
    const hasContainerIframe =
        (await page.locator(`${target.container} iframe`).count()) > 0;

    if (hasContainerIframe) {
        for (const selector of target.selectors) {
            const input = containerIframe.locator(selector).first();

            if ((await input.count()) > 0) {
                return input;
            }
        }
    }

    for (const selector of target.selectors) {
        const input = page.locator(`${target.container} ${selector}`).first();

        if ((await input.count()) > 0) {
            return input;
        }
    }

    const iframeCount = await page.locator(`${target.container} iframe`).count();

    for (let index = 0; index < iframeCount; index += 1) {
        const frame = page
            .frameLocator(`${target.container} iframe`)
            .nth(index);

        for (const selector of target.selectors) {
            const input = frame.locator(selector).first();

            if ((await input.count()) > 0) {
                return input;
            }
        }
    }

    for (const frame of page.frames()) {
        for (const selector of target.selectors) {
            const input = frame.locator(selector).first();

            if ((await input.count()) === 0) {
                continue;
            }

            if (await input.isVisible({ timeout: 250 }).catch(() => false)) {
                return input;
            }
        }
    }

    return null;
}

async function waitForPayPalAdvancedCardFields(page: Page): Promise<void> {
    await expect
        .poll(async () => {
            for (const field of ['number', 'expiry', 'cvv'] as const) {
                const input = await findPayPalHostedCardInput(page, field);

                if (!input) {
                    return false;
                }

                if (!(await input.isVisible({ timeout: 250 }).catch(() => false))) {
                    return false;
                }
            }

            return true;
        }, { timeout: 90_000 })
        .toBe(true);
}

async function ensurePayPalAdvancedCardFormReady(page: Page): Promise<void> {
    await ensurePayPalCheckoutIsUnblocked(page);
    await assertPayPalAdvancedCardCheckoutReadyWhenUnblocked(page);
}

export async function assertPayPalAdvancedCardCheckoutReadyWhenUnblocked(
    page: Page,
): Promise<void> {
    await clearPortalOverlays(page);

    if (await isRequiredClientInfoBlockingCheckout(page)) {
        throw new Error(
            'PayPal Advanced Cards checkout is blocked by required payment details',
        );
    }

    const newCardToggle = page.locator('#toggle-payment-with-credit-card');

    if (await newCardToggle.isVisible().catch(() => false)) {
        if (!(await newCardToggle.isChecked().catch(() => false))) {
            await newCardToggle.click({ force: true });
        }
    }

    await expect(page.locator('#checkout-form')).toBeVisible({ timeout: 30_000 });
    await expect(page.locator('#pay-now')).toBeVisible({ timeout: 30_000 });
    await waitForPayPalAdvancedCardFields(page);
}

function fundingSourceButtonSelectors(fundingSource: string): string[] {
    const selectors = [
        `[data-funding-source="${fundingSource}"]`,
        `[data-funding-source="${fundingSource.replace(/_/g, '-')}"]`,
    ];

    for (const label of PAYPAL_FUNDING_BUTTON_LABELS[fundingSource] ?? []) {
        selectors.push(`[aria-label="${label}"]`);
    }

    selectors.push('[role="button"]');

    return selectors;
}

async function clickFundingSourceInFrameLocator(
    page: Page,
    fundingSource: string,
): Promise<boolean> {
    const frameLocators = [
        page
            .frameLocator('#paypal-button-container iframe.component-frame')
            .first()
            .frameLocator('iframe'),
        page.frameLocator('#paypal-button-container iframe').first(),
        page.frameLocator('iframe[name^="__zoid__paypal_buttons"]').first(),
        page.frameLocator('iframe[title*="PayPal"]').first(),
        page.frameLocator('iframe[title*="Venmo"]').first(),
    ];

    for (const frame of frameLocators) {
        for (const selector of fundingSourceButtonSelectors(fundingSource)) {
            const button = frame.locator(selector).first();

            if (await button.isVisible({ timeout: 500 }).catch(() => false)) {
                await button.click({ timeout: 15_000, force: true });

                return true;
            }
        }
    }

    return false;
}

async function clickPayPalButtonContainer(page: Page): Promise<boolean> {
    const container = page.locator('#paypal-button-container');

    if ((await container.count()) === 0) {
        return false;
    }

    await container.scrollIntoViewIfNeeded();

    const iframe = container.locator('iframe').first();

    if ((await iframe.count()) === 0) {
        return false;
    }

    const box = await iframe.boundingBox();

    if (box && box.width > 0 && box.height > 0) {
        await page.mouse.click(
            box.x + box.width / 2,
            box.y + box.height / 2,
        );

        return true;
    }

    await iframe.click({ timeout: 15_000, force: true });

    return true;
}

async function waitForPayPalButtonContainerReady(page: Page): Promise<void> {
    await expect
        .poll(async () => {
            const container = page.locator('#paypal-button-container');

            if ((await container.count()) === 0) {
                return false;
            }

            const isVisible = await container.evaluate((element) => {
                const htmlElement = element as HTMLElement;

                return (
                    !htmlElement.hidden &&
                    htmlElement.offsetParent !== null &&
                    !htmlElement.classList.contains('hidden')
                );
            });

            if (!isVisible) {
                return false;
            }

            const iframe = container.locator('iframe').first();
            const box = await iframe.boundingBox().catch(() => null);

            return Boolean(box && box.width > 40 && box.height > 20);
        }, { timeout: 90_000 })
        .toBe(true);
}

async function findPayPalFundingButtonFrame(
    page: Page,
    fundingSource: string,
): Promise<{ frame: Frame; selector: string } | null> {
    for (const frame of page.frames()) {
        for (const selector of fundingSourceButtonSelectors(fundingSource)) {
            const locator = frame.locator(selector).first();

            if ((await locator.count()) === 0) {
                continue;
            }

            if (await locator.isVisible({ timeout: 250 }).catch(() => false)) {
                return { frame, selector };
            }

            const box = await locator.boundingBox().catch(() => null);

            if (box && box.width > 0 && box.height > 0) {
                return { frame, selector };
            }
        }
    }

    return null;
}

export async function waitForPayPalFundingButton(
    page: Page,
    fundingSource: string,
): Promise<void> {
    await expect
        .poll(
            async () =>
                (await findPayPalFundingButtonFrame(page, fundingSource)) !==
                null,
            { timeout: 90_000 },
        )
        .toBe(true);
}

export async function clickPayPalFundingButton(
    page: Page,
    fundingSource: string,
): Promise<void> {
    await waitForPayPalButtonContainerReady(page);
    await waitForPayPalFundingButton(page, fundingSource);
    await clearPortalOverlays(page);

    const container = page.locator('#paypal-button-container');
    await container.scrollIntoViewIfNeeded();

    if (await clickPayPalButtonContainer(page)) {
        return;
    }

    if (await clickFundingSourceInFrameLocator(page, fundingSource)) {
        return;
    }

    const match = await findPayPalFundingButtonFrame(page, fundingSource);

    if (match) {
        await match.frame.locator(match.selector).first().click({
            timeout: 15_000,
            force: true,
        });

        return;
    }

    throw new Error(
        `PayPal "${fundingSource}" button was not clicked — complete required payment details and run headed (VS Code Testing panel)`,
    );
}

type PayPalCheckoutTarget = Page | Frame;

function payPalCheckoutTargets(root: Page | Frame): PayPalCheckoutTarget[] {
    const targets: PayPalCheckoutTarget[] = [root];

    if (typeof (root as Page).frames === 'function') {
        targets.push(...(root as Page).frames());
    }

    if (typeof (root as Frame).childFrames === 'function') {
        targets.push(...(root as Frame).childFrames());
    }

    return targets;
}

function payPalCheckoutTargetUrl(target: PayPalCheckoutTarget): string {
    return typeof (target as Page).url === 'function'
        ? (target as Page).url()
        : (target as Frame).url();
}

function payPalCheckoutRootPage(
    merchantPage: Page,
    target: PayPalCheckoutTarget,
): Page {
    return typeof (target as Page).bringToFront === 'function'
        ? (target as Page)
        : merchantPage;
}

async function findPayPalGuestCardCheckoutTarget(
    merchantPage: Page,
): Promise<PayPalCheckoutTarget | null> {
    for (const candidate of merchantPage.context().pages()) {
        if (candidate.isClosed()) {
            continue;
        }

        for (const target of payPalCheckoutTargets(candidate)) {
            const cardField = target
                .locator(PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.cardNumber)
                .first();

            if (await cardField.isVisible().catch(() => false)) {
                return target;
            }
        }
    }

    return null;
}

async function isPayPalGuestCardCheckoutVisible(
    merchantPage: Page,
): Promise<boolean> {
    return (await findPayPalGuestCardCheckoutTarget(merchantPage)) !== null;
}

function isPayPalHostedUrl(url: string): boolean {
    return /sandbox\.paypal\.com|paypal\.com/.test(url);
}

function isMerchantPaymentResponseUrl(url: string): boolean {
    return (
        url.includes('/client/payments/process/response') ||
        url.includes('/payment_response')
    );
}

/** Portal payment receipt — not the checkout route `/client/payments/process`. */
export function isPayPalPaymentConfirmationUrl(url: string): boolean {
    try {
        const pathname = new URL(url).pathname;

        return (
            /^\/client\/payments\/[^/]+$/.test(pathname) &&
            !pathname.startsWith('/client/payments/process')
        );
    } catch {
        return (
            /\/client\/payments\/(?!process(?:\/|$))[^/?#]+/.test(url) &&
            !url.includes('/client/payments/process/response')
        );
    }
}

export function merchantPaymentResponseUrlMatcher(url: string): boolean {
    return isMerchantPaymentResponseUrl(url);
}

async function ensurePayPalCheckoutPopup(page: Page): Promise<void> {
    let continueAttempted = false;

    await expect
        .poll(async () => {
            if (await isPayPalGuestCardCheckoutVisible(page)) {
                return true;
            }

            for (const candidate of page.context().pages()) {
                if (candidate.isClosed()) {
                    continue;
                }

                for (const target of payPalCheckoutTargets(candidate)) {
                    const emailField = target
                        .locator('#email, input[name="login_email"]')
                        .first();

                    if (await emailField.isVisible().catch(() => false)) {
                        return true;
                    }
                }

                if (candidate !== page && isPayPalHostedUrl(candidate.url())) {
                    return true;
                }
            }

            if (!continueAttempted) {
                const continueLink = page
                    .locator('text=Click to Continue')
                    .or(page.getByRole('link', { name: /click to continue/i }))
                    .first();

                if (await continueLink.isVisible().catch(() => false)) {
                    continueAttempted = true;
                    await continueLink.click({ force: true });
                }
            }

            return false;
        }, { timeout: 90_000 })
        .toBe(true);
}

async function waitForPayPalCheckoutPage(
    page: Page,
    popupPromise: Promise<Page | null>,
): Promise<Page> {
    let checkoutPage: Page | null = null;

    await expect
        .poll(async () => {
            const popup = await popupPromise.catch(() => null);

            if (popup) {
                await popup
                    .waitForURL(/sandbox\.paypal\.com|paypal\.com|about:blank/, {
                        timeout: 2_000,
                    })
                    .catch(() => null);
            }

            for (const candidate of page.context().pages()) {
                if (candidate !== page && isPayPalHostedUrl(candidate.url())) {
                    checkoutPage = candidate;

                    return true;
                }
            }

            if (popup && (isPayPalHostedUrl(popup.url()) || popup.url() === 'about:blank')) {
                checkoutPage = popup;

                return true;
            }

            if (isPayPalHostedUrl(page.url())) {
                checkoutPage = page;

                return true;
            }

            for (const target of payPalCheckoutTargets(page)) {
                if (!isPayPalHostedUrl(payPalCheckoutTargetUrl(target))) {
                    continue;
                }

                const loginField = target
                    .locator(
                        '#email, input[name="login_email"], #password, input[name="login_password"], #confirmButtonTop, button:has-text("Pay Now")',
                    )
                    .first();

                if (await loginField.isVisible().catch(() => false)) {
                    checkoutPage = page;

                    return true;
                }
            }

            for (const target of payPalCheckoutTargets(page)) {
                const loginField = target
                    .locator('#email, input[name="login_email"], input[type="email"]')
                    .first();

                if (await loginField.isVisible().catch(() => false)) {
                    checkoutPage = page;

                    return true;
                }
            }

            return false;
        }, { timeout: 90_000 })
        .toBe(true);

    if (!checkoutPage) {
        throw new Error(
            'PayPal checkout did not open after clicking the funding button',
        );
    }

    if (checkoutPage !== page) {
        await checkoutPage
            .waitForURL(/sandbox\.paypal\.com|paypal\.com/, {
                timeout: 90_000,
            })
            .catch(() => null);
        await checkoutPage.bringToFront().catch(() => null);
    }

    return checkoutPage;
}

async function resolvePayPalLoginPage(merchantPage: Page): Promise<Page> {
    const candidates = merchantPage
        .context()
        .pages()
        .filter((page) => !page.isClosed())
        .sort((left, right) => {
            const leftScore = isPayPalHostedUrl(left.url())
                ? 0
                : left.url().includes('/client/')
                  ? 2
                  : 1;
            const rightScore = isPayPalHostedUrl(right.url())
                ? 0
                : right.url().includes('/client/')
                  ? 2
                  : 1;

            return leftScore - rightScore;
        });

    await expect
        .poll(async () => {
            for (const page of candidates) {
                const emailField = page.locator('#email, input[name="login_email"]').first();

                if (await emailField.isVisible().catch(() => false)) {
                    return page;
                }
            }

            return null;
        }, { timeout: 60_000 })
        .not.toBeNull();

    for (const page of candidates) {
        const emailField = page.locator('#email, input[name="login_email"]').first();

        if (await emailField.isVisible().catch(() => false)) {
            await page.bringToFront().catch(() => null);

            return page;
        }
    }

    throw new Error('PayPal sandbox login page with #email was not found');
}

async function dispatchPayPalHostedInputValue(
    field: ReturnType<PayPalCheckoutTarget['locator']>,
    value: string,
): Promise<void> {
    await field.evaluate((element, nextValue) => {
        const input = element as HTMLInputElement;
        input.value = nextValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, value);
}

async function fillPayPalHostedFloatingLabelInput(
    target: PayPalCheckoutTarget,
    selector: string,
    value: string,
): Promise<void> {
    const field = target.locator(selector).first();
    const normalizedValue = value.replace(/\s/g, '');

    await expect(field).toBeVisible({ timeout: 60_000 });
    await field.click({ force: true });
    await field.press('ControlOrMeta+a').catch(() => null);
    await field.press('Backspace').catch(() => null);
    await field.pressSequentially(normalizedValue, { delay: 50 });
    await field.press('Tab').catch(() => null);

    await expect
        .poll(async () => (await field.inputValue()).replace(/\D/g, ''), {
            timeout: 10_000,
        })
        .toContain(normalizedValue.replace(/\D/g, '').slice(0, 4))
        .catch(async () => {
            await dispatchPayPalHostedInputValue(field, value);
        });
}

async function fillPayPalHostedTextInput(
    field: ReturnType<PayPalCheckoutTarget['locator']>,
    value: string,
): Promise<void> {
    await expect(field).toBeVisible({ timeout: 15_000 });
    await field.click({ force: true });
    await field.press('ControlOrMeta+a').catch(() => null);
    await field.press('Backspace').catch(() => null);
    await field.pressSequentially(value, { delay: 40 });
    await field.press('Tab').catch(() => null);

    await expect
        .poll(async () => (await field.inputValue()).trim(), {
            timeout: 10_000,
        })
        .toBe(value)
        .catch(async () => {
            await dispatchPayPalHostedInputValue(field, value);
        });
}

async function resolvePayPalGuestCardCheckoutTarget(
    merchantPage: Page,
): Promise<PayPalCheckoutTarget> {
    await expect
        .poll(async () => findPayPalGuestCardCheckoutTarget(merchantPage), {
            timeout: 90_000,
        })
        .not.toBeNull();

    const target = await findPayPalGuestCardCheckoutTarget(merchantPage);

    if (!target) {
        throw new Error(
            'PayPal guest card checkout with #credit-card-number was not found',
        );
    }

    await payPalCheckoutRootPage(merchantPage, target)
        .bringToFront()
        .catch(() => null);

    return target;
}

function payPalGuestCardBillingSection(
    checkoutTarget: PayPalCheckoutTarget,
): ReturnType<PayPalCheckoutTarget['locator']> {
    return checkoutTarget.locator(PAYPAL_GUEST_CARD_BILLING_SELECTORS.section).first();
}

async function ensurePayPalGuestCardBillingTextField(
    checkoutTarget: PayPalCheckoutTarget,
    selector: string,
    value: string,
): Promise<void> {
    const field = payPalGuestCardBillingSection(checkoutTarget)
        .locator(selector)
        .first();

    if (!(await field.isVisible().catch(() => false))) {
        return;
    }

    const currentValue = (await field.inputValue()).trim();
    const isInvalid =
        (await field.getAttribute('aria-invalid').catch(() => null)) === 'true';

    if (!currentValue || isInvalid) {
        await fillPayPalHostedTextInput(field, value);
    }
}

async function ensurePayPalGuestCardBillingSelectField(
    checkoutTarget: PayPalCheckoutTarget,
    selector: string,
    value: string,
): Promise<void> {
    const field = payPalGuestCardBillingSection(checkoutTarget)
        .locator(selector)
        .first();

    if (!(await field.isVisible().catch(() => false))) {
        return;
    }

    const currentValue = await field.inputValue().catch(() => '');
    const isInvalid =
        (await field.getAttribute('aria-invalid').catch(() => null)) === 'true';

    if (currentValue !== value || isInvalid) {
        await field.selectOption(value);
        await field.dispatchEvent('change').catch(() => null);
        await field.dispatchEvent('blur').catch(() => null);
    }
}

async function fillPayPalGuestCardBillingAddressIfPresent(
    checkoutTarget: PayPalCheckoutTarget,
): Promise<void> {
    const billingSection = payPalGuestCardBillingSection(checkoutTarget);

    if (!(await billingSection.isVisible().catch(() => false))) {
        return;
    }

    await ensurePayPalGuestCardBillingSelectField(
        checkoutTarget,
        PAYPAL_GUEST_CARD_BILLING_SELECTORS.country,
        PAYPAL_GUEST_CARD_BILLING_ADDRESS.country,
    );

    await expect
        .poll(async () => {
            const stateField = payPalGuestCardBillingSection(checkoutTarget)
                .locator(PAYPAL_GUEST_CARD_BILLING_SELECTORS.state)
                .first();

            return stateField.isVisible().catch(() => false);
        }, { timeout: 10_000 })
        .toBe(true)
        .catch(() => null);

    for (const [selector, value] of [
        [
            PAYPAL_GUEST_CARD_BILLING_SELECTORS.givenName,
            PAYPAL_GUEST_CARD_BILLING_ADDRESS.givenName,
        ],
        [
            PAYPAL_GUEST_CARD_BILLING_SELECTORS.familyName,
            PAYPAL_GUEST_CARD_BILLING_ADDRESS.familyName,
        ],
        [
            PAYPAL_GUEST_CARD_BILLING_SELECTORS.line1,
            PAYPAL_GUEST_CARD_BILLING_ADDRESS.line1,
        ],
        [
            PAYPAL_GUEST_CARD_BILLING_SELECTORS.city,
            PAYPAL_GUEST_CARD_BILLING_ADDRESS.city,
        ],
        [
            PAYPAL_GUEST_CARD_BILLING_SELECTORS.postcode,
            PAYPAL_GUEST_CARD_BILLING_ADDRESS.postcode,
        ],
    ] as const) {
        await ensurePayPalGuestCardBillingTextField(
            checkoutTarget,
            selector,
            value,
        );
    }

    await ensurePayPalGuestCardBillingSelectField(
        checkoutTarget,
        PAYPAL_GUEST_CARD_BILLING_SELECTORS.state,
        PAYPAL_GUEST_CARD_BILLING_ADDRESS.state,
    );
}

async function clickPayPalGuestCardPayButton(
    checkoutTarget: PayPalCheckoutTarget,
): Promise<void> {
    const submitButton = checkoutTarget
        .locator(PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.submit)
        .or(checkoutTarget.getByRole('button', { name: /^pay\b/i }))
        .first();

    await expect(submitButton).toBeVisible({ timeout: 15_000 });
    await submitButton.scrollIntoViewIfNeeded().catch(() => null);
    await submitButton.click({ timeout: 15_000 });
}

async function fillPayPalGuestCardCheckout(
    checkoutTarget: PayPalCheckoutTarget,
    credentials: PayPalSandboxBuyerCredentials,
): Promise<void> {
    const emailField = checkoutTarget
        .locator(PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.email)
        .first();

    if (await emailField.isVisible().catch(() => false)) {
        const currentEmail = (await emailField.inputValue()).trim();

        if (credentials.email && currentEmail !== credentials.email) {
            await fillPayPalHostedFloatingLabelInput(
                checkoutTarget,
                PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.email,
                credentials.email,
            );
        }
    }

    await fillPayPalHostedFloatingLabelInput(
        checkoutTarget,
        PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.cardNumber,
        PAYPAL_SANDBOX_TEST_CARD.number,
    );
    await fillPayPalHostedFloatingLabelInput(
        checkoutTarget,
        PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.expiry,
        payPalGuestCardExpiryDigits(),
    );
    await fillPayPalHostedFloatingLabelInput(
        checkoutTarget,
        PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.security,
        PAYPAL_SANDBOX_TEST_CARD.cvv,
    );

    const phoneField = checkoutTarget
        .locator(PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.phone)
        .first();

    if (await phoneField.isVisible().catch(() => false)) {
        const currentPhone = (await phoneField.inputValue()).trim();

        if (!currentPhone) {
            await fillPayPalHostedFloatingLabelInput(
                checkoutTarget,
                PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.phone,
                PAYPAL_SANDBOX_GUEST_PHONE,
            );
        }
    }

    await fillPayPalGuestCardBillingAddressIfPresent(checkoutTarget);

    await clickPayPalGuestCardPayButton(checkoutTarget);
}

async function waitForPayPalGuestCardCheckoutProcessing(
    merchantPage: Page,
): Promise<void> {
    await expect
        .poll(async () => {
            await completePayPalAdvancedCard3dsIfPresent(merchantPage);

            if (isPayPalPaymentConfirmationUrl(merchantPage.url())) {
                return true;
            }

            const loader = merchantPage.locator('#is_working:not(.hidden)');

            if (await loader.isVisible().catch(() => false)) {
                return true;
            }

            const checkoutTarget =
                await findPayPalGuestCardCheckoutTarget(merchantPage);

            if (!checkoutTarget) {
                return true;
            }

            const cardField = checkoutTarget
                .locator(PAYPAL_GUEST_CARD_CHECKOUT_SELECTORS.cardNumber)
                .first();

            return !(await cardField.isVisible().catch(() => false));
        }, { timeout: 120_000 })
        .toBe(true);
}

async function completePayPalLegacyCardGuestCheckout(
    merchantPage: Page,
    credentials: PayPalSandboxBuyerCredentials,
): Promise<void> {
    const checkoutTarget =
        await resolvePayPalGuestCardCheckoutTarget(merchantPage);

    await fillPayPalGuestCardCheckout(checkoutTarget, credentials);
    await waitForPayPalGuestCardCheckoutProcessing(merchantPage);
}

async function replacePayPalLoginEmail(
    page: Page,
    email: string,
): Promise<void> {
    const emailField = page.locator('#email, input[name="login_email"]').first();

    await expect(emailField).toBeVisible({ timeout: 60_000 });
    await emailField.click({ force: true });
    await emailField.fill('');

    await expect
        .poll(async () => (await emailField.inputValue()).length === 0, {
            timeout: 5_000,
        })
        .toBe(true)
        .catch(async () => {
            await emailField.press('ControlOrMeta+a');
            await emailField.press('Backspace');
        });

    await emailField.fill(email);

    await expect
        .poll(async () => (await emailField.inputValue()).trim(), {
            timeout: 10_000,
        })
        .toBe(email)
        .catch(async () => {
            await emailField.evaluate((element, nextEmail) => {
                const input = element as HTMLInputElement;
                input.value = nextEmail;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
            }, email);

            await expect(emailField).toHaveValue(email, { timeout: 5_000 });
        });

    const nextButton = page.locator('#btnNext, button:has-text("Next")').first();

    await expect(nextButton).toBeVisible({ timeout: 15_000 });
    await nextButton.click({ force: true });
}

async function resolvePayPalPasswordPage(merchantPage: Page): Promise<Page> {
    const candidates = merchantPage
        .context()
        .pages()
        .filter((page) => !page.isClosed())
        .sort((left, right) => {
            const leftScore = isPayPalHostedUrl(left.url())
                ? 0
                : left.url().includes('/client/')
                  ? 2
                  : 1;
            const rightScore = isPayPalHostedUrl(right.url())
                ? 0
                : right.url().includes('/client/')
                  ? 2
                  : 1;

            return leftScore - rightScore;
        });

    await expect
        .poll(async () => {
            for (const page of candidates) {
                const passwordField = page
                    .locator('#password, input[name="login_password"]')
                    .first();

                if (await passwordField.isVisible().catch(() => false)) {
                    return page;
                }
            }

            return null;
        }, { timeout: 60_000 })
        .not.toBeNull();

    for (const page of candidates) {
        const passwordField = page
            .locator('#password, input[name="login_password"]')
            .first();

        if (await passwordField.isVisible().catch(() => false)) {
            await page.bringToFront().catch(() => null);

            return page;
        }
    }

    throw new Error('PayPal sandbox login page with #password was not found');
}

async function replacePayPalLoginPassword(
    page: Page,
    password: string,
): Promise<void> {
    const passwordField = page
        .locator('#password, input[name="login_password"]')
        .first();

    await expect(passwordField).toBeVisible({ timeout: 60_000 });
    await passwordField.click({ force: true });
    await passwordField.fill('');

    try {
        await passwordField.pressSequentially(password, { delay: 40 });
    } catch {
        await passwordField.fill(password);
    }

    await passwordField.evaluate((element, nextPassword) => {
        const input = element as HTMLInputElement;
        input.value = nextPassword;
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }, password);

    const loginButton = page
        .locator('#btnLogin')
        .or(page.getByRole('button', { name: /^log in$/i }))
        .first();

    await expect(loginButton).toBeVisible({ timeout: 15_000 });
    await expect(loginButton).toBeEnabled({ timeout: 15_000 });
    await loginButton.click({ force: true });

    await expect
        .poll(async () => {
            if (!(await passwordField.isVisible().catch(() => false))) {
                return true;
            }

            const reviewButton = page
                .locator(
                    '#confirmButtonTop, #payment-submit-btn, button:has-text("Pay Now")',
                )
                .first();

            return await reviewButton.isVisible().catch(() => false);
        }, { timeout: 60_000 })
        .toBe(true);
}

async function fillPayPalSandboxPassword(
    merchantPage: Page,
    password: string,
): Promise<void> {
    const loginPage = await resolvePayPalPasswordPage(merchantPage);

    await replacePayPalLoginPassword(loginPage, password);
}

async function fillPayPalSandboxLogin(
    merchantPage: Page,
    credentials: PayPalSandboxBuyerCredentials,
): Promise<void> {
    const loginPage = await resolvePayPalLoginPage(merchantPage);

    await replacePayPalLoginEmail(loginPage, credentials.email);
    await fillPayPalSandboxPassword(merchantPage, credentials.password);
}

async function clickPayPalActionButton(
    targets: PayPalCheckoutTarget[],
    pattern: RegExp,
): Promise<boolean> {
    for (const target of targets) {
        const button = target.getByRole('button', { name: pattern }).first();

        if (await button.isVisible({ timeout: 500 }).catch(() => false)) {
            await button.click({ timeout: 15_000, force: true });

            return true;
        }
    }

    return false;
}

async function isPayPalPayInFourConfirmInfoVisible(
    checkoutRoot: Page,
): Promise<boolean> {
    for (const page of checkoutRoot
        .context()
        .pages()
        .filter((candidate) => !candidate.isClosed())) {
        for (const target of payPalCheckoutTargets(page)) {
            const confirmButton = target
                .locator('#confirmInfoContinue, [data-testid="confirmInfoContinue"]')
                .or(target.getByRole('button', { name: /agree and apply/i }))
                .first();

            if (await confirmButton.isVisible({ timeout: 250 }).catch(() => false)) {
                return true;
            }
        }
    }

    return false;
}

async function confirmPayPalPayInFourInfoIfPresent(
    checkoutRoot: Page,
): Promise<boolean> {
    for (const page of checkoutRoot
        .context()
        .pages()
        .filter((candidate) => !candidate.isClosed())) {
        await page.bringToFront().catch(() => null);

        for (const target of payPalCheckoutTargets(page)) {
            const confirmButton = target
                .locator('#confirmInfoContinue, [data-testid="confirmInfoContinue"]')
                .or(target.getByRole('button', { name: /agree and apply/i }))
                .first();

            if (await confirmButton.isVisible({ timeout: 500 }).catch(() => false)) {
                await confirmButton.click({ timeout: 15_000, force: true });

                return true;
            }
        }
    }

    return false;
}

async function findPayPalPayInFourAutopayTarget(
    checkoutRoot: Page,
): Promise<{ page: Page; target: PayPalCheckoutTarget } | null> {
    for (const page of checkoutRoot
        .context()
        .pages()
        .filter((candidate) => !candidate.isClosed())) {
        for (const target of payPalCheckoutTargets(page)) {
            const autopaySection = target
                .locator(
                    '#autopay, [data-testid="autopayHeading"], [data-testid="autopaySelectionCardGroup"]',
                )
                .first();
            const disclosureCheckbox = target
                .locator(
                    '#payLaterApplicationAutopayDisclosureContent, [data-testid="payLaterApplicationAutopayDisclosureContent"], input[name="payLaterApplicationAutopayDisclosureContent"]',
                )
                .first();

            if (
                (await autopaySection.isVisible({ timeout: 250 }).catch(() => false)) ||
                (await disclosureCheckbox
                    .isVisible({ timeout: 250 })
                    .catch(() => false))
            ) {
                return { page, target };
            }
        }
    }

    return null;
}

async function isPayPalPayInFourAutopayVisible(
    checkoutRoot: Page,
): Promise<boolean> {
    return (await findPayPalPayInFourAutopayTarget(checkoutRoot)) !== null;
}

async function ensurePayPalPayInFourAutopaySelection(
    checkoutRoot: Page,
): Promise<boolean> {
    const match = await findPayPalPayInFourAutopayTarget(checkoutRoot);

    if (!match) {
        return false;
    }

    await match.page.bringToFront().catch(() => null);

    const checkedRadio = match.target
        .locator(
            'input[name="AutopaySelectionInput"][checked]:not([type="hidden"])',
        )
        .first();

    if ((await checkedRadio.count()) > 0) {
        return true;
    }

    const enabledRadio = match.target
        .locator(
            'input[name="AutopaySelectionInput"]:not([disabled]):not([type="hidden"])',
        )
        .first();

    if ((await enabledRadio.count()) === 0) {
        return false;
    }

    const radioId = await enabledRadio.getAttribute('id');

    if (radioId) {
        await match.target.locator(`label[for="${radioId}"]`).click({
            force: true,
        });

        return true;
    }

    await enabledRadio.click({ force: true });

    return true;
}

async function ensurePayPalPayInFourAutopayDisclosureChecked(
    checkoutRoot: Page,
): Promise<boolean> {
    const match = await findPayPalPayInFourAutopayTarget(checkoutRoot);

    if (!match) {
        return false;
    }

    await match.page.bringToFront().catch(() => null);

    const checkbox = match.target
        .locator(
            '#payLaterApplicationAutopayDisclosureContent, [data-testid="payLaterApplicationAutopayDisclosureContent"], input[name="payLaterApplicationAutopayDisclosureContent"]',
        )
        .first();

    if (!(await checkbox.isVisible({ timeout: 500 }).catch(() => false))) {
        return true;
    }

    if (await checkbox.isChecked().catch(() => false)) {
        return true;
    }

    const checkboxId = await checkbox.getAttribute('id');

    if (checkboxId) {
        const label = match.target.locator(`label[for="${checkboxId}"]`).first();

        if (await label.isVisible().catch(() => false)) {
            await label.click({ force: true });
        } else {
            await checkbox.click({ force: true });
        }
    } else {
        await checkbox.click({ force: true });
    }

    await expect
        .poll(async () => checkbox.isChecked().catch(() => false), {
            timeout: 5_000,
        })
        .toBe(true)
        .catch(() => null);

    return true;
}

async function preparePayPalPayInFourAutopayStep(
    checkoutRoot: Page,
): Promise<void> {
    await ensurePayPalPayInFourAutopaySelection(checkoutRoot);
    await ensurePayPalPayInFourAutopayDisclosureChecked(checkoutRoot);
}

async function clickPayPalPayInFourAutopayContinue(
    checkoutRoot: Page,
): Promise<boolean> {
    const match = await findPayPalPayInFourAutopayTarget(checkoutRoot);

    if (!match) {
        return false;
    }

    await match.page.bringToFront().catch(() => null);

    const continuePatterns = [
        /continue to review/i,
        /^continue$/i,
        /^next$/i,
        /confirm/i,
    ];

    for (const pattern of continuePatterns) {
        const button = match.target.getByRole('button', { name: pattern }).first();

        if (!(await button.isVisible({ timeout: 500 }).catch(() => false))) {
            continue;
        }

        if (await button.isDisabled().catch(() => false)) {
            continue;
        }

        await button.click({ timeout: 15_000, force: true });

        return true;
    }

    for (const selector of [
        'button[type="submit"]',
        '#confirmButtonTop',
        '#payment-submit-btn',
        '[data-testid="submit-button"]',
    ]) {
        const button = match.target.locator(selector).first();

        if (!(await button.isVisible({ timeout: 250 }).catch(() => false))) {
            continue;
        }

        if (await button.isDisabled().catch(() => false)) {
            continue;
        }

        await button.click({ timeout: 15_000, force: true });

        return true;
    }

    return false;
}

async function selectPayPalPayInFourOffer(
    checkoutRoot: Page,
): Promise<boolean> {
    const candidates = checkoutRoot
        .context()
        .pages()
        .filter((page) => !page.isClosed());

    for (const page of candidates) {
        await page.bringToFront().catch(() => null);

        for (const target of payPalCheckoutTargets(page)) {
            const payLaterSection = target.locator('[data-testid="pay-later"]');

            if (!(await payLaterSection.isVisible({ timeout: 500 }).catch(() => false))) {
                continue;
            }

            const payInFourRadio = payLaterSection.getByRole('radio', {
                name: /Pay in 4/i,
            });

            if ((await payInFourRadio.count()) > 0) {
                await payInFourRadio.first().click({ force: true });

                return true;
            }

            const payInFourName = payLaterSection
                .locator('[data-testid="c3-fi-details-name"]', {
                    hasText: /^Pay in 4$/i,
                })
                .first();

            if (await payInFourName.isVisible().catch(() => false)) {
                const radioId = await payLaterSection
                    .locator(
                        'input[name="PayLaterRadioGroup"]:not([disabled])',
                    )
                    .first()
                    .getAttribute('id');

                if (radioId) {
                    await target.locator(`label[for="${radioId}"]`).click({
                        force: true,
                    });

                    return true;
                }

                await payInFourName.click({ force: true });

                return true;
            }

            const enabledOffer = payLaterSection
                .locator('input[data-testid^="credit-offer-"]:not([disabled])')
                .first();

            if (await enabledOffer.isVisible().catch(() => false)) {
                const offerId = await enabledOffer.getAttribute('id');

                if (offerId) {
                    await target.locator(`label[for="${offerId}"]`).click({
                        force: true,
                    });

                    return true;
                }

                await enabledOffer.click({ force: true });

                return true;
            }
        }
    }

    return false;
}

async function completePayPalSandboxApproval(
    checkoutRoot: Page,
    method?: PayPalRestPaymentMethod,
): Promise<void> {
    if (method?.fundingSource === 'paylater') {
        await expect
            .poll(async () => selectPayPalPayInFourOffer(checkoutRoot), {
                timeout: 90_000,
            })
            .toBe(true);

        // Pay in 4 may show an optional payer confirmation step before review.
        await expect
            .poll(async () => {
                if (await confirmPayPalPayInFourInfoIfPresent(checkoutRoot)) {
                    return true;
                }

                return !(await isPayPalPayInFourConfirmInfoVisible(checkoutRoot));
            }, { timeout: 45_000 })
            .toBe(true);

        // Pay in 4 may require choosing an autopay funding instrument.
        await expect
            .poll(async () => {
                if (!(await isPayPalPayInFourAutopayVisible(checkoutRoot))) {
                    return true;
                }

                await preparePayPalPayInFourAutopayStep(checkoutRoot);

                if (await clickPayPalPayInFourAutopayContinue(checkoutRoot)) {
                    await checkoutRoot.waitForTimeout(750);
                }

                return !(await isPayPalPayInFourAutopayVisible(checkoutRoot));
            }, { timeout: 45_000 })
            .toBe(true);
    }

    const actionPatterns = [
        /continue to review/i,
        /^continue$/i,
        /^next$/i,
        /pay now/i,
        /complete purchase/i,
        /agree and apply/i,
        /agree.?&.?pay/i,
        /agree and pay/i,
        /approve/i,
        /submit/i,
    ];

    const checkoutPages = (): Page[] =>
        checkoutRoot
            .context()
            .pages()
            .filter((candidate) => !candidate.isClosed());

    for (let attempt = 0; attempt < 10; attempt += 1) {
        const openPages = checkoutPages().filter(
            (candidate) =>
                candidate !== checkoutRoot || isPayPalHostedUrl(candidate.url()),
        );

        if (openPages.length === 0) {
            return;
        }

        let clicked = false;

        for (const checkoutPage of openPages) {
            if (method?.fundingSource === 'paylater') {
                if (await isPayPalPayInFourAutopayVisible(checkoutRoot)) {
                    await preparePayPalPayInFourAutopayStep(checkoutRoot);

                    if (await clickPayPalPayInFourAutopayContinue(checkoutRoot)) {
                        clicked = true;
                        break;
                    }
                }
            }

            for (const pattern of actionPatterns) {
                if (
                    await clickPayPalActionButton(
                        payPalCheckoutTargets(checkoutPage),
                        pattern,
                    )
                ) {
                    clicked = true;
                    break;
                }
            }

            if (clicked) {
                break;
            }

            for (const target of payPalCheckoutTargets(checkoutPage)) {
                for (const selector of [
                    '#confirmInfoContinue',
                    '[data-testid="confirmInfoContinue"]',
                    '#confirmButtonTop',
                    '#payment-submit-btn',
                    'button[data-testid="submit-button-initial"]',
                    'button[data-testid="submit-button"]',
                    '[data-testid="payment-submit-btn"]',
                ]) {
                    const button = target.locator(selector).first();

                    if (await button.isVisible({ timeout: 250 }).catch(() => false)) {
                        await button.click({ timeout: 15_000, force: true });
                        clicked = true;
                        break;
                    }
                }

                if (clicked) {
                    break;
                }
            }
        }

        const remainingPages = checkoutPages().filter(
            (candidate) =>
                candidate !== checkoutRoot || isPayPalHostedUrl(candidate.url()),
        );

        if (remainingPages.every((candidate) => candidate.isClosed())) {
            return;
        }

        if (!clicked) {
            await checkoutRoot.waitForTimeout(1_000);
        } else {
            await checkoutRoot.waitForTimeout(1_500);
        }
    }
}

export async function waitForPayPalMerchantPaymentComplete(
    page: Page,
): Promise<void> {
    await page.bringToFront().catch(() => null);

    const loader = page.locator('#is_working');

    if (await loader.isVisible().catch(() => false)) {
        await expect(loader).toBeHidden({ timeout: 180_000 });
    }

    await page.waitForURL(
        (url) => isPayPalPaymentConfirmationUrl(url),
        { timeout: 180_000 },
    );

    await expect(
        page.getByRole('heading', { name: /payment/i }).first(),
    ).toBeVisible({ timeout: 30_000 });
}

async function ensurePayPalCheckoutIsUnblocked(page: Page): Promise<void> {
    await clearPortalOverlays(page);
    await completeRequiredClientInfoForm(page);

    await expect
        .poll(() => isRequiredClientInfoBlockingCheckout(page), {
            timeout: 45_000,
        })
        .toBe(false);
}

async function completePayPalButtonsSandboxPayment(
    page: Page,
    method: PayPalRestPaymentMethod,
    credentials: PayPalSandboxBuyerCredentials,
): Promise<void> {
    if (!method.fundingSource) {
        throw new Error(
            `PayPal method ${method.label} does not use button checkout`,
        );
    }

    await ensurePayPalCheckoutIsUnblocked(page);

    const paymentResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            isMerchantPaymentResponseUrl(response.url()) &&
            response.ok(),
        { timeout: 180_000 },
    );

    await clickPayPalFundingButton(page, method.fundingSource);

    if (usesPayPalGuestCardCheckout(method)) {
        await completePayPalLegacyCardGuestCheckout(page, credentials);
    } else {
        await ensurePayPalCheckoutPopup(page);
        await fillPayPalSandboxLogin(page, credentials);
        await completePayPalSandboxApproval(page, method);
    }

    for (const candidate of page.context().pages()) {
        if (candidate !== page && !candidate.isClosed()) {
            await candidate
                .waitForEvent('close', { timeout: 120_000 })
                .catch(() => null);
        }
    }

    await page.bringToFront();

    await Promise.race([
        paymentResponsePromise,
        waitForPayPalMerchantPaymentComplete(page),
    ]).catch(async () => {
        await waitForPayPalMerchantPaymentComplete(page);
    });
}

async function fillPayPalAdvancedCardFieldsWithNumber(
    page: Page,
    cardNumber: string,
): Promise<void> {
    await ensurePayPalAdvancedCardFormReady(page);
    await clearPortalOverlays(page);

    await fillPayPalHostedCardInput(page, 'number', cardNumber);
    await fillPayPalHostedCardInput(
        page,
        'expiry',
        PAYPAL_SANDBOX_TEST_CARD.expiry,
    );
    await fillPayPalHostedCardInput(page, 'cvv', PAYPAL_SANDBOX_TEST_CARD.cvv);
}

async function clickPayPalAdvancedCardPayNow(page: Page): Promise<void> {
    const payNow = page.locator('#pay-now');

    await payNow.scrollIntoViewIfNeeded();
    await expect(payNow).toBeVisible({ timeout: 15_000 });

    await expect
        .poll(async () => !(await payNow.isDisabled().catch(() => true)), {
            timeout: 30_000,
        })
        .toBe(true)
        .catch(() => null);

    await payNow.click({ timeout: 15_000, force: true });
}

export async function submitPayPalAdvancedCardWithInvalidNumber(
    page: Page,
    cardNumber: string = PAYPAL_INVALID_SANDBOX_CARD_NUMBER,
): Promise<void> {
    await fillPayPalAdvancedCardFieldsWithNumber(page, cardNumber);

    const confirmSourceResponse = page.waitForResponse(
        (response) =>
            response.url().includes('/confirm-payment-source') &&
            response.status() === 422,
        { timeout: 60_000 },
    );

    await clickPayPalAdvancedCardPayNow(page);
    await confirmSourceResponse.catch(() => null);
}

export async function assertPayPalAdvancedCardValidationError(
    page: Page,
    messagePattern: RegExp = /invalid card number|credit card number is not valid/i,
): Promise<void> {
    const errors = payPalAdvancedCardErrorsLocator(page);

    await expect(errors).toBeVisible({ timeout: 30_000 });

    await expect
        .poll(async () => {
            const text = ((await errors.textContent()) ?? '').trim();

            if (!text || /^UNPROCESSABLE_ENTITY$/i.test(text)) {
                return null;
            }

            return text;
        }, { timeout: 30_000 })
        .toMatch(messagePattern);

    await expect(page.locator('#pay-now')).toBeEnabled({ timeout: 15_000 });
    expect(isPayPalPaymentConfirmationUrl(page.url())).toBe(false);
}

export async function completePayPalAdvancedCardSandboxPayment(
    page: Page,
): Promise<void> {
    await fillPayPalAdvancedCardFields(page);
}

async function fillPayPalAdvancedCardFields(page: Page): Promise<void> {
    await fillPayPalAdvancedCardFieldsWithNumber(
        page,
        PAYPAL_SANDBOX_TEST_CARD.number,
    );
    await assertPayPalAdvancedCardCheckoutHasNoErrors(page);

    const paymentResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            isMerchantPaymentResponseUrl(response.url()),
        { timeout: 180_000 },
    );

    await clickPayPalAdvancedCardPayNow(page);

    const paymentResponse = await paymentResponsePromise.catch(() => null);

    if (paymentResponse) {
        await followPayPalAdvancedCardPaymentRedirect(page, paymentResponse);
    }

    await waitForPayPalAdvancedCardPaymentResult(page);
    await waitForPayPalMerchantPaymentComplete(page);
}

export async function assertPayPalMethodCheckoutReady(
    page: Page,
    method: PayPalRestPaymentMethod,
): Promise<void> {
    await ensurePayPalCheckoutIsUnblocked(page);
    await assertPayPalMethodCheckoutReadyWhenUnblocked(page, method);
}

export async function assertPayPalMethodCheckoutReadyWhenUnblocked(
    page: Page,
    method: PayPalRestPaymentMethod,
): Promise<void> {
    await clearPortalOverlays(page);

    if (await isRequiredClientInfoBlockingCheckout(page)) {
        throw new Error(
            `${method.label} checkout is blocked by required payment details`,
        );
    }

    if (method.checkoutKind === 'advanced-cards') {
        await assertPayPalAdvancedCardCheckoutReadyWhenUnblocked(page);

        await expect(page.locator('#card-number-field-container')).toBeVisible({
            timeout: 30_000,
        });

        return;
    }

    await expect(
        page
            .locator('#paypal-payment')
            .or(page.locator('#paypal-button-container'))
            .first(),
    ).toBeAttached({ timeout: 30_000 });

    if (!method.fundingSource) {
        return;
    }

    try {
        await waitForPayPalFundingButton(page, method.fundingSource);
    } catch (error) {
        throw new Error(
            `${method.label} checkout loaded but the "${method.fundingSource}" button is unavailable in this sandbox — ${error instanceof Error ? error.message : String(error)}`,
        );
    }
}

export async function completePayPalSandboxPayment(
    page: Page,
    method: PayPalRestPaymentMethod,
    credentials: PayPalSandboxBuyerCredentials,
): Promise<void> {
    if (method.checkoutKind === 'advanced-cards') {
        await fillPayPalAdvancedCardFields(page);

        return;
    }

    await completePayPalButtonsSandboxPayment(page, method, credentials);
}
