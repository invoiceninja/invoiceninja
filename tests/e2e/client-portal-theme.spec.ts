import { createAndLogInClient, expectPortalPage } from './client-portal-helpers';
import { expect, test } from './fixtures';

test('saved portal CSS and named targets survive Livewire table updates', async ({ api, page }) => {
    await createAndLogInClient(api, page, {
        settings: {
            portal_custom_css: ':root { --primary-color: #176b55; } [data-portal="client"] { --portal-table-header-background: #173b32; }',
        },
    });
    await expectPortalPage(page, '/client/invoices', 'Invoices');
    const table = page.locator('[data-portal-table="invoices"]');
    await expect(table).toHaveAttribute('data-portal-target', 'table');
    await expect(table.locator('th').first()).toHaveCSS('background-color', 'rgb(23, 59, 50)');
    await expect(page.locator('[data-portal-target="navigation-link"][aria-current="page"]').last())
        .toHaveCSS('background-color', 'rgb(23, 107, 85)');

    const response = page.waitForResponse((response) => response.url().includes('/livewire/update') && response.request().method() === 'POST');
    await page.locator('select[wire\\:model\\.live="per_page"]').selectOption('5');
    expect((await response).ok()).toBe(true);
    await expect(table).toHaveAttribute('data-portal-target', 'table');
    await expect(table.locator('th').first()).toHaveCSS('background-color', 'rgb(23, 59, 50)');
});
