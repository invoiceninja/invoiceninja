import type { Browser } from '@playwright/test';
import { getCompany } from './api-helpers';
import {
    clearPortalOverlays,
    createAndLogInClient,
    dismissCookieConsent,
    hasPayNowDropdown,
    launchIsolatedBrowser,
    selectEntityTableRow,
    withGuestPortalPage,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    clickBulkPayNow,
    paymentTestSettings,
} from './gateways/payment-flow-helpers';
import {
    createInvoicedPortalTask,
    createPortalTask,
    createSentCredit,
    createSentInvoice,
    markExistingInvoicePaid,
    type PortalEntity,
    uploadEntityDocument,
} from './portal-entity-helpers';

const amountField = 'input[name="payable_invoices[0][amount]"]';

/**
 * Opens the default-flow bulk payment page for a single invoice, which is where
 * the over/under payment settings surface their editable amount field.
 */
async function openBulkPaymentPage(
    page: import('@playwright/test').Page,
    invoice: PortalEntity,
): Promise<boolean> {
    await page.goto('/client/invoices');
    await dismissCookieConsent(page);

    if (
        (await page.locator('button[name="action"][value="payment"]').count()) === 0
    ) {
        return false;
    }

    await selectEntityTableRow(page, '.invoices-table', invoice.number ?? '');
    await clickBulkPayNow(page);
    await expect(page).toHaveURL(/\/client\/invoices\/payment/);
    await dismissCookieConsent(page);

    return true;
}

test.describe('Client portal payment amount settings', () => {
    test('keeps the payment amount read-only when over and under payment are disabled', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { ...paymentTestSettings, payment_flow: 'default' },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('fixed-amount-invoice'),
            cost: 40,
        });

        if (!(await openBulkPaymentPage(page, invoice))) {
            test.skip(true, 'No payment gateway configured for bulk pay');
        }

        await expect(page.locator(amountField)).toHaveAttribute('readonly', '');
        await expect(
            page.getByText(/you can pay more than the amount shown/i),
        ).toHaveCount(0);
        await expect(page.getByText(/Minimum Payment/i)).toHaveCount(0);
    });

    test('allows an editable over payment amount', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                ...paymentTestSettings,
                payment_flow: 'default',
                client_portal_allow_over_payment: true,
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('over-payment-invoice'),
            cost: 41,
        });

        if (!(await openBulkPaymentPage(page, invoice))) {
            test.skip(true, 'No payment gateway configured for bulk pay');
        }

        const amount = page.locator(amountField);
        await expect(amount).toBeVisible();
        await expect(amount).not.toHaveAttribute('readonly', '');
        await expect(
            page.getByText(/you can pay more than the amount shown/i),
        ).toBeVisible();

        await amount.fill('99.00');
        await expect(amount).toHaveValue('99.00');
    });

    test('shows the minimum amount when under payment is allowed', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                ...paymentTestSettings,
                payment_flow: 'default',
                client_portal_allow_under_payment: true,
                client_portal_under_payment_minimum: 5,
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('under-payment-invoice'),
            cost: 42,
        });

        if (!(await openBulkPaymentPage(page, invoice))) {
            test.skip(true, 'No payment gateway configured for bulk pay');
        }

        await expect(page.locator(amountField)).not.toHaveAttribute(
            'readonly',
            '',
        );
        await expect(page.getByText(/Minimum Payment/i)).toContainText('5');
    });

    test('offers an apply credit option when available credits are usable', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                ...paymentTestSettings,
                payment_flow: 'default',
                use_credits_payment: 'option',
            },
        });
        await createSentCredit(api, client, {
            label: uniqueName('usable-credit'),
            cost: 25,
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('credit-option-invoice'),
            cost: 60,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        if (!(await hasPayNowDropdown(page))) {
            test.skip(true, 'No payment gateway configured for the credit option');
        }

        await page.locator('[dusk="pay-now-dropdown"]').click();
        await expect(
            page
                .locator('[dusk="payment-methods-dropdown"]')
                .getByText('Apply Credit', { exact: true }),
        ).toBeVisible();
    });

    test('hides the apply credit option when credits are switched off', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                ...paymentTestSettings,
                payment_flow: 'default',
                use_credits_payment: 'off',
            },
        });
        await createSentCredit(api, client, {
            label: uniqueName('unusable-credit'),
            cost: 25,
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('credit-off-invoice'),
            cost: 60,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        if (!(await hasPayNowDropdown(page))) {
            test.skip(true, 'No payment gateway configured for the credit option');
        }

        await page.locator('[dusk="pay-now-dropdown"]').click();
        await expect(
            page
                .locator('[dusk="payment-methods-dropdown"]')
                .getByText('Apply Credit', { exact: true }),
        ).toHaveCount(0);
    });

    test('offers an apply credit option when credits are always applied', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                ...paymentTestSettings,
                payment_flow: 'default',
                use_credits_payment: 'always',
            },
        });
        await createSentCredit(api, client, {
            label: uniqueName('always-credit'),
            cost: 25,
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('credit-always-invoice'),
            cost: 60,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        if (!(await hasPayNowDropdown(page))) {
            test.skip(true, 'No payment gateway configured for the credit option');
        }

        await page.locator('[dusk="pay-now-dropdown"]').click();
        await expect(
            page
                .locator('[dusk="payment-methods-dropdown"]')
                .getByText('Apply Credit', { exact: true }),
        ).toBeVisible();
    });
});

