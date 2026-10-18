import { getCompany, updateClient } from './api-helpers';
import {
    createAndLogInClient,
    createPortalClient,
    dismissCookieConsent,
    drawSignature,
    expectMetaFlag,
    expectPortalPage,
    hasPayNowDropdown,
    openGuestPortalPage,
    portalContact,
    selectEntityTableRow,
    startPayNowCheckout,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import { StripePaymentGateway } from './gateways/stripe-payment-gateway';
import {
    clickBulkPayNow,
    fillRequiredPaymentInformationIfPresent,
    paymentTestSettings,
    selectFirstAvailableGateway,
    selectGatewayFromDropdown,
} from './gateways/payment-flow-helpers';
import {
    createSentInvoice,
    invitationKey,
    markInvoicePaid,
} from './portal-entity-helpers';
import type { Page } from '@playwright/test';

const defaultClientAddress = {
    address1: '5 Wallaby Way',
    city: 'Los Angeles',
    state: 'CA',
    postal_code: '90210',
    country_id: '840',
    shipping_address1: '5 Wallaby Way',
    shipping_city: 'Los Angeles',
    shipping_state: 'CA',
    shipping_postal_code: '90210',
    shipping_country_id: '840',
};

test.describe('Client portal invoices', () => {
    test('lists a sent invoice and opens it from the table', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const marker = uniqueName('invoice-list');
        const invoice = await createSentInvoice(api, client, {
            label: marker,
            cost: 42,
        });

        await expectPortalPage(page, '/client/invoices', 'Invoices');
        await expect(
            page.locator('.invoices-table').getByText(invoice.number ?? ''),
        ).toBeVisible();

        await page
            .locator('.invoices-table tbody tr')
            .filter({ hasText: invoice.number ?? '' })
            .getByRole('link', { name: 'View' })
            .click();

        await expect(page).toHaveURL(
            new RegExp(`/client/invoices/${invoice.id}(?:\\?.*)?$`),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
        await expect(
            page.getByRole('heading', { name: new RegExp(invoice.number ?? '') }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /\$42\.00/ }),
        ).toBeVisible();
    });

    test('shows invoice details including number and balance', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-detail'),
            cost: 75,
        });

        await page.goto(`/client/invoices/${invoice.id}`);

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
        await expect(
            page.getByRole('heading', {
                name: new RegExp(invoice.number ?? ''),
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /\$75\.00/ }),
        ).toBeVisible();
        await expect(page.locator('main')).toBeVisible();
    });

    test('opens an invoice from its invitation link while authenticated', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-invite'),
        });
        const key = invitationKey(invoice);

        const response = await page.goto(`/client/invoice/${key}`);

        expect(response?.ok()).toBe(true);
        await expect(page).toHaveURL(
            new RegExp(`/client/invoices/${invoice.id}(?:\\?.*)?$`),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
    });

    test('filters the invoice list to unpaid invoices only', async ({
        api,
        notificationGuard,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        await notificationGuard.suppressPaymentEmails();

        const unpaid = await createSentInvoice(api, client, {
            label: uniqueName('invoice-unpaid'),
            cost: 20,
        });
        const paid = await markInvoicePaid(api, client, {
            label: uniqueName('invoice-paid'),
            cost: 30,
        });

        await page.goto('/client/invoices');
        await page.locator('#paid-checkbox').uncheck();
        await page.locator('#unpaid-checkbox').check();

        await expect(
            page.locator('.invoices-table').getByText(unpaid.number ?? ''),
        ).toBeVisible();
        await expect(
            page.locator('.invoices-table').getByText(paid.number ?? ''),
        ).toHaveCount(0);
    });

    test('filters the invoice list to paid invoices only', async ({
        api,
        notificationGuard,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        await notificationGuard.suppressPaymentEmails();

        const unpaid = await createSentInvoice(api, client, {
            label: uniqueName('filter-unpaid'),
        });
        const paid = await markInvoicePaid(api, client, {
            label: uniqueName('filter-paid'),
            cost: 45,
        });

        await page.goto('/client/invoices');
        await page.locator('#unpaid-checkbox').uncheck();
        await page.locator('#paid-checkbox').check();

        await expect(
            page.locator('.invoices-table').getByText(paid.number ?? ''),
        ).toBeVisible();
        await expect(
            page.locator('.invoices-table').getByText(unpaid.number ?? ''),
        ).toHaveCount(0);
    });

    test('filters the invoice list to overdue invoices', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const overdue = await createSentInvoice(api, client, {
            label: uniqueName('overdue-invoice'),
            dueInDays: -10,
        });
        const current = await createSentInvoice(api, client, {
            label: uniqueName('current-invoice'),
            dueInDays: 30,
        });

        await page.goto('/client/invoices');
        await page.locator('#unpaid-checkbox').uncheck();
        await page.locator('#paid-checkbox').uncheck();
        await page.locator('#overdue-checkbox').check();

        await expect(
            page.locator('.invoices-table').getByText(overdue.number ?? ''),
        ).toBeVisible();
        await expect(
            page.locator('.invoices-table').getByText(current.number ?? ''),
        ).toHaveCount(0);
    });

    test('shows a custom unpaid invoice message', async ({ api, page }) => {
        const message = 'Please settle this invoice promptly.';
        const client = await createAndLogInClient(api, page, {
            settings: { custom_message_unpaid_invoice: message },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('custom-message-invoice'),
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await expect(page.getByText(message)).toBeVisible();
    });

    test('shows a custom paid invoice message', async ({
        api,
        notificationGuard,
        page,
    }) => {
        const message = 'Thank you for your payment.';
        const client = await createAndLogInClient(api, page, {
            settings: { custom_message_paid_invoice: message },
        });
        await notificationGuard.suppressPaymentEmails();
        const invoice = await markInvoicePaid(api, client, {
            label: uniqueName('paid-message-invoice'),
            cost: 50,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await expect(page.getByText(message)).toBeVisible();
    });

    test('downloads selected invoices from the list bulk action', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('bulk-download-invoice'),
            cost: 33,
        });

        await page.goto('/client/invoices');
        await selectEntityTableRow(
            page,
            '.invoices-table',
            invoice.number ?? '',
        );
        await page
            .locator('form[action*="invoices"]')
            .getByRole('button', { name: 'Download', exact: true })
            .click();

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            /View Invoice|Invoices/,
        );
        await expect(page.getByText(invoice.number ?? '')).toBeVisible();

        const downloadPromise = page.waitForEvent('download');
        await page
            .locator('#bulkActions')
            .getByRole('button', { name: 'Download', exact: true })
            .click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/\.pdf$/i);
    });

    test('starts bulk payment for selected invoices', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { payment_flow: 'default', ...paymentTestSettings },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('bulk-pay-invoice'),
            cost: 44,
        });

        await page.goto('/client/invoices');
        const bulkPay = page.locator('button[name="action"][value="payment"]');

        if ((await bulkPay.count()) === 0) {
            test.skip(true, 'No payment gateway configured for bulk pay test');
        }

        await selectEntityTableRow(
            page,
            '.invoices-table',
            invoice.number ?? '',
        );
        await clickBulkPayNow(page);

        await expect(page).toHaveURL(/\/client\/invoices\/payment/);
        await expect(
            page.getByText(invoice.number ?? '', { exact: true }).first(),
        ).toBeVisible();
        await expect(page.locator('[dusk="payment-methods-dropdown"]')).toBeVisible();
    });

    test('starts Pay Now from invoice detail with the default payment flow', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { payment_flow: 'default', ...paymentTestSettings },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('detail-pay-default'),
            cost: 51,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        const payDropdown = page.locator('[dusk="pay-now-dropdown"]');
        if ((await payDropdown.count()) === 0) {
            test.skip(true, 'No payment gateway configured for detail Pay Now');
        }

        await payDropdown.click();
        await selectFirstAvailableGateway(page);

        await expect(page).toHaveURL(/\/client\/payments\/process/, {
            timeout: 30_000,
        });
        await expect(page.locator('main')).toBeVisible();
    });

    test('shows the smooth payment flow on invoice detail', async ({
        api,
        page,
    }) => {
        let client = await createAndLogInClient(api, page, {
            settings: { payment_flow: 'smooth', ...paymentTestSettings },
        });
        client = await updateClient(api.context, client, defaultClientAddress);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('detail-pay-smooth'),
            cost: 52,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
        await expect(
            page.getByRole('heading', {
                name: new RegExp(invoice.number ?? ''),
            }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'View PDF', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: /Balance Due/i }),
        ).toBeVisible();

        // Smooth flow either shows method buttons, required-fields, or checkout
        // (a single gateway auto-advances past the method picker).
        const smoothStep = page
            .locator('main')
            .getByText('Payment Methods', { exact: true })
            .or(page.locator('main').getByText('Required Fields', { exact: true }))
            .or(page.locator('main #card-element'))
            .or(page.locator('main #pay-now'))
            .or(
                page
                    .locator('main')
                    .getByRole('button', { name: /Credit Card|PayPal|Bank/i }),
            );

        await expect(smoothStep.first()).toBeVisible({ timeout: 30_000 });
    });

    test('completes bulk Pay Now when Stripe is available', async ({
        api,
        page,
        notificationGuard,
    }) => {
        test.setTimeout(90_000);
        const gateway = new StripePaymentGateway();
        const availability = await gateway.checkAvailability(api.context);
        gateway.skipUnlessAvailable(availability);

        await notificationGuard.suppressPaymentEmails();

        let client = await createAndLogInClient(api, page, {
            settings: { payment_flow: 'default', ...paymentTestSettings },
        });
        client = await updateClient(api.context, client, defaultClientAddress);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('bulk-pay-complete'),
            cost: 53,
        });

        await page.goto('/client/invoices');
        await dismissCookieConsent(page);
        await selectEntityTableRow(
            page,
            '.invoices-table',
            invoice.number ?? '',
        );
        await clickBulkPayNow(page);

        await expect(page).toHaveURL(/\/client\/invoices\/payment/);
        await dismissCookieConsent(page);
        await selectGatewayFromDropdown(
            page,
            availability.companyGateway!,
            gateway.gatewayTypeId,
        );
        await fillRequiredPaymentInformationIfPresent(page);
        await gateway.assertCheckoutReady(page);
        await gateway.completePayment(page);
        await gateway.assertPaymentSucceeded(page);
    });
});

