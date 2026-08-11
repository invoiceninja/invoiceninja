import {
    createAndLogInClient,
    openProfilePage,
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
        test.setTimeout(90_000);

        const client = await createAndLogInClient(api, page);
        const marker = uniqueName('profile-update');
        await openProfilePage(page, client.contacts[0].id);

        await test.step('contact details', async () => {
            await page.locator('#contact_first_name').fill(`${marker}-first`);
            await page.locator('#contact_last_name').fill(`${marker}-last`);
            await page.locator('#contact_phone').fill('555-0100');
            await page.locator('[data-ref="update-contact-details"]').click();
            await expect(page.locator('[data-ref="update-contact-details"]')).toHaveText(
                /Saved at/i,
            );
        });

        await test.step('client details', async () => {
            await page.locator('#client_name').fill(`${marker}-client`);
            await page.locator('#client_website').fill('https://example.test');
            await page.locator('#client_vat_number').fill('FR12345678901');
            await page.locator('#update_contact .button-primary').click();
            await expect(page.locator('#update_contact .button-primary')).toHaveText(
                /Saved at/i,
            );
        });

        await test.step('billing address', async () => {
            await page.locator('#address1').fill(`${marker} Billing Street`);
            await page.locator('#city').fill('Paris');
            await page.locator('#postal_code').fill('75001');
            await page.locator('#update_billing_address .button-primary').click();
            await expect(
                page.locator('#update_billing_address .button-primary'),
            ).toHaveText(/Saved at/i);
        });

        await test.step('shipping address', async () => {
            await page.locator('#shipping_address1').fill(`${marker} Shipping Street`);
            await page.locator('#shipping_city').fill('Lyon');
            await page.locator('#shipping_postal_code').fill('69001');
            await page.locator('#update_shipping_address .button-primary').click();
            await expect(
                page.locator('#update_shipping_address .button-primary'),
            ).toHaveText(/Saved at/i);
        });

        await page.reload();

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
