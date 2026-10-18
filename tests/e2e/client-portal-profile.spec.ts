import {
    createAndLogInClient,
    dismissCookieConsent,
    fillLivewireInput,
    openProfilePage,
    submitLivewireComponent,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';

test.describe('Client portal profile', () => {
    test('renders every editable profile section', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        await openProfilePage(page, client.contacts[0].id);

        await expect(page.getByRole('heading', { name: 'Client Details' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Contact Details' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Billing Address' })).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Shipping Address' })).toBeVisible();

        await expect(page.locator('#client_name')).toBeVisible();
        await expect(page.locator('#contact_first_name')).toBeVisible();
        await expect(page.locator('#address1')).toBeVisible();
        await expect(page.locator('#shipping_address1')).toBeVisible();
    });

    test('updates contact, client, and address details', async ({ api, page }) => {
        test.setTimeout(120_000);

        const client = await createAndLogInClient(api, page);
        const marker = uniqueName('profile-update');
        await openProfilePage(page, client.contacts[0].id);

        await test.step('contact details', async () => {
            await fillLivewireInput(page, '#contact_first_name', `${marker}-first`);
            await fillLivewireInput(page, '#contact_last_name', `${marker}-last`);
            await fillLivewireInput(page, '#contact_phone', '555-0100');
            await submitLivewireComponent(page, '#update_client');
            await expect(page.locator('[data-ref="update-contact-details"]')).toHaveText(
                /Saved at/i,
                { timeout: 15_000 },
            );
        });

        await test.step('client details', async () => {
            await fillLivewireInput(page, '#client_name', `${marker}-client`);
            await fillLivewireInput(page, '#client_website', 'https://example.test');
            await fillLivewireInput(page, '#client_vat_number', 'FR12345678901');
            await submitLivewireComponent(page, '#update_contact');
            await expect(page.locator('#update_contact .button-primary')).toHaveText(
                /Saved at/i,
                { timeout: 15_000 },
            );
        });

        await test.step('billing address', async () => {
            await fillLivewireInput(page, '#address1', `${marker} Billing Street`);
            await fillLivewireInput(page, '#city', 'Paris');
            await fillLivewireInput(page, '#postal_code', '75001');
            await submitLivewireComponent(page, '#update_billing_address');
            await expect(
                page.locator('#update_billing_address .button-primary'),
            ).toHaveText(/Saved at/i, { timeout: 15_000 });
        });

        await test.step('shipping address', async () => {
            await fillLivewireInput(
                page,
                '#shipping_address1',
                `${marker} Shipping Street`,
            );
            await fillLivewireInput(page, '#shipping_city', 'Lyon');
            await fillLivewireInput(page, '#shipping_postal_code', '69001');
            await submitLivewireComponent(page, '#update_shipping_address');
            await expect(
                page.locator('#update_shipping_address .button-primary'),
            ).toHaveText(/Saved at/i, { timeout: 15_000 });
        });

        await page.reload();
        await dismissCookieConsent(page);

        await expect(page.locator('#contact_first_name')).toHaveValue(`${marker}-first`);
        await expect(page.locator('#client_name')).toHaveValue(`${marker}-client`);
        await expect(page.locator('#address1')).toHaveValue(`${marker} Billing Street`);
        await expect(page.locator('#shipping_address1')).toHaveValue(
            `${marker} Shipping Street`,
        );
    });

    test('hides profile navigation when client profile updates are disabled', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: {
                enable_client_profile_update: false,
            },
        });

        await page.locator('[data-ref="client-profile-dropdown"]').click();
        await expect(
            page.locator('[data-ref="client-profile-dropdown-settings"]'),
        ).toHaveCount(0);
        await expect(page.getByRole('link', { name: 'Log Out', exact: true })).toBeVisible();
    });

    test('keeps profile sections editable and persists website updates', async ({
        api,
        page,
    }) => {
        test.setTimeout(90_000);

        const client = await createAndLogInClient(api, page);
        await openProfilePage(page, client.contacts[0].id);

        await expect(page.getByRole('heading', { name: 'Client Details' })).toBeVisible();
        await expect(page.locator('#client_website')).toBeVisible();

        const website = `https://example-${uniqueName('site').slice(-6)}.test`;
        await fillLivewireInput(page, '#client_website', website);
        await submitLivewireComponent(page, '#update_contact');
        await expect(page.locator('#update_contact .button-primary')).toHaveText(
            /Saved at/i,
            { timeout: 15_000 },
        );

        await page.reload();
        await dismissCookieConsent(page);
        await expect(page.locator('#client_website')).toHaveValue(website);
    });

    test('redirects direct profile edits when updates are disabled', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                enable_client_profile_update: false,
            },
        });

        const response = await page.goto(
            `/client/profile/${client.contacts[0].id}/edit`,
        );

        expect(response?.ok()).toBe(true);
        await expect(page).toHaveURL(/\/client\/dashboard(?:\/|$)/);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('Dashboard');
    });
});