test.describe('Client portal document upload settings', () => {
    test('shows the upload form when client uploads are enabled', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: { client_portal_enable_uploads: true },
        });

        await page.goto('/client/documents');
        await dismissCookieConsent(page);

        await expect(page.locator('form.dropzone')).toBeVisible();
    });

    test('hides the upload form and rejects uploads when disabled', async ({
        api,
        page,
    }) => {
        await createAndLogInClient(api, page, {
            settings: { client_portal_enable_uploads: false },
        });

        await page.goto('/client/documents');
        await dismissCookieConsent(page);

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'Documents',
        );
        await expect(page.locator('form.dropzone')).toHaveCount(0);

        const token = await page
            .locator('#multiple-downloads input[name="_token"]')
            .inputValue();
        const uploadResponse = await page.request.post('/client/upload', {
            multipart: {
                _token: token,
                is_public: 'true',
                'file[]': {
                    name: `${uniqueName('blocked')}.txt`,
                    mimeType: 'text/plain',
                    buffer: Buffer.from('Uploads are disabled.'),
                },
            },
        });

        expect(uploadResponse.status()).toBe(403);
    });
});

test.describe('Client portal branding settings', () => {
    test('links the portal terms and privacy policy in the footer', async ({
        api,
        page,
    }) => {
        const terms = 'Playwright portal terms of service.';
        const privacy = 'Playwright portal privacy policy.';
        await createAndLogInClient(api, page, {
            settings: {
                client_portal_terms: terms,
                client_portal_privacy_policy: privacy,
            },
        });

        await page.goto('/client/dashboard');
        await clearPortalOverlays(page);

        const termsPopup = page.locator('[x-show="tos"]');
        const privacyPopup = page.locator('[x-show="privacy"]');

        await expect(termsPopup).toBeHidden();
        await expect(privacyPopup).toBeHidden();

        await page.getByRole('link', { name: 'Terms', exact: true }).click();
        await expect(termsPopup).toBeVisible();
        await expect(termsPopup).toContainText(terms);

        await termsPopup.getByRole('button', { name: 'Close pop-up' }).click();
        await expect(termsPopup).toBeHidden();

        await page
            .getByRole('link', { name: 'Privacy Policy', exact: true })
            .click();
        await expect(privacyPopup).toBeVisible();
        await expect(privacyPopup).toContainText(privacy);
    });

    test('renders the custom portal header banner', async ({ api, page }) => {
        await createAndLogInClient(api, page, {
            settings: {
                portal_custom_head:
                    '<span data-ref="playwright-custom-head">Playwright custom banner</span>',
            },
        });

        await page.goto('/client/dashboard');
        await dismissCookieConsent(page);

        if (
            (await page.locator('[data-ref="playwright-custom-head"]').count()) === 0
        ) {
            test.skip(
                true,
                'Custom portal head needs a self-hosted install or a paid account',
            );
        }

        await expect(
            page.locator('[data-ref="playwright-custom-head"]'),
        ).toHaveText('Playwright custom banner');
    });

    test('injects custom portal CSS', async ({ api, page }) => {
        await createAndLogInClient(api, page, {
            settings: {
                portal_custom_css:
                    '/* playwright-css-marker */ [data-ref="meta-title"] { visibility: hidden; }',
            },
        });

        await page.goto('/client/invoices');
        await dismissCookieConsent(page);

        const injected = await page.evaluate(() =>
            document.head.innerHTML.includes('playwright-css-marker'),
        );

        if (!injected) {
            test.skip(
                true,
                'Custom portal CSS is only injected on self-hosted installs',
            );
        }

        const title = page.locator('[data-ref="meta-title"]');
        await expect(title).toHaveCount(1);
        await expect(title).toBeHidden();
    });

    test('injects custom portal JavaScript', async ({ api, page }) => {
        await createAndLogInClient(api, page, {
            settings: {
                portal_custom_js:
                    "/* playwright-js-marker */ document.title = 'Playwright Custom JS';",
            },
        });

        await page.goto('/client/dashboard');
        await dismissCookieConsent(page);

        const injected = await page.evaluate(() =>
            document.documentElement.innerHTML.includes('playwright-js-marker'),
        );

        if (!injected) {
            test.skip(
                true,
                'Custom portal JavaScript is only injected on self-hosted installs',
            );
        }

        await expect(page).toHaveTitle('Playwright Custom JS');
    });

    test('renders the custom portal footer banner', async ({ api, page }) => {
        await createAndLogInClient(api, page, {
            settings: {
                portal_custom_footer:
                    '<span data-ref="playwright-custom-footer">Playwright custom footer</span>',
            },
        });

        await page.goto('/client/dashboard');
        await clearPortalOverlays(page);

        const banner = page.locator('[data-ref="playwright-custom-footer"]');

        if ((await banner.count()) === 0) {
            test.skip(true, 'Custom portal footer requires a paid account');
        }

        await expect(banner).toHaveText('Playwright custom footer');
    });
});

