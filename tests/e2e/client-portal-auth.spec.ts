import {
    createAndLogInClient,
    dismissCookieConsent,
    loginWithEmailPassword,
    type PortalClient,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';

test.describe('Client portal authentication', () => {
    test('logs in with email and password when portal passwords are enabled', async ({
        api,
        page,
    }) => {
        const marker = uniqueName('password-login');
        const client = await api.createEntity<PortalClient>('clients', {
            name: marker,
            settings: {
                enable_client_portal: true,
                enable_client_portal_password: true,
            },
            contacts: [
                {
                    first_name: 'Password',
                    last_name: 'Login',
                    email: `${marker}@example.test`,
                    password: 'Portal123!',
                },
            ],
        });

        await loginWithEmailPassword(
            page,
            client.contacts[0].email,
            'Portal123!',
        );

        await expect(page).toHaveURL(/\/client\/(dashboard|invoices)(?:\/|$)/);
        await expect(page.locator('main')).toBeVisible();
    });

    test('shows validation errors for invalid login credentials', async ({
        page,
    }) => {
        await page.goto('/client/login');
        await page.locator('#email').fill('missing-user@example.test');
        await page.locator('#password').fill('wrong-password');
        await page.locator('#loginBtn').click();

        await expect(page).toHaveURL(/\/client\/login(?:\/|$|\?)/);
        await expect(page.locator('.validation-fail')).toBeVisible();
    });

    test('renders forgot password link on the login page', async ({ page }) => {
        await page.goto('/client/login');
        await dismissCookieConsent(page);
        await expect(
            page.getByRole('link', { name: 'Forgot your password?' }),
        ).toHaveAttribute('href', /\/client\/password\/reset/);
    });
});