/**
 * `show_accept_invoice_terms` and `require_invoice_signature` gate the default
 * payment flow: selecting a method from the Pay Now dropdown opens the signature
 * modal first, then the terms modal, before the payment form is submitted.
 */
test.describe('Client portal invoice checkout gates', () => {
    const checkoutSettings = {
        ...paymentTestSettings,
        payment_flow: 'default',
    };

    test('does not gate checkout when terms and signatures are disabled', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: checkoutSettings,
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-no-gates'),
            cost: 21,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        await expectMetaFlag(page, 'show-invoice-terms', false);
        await expectMetaFlag(page, 'require-invoice-signature', false);
        await expect(page.locator('#displayTermsModal')).toBeHidden();
        await expect(page.locator('#displaySignatureModal')).toBeHidden();
    });

    test('accepts invoice terms before starting checkout', async ({
        api,
        page,
    }) => {
        const terms = 'These are the Playwright invoice terms.';
        const client = await createAndLogInClient(api, page, {
            settings: { ...checkoutSettings, show_accept_invoice_terms: true },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-terms'),
            cost: 31,
            terms,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);
        await expectMetaFlag(page, 'show-invoice-terms', true);

        if (!(await hasPayNowDropdown(page))) {
            test.skip(true, 'No payment gateway configured for the terms gate');
        }

        await startPayNowCheckout(page);

        await expect(page.locator('#displayTermsModal')).toBeVisible();
        await expect(page.locator('[data-ref="entity-terms"]')).toContainText(
            'Playwright invoice terms',
        );

        await page.locator('#accept-terms-button').click();
        await expect(page).toHaveURL(/\/client\/payments\/process/, {
            timeout: 30_000,
        });
    });

    test('closes the invoice terms modal without starting checkout', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { ...checkoutSettings, show_accept_invoice_terms: true },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-terms-cancel'),
            cost: 32,
            terms: 'Declined Playwright terms.',
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        if (!(await hasPayNowDropdown(page))) {
            test.skip(true, 'No payment gateway configured for the terms gate');
        }

        await startPayNowCheckout(page);
        await expect(page.locator('#displayTermsModal')).toBeVisible();
        await page.locator('#close-terms-button').click();

        await expect(page.locator('#displayTermsModal')).toBeHidden();
        await expect(page).not.toHaveURL(/\/client\/payments\/process/);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
    });

    test('requires a drawn signature before starting checkout', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { ...checkoutSettings, require_invoice_signature: true },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-signature'),
            cost: 33,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        const signatureEnabled = await page
            .locator('meta[name="require-invoice-signature"]')
            .getAttribute('content');

        if (signatureEnabled !== '1') {
            // Invoice signatures need the invoice-settings plan feature, and
            // DocuNinja replaces the canvas pad when it is active.
            test.skip(
                true,
                'Invoice signatures are unavailable for this account',
            );
        }

        if (!(await hasPayNowDropdown(page))) {
            test.skip(
                true,
                'No payment gateway configured for the signature gate',
            );
        }

        await startPayNowCheckout(page);

        await expect(page.locator('#displaySignatureModal')).toBeVisible();
        await drawSignature(page);
        await page.locator('#signature-next-step').click();

        await expect(page).toHaveURL(/\/client\/payments\/process/, {
            timeout: 30_000,
        });
    });

    test('collects the signature then the terms before starting checkout', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                ...checkoutSettings,
                require_invoice_signature: true,
                show_accept_invoice_terms: true,
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-signature-terms'),
            cost: 34,
            terms: 'Signed Playwright invoice terms.',
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        const signatureEnabled = await page
            .locator('meta[name="require-invoice-signature"]')
            .getAttribute('content');

        if (signatureEnabled !== '1' || !(await hasPayNowDropdown(page))) {
            test.skip(
                true,
                'Invoice signatures or a payment gateway are unavailable',
            );
        }

        await startPayNowCheckout(page);

        await expect(page.locator('#displaySignatureModal')).toBeVisible();
        await drawSignature(page);
        await page.locator('#signature-next-step').click();

        await expect(page.locator('#displayTermsModal')).toBeVisible();
        await expect(
            page.locator('#payment-form input[name="signature"]'),
        ).toHaveValue(/^data:image\/png;base64,/);
        await expect(page.locator('[data-ref="entity-terms"]')).toContainText(
            'Signed Playwright invoice terms',
        );

        await page.locator('#accept-terms-button').click();
        await expect(page).toHaveURL(/\/client\/payments\/process/, {
            timeout: 30_000,
        });
    });

    test('accepts invoice terms from the bulk payment page', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { ...checkoutSettings, show_accept_invoice_terms: true },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-bulk-terms'),
            cost: 35,
            terms: 'Bulk Playwright invoice terms.',
        });

        await page.goto('/client/invoices');
        await dismissCookieConsent(page);

        if ((await page.locator('button[name="action"][value="payment"]').count()) === 0) {
            test.skip(true, 'No payment gateway configured for bulk pay');
        }

        await selectEntityTableRow(
            page,
            '.invoices-table',
            invoice.number ?? '',
        );
        await clickBulkPayNow(page);

        await expect(page).toHaveURL(/\/client\/invoices\/payment/);
        await dismissCookieConsent(page);
        await expectMetaFlag(page, 'show-invoice-terms', true);

        // The Pay Now dropdown is a Livewire component; let it finish hydrating
        // so it does not remount over the payment.js click listeners.
        await page.waitForLoadState('networkidle');
        await startPayNowCheckout(page);

        await expect(page.locator('#displayTermsModal')).toBeVisible();
        await expect(page.locator('[data-ref="entity-terms"]')).toContainText(
            'Bulk Playwright invoice terms',
        );

        await page.locator('#accept-terms-button').click();
        await expect(page).toHaveURL(/\/client\/payments\/process/, {
            timeout: 30_000,
        });
    });

    test('requires a drawn signature from the bulk payment page', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { ...checkoutSettings, require_invoice_signature: true },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-bulk-signature'),
            cost: 36,
        });

        await page.goto('/client/invoices');
        await dismissCookieConsent(page);

        if (
            (await page.locator('button[name="action"][value="payment"]').count()) ===
            0
        ) {
            test.skip(true, 'No payment gateway configured for bulk pay');
        }

        await selectEntityTableRow(
            page,
            '.invoices-table',
            invoice.number ?? '',
        );
        await clickBulkPayNow(page);
        await expect(page).toHaveURL(/\/client\/invoices\/payment/);
        await dismissCookieConsent(page);

        const signatureEnabled = await page
            .locator('meta[name="require-invoice-signature"]')
            .getAttribute('content');

        if (signatureEnabled !== '1') {
            test.skip(
                true,
                'Invoice signatures are unavailable for this account',
            );
        }

        await page.waitForLoadState('networkidle');
        await startPayNowCheckout(page);

        await expect(page.locator('#displaySignatureModal')).toBeVisible();
        await drawSignature(page);
        await page.locator('#signature-next-step').click();

        await expect(page).toHaveURL(/\/client\/payments\/process/, {
            timeout: 30_000,
        });
    });
});

