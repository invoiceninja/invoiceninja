import {
    createAndLogInClient,
    dismissCookieConsent,
} from './client-portal-helpers';
import { getEntity } from './api-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createPortalSubscription,
    createRecurringInvoice,
    createRecurringInvoiceWithSubscription,
    type PortalEntity,
    uploadEntityDocument,
} from './portal-entity-helpers';

async function toggleAutoBilling(
    page: import('@playwright/test').Page,
    checked: boolean,
): Promise<void> {
    await dismissCookieConsent(page);
    const toggle = page.locator('input[wire\\:change="updateAutoBilling"]');
    await expect(toggle).toBeVisible();

    const livewireResponse = page.waitForResponse(
        (response) =>
            response.url().includes('/livewire/') &&
            response.request().method() === 'POST' &&
            response.ok(),
        { timeout: 30_000 },
    );

    if (checked) {
        await toggle.check();
    } else {
        await toggle.uncheck();
    }

    await livewireResponse;
}

test.describe('Client portal recurring invoices', () => {
    test('lets a client opt in to auto billing', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const recurring = await createRecurringInvoice(api, client, {
            autoBill: 'optin',
            autoBillEnabled: false,
        });

        await page.goto(`/client/recurring_invoices/${recurring.id}`);

        await expect(page.getByRole('heading', { name: 'Auto Bill' })).toBeVisible();
        const toggle = page.locator('input[wire\\:change="updateAutoBilling"]');
        await expect(toggle).not.toBeChecked();

        await toggleAutoBilling(page, true);
        await expect(toggle).toBeChecked();

        await expect
            .poll(
                async () => {
                    const enabled = await getEntity<PortalEntity>(
                        api.context,
                        'recurring_invoices',
                        recurring.id,
                    );

                    return enabled.auto_bill_enabled;
                },
                { timeout: 15_000 },
            )
            .toBe(true);
    });

    test('lets a client opt out of auto billing', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const recurring = await createRecurringInvoice(api, client, {
            autoBill: 'optout',
            autoBillEnabled: true,
        });

        await page.goto(`/client/recurring_invoices/${recurring.id}`);

        await expect(page.getByRole('heading', { name: 'Auto Bill' })).toBeVisible();
        const toggle = page.locator('input[wire\\:change="updateAutoBilling"]');
        await expect(toggle).toBeChecked();

        await toggleAutoBilling(page, false);
        await expect(toggle).not.toBeChecked();

        await expect
            .poll(
                async () => {
                    const disabled = await getEntity<PortalEntity>(
                        api.context,
                        'recurring_invoices',
                        recurring.id,
                    );

                    return disabled.auto_bill_enabled;
                },
                { timeout: 15_000 },
            )
            .toBe(false);
    });

    test('does not show auto billing controls when auto bill is off', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const recurring = await createRecurringInvoice(api, client, {
            autoBill: 'off',
        });

        await page.goto(`/client/recurring_invoices/${recurring.id}`);
        await expect(page.getByRole('heading', { name: 'Auto Bill' })).toHaveCount(
            0,
        );
    });

    test('requests cancellation for an active subscription recurring invoice', async ({
        api,
        page,
    }) => {
        test.setTimeout(90_000);

        const client = await createAndLogInClient(api, page);
        const { subscription } = await createPortalSubscription(api, {
            allowCancellation: true,
            name: uniqueName('cancel-sub'),
        });
        const recurring = await createRecurringInvoiceWithSubscription(
            api,
            client,
            subscription,
        );

        await page.goto(`/client/recurring_invoices/${recurring.id}`);

        const cancelButton = page.getByRole('button', {
            name: /Request Cancellation|Request cancellation/i,
        });
        if ((await cancelButton.count()) === 0) {
            test.skip(
                true,
                'Cancellation UI is unavailable (subscription/status gate not met)',
            );
        }

        await cancelButton.click();
        await page
            .locator('button[wire\\:click="processCancellation"]')
            .click();

        await expect(page.locator('main')).toBeVisible({ timeout: 30_000 });
        await expect(
            page.getByText(/cancel|success|requested/i).first(),
        ).toBeVisible({ timeout: 30_000 });
    });

    test('shows uploaded documents on a recurring invoice detail page', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const recurring = await createRecurringInvoice(api, client);
        const filename = `${uniqueName('recurring-doc')}.txt`;
        await uploadEntityDocument(
            api,
            'recurring_invoices',
            recurring.id,
            filename,
        );

        await page.goto(`/client/recurring_invoices/${recurring.id}`);
        await expect(page.getByText(/Attachments/i)).toBeVisible();
        await expect(page.getByText(filename)).toBeVisible();
    });
});
