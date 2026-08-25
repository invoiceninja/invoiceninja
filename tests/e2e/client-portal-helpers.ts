import {
    chromium,
    expect,
    type Browser,
    type BrowserContext,
    type Page,
} from '@playwright/test';
import { type ApiEntity, updateClient, type ClientEntity } from './api-helpers';
import { resolvePlaywrightUrls } from './environment';
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
        /** Pass `null` to create a contact with no portal password. */
        password?: string | null;
    };
}

/** Most portal tests skip the relatively expensive PDF generation request. */
export async function blockPdfBlobs(page: Page): Promise<void> {
    await page.route('**/client/showBlob/**', async (route) => {
        await route.abort();
    });
}

/** Re-enables real PDF responses for the focused preview-resolution tests. */
export async function allowPdfBlobs(page: Page): Promise<void> {
    await page.unroute('**/client/showBlob/**');
}

/**
 * Creates a portal client through the API without establishing a portal
 * session. Use this when the test needs to exercise the portal's own
 * authentication gates (password protection, set-password, login).
 */
export async function createPortalClient(
    api: ApiFixture,
    options: PortalClientOptions = {},
): Promise<PortalClient> {
    const marker = options.name ?? uniqueName('playwright-portal-client');
    const { password, ...contactOverrides } = options.contact ?? {};

    return api.createEntity<PortalClient>('clients', {
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
                // A `null` password leaves the contact without one so the
                // set-password gate can be exercised.
                ...(password === null ? {} : { password: password ?? 'Portal123' }),
                ...contactOverrides,
            },
        ],
    });
}

export function portalContact(client: PortalClient): PortalContact {
    const contact = client.contacts?.[0];

    if (!contact?.contact_key) {
        throw new Error('Created client did not return a portal contact key.');
    }

    return contact;
}

export async function logInPortalClient(
    page: Page,
    client: PortalClient,
): Promise<void> {
    await blockPdfBlobs(page);

    const contact = portalContact(client);
    const response = await page.goto(`/client/key_login/${contact.contact_key}`);

    expect(response).not.toBeNull();
    expect(response?.ok()).toBe(true);
    await expect(page.locator('main')).toBeVisible();
    await dismissCookieConsent(page);
}

export async function createAndLogInClient(
    api: ApiFixture,
    page: Page,
    options: PortalClientOptions = {},
): Promise<PortalClient> {
    const client = await createPortalClient(api, options);
    await logInPortalClient(page, client);

    return client;
}

/** Opens an isolated browser context so invitation links start without a session. */
export async function openGuestPortalPage(
    browser: Browser,
): Promise<{ context: BrowserContext; page: Page }> {
    const { baseUrl } = resolvePlaywrightUrls();
    const context = await browser.newContext({
        baseURL: baseUrl,
        bypassCSP: true,
    });
    const page = await context.newPage();
    await blockPdfBlobs(page);

    return { context, page };
}

/**
 * Same as `openGuestPortalPage`, but always closes the guest context — including
 * on assertion failure / timeout — so headed runs do not leave zombie windows.
 */
export async function withGuestPortalPage<T>(
    browser: Browser,
    run: (page: Page) => Promise<T>,
): Promise<T> {
    const { context, page } = await openGuestPortalPage(browser);

    try {
        return await run(page);
    } finally {
        await context.close().catch(() => undefined);
    }
}

/**
 * Launch a Chromium that is independent of the worker browser. Use this when
 * earlier suite tests may have wedged the shared Playwright browser so
 * `setting up "context"` hangs until the process is killed.
 */
export async function launchIsolatedBrowser(): Promise<Browser> {
    return chromium.launch({
        headless: !process.argv.includes('--headed'),
        args: [
            '--disable-web-security',
            '--disable-features=Translate,BackForwardCache',
        ],
    });
}

export async function withIsolatedBrowser<T>(
    run: (browser: Browser) => Promise<T>,
): Promise<T> {
    const browser = await launchIsolatedBrowser();

    try {
        return await run(browser);
    } finally {
        await browser.close().catch(() => undefined);
    }
}

export async function dismissCookieConsent(page: Page): Promise<void> {
    const consentButton = page.getByRole('button', { name: 'Got it!' });

    if (await consentButton.isVisible().catch(() => false)) {
        await consentButton.click();
    }

    // Cookie banner can re-init after Livewire navigations; remove leftovers so
    // they cannot intercept Pay Now / dropdown clicks.
    await page.evaluate(() => {
        document.querySelectorAll('.cc-window').forEach((element) => {
            element.remove();
        });
    });
}

