import { expect, type Frame, type Page } from '@playwright/test';
import { dismissCookieConsent, clearPortalOverlays } from '../client-portal-helpers';
import {
    completeRequiredClientInfoForm,
    isRequiredClientInfoBlockingCheckout,
} from './payment-flow-helpers';
import { type PayPalRestKeys } from './paypal-env';
import { type PayPalRestPaymentMethod } from './paypal-payment-methods';

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

const PAYPAL_SANDBOX_TEST_CARD = {
    number: '4032039850364823',
    expiry: '12/30',
    cvv: '123',
};

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

async function fillPayPalHostedCardInput(
    page: Page,
    field: keyof typeof PAYPAL_ADVANCED_CARD_FIELD_TARGETS,
    value: string,
): Promise<void> {
    const target = PAYPAL_ADVANCED_CARD_FIELD_TARGETS[field];

    await expect(page.locator(target.container)).toBeVisible({
        timeout: 45_000,
    });

    for (const selector of target.selectors) {
        const input = page.locator(`${target.container} ${selector}`).first();

        if ((await input.count()) > 0) {
            await input.click({ timeout: 5_000, force: true });
            await input.fill(value, { force: true }).catch(async () => {
                await input.pressSequentially(value, { delay: 40 });
            });

            return;
        }
    }

    const iframeLocators = page.locator(`${target.container} iframe`);
    const iframeCount = await iframeLocators.count();

    for (let index = 0; index < iframeCount; index += 1) {
        const frame = page
            .frameLocator(`${target.container} iframe`)
            .nth(index);

        for (const selector of target.selectors) {
            const input = frame.locator(selector).first();

            if ((await input.count()) > 0) {
                await input.click({ timeout: 5_000, force: true });
                await input.fill(value, { force: true }).catch(async () => {
                    await input.pressSequentially(value, { delay: 40 });
                });

                return;
            }
        }
    }

    for (const frame of page.frames()) {
        for (const selector of target.selectors) {
            const input = frame.locator(selector).first();

            if ((await input.count()) === 0) {
                continue;
            }

            try {
                await input.click({ timeout: 5_000, force: true });
                await input.fill(value, { force: true }).catch(async () => {
                    await input.pressSequentially(value, { delay: 40 });
                });

                return;
            } catch {
                continue;
            }
        }
    }

    const pageIframeCount = await page.locator('iframe').count();

    for (let index = 0; index < pageIframeCount; index += 1) {
        const frame = page.frameLocator('iframe').nth(index);

        for (const selector of target.selectors) {
            const input = frame.locator(selector).first();

            if ((await input.count()) === 0) {
                continue;
            }

            try {
                await input.click({ timeout: 5_000, force: true });
                await input.fill(value, { force: true }).catch(async () => {
                    await input.pressSequentially(value, { delay: 40 });
                });

                return;
            } catch {
                continue;
            }
        }
    }

    throw new Error(
        `PayPal advanced card field "${field}" was not found in ${target.container}`,
    );
}

async function waitForPayPalAdvancedCardFields(page: Page): Promise<void> {
    await expect
        .poll(async () => {
            for (const selector of PAYPAL_ADVANCED_CARD_FIELD_TARGETS.number
                .selectors) {
                const input = page
                    .locator(
                        `${PAYPAL_ADVANCED_CARD_FIELD_TARGETS.number.container} ${selector}`,
                    )
                    .first();

                if ((await input.count()) > 0) {
                    return true;
                }
            }

            for (const frame of page.frames()) {
                if (
                    (await frame
                        .locator(
                            'input.card-field-number, input[name="number"][autocomplete="cc-number"]',
                        )
                        .count()) > 0
                ) {
                    return true;
                }
            }

            for (const selector of [
                '#card-number-field-container iframe',
                '#card-expiry-field-container iframe',
                '#card-cvv-field-container iframe',
            ]) {
                if ((await page.locator(selector).count()) > 0) {
                    return true;
                }
            }

            return false;
        }, { timeout: 90_000 })
        .toBe(true);
}