/**
 * Smooth checkout (`payment_flow: smooth`) gates terms then signature via
 * Livewire Flow2 before the payment method step — opposite of the default
 * Pay Now dropdown order (signature → terms).
 */
test.describe('Client portal smooth checkout gates', () => {
    const smoothSettings = {
        ...paymentTestSettings,
        payment_flow: 'smooth',
        client_portal_allow_over_payment: false,
        client_portal_allow_under_payment: false,
    };

    async function expectSmoothPaymentStep(page: Page) {
        const smoothStep = page
            .locator('main')
            .getByText('Payment Methods', { exact: true })
            .or(page.locator('main').getByText('Required Fields', { exact: true }))
            .or(page.locator('main #card-element'))
            .or(page.locator('main #pay-now'))
            .or(
                page
                    .locator('main')
                    .getByRole('button', { name: /Credit Card|PayPal|Bank/i }),
            );

        await expect(smoothStep.first()).toBeVisible({ timeout: 30_000 });
    }

    test('accepts invoice terms before the smooth payment step', async ({
        api,
        page,
    }) => {
        let client = await createAndLogInClient(api, page, {
            settings: {
                ...smoothSettings,
                show_accept_invoice_terms: true,
            },
        });
        client = await updateClient(api.context, client, defaultClientAddress);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('smooth-terms'),
            cost: 41,
            terms: 'Smooth Playwright invoice terms.',
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        await expect(
            page.getByRole('heading', { name: 'Terms', exact: true }),
        ).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('[data-ref="entity-terms"]')).toContainText(
            'Smooth Playwright invoice terms',
        );

        await page.locator('#accept-terms-button').click();
        await expectSmoothPaymentStep(page);
    });

    test('requires a drawn signature before the smooth payment step', async ({
        api,
        page,
    }) => {
        let client = await createAndLogInClient(api, page, {
            settings: {
                ...smoothSettings,
                require_invoice_signature: true,
            },
        });
        client = await updateClient(api.context, client, defaultClientAddress);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('smooth-signature'),
            cost: 42,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        const signaturePad = page.locator('#signature-pad');
        const paymentMethods = page
            .locator('main')
            .getByText('Payment Methods', { exact: true });

        // Wait for Flow2 to settle before deciding whether signatures are offered.
        await expect(signaturePad.or(paymentMethods).first()).toBeVisible({
            timeout: 15_000,
        });

        if ((await signaturePad.count()) === 0) {
            test.skip(
                true,
                'Invoice signatures are unavailable for this account',
            );
        }

        await drawSignature(page);
        await page.locator('#save-button').click();
        await expectSmoothPaymentStep(page);
    });

    test('collects terms then the signature in the smooth payment flow', async ({
        api,
        page,
    }) => {
        let client = await createAndLogInClient(api, page, {
            settings: {
                ...smoothSettings,
                show_accept_invoice_terms: true,
                require_invoice_signature: true,
            },
        });
        client = await updateClient(api.context, client, defaultClientAddress);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('smooth-terms-signature'),
            cost: 43,
            terms: 'Smooth signed Playwright terms.',
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        await expect(
            page.getByRole('heading', { name: 'Terms', exact: true }),
        ).toBeVisible({ timeout: 15_000 });
        await expect(page.locator('[data-ref="entity-terms"]')).toContainText(
            'Smooth signed Playwright terms',
        );
        await page.locator('#accept-terms-button').click();

        const signaturePad = page.locator('#signature-pad');
        if ((await signaturePad.count()) === 0) {
            test.skip(
                true,
                'Invoice signatures are unavailable for this account',
            );
        }

        await expect(signaturePad).toBeVisible({ timeout: 15_000 });
        await drawSignature(page);
        await page.locator('#save-button').click();
        await expectSmoothPaymentStep(page);
    });
});

