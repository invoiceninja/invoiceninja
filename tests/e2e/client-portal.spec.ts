import { type ApiEntity } from './api-helpers';
import {
    createAndLogInClient,
    expectPortalPage,
    type PortalClient,
    waitForAlpine,
} from './client-portal-helpers';
import { expect, test, uniqueName, type ApiFixture } from './fixtures';

const SIDEBAR_PAGES = [
    { id: 'dashboard', path: '/client/dashboard', title: 'Dashboard' },
    { id: 'invoices', path: '/client/invoices', title: 'Invoices' },
    {
        id: 'recurring_invoices',
        path: '/client/recurring_invoices',
        title: 'Recurring Invoices',
    },
    { id: 'payments', path: '/client/payments', title: 'Payments' },
    { id: 'quotes', path: '/client/quotes', title: 'Quotes' },
    { id: 'credits', path: '/client/credits', title: 'Credits' },
    {
        id: 'payment_methods',
        path: '/client/payment_methods',
        title: 'Payment Methods',
    },
    { id: 'documents', path: '/client/documents', title: 'Documents' },
    { id: 'tasks', path: '/client/tasks', title: 'Tasks' },
    { id: 'projects', path: '/client/projects', title: 'Projects' },
    { id: 'statement', path: '/client/statement', title: 'Statement' },
    {
        id: 'subscriptions',
        path: '/client/subscriptions',
        title: 'Subscriptions',
    },
    {
        id: 'pre_payment',
        path: '/client/pre_payments',
        title: 'Pre Payment',
    },
] as const;

interface Invitation {
    key: string;
}

interface PortalEntity extends ApiEntity {
    id: string;
    number?: string;
    invitations?: Invitation[];
}

interface PortalResources {
    invoice: PortalEntity;
    recurringInvoice: PortalEntity;
    payment: PortalEntity;
    quote: PortalEntity;
    credit: PortalEntity;
    project: PortalEntity;
}

test.describe('Public client portal pages', () => {
    test('renders the client entry point and login page', async ({ page }) => {
        for (const path of ['/client/', '/client/login']) {
            await test.step(path, async () => {
                const response = await page.goto(path);

                expect(response?.ok()).toBe(true);
                await expect(
                    page.getByRole('heading', { name: 'Client Portal' }),
                ).toBeVisible();
                await expect(page.getByLabel('Email address')).toBeVisible();
                await expect(
                    page.getByLabel('Password', { exact: true }),
                ).toBeVisible();
            });
        }
    });

    test('renders password recovery', async ({ page }) => {
        const response = await page.goto('/client/password/reset');

        expect(response?.ok()).toBe(true);
        await expect(
            page.getByRole('heading', { name: 'Password Recovery' }),
        ).toBeVisible();
        await expect(page.getByLabel('Email address')).toBeVisible();
    });

    test('renders registration when the company enables it', async ({
        companyGuard,
        page,
    }) => {
        const company = await companyGuard.update({ client_can_register: true });
        const response = await page.goto(
            `/client/register/${company.company_key}`,
        );

        expect(response?.ok()).toBe(true);
        await expect(
            page.getByRole('heading', { name: 'Register' }),
        ).toBeVisible();
        await expect(page.locator('#register-form')).toBeVisible();
    });
});

