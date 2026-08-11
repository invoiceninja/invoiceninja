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
        const context = await browser.newContext();
        const page = await context.newPage();
        await page.route('**/client/showBlob/**', async (route) => {
            await route.abort();
        });

        await page.goto(`/client/quote/${invitationKey(quote)}`);

        await expect(page).toHaveURL(
            expectInvitationUrl(
                `/client/quotes/${quote.id}`,
                `/client/quote/${invitationKey(quote)}`,
            ),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(/Quote/);

        await context.close();
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
        const context = await browser.newContext();
        const page = await context.newPage();
        await page.route('**/client/showBlob/**', async (route) => {
            await route.abort();
        });

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

        await context.close();
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
            name: uniqueName('unsub-client'),
            settings: { enable_client_portal: true },
            contacts: [
                {
                    first_name: 'Unsub',
                    last_name: 'Contact',
                    email: `${uniqueName('unsub')}@example.test`,
                },
            ],
        });
        const quote = await createSentQuote(api, client as never, {
            label: uniqueName('unsub-quote'),
        });

        await page.goto(`/client/unsubscribe/quote/${invitationKey(quote)}`);
        await expect(
            page.getByRole('heading', { name: 'Unsubscribed' }),
        ).toBeVisible();
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
        const context = await browser.newContext();
        const page = await context.newPage();
        await page.route('**/client/showBlob/**', async (route) => {
            await route.abort();
        });

        await page.goto(`/client/credit/${invitationKey(credit)}`);
        await expect(page).toHaveURL(
            expectInvitationUrl(
                `/client/credits/${credit.id}`,
                `/client/credit/${invitationKey(credit)}`,
            ),
        );

        await context.close();
    });
});
