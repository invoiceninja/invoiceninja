import { expect, type Page } from '@playwright/test';
import { type ApiEntity, updateClient, type ClientEntity } from './api-helpers';
import { type ApiFixture, uniqueName } from './fixtures';

export interface PortalContact {
    id: string;
    contact_key: string;
    email: string;
}

export interface PortalClient extends ApiEntity {
    id: string;
    name: string;
    contacts: PortalContact[];
}

export interface PortalClientOptions {
    name?: string;
    settings?: Record<string, unknown>;
    contact?: {
        first_name?: string;
        last_name?: string;
        email?: string;
        password?: string;
    };
}

export async function createAndLogInClient(
    api: ApiFixture,
    page: Page,
    options: PortalClientOptions = {},
): Promise<PortalClient> {
    // Entity pages embed generated PDFs. Their binary rendering is covered by
    // backend tests and would otherwise dominate this page-navigation suite.
    await page.route('**/client/showBlob/**', async (route) => {
        await route.abort();
    });

    const marker = options.name ?? uniqueName('playwright-portal-client');
    const client = await api.createEntity<PortalClient>('clients', {
        name: marker,
        settings: {
            enable_client_portal: true,
            enable_client_portal_dashboard: true,
            enable_client_portal_password: false,
            enable_client_profile_update: true,
            enable_client_portal_tasks: true,
            client_initiated_payments: true,
            client_portal_enable_uploads: true,
            client_manual_payment_notification: false,
            show_all_tasks_client_portal: 'all',
            auto_convert_quote: false,
            require_quote_signature: false,
            show_accept_quote_terms: false,
            accept_client_input_quote_approval: false,
            ...options.settings,
        },
        contacts: [
            {
                first_name: 'Playwright',
                last_name: 'Portal',
                email: `${marker}@example.test`,
                password: 'Portal123',
                ...options.contact,
            },
        ],
    });

    const contact = client.contacts[0];

    if (!contact?.contact_key) {
        throw new Error('Created client did not return a portal contact key.');
    }

    const response = await page.goto(`/client/key_login/${contact.contact_key}`);

    expect(response).not.toBeNull();
    expect(response?.ok()).toBe(true);
    await expect(page.locator('main')).toBeVisible();
    await dismissCookieConsent(page);

    return client;
}

export async function dismissCookieConsent(page: Page): Promise<void> {
    const consentButton = page.getByRole('button', { name: 'Got it!' });

    if (await consentButton.isVisible().catch(() => false)) {
        await consentButton.click();
    }
}

export async function openProfilePage(
    page: Page,
    contactId: string,
): Promise<void> {
    await page.locator('[data-ref="client-profile-dropdown"]').click();
    await page.locator('[data-ref="client-profile-dropdown-settings"]').click();
    await expect(page).toHaveURL(
        new RegExp(`/client/profile/${contactId}/edit$`),
    );
    await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
        'Client Information',
    );
}

export async function expectSidebarLinkIds(
    page: Page,
    expectedIds: string[],
): Promise<void> {
    await page.goto('/client/dashboard');

    const sidebar = desktopSidebarLinks(page);
    await expect(sidebar).toHaveCount(expectedIds.length);
    expect(
        await sidebar.evaluateAll((links) => links.map((link) => link.id)),
    ).toEqual(expectedIds);
}

export async function expectSidebarMissing(
    page: Page,
    hiddenIds: string[],
): Promise<void> {
    for (const id of hiddenIds) {
        await expect(page.locator(`.hidden.md\\:flex nav a#${id}`)).toHaveCount(0);
    }
}

function desktopSidebarLinks(page: Page) {
    return page.locator('.hidden.md\\:flex nav a[id]');
}

export async function expectPortalRouteForbidden(
    page: Page,
    path: string,
): Promise<void> {
    const response = await page.goto(path);

    expect(response, `${path} should return an HTTP response`).not.toBeNull();
    expect(
        response?.status(),
        `${path} should be forbidden when the module is disabled`,
    ).toBe(403);
}

export async function expectPortalRouteRedirects(
    page: Page,
    path: string,
    destination: string | RegExp,
): Promise<void> {
    const response = await page.goto(path);

    expect(response, `${path} should return an HTTP response`).not.toBeNull();
    expect(response?.ok(), `${path} should redirect successfully`).toBe(true);
    await expect(page).toHaveURL(destination);
}

export async function patchPortalClient(
    api: ApiFixture,
    client: PortalClient,
    changes: Record<string, unknown>,
): Promise<PortalClient> {
    return updateClient(api.context, client as ClientEntity, changes) as Promise<PortalClient>;
}

export async function expectPortalPage(
    page: Page,
    path: string,
    title: string | RegExp,
): Promise<void> {
    const response = await page.goto(path);

    expect(response, `${path} should return an HTTP response`).not.toBeNull();
    expect(response?.ok(), `${path} should return a successful response`).toBe(
        true,
    );
    await expect(page.locator('main')).toBeVisible();
    await expect(page.locator('[data-ref="meta-title"]')).toHaveText(title);
}

export async function loginWithEmailPassword(
    page: Page,
    email: string,
    password: string,
): Promise<void> {
    await page.route('**/client/showBlob/**', async (route) => {
        await route.abort();
    });

    const response = await page.goto('/client/login');
    expect(response?.ok()).toBe(true);
    await page.locator('#email').fill(email);
    await page.locator('#password').fill(password);
    await page.locator('#loginBtn').click();
    await expect(page.locator('main')).toBeVisible();
    await dismissCookieConsent(page);
}

export async function selectEntityTableRow(
    page: Page,
    tableSelector: string,
    entityNumber: string,
): Promise<void> {
    await page
        .locator(`${tableSelector} tbody tr`)
        .filter({ hasText: entityNumber })
        .getByRole('checkbox')
        .check();
}

export async function drawSignature(page: Page): Promise<void> {
    const canvas = page.locator('#signature-pad');
    await expect(canvas).toBeVisible();
    const box = await canvas.boundingBox();

    if (!box) {
        throw new Error('Could not locate the signature pad canvas.');
    }

    await page.mouse.move(box.x + 20, box.y + 20);
    await page.mouse.down();
    await page.mouse.move(box.x + 120, box.y + 80);
    await page.mouse.up();
}
