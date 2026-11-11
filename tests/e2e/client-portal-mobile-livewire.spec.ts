import { type Locator, type Page } from '@playwright/test';
import { expect, test, uniqueName } from './fixtures';
import { createAndLogInClient, waitForAlpine } from './client-portal-helpers';
import { createSentInvoice, createSentQuote } from './portal-entity-helpers';
import { updateClient } from './api-helpers';
import {
    assertRequiredClientInfoBlocksCheckout,
    defaultClientAddress,
    navigateToGatewayCheckoutWithoutRequiredClientInfo,
    paymentTestSettings,
    prepareIncompleteClientPaymentContext,
    requiredClientInfoForm,
} from './gateways/payment-flow-helpers';
import { StripePaymentGateway } from './gateways/stripe-payment-gateway';
import { GatewayType } from './gateways/types';
import { disableNativeDialogSupport } from './client-portal-mobile-compatibility';

// These tests use the existing seeded account lanes and real Livewire HTTP updates.
// They never submit a charge or a final quote approval.
test.use({ viewport: { width: 390, height: 844 }, hasTouch: true });
test.describe.configure({ timeout: 120_000 });
test.beforeEach(async ({ page }) => {
    // The local development toolbar is not part of the customer portal.
    await page.addInitScript(() => {
        document.addEventListener('DOMContentLoaded', () => {
            const style = document.createElement('style');
            style.textContent = '.phpdebugbar { display: none !important; }';
            document.head.append(style);
        });
    });
    if (process.env.PLAYWRIGHT_LEGACY_DIALOGS) await disableNativeDialogSupport(page);
});

async function expectReachable(control: Locator) {
    await expect(control).toBeVisible();
    await control.scrollIntoViewIfNeeded();
    await expect.poll(() => control.evaluate(element => {
        const rect = element.getBoundingClientRect();
        const hit = document.elementFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2);
        return rect.x >= 0 && rect.right <= innerWidth && rect.y >= 0 && rect.bottom <= innerHeight
            && !!hit && (hit === element || element.contains(hit));
    })).toBe(true);
}

async function tap(control: Locator) {
    await expectReachable(control);
    await expect(control).toBeEnabled();
    await control.tap();
}

async function touchSignature(page: Page) {
    const canvas = page.locator('#signature-pad');
    await expectReachable(canvas);
    const rect = (await canvas.boundingBox())!;
    // Real touch input, rather than mouse events or injecting signature data.
    await page.touchscreen.tap(rect.x + 30, rect.y + 40);
    await page.touchscreen.tap(rect.x + 70, rect.y + 60);
}

async function expectNoOverflow(page: Page) {
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    expect(await page.locator('main').evaluate(el => el.scrollWidth <= el.clientWidth)).toBe(true);
}

test('Livewire terms mount a usable signature step, which advances after touch signing', async ({ api, page }) => {
    let client = await createAndLogInClient(api, page, {
        settings: { ...paymentTestSettings, payment_flow: 'smooth', show_accept_invoice_terms: true, require_invoice_signature: true },
    });
    client = await updateClient(api.context, client, defaultClientAddress);
    const invoice = await createSentInvoice(api, client, {
        label: uniqueName('mobile-livewire'),
        terms: 'Mobile terms. '.repeat(100),
    });
    await page.goto(`/client/invoices/${invoice.id}`);
    await expect(page.locator('#accept-terms-button')).toBeVisible();
    await tap(page.getByRole('button', { name: 'View PDF', exact: true }));
    await expect(page.locator('body > #dialogPdf')).toBeVisible();
    await expect(page.locator('html')).toHaveClass(/portal-dialog-open/);
    await tap(page.locator('#dialogPdf').getByRole('button', { name: 'Close', exact: true }));
    await expect(page.locator('#dialogPdf')).toBeHidden();
    await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
    await expect(page.locator('.portal-summary details')).not.toHaveAttribute('open');
    await expectNoOverflow(page);
    const termsUpdate = page.waitForResponse(r => r.url().includes('/livewire/update') && r.request().method() === 'POST');
    await tap(page.locator('#accept-terms-button'));
    expect((await termsUpdate).ok()).toBe(true);
    await expect(page.locator('#signature-pad')).toBeVisible();
    await expect(page.locator('#save-button')).toBeDisabled();
    await touchSignature(page);
    await expect(page.locator('#save-button')).toBeEnabled();
    await page.setViewportSize({ width: 844, height: 390 });
    await expect.poll(() => page.locator('#signature-pad').evaluate((canvas: HTMLCanvasElement) =>
        canvas.getContext('2d')!.getImageData(0, 0, canvas.width, canvas.height).data.some((v, i) => i % 4 === 3 && v > 0),
    )).toBe(true);
    await tap(page.locator('#clear-signature'));
    await expect(page.locator('#save-button')).toBeDisabled();
    await page.setViewportSize({ width: 390, height: 844 });
    await touchSignature(page);
    const signatureUpdate = page.waitForResponse(r => r.url().includes('/livewire/update') && r.request().method() === 'POST');
    await tap(page.locator('#save-button'));
    expect((await signatureUpdate).ok()).toBe(true);
    await expect(page.locator('#signature-pad')).toHaveCount(0);
    await expect(page.locator('main').getByText('Payment Methods', { exact: true })
        .or(page.locator('main').getByText('Required Fields', { exact: true }))
        .or(page.locator('main #pay-now')).first()).toBeVisible();
    await expectNoOverflow(page);
});