/**
 * Clears the fixed overlays that cover the bottom of portal pages: the cookie
 * consent banner (which initialises on window load, so it can appear after an
 * earlier dismissal attempt) and Laravel's debug bar.
 */
export async function clearPortalOverlays(page: Page): Promise<void> {
    await page.waitForLoadState('load');
    await dismissCookieConsent(page);
    await page.evaluate(() => {
        document
            .querySelectorAll('.cc-window, .phpdebugbar')
            .forEach((element) => element.remove());
    });
}

/**
 * Portal dropdowns and modals are driven by Alpine, which only binds its
 * `@click` handlers once the bundle has executed. Clicking earlier is a silent
 * no-op, so wait for Alpine before driving those controls.
 */
export async function waitForAlpine(page: Page): Promise<void> {
    await page.waitForFunction(() => Boolean(window.Alpine));
}

export async function waitForLivewire(
    page: Page,
    action: () => Promise<void>,
): Promise<void> {
    const responsePromise = page.waitForResponse(
        (response) =>
            /\/livewire(\/|$)/.test(response.url()) &&
            response.request().method() === 'POST',
        { timeout: 30_000 },
    );

    await action();
    await responsePromise;
}

export async function fillLivewireInput(
    page: Page,
    selector: string,
    value: string,
): Promise<void> {
    const input = page.locator(selector);
    await expect(input).toBeVisible();
    await page.waitForFunction(() => Boolean(window.Livewire?.find));

    await input.evaluate((el, nextValue) => {
        const field = el as HTMLInputElement;
        const root = field.closest('[wire\\:id]');
        const id = root?.getAttribute('wire:id');
        const property = field.getAttribute('wire:model') ?? field.name;
        const livewire = (
            window as unknown as {
                Livewire: {
                    find: (id: string) => {
                        set: (property: string, value: string) => void;
                    };
                };
            }
        ).Livewire;

        if (!id || !property) {
            throw new Error(
                `Unable to set Livewire property for #${field.id || field.name}`,
            );
        }

        livewire.find(id).set(property, nextValue);
    }, value);

    await expect(input).toHaveValue(value);
}

export async function submitLivewireComponent(
    page: Page,
    formSelector: string,
): Promise<void> {
    await dismissCookieConsent(page);
    await page.waitForFunction(() => Boolean(window.Livewire?.find));

    const responsePromise = page.waitForResponse(
        (response) =>
            /\/livewire(\/|$)/.test(response.url()) &&
            response.request().method() === 'POST',
        { timeout: 30_000 },
    );

    await page.locator(formSelector).evaluate((form) => {
        const root = form.closest('[wire\\:id]');
        const id = root?.getAttribute('wire:id');
        const livewire = (
            window as unknown as {
                Livewire: {
                    find: (id: string) => { call: (method: string) => void };
                };
            }
        ).Livewire;

        if (!id) {
            throw new Error(
                `Livewire component not found for form #${form.id || 'unknown'}`,
            );
        }

        livewire.find(id).call('submit');
    });

    await responsePromise;
}

export async function openProfilePage(
    page: Page,
    contactId: string,
): Promise<void> {
    await dismissCookieConsent(page);
    await page.locator('[data-ref="client-profile-dropdown"]').click();
    await page.locator('[data-ref="client-profile-dropdown-settings"]').click();
    await expect(page).toHaveURL(
        new RegExp(`/client/profile/${contactId}/edit$`),
    );
    await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
        'Client Information',
    );
    await dismissCookieConsent(page);
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

/**
 * `true` when the portal rendered the Pay Now dropdown, which requires at least
 * one enabled company gateway.
 */
export async function hasPayNowDropdown(page: Page): Promise<boolean> {
    return (await page.locator('[dusk="pay-now-dropdown"]').count()) > 0;
}

/**
 * Starts checkout from the Pay Now dropdown using the first non-PayPal method.
 * PayPal methods are skipped because they add the required-fields step, which
 * would obscure the terms and signature gates under test.
 */
export async function startPayNowCheckout(page: Page): Promise<void> {
    const dropdown = page.locator('[dusk="pay-now-dropdown"]');
    await expect(dropdown).toBeVisible();
    await dropdown.click();

    const method = page
        .locator(
            '[dusk="payment-methods-dropdown"] .dropdown-gateway-button:not([data-is-paypal="1"])',
        )
        .first();

    await expect(method).toBeVisible();
    await method.click();
}

export async function expectMetaFlag(
    page: Page,
    name: string,
    enabled: boolean,
): Promise<void> {
    // Blade renders booleans as "1" and an empty string.
    await expect(page.locator(`meta[name="${name}"]`)).toHaveAttribute(
        'content',
        enabled ? '1' : '',
    );
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
