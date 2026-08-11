import { updateClient } from './api-helpers';
import {
    createAndLogInClient,
    dismissCookieConsent,
    expectPortalPage,
    selectEntityTableRow,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    fillStripeTestCard,
    paymentTestSettings,
    submitPrePayment,
} from './gateways/payment-flow-helpers';
import { StripePaymentGateway } from './gateways/stripe-payment-gateway';
import {
    createPortalPayment,
    createPortalProject,
    createPortalTask,
    createRecurringInvoice,
    createSentCredit,
    createSentInvoice,
    expectInvitationUrl,
    invitationKey,
} from './portal-entity-helpers';

test.describe('Client portal entity pages', () => {
    test('shows dashboard totals for a client with activity', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { enable_client_portal_dashboard: true },
        });
        await createSentInvoice(api, client, {
            label: uniqueName('dashboard-invoice'),
            cost: 100,
        });

        await expectPortalPage(page, '/client/dashboard', 'Dashboard');
        await expect(page.getByText('Total Invoices')).toBeVisible();
        await expect(page.getByText('Paid to Date')).toBeVisible();
        await expect(page.getByText('Open Balance')).toBeVisible();
    });

    test('shows a custom dashboard message when configured', async ({
        api,
        page,
    }) => {
        const message = 'Welcome to your Playwright dashboard.';
        await createAndLogInClient(api, page, {
            settings: {
                enable_client_portal_dashboard: true,
                custom_message_dashboard: message,
            },
        });

        await page.goto('/client/dashboard');
        await expect(page.getByText(message)).toBeVisible();
    });

    test('lists payments and opens a payment detail page', async ({
        api,
        notificationGuard,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        await notificationGuard.suppressPaymentEmails();
        const payment = await createPortalPayment(api, client, { amount: 25 });

        await expectPortalPage(page, '/client/payments', 'Payments');
        await expect(
            page.locator('.payments-table').getByText(/\$25\.00/),
        ).toBeVisible();

        await page.goto(`/client/payments/${payment.id}`);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('Payment');
    });

    test('lists credits and opens a credit detail page', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const credit = await createSentCredit(api, client, {
            label: uniqueName('portal-credit'),
            cost: 33,
        });

        await expectPortalPage(page, '/client/credits', 'Credits');
        await expect(
            page.locator('.credits-table').getByText(credit.number ?? ''),
        ).toBeVisible();

        await page.goto(`/client/credits/${credit.id}`);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Credit',
        );
    });

    test('opens a credit from its invitation link', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const credit = await createSentCredit(api, client, {
            label: uniqueName('credit-invite'),
        });

        await page.goto(`/client/credit/${invitationKey(credit)}`);
        await expect(page).toHaveURL(
            expectInvitationUrl(
                `/client/credits/${credit.id}`,
                `/client/credit/${invitationKey(credit)}`,
            ),
        );
    });

    test('lists recurring invoices and opens the detail page', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const recurring = await createRecurringInvoice(api, client);

        await expectPortalPage(page, '/client/recurring_invoices', 'Recurring Invoices');
        await expect(
            page.locator('.recurring-invoices-table').getByText('$11.00'),
        ).toBeVisible();
        await expect(
            page.locator('.recurring-invoices-table').getByText('Monthly'),
        ).toBeVisible();

        await page.goto(`/client/recurring_invoices/${recurring.id}`);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'Recurring Invoice',
        );
    });

    test('lists projects and tasks for the client', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const project = await createPortalProject(api, client);
        const task = await createPortalTask(api, client, project);

        await expectPortalPage(page, '/client/projects', 'Projects');
        await expect(
            page.locator('.projects-table').getByText(project.name ?? ''),
        ).toBeVisible();

        await page.goto(`/client/projects/${project.id}`);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Project',
        );

        await expectPortalPage(page, '/client/tasks', 'Tasks');
        await expect(page.getByText(task.description ?? '')).toBeVisible();
    });

    test('shows the document library tabs and filters', async ({ api, page }) => {
        await createAndLogInClient(api, page);
        await expectPortalPage(page, '/client/documents', 'Documents');
        await expect(page.locator('main').getByText('My Documents')).toBeVisible();
        await expect(page.getByText('Download Selected')).toBeVisible();
        await expect(page.locator('select').first()).toBeVisible();
    });

    test('hides the upload dropzone when client uploads are disabled', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: { client_portal_enable_uploads: false },
        });

        await page.goto('/client/documents');
        await expect(page.locator('form.dropzone')).toHaveCount(0);
    });

    test('renders the statement builder controls', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        await createSentInvoice(api, client, {
            label: uniqueName('statement-invoice'),
        });

        await expectPortalPage(page, '/client/statement', 'Statement');
        await expect(page.locator('#date-from')).toBeVisible();
        await expect(page.locator('#date-to')).toBeVisible();
        await expect(page.locator('#status')).toBeVisible();
        await expect(page.locator('#show-payments-table')).toBeVisible();
        await expect(page.locator('#show-aging-table')).toBeVisible();
        await expect(page.locator('#pdf-download')).toBeVisible();
    });

    test('downloads a statement PDF', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        await createSentInvoice(api, client, {
            label: uniqueName('statement-download'),
        });

        await page.goto('/client/statement');
        const downloadPromise = page.waitForEvent('download');
        await page.locator('#pdf-download').click();
        const download = await downloadPromise;

        expect(download.suggestedFilename()).toMatch(/\.pdf$/i);
    });

    test('shows the pre-payment form when client initiated payments are enabled', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: {
                client_initiated_payments: true,
                client_initiated_payments_minimum: 25,
            },
        });

        await expectPortalPage(page, '/client/pre_payments', 'Pre Payment');
        await expect(page.locator('#payment-form')).toBeVisible();
        await expect(page.locator('input[name="amount"]')).toBeVisible();
        await expect(page.getByText(/minimum/i)).toBeVisible();
    });

    test('submits a pre-payment and opens the payment page', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: {
                client_initiated_payments: true,
                client_initiated_payments_minimum: 25,
                payment_flow: 'default',
                ...paymentTestSettings,
            },
        });

        await submitPrePayment(page, 40, 'Playwright pre-payment note');

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('Pay Now');
        await expect(page.locator('#payment-form')).toBeVisible();

        const payDropdown = page.locator('[dusk="payment-methods-dropdown"]');
        if ((await payDropdown.count()) === 0) {
            test.skip(
                true,
                'No payment gateway configured after pre-payment submit',
            );
        }

        await expect(payDropdown).toBeVisible();
    });

    test('renders the payment methods page', async ({ api, page }) => {
        await createAndLogInClient(api, page);
        await expectPortalPage(page, '/client/payment_methods', 'Payment Methods');
        await expect(page.locator('.payment-methods-table')).toBeVisible();
    });

    test('opens Stripe credit card authorization to add a payment method', async ({
        api,
        page,
    }) => {
        test.setTimeout(90_000);

        const gateway = new StripePaymentGateway();
        const availability = await gateway.checkAvailability(api.context);
        gateway.skipUnlessAvailable(availability);

        let client = await createAndLogInClient(api, page, {
            settings: { ...paymentTestSettings },
        });
        client = await updateClient(api.context, client, {
            address1: '5 Wallaby Way',
            city: 'Los Angeles',
            state: 'CA',
            postal_code: '90210',
            country_id: '840',
        });

        await page.goto('/client/payment_methods');
        await dismissCookieConsent(page);
        const addButton = page.locator('[data-cy="add-payment-method"]');
        if ((await addButton.count()) === 0) {
            test.skip(
                true,
                'Add payment method is unavailable for this company gateway setup',
            );
        }

        await addButton.click();
        await page.locator('[data-cy="add-credit-card-link"]').click();

        await expect(page).toHaveURL(/\/client\/payment_methods\/create/);
        await expect(
            page.locator('meta[name="stripe-publishable-key"]'),
        ).toHaveAttribute('content', /.+/);
        await expect(page.locator('#card-element')).toBeVisible();
        await expect(page.locator('#authorize-card')).toBeVisible();

        await fillStripeTestCard(page);
        await page.locator('#authorize-card').click();

        await expect(page).toHaveURL(/\/client\/payment_methods(?:\/)?$/, {
            timeout: 60_000,
        });
        await expect(page.locator('[data-cy="pm-last4"]')).toContainText('4242');
    });

    test('renders the subscriptions page', async ({ api, page }) => {
        await createAndLogInClient(api, page);
        await expectPortalPage(page, '/client/subscriptions', 'Subscriptions');
        await expect(page.locator('main')).toBeVisible();
    });

    test('selects an invoice row for bulk actions', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('bulk-select-invoice'),
        });

        await page.goto('/client/invoices');
        await selectEntityTableRow(
            page,
            '.invoices-table',
            invoice.number ?? '',
        );

        await expect(
            page.locator('.invoices-table tbody tr').filter({
                hasText: invoice.number ?? '',
            }).getByRole('checkbox'),
        ).toBeChecked();
        await expect(
            page.locator('form[action*="invoices"]').getByRole('button', {
                name: 'Download',
            }),
        ).toBeEnabled();
    });
});