test('quote dialogs remain tappable with consent visible, and cancellation allows retry', async ({ api, page, companyGuard }) => {
    const trackingRequests: string[] = [];
    await page.route('https://analytics.example.test/**', route => {
        trackingRequests.push(route.request().url());
        return route.fulfill({ contentType: 'text/javascript', body: '' });
    });
    await companyGuard.update({ matomo_url: 'https://analytics.example.test/', matomo_id: '1' });
    const client = await createAndLogInClient(api, page, {
        settings: { require_quote_signature: true, show_accept_quote_terms: true, accept_client_input_quote_approval: true },
    });
    const quote = await createSentQuote(api, client, { terms: 'Long quote terms. '.repeat(100) });
    await page.goto(`/client/quotes/${quote.id}`);
    await expect(page.locator('[data-consent-notice]')).toBeVisible();
    expect(trackingRequests).toEqual([]);
    await tap(page.locator('#approve-button'));
    await expect(page.locator('#reject-button')).toBeDisabled();
    await page.locator('#user_input').fill('MOBILE-PO-123');
    await tap(page.locator('#input-next-step'));
    await expect(page.locator('#displaySignatureModal')).toBeVisible();
    await touchSignature(page);
    await tap(page.locator('#signature-next-step'));
    await expectReachable(page.locator('#accept-terms-button'));
    await tap(page.locator('#close-terms-button'));
    await expect(page.locator('#approve-button')).toBeEnabled();
    await expect(page.locator('#reject-button')).toBeEnabled();
    await tap(page.locator('#approve-button'));
    await tap(page.locator('#close-input-button'));
    await tap(page.locator('#reject-button'));
    await expect(page.locator('#approve-button')).toBeDisabled();
    await page.locator('#reject_reason').fill('Mobile test cancellation');
    await tap(page.locator('#reject-close-button'));
    await expect(page.locator('#reject-button')).toBeEnabled();
    await expect(page.locator('#approve-button')).toBeEnabled();
    await tap(page.locator('[data-consent-choice="rejected"]'));
    await page.reload();
    await expect(page.locator('[data-consent-notice]')).toBeHidden();
    expect(trackingRequests).toEqual([]);
    await expectNoOverflow(page);
});

test('Livewire required-field errors remain accessible and valid input reveals the gateway', async ({ api, page }) => {
    const stripe = new StripePaymentGateway();
    const availability = await stripe.checkAvailability(api.context);
    stripe.skipUnlessAvailable(availability);
    const context = await prepareIncompleteClientPaymentContext(api, page, availability.companyGateway!);
    try {
        await navigateToGatewayCheckoutWithoutRequiredClientInfo(page, context.companyGateway, GatewayType.CREDIT_CARD, context.invoice);
        await assertRequiredClientInfoBlocksCheckout(page);
        await waitForAlpine(page);
        // Normal invoice checkout has already handled terms. Configure the
        // component's alternate terms state, then exercise its real UI and morphs.
        const configuredTerms = page.waitForResponse(r => r.url().includes('/livewire/update') && (r.request().postData() || '').includes('show_terms'));
        await page.evaluate(() => {
            const root = document.querySelector('#required-client-info-form')!.closest('[wire\\:id]')!;
            const wire = (window as any).Livewire.find(root.getAttribute('wire:id'));
            wire.$set('invoice_terms', 'Gateway terms for mobile review. '.repeat(100), false);
            wire.$set('terms_accepted', false, false);
            wire.$set('show_terms', true);
        });
        expect((await configuredTerms).ok()).toBe(true);
        const form = requiredClientInfoForm(page);
        const termsLink = form.locator('a').filter({ hasText: /terms/i });
        await tap(termsLink);
        await expect(page.locator('body > dialog[aria-labelledby="gateway-terms-title"]')).toBeVisible();
        await tap(page.locator('dialog[aria-labelledby="gateway-terms-title"] button'));
        await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
        await expect(form.locator('button.button-primary')).toBeDisabled();
        await tap(form.locator('[name="terms_accepted"]'));
        await form.locator('[name="contact_email"]').fill('not-an-email');
        await tap(form.locator('button.button-primary'));
        const error = form.locator('p.border-red-300').first();
        await expect(error).toBeVisible();
        // The teleported dialog must still work after a real Livewire morph.
        await tap(termsLink);
        await expect(page.locator('dialog[aria-labelledby="gateway-terms-title"]')).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
        await expectReachable(error);
        await assertRequiredClientInfoBlocksCheckout(page);
        const values: Record<string, string> = {
            contact_first_name: 'Mobile', contact_last_name: 'Client', contact_email: 'mobile@example.test',
            client_phone: '5555555555', client_address_line_1: '5 Wallaby Way', client_city: 'Perth',
            client_state: 'WA', client_postal_code: '90210', client_shipping_address_line_1: '5 Wallaby Way',
            client_shipping_city: 'Perth', client_shipping_state: 'WA', client_shipping_postal_code: '90210',
        };
        for (const [name, value] of Object.entries(values)) {
            const input = form.locator(`[name="${name}"]`);
            if (await input.isVisible()) await input.fill(value);
        }
        for (const select of await form.locator('select').all()) await select.selectOption('840');
        const terms = form.locator('[name="terms_accepted"]');
        if (await terms.isVisible() && !(await terms.isChecked())) await tap(terms);
        // Submit through the real control: an overlay must fail this test.
        await tap(form.locator('button.button-primary'));
        await expect(page.locator('[data-ref="required-fields-container"]')).toBeHidden();
        await expect(page.locator('[data-ref="gateway-container"]')).not.toHaveClass(/pointer-events-none/);
        await expectNoOverflow(page);
    } finally {
        await context.restoreGatewayRequirements();
    }
});