async function ensurePayPalAdvancedCardFormReady(page: Page): Promise<void> {
    await ensurePayPalCheckoutIsUnblocked(page);

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
    ];

    for (const frame of frameLocators) {
        for (const selector of [
            `[data-funding-source="${fundingSource}"]`,
            `[data-funding-source="${fundingSource.replace(/_/g, '-')}"]`,
            '[aria-label="PayPal"]',
            '[aria-label="Pay with PayPal"]',
            '[role="button"]',
        ]) {
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
        for (const selector of [
            `[data-funding-source="${fundingSource}"]`,
            `[data-funding-source="${fundingSource.replace(/_/g, '-')}"]`,
            '[aria-label="PayPal"]',
            '[aria-label="Pay with PayPal"]',
            '[role="button"]',
        ]) {
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

function isPayPalHostedUrl(url: string): boolean {
    return /sandbox\.paypal\.com|paypal\.com/.test(url);
}

function isMerchantPaymentResponseUrl(url: string): boolean {
    return (
        url.includes('/client/payments/process/response') ||
        url.includes('/client/payments/response') ||
        url.includes('/payment_response')
    );
}

async function ensurePayPalCheckoutPopup(page: Page): Promise<void> {
    let continueAttempted = false;

    await expect
        .poll(async () => {
            for (const candidate of page.context().pages()) {
                if (candidate.isClosed()) {
                    continue;
                }

                const emailField = candidate
                    .locator('#email, input[name="login_email"]')
                    .first();

                if (await emailField.isVisible().catch(() => false)) {
                    return true;
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

async function completePayPalSandboxApproval(checkoutRoot: Page): Promise<void> {
    const actionPatterns = [
        /continue to review/i,
        /^continue$/i,
        /^next$/i,
        /pay now/i,
        /complete purchase/i,
        /agree.?&.?pay/i,
        /agree and pay/i,
        /approve/i,
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
                    '#confirmButtonTop',
                    '#payment-submit-btn',
                    'button[data-testid="submit-button-initial"]',
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

    await expect(page).toHaveURL(/\/client\/payments\/[^/?#]+/, {
        timeout: 180_000,
    });
    await expect(page.locator('main')).toBeVisible({ timeout: 30_000 });
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

    await ensurePayPalCheckoutPopup(page);
    await fillPayPalSandboxLogin(page, credentials);
    await completePayPalSandboxApproval(page);

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

async function fillPayPalAdvancedCardFields(page: Page): Promise<void> {
    await ensurePayPalAdvancedCardFormReady(page);

    await fillPayPalHostedCardInput(
        page,
        'number',
        PAYPAL_SANDBOX_TEST_CARD.number,
    );
    await fillPayPalHostedCardInput(
        page,
        'expiry',
        PAYPAL_SANDBOX_TEST_CARD.expiry,
    );
    await fillPayPalHostedCardInput(page, 'cvv', PAYPAL_SANDBOX_TEST_CARD.cvv);

    const paymentResponsePromise = page.waitForResponse(
        (response) =>
            response.request().method() === 'POST' &&
            isMerchantPaymentResponseUrl(response.url()) &&
            response.ok(),
        { timeout: 180_000 },
    );

    const payNow = page.locator('#pay-now');
    await payNow.scrollIntoViewIfNeeded();
    await payNow.click({ timeout: 15_000, force: true });

    await Promise.race([
        paymentResponsePromise,
        waitForPayPalMerchantPaymentComplete(page),
    ]).catch(async () => {
        await waitForPayPalMerchantPaymentComplete(page);
    });
}

export async function assertPayPalMethodCheckoutReady(
    page: Page,
    method: PayPalRestPaymentMethod,
): Promise<void> {
    await ensurePayPalCheckoutIsUnblocked(page);

    if (await isRequiredClientInfoBlockingCheckout(page)) {
        throw new Error(
            `${method.label} checkout is blocked by required payment details`,
        );
    }

    if (method.checkoutKind === 'advanced-cards') {
        await ensurePayPalAdvancedCardFormReady(page);

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
