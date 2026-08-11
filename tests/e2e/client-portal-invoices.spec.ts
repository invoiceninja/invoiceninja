import { updateClient } from './api-helpers';
import {
    createAndLogInClient,
    dismissCookieConsent,
    expectPortalPage,
    selectEntityTableRow,
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
