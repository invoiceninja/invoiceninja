import { createAndLogInClient } from './client-portal-helpers';
import { getEntity } from './api-helpers';
import { expect, test } from './fixtures';
import {
    createRecurringInvoice,
    type PortalEntity,
} from './portal-entity-helpers';

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

        await toggle.check();
        await expect(toggle).toBeChecked();

        const enabled = await getEntity<PortalEntity>(
            api.context,
            'recurring_invoices',
            recurring.id,
        );
        expect(enabled.auto_bill_enabled).toBe(true);
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

        await toggle.uncheck();
        await expect(toggle).not.toBeChecked();

        const disabled = await getEntity<PortalEntity>(
            api.context,
            'recurring_invoices',
            recurring.id,
        );
        expect(disabled.auto_bill_enabled).toBe(false);
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
});