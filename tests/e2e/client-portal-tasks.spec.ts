import {
    createAndLogInClient,
    selectEntityTableRow,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createInvoicedPortalTask,
    createPortalTask,
} from './portal-entity-helpers';

test.describe('Client portal tasks', () => {
    test('shows expanded time logs when item descriptions are enabled', async ({
        api,
        page,
    }) => {
        const logDescription = 'Detailed billable time entry for Playwright.';
        const client = await createAndLogInClient(api, page, {
            settings: { show_task_item_description: true },
        });
        const task = await createPortalTask(api, client, undefined, {
            description: uniqueName('task-with-logs'),
            timeLogDescription: logDescription,
        });

        await page.goto('/client/tasks');

        await expect(page.getByText(task.description ?? '')).toBeVisible();
        await expect(page.getByText(logDescription)).toBeVisible();
        await expect(page.locator('.task_description').first()).toBeVisible();
    });

    test('links an invoiced task to its invoice', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const { task, invoice } = await createInvoicedPortalTask(api, client, {
            taskDescription: uniqueName('linked-task'),
        });

        await page.goto('/client/tasks');

        const taskRow = page
            .locator('.credits-table tbody tr')
            .filter({ hasText: task.description ?? '' });
        await expect(taskRow).toBeVisible();

        const invoiceLink = taskRow.locator('a[href*="/client/invoices/"]');
        await expect(invoiceLink).toBeVisible();
        await invoiceLink.click();

        await expect(page).toHaveURL(
            new RegExp(`/client/invoices/${invoice.id}(?:\\?.*)?$`),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
    });
});