test.describe('Client portal document view settings', () => {
    const pdfWrapper = 'div:has(> [wire\\:init="getPdf()"])';

    test('renders the HTML entity view and hides the PDF on small screens', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { show_pdfhtml_on_mobile: true },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('html-view-invoice'),
            cost: 43,
        });

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        const entityDetails = page.locator('#entity-details');
        await expect(entityDetails).toHaveCount(1);
        await expect(entityDetails).toContainText(invoice.number ?? '');

        // The embedded PDF is restricted to large screens so the HTML view takes over.
        await expect(page.locator(pdfWrapper)).toHaveClass(/hidden/);
        await expect(page.locator(pdfWrapper)).toHaveClass(/lg:block/);
    });

    test('keeps the embedded PDF when the mobile HTML view is disabled', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { show_pdfhtml_on_mobile: false },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('pdf-only-invoice'),
            cost: 44,
        });

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
        await expect(page.locator('#entity-details')).toHaveCount(0);
        await expect(page.locator(pdfWrapper)).not.toHaveClass(/hidden/);
    });

    test('prefers product notes over product keys in the mobile HTML view', async ({
        api,
        page,
    }) => {
        const productKey = `KEY-${uniqueName('pk').slice(-6)}`;
        const notes = `NOTES-${uniqueName('nt').slice(-6)} line description`;
        const client = await createAndLogInClient(api, page, {
            settings: {
                show_pdfhtml_on_mobile: true,
                preference_product_notes_for_html_view: true,
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('html-notes-invoice'),
            cost: 45,
            productKey,
            notes,
        });

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        // Mobile HTML product lines live under #product-details (sibling of
        // #entity-details). Prefer attached text checks because the parent
        // `block lg:hidden` wrapper can confuse Playwright visibility.
        const productDetails = page.locator('#product-details');
        await expect(productDetails).toBeAttached({ timeout: 15_000 });
        await expect
            .poll(async () => (await productDetails.innerText()).trim(), {
                timeout: 15_000,
            })
            .toContain(notes);
        expect(await productDetails.innerText()).not.toContain(productKey);
    });

    test('prefers product keys over notes when HTML notes preference is off', async ({
        api,
        page,
    }) => {
        const productKey = `KEY-${uniqueName('pk').slice(-6)}`;
        const notes = `NOTES-${uniqueName('nt').slice(-6)} line description`;
        const client = await createAndLogInClient(api, page, {
            settings: {
                show_pdfhtml_on_mobile: true,
                preference_product_notes_for_html_view: false,
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('html-key-invoice'),
            cost: 46,
            productKey,
            notes,
        });

        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);

        const productDetails = page.locator('#product-details');
        await expect(productDetails).toBeAttached({ timeout: 15_000 });
        await expect
            .poll(async () => (await productDetails.innerText()).trim(), {
                timeout: 15_000,
            })
            .toContain(productKey);
        expect(await productDetails.innerText()).not.toContain(notes);
    });
});