test.describe('Authenticated client portal pages', () => {
    test('renders every page exposed by a fully enabled sidebar', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page);

        const sidebar = page.locator('nav a[id]:visible');
        await expect(sidebar).toHaveCount(SIDEBAR_PAGES.length);
        expect(
            await sidebar.evaluateAll((links) =>
                links.map((link) => link.id),
            ),
        ).toEqual(SIDEBAR_PAGES.map(({ id }) => id));

        for (const portalPage of SIDEBAR_PAGES) {
            await test.step(portalPage.title, async () => {
                await expectPortalPage(
                    page,
                    portalPage.path,
                    portalPage.title,
                );
            });
        }
    });

    test('opens the profile page and logs out', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const contact = client.contacts[0];

        await waitForAlpine(page);
        await page.locator('[data-ref="client-profile-dropdown"]').click();
        const profileLink = page.locator(
            '[data-ref="client-profile-dropdown-settings"]',
        );
        await expect(profileLink).toHaveAttribute(
            'href',
            new RegExp(`/client/profile/${contact.id}/edit$`),
        );
        await profileLink.click();

        await expect(page).toHaveURL(
            new RegExp(`/client/profile/${contact.id}/edit$`),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'Client Information',
        );

        await waitForAlpine(page);
        await page.locator('[data-ref="client-profile-dropdown"]').click();
        await page.getByRole('link', { name: 'Log Out', exact: true }).click();

        await expect(page).toHaveURL(/\/client\/login(?:\/|$)/);
        await expect(
            page.getByRole('heading', { name: 'Client Portal' }),
        ).toBeVisible();
    });

    test('renders entity detail pages owned by the client', async ({
        api,
        notificationGuard,
        page,
    }) => {
        test.setTimeout(120_000);

        const client = await createAndLogInClient(api, page);
        await notificationGuard.suppressPaymentEmails();
        const resources = await createPortalResources(api, client);
        const detailPages = [
            {
                path: `/client/invoices/${resources.invoice.id}`,
                title: 'View Invoice',
            },
            {
                path: `/client/recurring_invoices/${resources.recurringInvoice.id}`,
                title: 'Recurring Invoice',
            },
            {
                path: `/client/payments/${resources.payment.id}`,
                title: 'Payment',
            },
            {
                path: `/client/quotes/${resources.quote.id}`,
                title: /Quote/,
            },
            {
                path: `/client/credits/${resources.credit.id}`,
                title: 'View Credit',
            },
            {
                path: `/client/projects/${resources.project.id}`,
                title: 'View Project',
            },
        ];

        for (const detailPage of detailPages) {
            await test.step(detailPage.path, async () => {
                await expectPortalPage(
                    page,
                    detailPage.path,
                    detailPage.title,
                );
            });
        }
    });

    test('resolves invitation and direct payment links into portal pages', async ({
        api,
        notificationGuard,
        page,
    }) => {
        test.setTimeout(120_000);

        const client = await createAndLogInClient(api, page);
        await notificationGuard.suppressPaymentEmails();
        const resources = await createPortalResources(api, client);
        const invitationPages = [
            {
                entity: 'invoice',
                resource: resources.invoice,
                target: `/client/invoices/${resources.invoice.id}`,
                title: 'View Invoice',
            },
            {
                entity: 'recurring_invoice',
                resource: resources.recurringInvoice,
                target: `/client/recurring_invoices/${resources.recurringInvoice.id}`,
                title: 'Recurring Invoice',
            },
            {
                entity: 'quote',
                resource: resources.quote,
                target: `/client/quotes/${resources.quote.id}`,
                title: /Quote/,
            },
            {
                entity: 'credit',
                resource: resources.credit,
                target: `/client/credits/${resources.credit.id}`,
                title: 'View Credit',
            },
        ];

        for (const invitationPage of invitationPages) {
            await test.step(invitationPage.entity, async () => {
                const key = invitationKey(invitationPage.resource);
                const response = await page.goto(
                    `/client/${invitationPage.entity}/${key}`,
                );
                const invitationPath = `/client/${invitationPage.entity}/${key}`;

                expect(response?.ok()).toBe(true);
                await expect(page).toHaveURL(
                    new RegExp(
                        `(?:${invitationPage.target}|${invitationPath})(?:\\?.*)?$`,
                    ),
                );
                await expect(page.locator('main')).toBeVisible();
                await expect(
                    page.locator('[data-ref="meta-title"]'),
                ).toHaveText(invitationPage.title);
            });
        }

        const paymentPath = `/client/payments/${resources.payment.id}`;
        const paymentResponse = await page.goto(
            `/client/payment/${client.contacts[0].contact_key}/${resources.payment.id}?next=${paymentPath}`,
        );
        expect(paymentResponse?.ok()).toBe(true);
        await expect(page).toHaveURL(
            new RegExp(`${paymentPath}$`),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'Payment',
        );
    });

    test('renders invitation email preferences and unsubscribe pages', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const invoice = await createInvoice(api, client);
        const key = invitationKey(invoice);

        const preferencesResponse = await page.goto(
            `/client/email_preferences/invoice/${key}`,
        );
        expect(preferencesResponse?.ok()).toBe(true);
        await expect(
            page.getByRole('heading', { name: 'Email Preferences' }),
        ).toBeVisible();

        const unsubscribeResponse = await page.goto(
            `/client/unsubscribe/invoice/${key}`,
        );
        expect(unsubscribeResponse?.ok()).toBe(true);
        await expect(
            page.getByRole('heading', { name: 'Unsubscribed' }),
        ).toBeVisible();
    });
});

async function createPortalResources(
    api: ApiFixture,
    client: PortalClient,
): Promise<PortalResources> {
    const invoice = await createInvoice(api, client);
    const recurringInvoice =
        await api.createEntityFromBlank<PortalEntity>('recurring_invoices', {
            ...documentDefaults(client),
            frequency_id: '5',
            next_send_date: isoDate(30),
            remaining_cycles: -1,
            line_items: [lineItem('portal-recurring-invoice', 11)],
        });
    const payment = await api.createEntity<PortalEntity>('payments', {
        client_id: client.id,
        amount: 12,
        date: isoDate(),
        email_receipt: 'false',
    });
    const quote = await api.createEntityFromBlank<PortalEntity>('quotes', {
        ...documentDefaults(client),
        line_items: [lineItem('portal-quote', 13)],
    });
    const credit = await api.createEntityFromBlank<PortalEntity>('credits', {
        ...documentDefaults(client),
        line_items: [lineItem('portal-credit', 14)],
    });
    const project = await api.createEntity<PortalEntity>('projects', {
        client_id: client.id,
        name: uniqueName('portal-project'),
        public_notes: 'Project created by the client portal Playwright suite.',
    });

    return {
        invoice,
        recurringInvoice,
        payment,
        quote,
        credit,
        project,
    };
}

async function createInvoice(
    api: ApiFixture,
    client: PortalClient,
): Promise<PortalEntity> {
    return api.createEntityFromBlank<PortalEntity>('invoices', {
        ...documentDefaults(client),
        due_date: isoDate(30),
        line_items: [lineItem('portal-invoice', 10)],
    });
}

function documentDefaults(client: PortalClient) {
    return {
        client_id: client.id,
        date: isoDate(),
        status_id: '2',
    };
}

function isoDate(daysFromToday = 0): string {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() + daysFromToday);

    return date.toISOString().slice(0, 10);
}

function lineItem(label: string, cost: number) {
    return {
        product_key: uniqueName(label),
        notes: `${label} created by Playwright`,
        cost,
        quantity: 1,
    };
}

function invitationKey(entity: PortalEntity): string {
    const key = entity.invitations?.[0]?.key;

    if (!key) {
        throw new Error(`Entity ${entity.id} did not return an invitation key.`);
    }

    return key;
}
