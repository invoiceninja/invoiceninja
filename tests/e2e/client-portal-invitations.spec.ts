import { withGuestPortalPage } from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createSentCredit,
    createSentInvoice,
    createSentQuote,
    expectInvitationUrl,
    invitationKey,
} from './portal-entity-helpers';

test.describe('Client portal invitations', () => {
    test('opens a quote via invitation link without an existing session', async ({
        api,
        browser,
    }) => {
        const client = await api.createEntity('clients', {
            name: uniqueName('guest-quote-client'),
            settings: { enable_client_portal: true },
            contacts: [
                {
                    first_name: 'Guest',
                    last_name: 'Quote',
                    email: `${uniqueName('guest-quote')}@example.test`,
                },
            ],
        });
        const quote = await createSentQuote(api, client as never, {
            label: uniqueName('guest-quote'),
        });

        await withGuestPortalPage(browser, async (page) => {
            await page.goto(`/client/quote/${invitationKey(quote)}`);

            await expect(page).toHaveURL(
                expectInvitationUrl(
                    `/client/quotes/${quote.id}`,
                    `/client/quote/${invitationKey(quote)}`,
                ),
            );
            await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
                /Quote/,
            );
        });
    });

    test('opens an invoice invitation for a logged-out visitor', async ({
        api,
        browser,
    }) => {
        const client = await api.createEntity('clients', {
            name: uniqueName('guest-invoice-client'),
            settings: { enable_client_portal: true },
            contacts: [
                {
                    first_name: 'Guest',
                    last_name: 'Invoice',
                    email: `${uniqueName('guest-invoice')}@example.test`,
                },
            ],
        });
        const invoice = await createSentInvoice(api, client as never, {
            label: uniqueName('guest-invoice'),
        });

        await withGuestPortalPage(browser, async (page) => {
            await page.goto(`/client/invoice/${invitationKey(invoice)}`);

            await expect(page).toHaveURL(
                expectInvitationUrl(
                    `/client/invoices/${invoice.id}`,
                    `/client/invoice/${invitationKey(invoice)}`,
                ),
            );
            await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
                'View Invoice',
            );
        });
    });

    test('opens email preferences and shows the unsubscribe confirmation', async ({
        api,
        page,
    }) => {
        const client = await api.createEntity('clients', {
            name: uniqueName('prefs-client'),
            settings: { enable_client_portal: true },
            contacts: [
                {
                    first_name: 'Prefs',
                    last_name: 'Contact',
                    email: `${uniqueName('prefs')}@example.test`,
                },
            ],
        });
        const invoice = await createSentInvoice(api, client as never, {
            label: uniqueName('prefs-invoice'),
        });
        const key = invitationKey(invoice);

        await page.goto(`/client/email_preferences/invoice/${key}`);
        await expect(
            page.getByRole('heading', { name: 'Email Preferences' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Unsubscribe' }).first().click();
        await expect(page.getByText(/are you sure/i)).toBeVisible();
        await expect(page.locator('.button-danger')).toBeVisible();
    });

    test('renders unsubscribe confirmation for quote invitations', async ({
        api,
        page,
    }) => {
        const client = await api.createEntity('clients', {
            name: uniqueName('quote-unsub-client'),
            settings: { enable_client_portal: true },
            contacts: [
                {
                    first_name: 'Quote',
                    last_name: 'Unsub',
                    email: `${uniqueName('quote-unsub')}@example.test`,
                },
            ],
        });
        const quote = await createSentQuote(api, client as never, {
            label: uniqueName('quote-unsub'),
        });
        const key = invitationKey(quote);

        await page.goto(`/client/email_preferences/quote/${key}`);
        await expect(
            page.getByRole('heading', { name: 'Email Preferences' }),
        ).toBeVisible();
        await page.getByRole('button', { name: 'Unsubscribe' }).first().click();
        await expect(page.getByText(/are you sure/i)).toBeVisible();
    });

    test('opens a credit invitation link', async ({ api, browser }) => {
        const client = await api.createEntity('clients', {
            name: uniqueName('guest-credit-client'),
            settings: { enable_client_portal: true },
            contacts: [
                {
                    first_name: 'Guest',
                    last_name: 'Credit',
                    email: `${uniqueName('guest-credit')}@example.test`,
                },
            ],
        });
        const credit = await createSentCredit(api, client as never, {
            label: uniqueName('guest-credit'),
        });

        await withGuestPortalPage(browser, async (page) => {
            await page.goto(`/client/credit/${invitationKey(credit)}`);
            await expect(page).toHaveURL(
                expectInvitationUrl(
                    `/client/credits/${credit.id}`,
                    `/client/credit/${invitationKey(credit)}`,
                ),
            );
        });
    });

    test('prompts a contact without a password to set one from an invitation', async ({
        api,
        browser,
    }) => {
        test.setTimeout(90_000);

        const marker = uniqueName('set-password');
        const client = await api.createEntity('clients', {
            name: marker,
            settings: {
                enable_client_portal: true,
                enable_client_portal_password: true,
            },
            contacts: [
                {
                    first_name: 'Set',
                    last_name: 'Password',
                    email: `${marker}@example.test`,
                },
            ],
        });
        const quote = await createSentQuote(api, client as never, {
            label: uniqueName('set-password-quote'),
        });
        const key = invitationKey(quote);

        await withGuestPortalPage(browser, async (page) => {
            await page.goto(`/client/quote/${key}`);

            // Invitation router should send password-less contacts to set_password.
            if (!page.url().includes('set_password')) {
                const { invitationSetPasswordHash } = await import(
                    './portal-auth-helpers'
                );
                await page.goto(
                    `/set_password?entity_type=quote&invitation_key=${key}&hash=${invitationSetPasswordHash(key)}`,
                );
            }

            await expect(page.locator('#password')).toBeVisible({
                timeout: 15_000,
            });
            await page.locator('#password').fill('PortalSet123!');
            await page.getByRole('button', { name: /Continue/i }).click();

            await expect(page).toHaveURL(
                expectInvitationUrl(
                    `/client/quotes/${quote.id}`,
                    `/client/quote/${key}`,
                ),
                { timeout: 30_000 },
            );
        });
    });
});