test.describe('Client portal invoice document unlock settings', () => {
    test('hides private invoice documents until the invoice is paid', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                payment_flow: 'default',
                unlock_invoice_documents_after_payment: true,
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('unlock-docs-invoice'),
            cost: 47,
        });
        const filename = `lock-${uniqueName('d').slice(-8)}.txt`;
        await uploadEntityDocument(api, 'invoices', invoice.id, filename, {
            isPublic: false,
        });

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);
        await expect(page.getByText(/Attachments/i)).toHaveCount(0);
        await expect(
            page.getByRole('link', { name: new RegExp(filename.slice(0, 12)) }),
        ).toHaveCount(0);

        await markExistingInvoicePaid(api, invoice.id);

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);
        await expect(page.getByText(/Attachments/i)).toBeVisible({
            timeout: 15_000,
        });
        await expect(
            page.getByRole('link', { name: new RegExp(filename.slice(0, 12)) }),
        ).toBeVisible();
    });

    test('keeps private invoice documents hidden when unlock-after-payment is off', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: {
                payment_flow: 'default',
                unlock_invoice_documents_after_payment: false,
            },
        });
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('keep-locked-docs'),
            cost: 48,
        });
        const filename = `priv-${uniqueName('d').slice(-8)}.txt`;
        await uploadEntityDocument(api, 'invoices', invoice.id, filename, {
            isPublic: false,
        });

        await markExistingInvoicePaid(api, invoice.id);

        await page.goto(`/client/invoices/${invoice.id}`);
        await dismissCookieConsent(page);
        await expect(page.getByText(/Attachments/i)).toHaveCount(0);
        await expect(
            page.getByRole('link', { name: new RegExp(filename.slice(0, 12)) }),
        ).toHaveCount(0);
    });
});

test.describe('Client portal task visibility settings', () => {
    test('shows only uninvoiced tasks when configured', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { show_all_tasks_client_portal: 'uninvoiced' },
        });
        const uninvoiced = uniqueName('visible-uninvoiced-task');
        const invoiced = uniqueName('hidden-invoiced-task');

        await createPortalTask(api, client, undefined, {
            description: uninvoiced,
        });
        await createInvoicedPortalTask(api, client, {
            taskDescription: invoiced,
        });

        await page.goto('/client/tasks');
        await dismissCookieConsent(page);

        await expect(page.getByText(uninvoiced)).toBeVisible();
        await expect(
            page.locator('.credits-table tbody tr').filter({ hasText: invoiced }),
        ).toHaveCount(0);
    });

    test('shows only invoiced tasks when configured', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { show_all_tasks_client_portal: 'invoiced' },
        });
        const uninvoiced = uniqueName('hidden-uninvoiced-task');
        const invoiced = uniqueName('visible-invoiced-task');

        await createPortalTask(api, client, undefined, {
            description: uninvoiced,
        });
        await createInvoicedPortalTask(api, client, {
            taskDescription: invoiced,
        });

        await page.goto('/client/tasks');
        await dismissCookieConsent(page);

        await expect(page.getByText(invoiced)).toBeVisible();
        await expect(
            page
                .locator('.credits-table tbody tr')
                .filter({ hasText: uninvoiced }),
        ).toHaveCount(0);
    });
});

