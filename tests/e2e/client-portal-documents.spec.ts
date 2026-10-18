import {
    createAndLogInClient,
    dismissCookieConsent,
    expectPortalRouteForbidden,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createSentInvoice,
    createSentQuote,
    uploadClientDocument,
    uploadEntityDocument,
} from './portal-entity-helpers';

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

        await tabBar
            .getByRole('button', { name: 'Recurring Invoices', exact: true })
            .click();
        await expect(
            tabBar.getByRole('button', {
                name: 'Recurring Invoices',
                exact: true,
            }),
        ).toHaveClass(/(?:^|\s)border-gray-600(?:\s|$)/);
    });

    test('shows invoice-attached documents under the Invoices tab', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-doc'),
        });
        const filename = `${uniqueName('invoice-tab-doc')}.txt`;
        await uploadEntityDocument(api, 'invoices', invoice.id, filename);

        await page.goto('/client/documents');
        await page
            .locator('.space-x-2.flex.flex-row')
            .getByRole('button', { name: 'Invoices', exact: true })
            .click();

        await expect(page.getByText(filename)).toBeVisible({ timeout: 15_000 });
    });

    test('shows quote-attached documents under the Quotes tab', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-doc'),
        });
        const filename = `${uniqueName('quote-tab-doc')}.txt`;
        await uploadEntityDocument(api, 'quotes', quote.id, filename);

        await page.goto('/client/documents');
        await page
            .locator('.space-x-2.flex.flex-row')
            .getByRole('button', { name: 'Quotes', exact: true })
            .click();

        await expect(page.getByText(filename)).toBeVisible({ timeout: 15_000 });
    });

    test('shows invoice attachments on the invoice detail page', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { payment_flow: 'default' },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('invoice-attach'),
        });
        const filename = `inv-att-${uniqueName('d').slice(-8)}.txt`;
        await uploadEntityDocument(api, 'invoices', invoice.id, filename);

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);
        await expect(page.getByText(/Attachments/i)).toBeVisible();
        await expect(page.getByRole('link', { name: new RegExp(filename.slice(0, 20)) })).toBeVisible();
    });

    test('prepares selected documents for bulk download', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const firstName = `${uniqueName('bulk-one')}.txt`;
        const secondName = `${uniqueName('bulk-two')}.txt`;
        await uploadClientDocument(api, client, firstName);
        await uploadClientDocument(api, client, secondName);

        await page.goto('/client/documents');
        await expect(page.getByText(firstName)).toBeVisible();
        await expect(page.getByText(secondName)).toBeVisible();

        for (const filename of [firstName, secondName]) {
            await page
                .locator('.credits-table tbody tr')
                .filter({ hasText: filename })
                .locator('input[type="checkbox"]')
                .check();
        }

        await expect(
            page.locator('#multiple-downloads input[name="file_hash[]"]'),
        ).toHaveCount(2);
        await expect(
            page.getByRole('button', { name: /download selected/i }),
        ).toBeEnabled();
    });

    test('completes a bulk zip download when enabled', async ({ api, page }) => {
        test.setTimeout(90_000);

        if (!process.env.PLAYWRIGHT_ALLOW_BULK_ZIP) {
            test.skip(
                true,
                'Set PLAYWRIGHT_ALLOW_BULK_ZIP=1 when PHP can serve concurrent HTTP self-fetches',
            );
        }

        const client = await createAndLogInClient(api, page);
        const firstName = `${uniqueName('zip-one')}.txt`;
        const secondName = `${uniqueName('zip-two')}.txt`;
        await uploadClientDocument(api, client, firstName);
        await uploadClientDocument(api, client, secondName);

        await page.goto('/client/documents');
        await dismissCookieConsent(page);

        for (const filename of [firstName, secondName]) {
            await page
                .locator('.credits-table tbody tr')
                .filter({ hasText: filename })
                .locator('input[type="checkbox"]')
                .check();
        }

        const downloadPromise = page.waitForEvent('download', {
            timeout: 60_000,
        });
        await page.getByRole('button', { name: /download selected/i }).click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/\.zip$/i);
    });

    test('uploads a document through the portal upload endpoint', async ({
        api,
        page,
    }) => {
        const marker = uniqueName('portal-upload');
        const filename = `${marker}.txt`;
        await createAndLogInClient(api, page, {
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

    test('blocks cross-client document access', async ({ api, page }) => {
        await createAndLogInClient(api, page);
        const otherClient = await api.createEntity('clients', {
            name: uniqueName('other-doc-client'),
            contacts: [
                {
                    first_name: 'Other',
                    last_name: 'Docs',
                    email: `${uniqueName('other-docs')}@example.test`,
                },
            ],
        });
        const foreignDoc = await uploadClientDocument(
            api,
            otherClient as never,
            `${uniqueName('foreign-doc')}.txt`,
        );

        await expectPortalRouteForbidden(
            page,
            `/client/documents/${foreignDoc.id}`,
        );
    });
});