for (const width of [390, 1280]) {
    test(`invoice summary retains responsive behavior after a Livewire payment step at ${width}px`, async ({ api, page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.addInitScript(() => {
            const scrollIntoView = Element.prototype.scrollIntoView;
            (window as any).paymentPanelScrolls = 0;
            Element.prototype.scrollIntoView = function (...args) {
                if (this.getAttribute('wire:key')?.startsWith('step-')) {
                    (window as any).paymentPanelScrolls++;
                }
                return scrollIntoView.apply(this, args);
            };
        });
        let client = await createAndLogInClient(api, page, {
            settings: { ...paymentTestSettings, payment_flow: 'smooth', show_accept_invoice_terms: true, require_invoice_signature: true },
        });
        client = await updateClient(api.context, client, defaultClientAddress);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('responsive-summary'),
            terms: 'Review the invoice terms before signing.',
        });
        await page.goto(`/client/invoices/${invoice.id}`);
        const summary = page.locator('.portal-summary');
        const details = summary.locator('details');
        const toggle = summary.locator('summary');
        const panel = page.locator('[wire\\:key^="step-"]');
        await expect(page.locator('#accept-terms-button')).toBeVisible();
        await expect(panel).toBeFocused();
        await expect(panel).toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');
        expect(await page.evaluate(() => (window as any).paymentPanelScrolls)).toBe(width < 768 ? 1 : 0);
        await page.keyboard.press('Tab');
        await expect(page.locator('#accept-terms-button')).toBeFocused();
        await expect(page.locator('#accept-terms-button')).not.toHaveCSS('box-shadow', 'none');
        if (width < 768) {
            await expect(details).not.toHaveAttribute('open');
            await toggle.click();
        } else {
            await expect(toggle).toBeHidden();
        }
        await expect(details).toHaveAttribute('open');
        const update = page.waitForResponse(r => r.url().includes('/livewire/update') && r.request().method() === 'POST');
        await page.locator('#accept-terms-button').click();
        expect((await update).ok()).toBe(true);
        await expect(page.locator('#signature-pad')).toBeVisible();
        await expect(panel).toBeFocused();
        await expect(panel).toHaveCSS('outline-color', 'rgba(0, 0, 0, 0)');
        expect(await page.evaluate(() => (window as any).paymentPanelScrolls)).toBe(width < 768 ? 2 : 0);
        if (width < 768) {
            // Each payment step mounts a newly keyed summary. It starts compact on mobile.
            await expect(details).not.toHaveAttribute('open');
            await toggle.click();
        } else {
            await expect(toggle).toBeHidden();
        }
        await expect(details).toHaveAttribute('open');
        await expect(summary.getByRole('heading', { name: 'Invoices', exact: true })).toBeVisible();
        const download = page.waitForEvent('download');
        await summary.locator('button[wire\\:click^="downloadDocument"]').first().click();
        const pdf = await download;
        expect(pdf.suggestedFilename()).toMatch(/\.pdf$/);
        expect(await pdf.failure()).toBeNull();
        await expect(details).toHaveAttribute('open');
        await expectNoOverflow(page);
    });
}