test.describe('Client portal registration settings', () => {
    // Registration hits a public route (no portal login). Keep these serial so
    // companyGuard mutations cannot race across workers. Use an isolated
    // Chromium so a wedged worker browser (common late in the full suite)
    // cannot hang fixture setup on `context`.
    test.describe.configure({ mode: 'serial' });

    let registrationBrowser: Browser;

    test.beforeAll(async () => {
        registrationBrowser = await launchIsolatedBrowser();
    });

    test.afterAll(async () => {
        await registrationBrowser?.close().catch(() => undefined);
    });

    test('renders only the visible registration fields and marks required ones', async ({
        companyGuard,
    }) => {
        const company = await companyGuard.update({
            client_can_register: true,
            client_registration_fields: [
                { key: 'first_name', required: true, visible: true },
                { key: 'last_name', required: false, visible: true },
                { key: 'email', required: true, visible: true },
                { key: 'password', required: true, visible: true },
                { key: 'vat_number', required: true, visible: true },
                { key: 'website', required: false, visible: false },
            ],
        });

        await withGuestPortalPage(registrationBrowser, async (page) => {
            await page.goto(`/client/register/${company.company_key}`);
            await dismissCookieConsent(page);

            await expect(page.locator('#register-form')).toBeVisible();
            await expect(page.locator('#first_name')).toBeVisible();
            await expect(page.locator('#last_name')).toBeVisible();
            await expect(page.locator('#email')).toBeVisible();
            await expect(page.locator('#password')).toBeVisible();
            await expect(page.locator('#vat_number')).toBeVisible();
            await expect(page.locator('#website')).toHaveCount(0);

            await expect(
                page.locator('section:has(> label[for="vat_number"])'),
            ).toContainText('*');
            await expect(
                page.locator('section:has(> label[for="last_name"])'),
            ).not.toContainText('*');
        });
    });

    test('shows the terms and privacy policy agreement on the register form', async ({
        api,
        companyGuard,
    }) => {
        const terms = 'Playwright registration terms of service.';
        const privacy = 'Playwright registration privacy policy.';
        const current = await getCompany(api.context);
        const company = await companyGuard.update({
            client_can_register: true,
            settings: {
                ...(current.settings as Record<string, unknown>),
                client_portal_terms: terms,
                client_portal_privacy_policy: privacy,
            },
        });

        await withGuestPortalPage(registrationBrowser, async (page) => {
            await page.goto(`/client/register/${company.company_key}`);
            await dismissCookieConsent(page);

            await expect(page.locator('input[name="terms"]')).toBeChecked();

            const termsPopup = page
                .locator('[x-show="terms_of_service"]')
                .first();
            const privacyPopup = page
                .locator('[x-show="privacy_policy"]')
                .first();

            await expect(termsPopup).toBeHidden();
            await page
                .getByRole('link', { name: 'Terms of Service', exact: true })
                .click();
            await expect(termsPopup).toBeVisible();
            await expect(termsPopup).toContainText(terms);

            await termsPopup.getByRole('button').first().click();
            await expect(termsPopup).toBeHidden();

            await page
                .getByRole('link', { name: 'Privacy Policy', exact: true })
                .click();
            await expect(privacyPopup).toBeVisible();
            await expect(privacyPopup).toContainText(privacy);
        });
    });

    // The register form always renders; `client_can_register` is enforced when
    // the form is submitted. This assertion is API-only so it never depends on
    // the (possibly wedged) worker browser context fixture.
    test('rejects a registration submission when self-registration is disabled', async ({
        companyGuard,
        request,
    }) => {
        const company = await companyGuard.update({
            client_can_register: false,
        });
        const marker = uniqueName('blocked-register');
        const registerPath = `/client/register/${company.company_key}`;

        const formPage = await request.get(registerPath);
        expect(formPage.ok()).toBeTruthy();

        const html = await formPage.text();
        const token = html.match(/name="_token"\s+value="([^"]+)"/)?.[1];
        expect(token).toBeTruthy();

        const response = await request.post(registerPath, {
            form: {
                _token: token as string,
                company_key: company.company_key,
                first_name: 'Blocked',
                last_name: 'Register',
                email: `${marker}@example.test`,
                password: 'PortalRegister123!',
            },
        });

        expect(response.status()).toBe(403);
    });
});
