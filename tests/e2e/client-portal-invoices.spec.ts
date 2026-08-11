import {
    createAndLogInClient,
    expectPortalPage,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createSentInvoice,
    invitationKey,
    markInvoicePaid,
    type PortalEntity,
} from './portal-entity-helpers';

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
});