/**
 * `enable_client_portal_password` forces contacts through the portal login
 * before an invoice invitation resolves, and sends contacts without a password
 * to the set-password form first.
 */
test.describe('Client portal password protected invoices', () => {
    test('sends an invoice invitation to the portal login', async ({
        api,
        browser,
    }) => {
        const client = await createPortalClient(api, {
            name: uniqueName('pw-invoice-client'),
            settings: { enable_client_portal_password: true },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('pw-invoice'),
        });
        const { context, page } = await openGuestPortalPage(browser);

        try {
            await page.goto(`/client/invoice/${invitationKey(invoice)}`);

            await expect(page).toHaveURL(/\/client\/login/);
            await expect(page.locator('#email')).toBeVisible();
            await expect(page.locator('#password')).toBeVisible();
        } finally {
            await context.close();
        }
    });

    test('opens the invoice after logging in from a protected invitation', async ({
        api,
        browser,
    }) => {
        const marker = uniqueName('pw-invoice-login');
        const password = 'PortalProtected123!';
        const client = await createPortalClient(api, {
            name: marker,
            settings: { enable_client_portal_password: true },
            contact: { email: `${marker}@example.test`, password },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('pw-invoice-login-doc'),
            cost: 61,
        });
        const { context, page } = await openGuestPortalPage(browser);

        try {
            await page.goto(`/client/invoice/${invitationKey(invoice)}`);
            await expect(page).toHaveURL(/\/client\/login/);

            await page.locator('#email').fill(`${marker}@example.test`);
            await page.locator('#password').fill(password);
            await page.locator('#loginBtn').click();

            await expect(page).toHaveURL(
                new RegExp(`/client/invoices/${invoice.id}(?:\\?.*)?$`),
                { timeout: 30_000 },
            );
            await dismissCookieConsent(page);
            await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
                'View Invoice',
            );
        } finally {
            await context.close();
        }
    });

    test('rejects an incorrect portal password for a protected invitation', async ({
        api,
        browser,
    }) => {
        const marker = uniqueName('pw-invoice-wrong');
        const client = await createPortalClient(api, {
            name: marker,
            settings: { enable_client_portal_password: true },
            contact: {
                email: `${marker}@example.test`,
                password: 'PortalProtected123!',
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('pw-invoice-wrong-doc'),
        });
        const { context, page } = await openGuestPortalPage(browser);

        try {
            await page.goto(`/client/invoice/${invitationKey(invoice)}`);
            await expect(page).toHaveURL(/\/client\/login/);

            await page.locator('#email').fill(`${marker}@example.test`);
            await page.locator('#password').fill('WrongPortalPassword123!');
            await page.locator('#loginBtn').click();

            await expect(page).toHaveURL(/\/client\/login/);
            await expect(
                page.getByText(/credentials do not match our records/i),
            ).toBeVisible();
            await expect(page.locator('#password')).toBeVisible();
        } finally {
            await context.close();
        }
    });

    // The key_login middleware reads company settings rather than the client's,
    // so password protection has to be toggled at the company level here.
    test('sends a key_login to the portal login when password protection is on', async ({
        api,
        browser,
        companyGuard,
    }) => {
        const company = await getCompany(api.context);
        await companyGuard.update({
            settings: {
                ...(company.settings as Record<string, unknown>),
                enable_client_portal_password: true,
            },
        });

        const client = await createPortalClient(api, {
            name: uniqueName('pw-key-login'),
            settings: { enable_client_portal_password: true },
        });
        const { context, page } = await openGuestPortalPage(browser);

        try {
            await page.goto(
                `/client/key_login/${portalContact(client).contact_key}`,
            );

            await expect(page).toHaveURL(/\/client\/login/);
        } finally {
            await context.close();
        }
    });

    test('prompts a password-less contact to set a portal password', async ({
        api,
        browser,
    }) => {
        test.setTimeout(90_000);

        const client = await createPortalClient(api, {
            name: uniqueName('pw-invoice-set'),
            settings: { enable_client_portal_password: true },
            contact: { password: null },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('pw-invoice-set-doc'),
            cost: 62,
        });
        const key = invitationKey(invoice);
        const { context, page } = await openGuestPortalPage(browser);

        try {
            await page.goto(`/client/invoice/${key}`);

            if (!page.url().includes('set_password')) {
                const { invitationSetPasswordHash } = await import(
                    './portal-auth-helpers'
                );
                await page.goto(
                    `/set_password?entity_type=invoice&invitation_key=${key}&hash=${invitationSetPasswordHash(key)}`,
                );
            }

            await expect(page.locator('#password')).toBeVisible({
                timeout: 15_000,
            });
            await page.locator('#password').fill('PortalSetInvoice123!');
            await page.getByRole('button', { name: /Continue/i }).click();

            await expect(page).toHaveURL(
                new RegExp(
                    `(?:/client/invoices/${invoice.id}|/client/invoice/${key})(?:\\?.*)?$`,
                ),
                { timeout: 30_000 },
            );
        } finally {
            await context.close();
        }
    });
});
