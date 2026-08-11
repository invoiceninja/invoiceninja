import { createAndLogInClient } from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import { uploadClientDocument } from './portal-entity-helpers';

test.describe('Client portal documents', () => {
    test('views a document detail page and downloads it', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const filename = `${uniqueName('portal-doc')}.txt`;
        const document = await uploadClientDocument(api, client, filename);

        await page.goto('/client/documents');
        await expect(page.getByText(filename)).toBeVisible();

        await page
            .locator('.credits-table tbody tr')
            .filter({ hasText: filename })
            .getByRole('link', { name: 'View' })
            .click();

        await expect(page).toHaveURL(
            new RegExp(`/client/documents/${document.id}(?:\\?.*)?$`),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'Document',
        );
        await expect(page.getByText(filename)).toBeVisible();

        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('link', { name: 'Download' }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/\.txt$/i);
    });

    test('downloads a single document from the list', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const filename = `${uniqueName('list-download')}.txt`;
        await uploadClientDocument(api, client, filename);

        await page.goto('/client/documents');

        const row = page
            .locator('.credits-table tbody tr')
            .filter({ hasText: filename });
        const downloadPromise = page.waitForEvent('download');
        await row.locator('a[href*="/documents/"][href*="/download"]').click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/\.txt$/i);
    });

    test('switches document library tabs', async ({ api, page }) => {
        await createAndLogInClient(api, page);
        await page.goto('/client/documents');

        const tabBar = page.locator('.space-x-2.flex.flex-row');
        const myDocumentsTab = tabBar.getByRole('button', {
            name: 'My Documents',
            exact: true,
        });
        const invoicesTab = tabBar.getByRole('button', {
            name: 'Invoices',
            exact: true,
        });

        await expect(myDocumentsTab).toHaveClass(/(?:^|\s)border-gray-600(?:\s|$)/);
        await invoicesTab.click();
        await expect(invoicesTab).toHaveClass(/(?:^|\s)border-gray-600(?:\s|$)/);
        await expect(myDocumentsTab).not.toHaveClass(/(?:^|\s)border-gray-600(?:\s|$)/);

        await tabBar.getByRole('button', { name: 'Quotes', exact: true }).click();
        await expect(
            tabBar.getByRole('button', { name: 'Quotes', exact: true }),
        ).toHaveClass(/(?:^|\s)border-gray-600(?:\s|$)/);
    });

    test('downloads multiple selected documents', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const firstName = `${uniqueName('bulk-one')}.txt`;
        const secondName = `${uniqueName('bulk-two')}.txt`;
        const first = await uploadClientDocument(api, client, firstName);
        const second = await uploadClientDocument(api, client, secondName);

        await page.goto('/client/documents');

        for (const document of [first, second]) {
            await page
                .locator('.credits-table tbody tr')
                .filter({ hasText: document.name ?? '' })
                .locator('input[type="checkbox"]')
                .check();
        }

        const downloadPromise = page.waitForEvent('download');
        await page.getByRole('button', { name: 'Download Selected' }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/documents\.zip$/i);
    });

    test('uploads a document through the portal upload endpoint', async ({
        api,
        page,
    }) => {
        const marker = uniqueName('portal-upload');
        const filename = `${marker}.txt`;
        const client = await createAndLogInClient(api, page, {
            settings: { client_portal_enable_uploads: true },
        });

        await page.goto('/client/documents');
        await expect(page.locator('form.dropzone')).toBeVisible();

        const csrfToken = await page
            .locator('form.dropzone input[name="_token"]')
            .inputValue();

        const uploadResponse = await page.request.post('/client/upload', {
            multipart: {
                _token: csrfToken,
                file: {
                    name: filename,
                    mimeType: 'text/plain',
                    buffer: Buffer.from(`Uploaded from Playwright ${marker}`),
                },
            },
        });
        expect(uploadResponse.ok()).toBe(true);

        await page.reload();
        await expect(page.getByText(filename)).toBeVisible({
            timeout: 15_000,
        });
    });
});
