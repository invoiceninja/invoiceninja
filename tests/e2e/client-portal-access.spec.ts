import {
    createAndLogInClient,
    expectPortalRouteForbidden,
    expectPortalRouteRedirects,
    patchPortalClient,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createSentInvoice,
    uploadClientDocument,
} from './portal-entity-helpers';

test.describe('Client portal access control', () => {
    test('returns forbidden when viewing another client invoice', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page);
        const otherClient = await api.createEntity('clients', {
            name: uniqueName('other-portal-client'),
            contacts: [
                {
                    first_name: 'Other',
                    last_name: 'Client',
                    email: `${uniqueName('other-client')}@example.test`,
                },
            ],
        });
        const foreignInvoice = await createSentInvoice(api, otherClient as never, {
            label: uniqueName('foreign-invoice'),
        });

        await expectPortalRouteForbidden(
            page,
            `/client/invoices/${foreignInvoice.id}`,
        );
    });

    test('returns forbidden when viewing another client document', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page);
        const otherClient = await api.createEntity('clients', {
            name: uniqueName('other-access-doc-client'),
            contacts: [
                {
                    first_name: 'Other',
                    last_name: 'Docs',
                    email: `${uniqueName('other-access-docs')}@example.test`,
                },
            ],
        });
        const foreignDoc = await uploadClientDocument(
            api,
            otherClient as never,
            `${uniqueName('foreign-access-doc')}.txt`,
        );

        await expectPortalRouteForbidden(
            page,
            `/client/documents/${foreignDoc.id}`,
        );
        await expectPortalRouteForbidden(
            page,
            `/client/documents/${foreignDoc.id}/download`,
        );
    });

    test('redirects to the error page when the portal is disabled', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        await patchPortalClient(api, client, {
            settings: { enable_client_portal: false },
        });

        const response = await page.goto('/client/invoices');

        expect(response?.ok()).toBe(true);
        await expect(page).toHaveURL(/\/error(?:\/|$)/);
        await expect(page.getByText(/disabled by the administrator/i)).toBeVisible();
    });

    test('redirects dashboard visitors to invoices when dashboard is disabled', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: { enable_client_portal_dashboard: false },
        });

        await expectPortalRouteRedirects(
            page,
            '/client/dashboard',
            /\/client\/invoices(?:\/|$)/,
        );
    });

    test('still allows statement access when optional modules are disabled', async ({
        api,
        companyGuard,
        page,
    }) => {
        await companyGuard.update({ enabled_modules: 65535 & ~4096 & ~4 });
        await createAndLogInClient(api, page);

        const response = await page.goto('/client/statement');
        expect(response?.ok()).toBe(true);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('Statement');
        await expect(page.locator('#pdf-download')).toBeVisible();
    });
});
