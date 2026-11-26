import { type Page } from '@playwright/test';
import { bulkAction, getCompany, getEntity } from './api-helpers';
import {
    docuNinjaActive,
    docuNinjaModulesEnabled,
    seedQuoteSigningDocument,
    setDocuNinjaModules,
} from './artisan-helpers';
import {
    createAndLogInClient,
    dismissCookieConsent,
    drawSignature,
    waitForLivewire,
    type PortalClient,
} from './client-portal-helpers';
import { expect, test, uniqueName, type ApiFixture } from './fixtures';
import { documentDefaults, isoDate, type PortalEntity } from './portal-entity-helpers';

const QUOTE_STATUS = { SENT: 2, APPROVED: 3, REJECTED: 5 } as const;
const CONFIRMED_TEXT = 'Your selections are confirmed. Continue below to complete approval.';
const CHANGED_TEXT = 'This quote has changed. Please reopen it and review your selections.';

interface QuoteLineItem {
    product_key?: string;
    cost?: number;
    is_optional?: boolean;
}

type OptionsQuote = PortalEntity & { line_items?: QuoteLineItem[]; po_number?: string };

/** A sent quote with one required line (100) and two optional lines (40 and 20). */
async function createOptionsQuote(api: ApiFixture, client: PortalClient, overrides: Record<string, unknown> = {}) {
    const names = {
        required: uniqueName('Exterior painting'),
        chimney: uniqueName('Chimney repairs'),
        trim: uniqueName('Trim replacement'),
    };
    const quote = await api.createEntityFromBlank<OptionsQuote>('quotes', {
        ...documentDefaults(client),
        due_date: isoDate(30),
        discount: 0,
        partial: 0,
        tax_rate1: 0,
        tax_rate2: 0,
        tax_rate3: 0,
        line_items: [
            { product_key: names.required, notes: 'Prepare surfaces and apply two coats.', cost: 100, quantity: 1 },
            { product_key: names.chimney, notes: 'Repair damaged mortar.', cost: 40, quantity: 1, is_optional: true },
            { product_key: names.trim, notes: 'Replace damaged trim.', cost: 20, quantity: 1, is_optional: true },
        ],
        ...overrides,
    });
    await bulkAction(api.context, 'quotes', [quote.id], 'mark_sent');

    return { quote, names };
}

/** Ticks the given optional items on the options page and presses Review quote. */
async function selectOptionsAndReview(page: Page, quote: OptionsQuote, selected: string[], expected_total: RegExp) {
    await page.goto(`/client/quotes/${quote.id}`);
    await dismissCookieConsent(page);
    await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}/options$`));

    for (const name of selected) {
        await page.getByRole('checkbox', { name, exact: true }).check();
    }

    await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(expected_total);
    await page.getByRole('button', { name: 'Review quote' }).click();
    await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}$`));
    await page.waitForLoadState('load');
}

async function expectQuote(api: ApiFixture, quote: OptionsQuote, status_id: number, amount: number, line_count: number) {
    const current = await getEntity<OptionsQuote>(api.context, 'quotes', quote.id);
    expect(Number(current.status_id)).toBe(status_id);
    expect(Number(current.amount)).toBe(amount);
    expect(current.line_items).toHaveLength(line_count);

    return current;
}

/** Runs a describe block with the company's DocuNinja module flag forced on or off. */
function withDocuNinjaModules(enabled: boolean) {
    let company_id = '';
    let original = false;

    test.beforeEach(async ({ api }) => {
        company_id = (await getCompany(api.context)).id;
        original = docuNinjaModulesEnabled(company_id);
        setDocuNinjaModules(company_id, enabled);

        if (enabled) {
            test.skip(
                !docuNinjaActive(company_id),
                'DocuNinja is not active (needs APP_ENV=local or hosted, and an account with invoice settings)',
            );
        }
    });

    test.afterEach(() => {
        if (company_id) {
            setDocuNinjaModules(company_id, original);
        }
    });
}

test.describe('Quote options without DocuNinja', () => {
    withDocuNinjaModules(false);

    test('views the quote in a modal without losing option selections', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const { quote, names } = await createOptionsQuote(api, client);
        await page.goto(`/client/quotes/${quote.id}/options`);
        await dismissCookieConsent(page);
        await page.getByRole('checkbox', { name: names.chimney, exact: true }).check();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/140\.00/);

        const view_quote = page.getByRole('button', { name: 'View Quote', exact: true });
        const dialog = page.getByRole('dialog', { name: 'View Quote', exact: true });
        for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) {
            await page.setViewportSize(viewport);
            const layout = page.locator('main .max-w-6xl');
            const widths = await layout.evaluate(element => ({
                actual: element.getBoundingClientRect().width,
                expected: Math.min(element.parentElement!.clientWidth, 1152),
            }));
            expect(widths.actual).toBeCloseTo(widths.expected, 0);
            await page.screenshot({ path: `/tmp/invoiceninja-quote-options-layout-${viewport.width}.png` });
            await view_quote.click();
            await expect(dialog).toBeVisible();
            await expect(dialog.getByRole('button', { name: 'Download PDF', exact: true })).toBeVisible();
            await expect(dialog.locator('#pdf-iframe')).toHaveAttribute('src', /\/showBlob\/quote\//);
            if (viewport.width === 1280) {
                const pdf_url = (await dialog.locator('#pdf-iframe').getAttribute('src'))!;
                const pdf_response = await page.request.get(pdf_url);
                expect(pdf_response.ok()).toBe(true);
                expect(pdf_response.headers()['content-type']).toContain('application/pdf');
            }
            await expect(page.locator('html')).toHaveClass(/portal-dialog-open/);
            expect(await dialog.evaluate(element => element.scrollWidth <= element.clientWidth + 1)).toBe(true);
            await page.screenshot({ path: `/tmp/invoiceninja-quote-preview-${viewport.width}.png` });
            await dialog.getByRole('button', { name: 'Close', exact: true }).click();
            await expect(dialog).toBeHidden();
            await expect(view_quote).toBeFocused();
            await expect(page.locator('html')).not.toHaveClass(/portal-dialog-open/);
            await expect(page.getByRole('checkbox', { name: names.chimney, exact: true })).toBeChecked();
            await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/140\.00/);
            await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}/options$`));
        }

        expect(Number((await getEntity<PortalEntity>(api.context, 'quotes', quote.id)).amount)).toBe(100);
    });

    test('reviews optional quote items before continuing through normal approval', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const required_name = uniqueName('Exterior painting');
        const optional_name = uniqueName('Chimney repairs');
        const declined_name = uniqueName('Trim replacement');
        const quote = await api.createEntityFromBlank<PortalEntity>('quotes', {
            ...documentDefaults(client),
            due_date: isoDate(30),
            discount: 0,
            tax_rate1: 0,
            tax_rate2: 0,
            tax_rate3: 0,
            partial: 20,
            line_items: [
                { product_key: required_name, notes: 'Prepare surfaces and apply two coats.', cost: 100, quantity: 1 },
                { product_key: optional_name, notes: 'Repair damaged mortar and seal affected areas.', cost: 40, quantity: 1, is_optional: true },
                { product_key: declined_name, notes: 'Replace damaged trim around the garage.', cost: 20, quantity: 1, is_optional: true },
            ],
        });
        await bulkAction(api.context, 'quotes', [quote.id], 'mark_sent');

        await page.goto(`/client/quotes/${quote.id}`);
        await dismissCookieConsent(page);
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}/options$`));
        const document = page;
        await expect(page.locator('iframe[sandbox]')).toHaveCount(0);
        await expect(document.locator('[data-quote-group=required]')).toContainText(required_name);
        await expect(document.locator('[data-quote-group=optional]')).toContainText(optional_name);
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/100\.00/);

        await document.getByRole('checkbox', { name: optional_name, exact: true }).check();
        await expect(document.getByRole('checkbox', { name: optional_name, exact: true })).toBeChecked();
        await expect(page.getByTestId('quote-option-status-1')).toHaveText('Included in total');
        await expect(page.getByTestId('quote-options-selected-count')).toHaveText('1 of 2 additions selected');
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/140\.00/);
        expect(Number((await getEntity<PortalEntity>(api.context, 'quotes', quote.id)).amount)).toBe(100);
        await page.screenshot({ path: '/tmp/invoiceninja-quote-options-desktop.png', fullPage: true });

        await page.setViewportSize({ width: 390, height: 844 });
        await document.getByRole('checkbox', { name: declined_name, exact: true }).check();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/160\.00/);
        await document.getByRole('checkbox', { name: declined_name, exact: true }).uncheck();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/140\.00/);
        await expect(page.getByRole('button', { name: 'Review quote' })).toBeVisible();
        expect(await page.locator('main').evaluate(element => element.scrollWidth <= element.clientWidth + 1)).toBe(true);
        await expect(page.getByTestId('quote-options-sticky-total')).toBeVisible();
        await page.screenshot({ path: '/tmp/invoiceninja-quote-options-mobile.png', fullPage: true });
        await page.getByRole('button', { name: 'Review quote' }).click();
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}$`));
        await expect(page.getByTestId('quote-options-total')).toHaveText(/140\.00/);
        await expect(page.getByText(declined_name, { exact: true })).toHaveCount(0);
        const reviewed = await getEntity<PortalEntity>(api.context, 'quotes', quote.id);
        expect(Number(reviewed.amount)).toBe(140);
        expect(Number(reviewed.status_id)).toBe(2);

        await page.locator('#approve-button').click();
        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible();
        const approved = await getEntity<PortalEntity>(api.context, 'quotes', quote.id);
        expect(Number(approved.status_id)).toBe(3);
        expect(Number(approved.amount)).toBe(140);
    });

    test('selects description-only optional work independently of hidden PDF columns', async ({ api, page, companyGuard }) => {
        const company = await getCompany(api.context);
        const company_settings = company.settings as Record<string, unknown>;
        await companyGuard.update({
            settings: {
                ...company_settings,
                sync_invoice_quote_columns: false,
                hide_empty_columns_on_pdf: true,
                pdf_variables: {
                    ...(company_settings.pdf_variables as Record<string, unknown>),
                    product_quote_columns: ['$product.item', '$product.description', '$product.unit_cost', '$product.net_cost', '$product.quantity', '$product.tags', '$product.gross_line_total', '$product.line_total'],
                    total_columns: ['$net_subtotal', '$subtotal', '$gross_subtotal', '$paid_to_date', '$total'],
                },
            },
        });
        const client = await createAndLogInClient(api, page);
        const optional_name = uniqueName('Optional chimney repairs');
        const quote = await api.createEntityFromBlank<PortalEntity>('quotes', {
            ...documentDefaults(client),
            due_date: isoDate(30),
            discount: 0,
            tax_rate1: 0,
            tax_rate2: 0,
            tax_rate3: 0,
            line_items: [
                { product_key: '', notes: optional_name, cost: 40, quantity: 1, is_optional: true, tags: 'Upgrade' },
                { product_key: '', notes: 'Exterior painting', cost: 100, quantity: 1 },
            ],
        });
        await bulkAction(api.context, 'quotes', [quote.id], 'mark_sent');
        await page.goto(`/client/quotes/${quote.id}`);
        await dismissCookieConsent(page);

        const document = page;
        await expect(page.locator('iframe[sandbox]')).toHaveCount(0);
        await expect(document.locator('[data-quote-group=optional] article').first()).toContainText(optional_name);
        await expect(document.locator('[data-quote-group=optional] [data-quote-field=tags]')).toContainText('Upgrade');
        await expect(document.locator('[data-quote-group=optional] [data-quote-field=gross_line_total]')).toBeVisible();
        const checkbox = document.getByRole('checkbox', { name: optional_name, exact: true });
        await expect(checkbox).toBeVisible();
        await checkbox.check();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/140\.00/);
        await expect(page.getByTestId('quote-options-net_subtotal')).toHaveText(/140\.00/);
        await expect(page.getByTestId('quote-options-gross_subtotal')).toBeVisible();
        await expect(page.getByTestId('quote-options-paid_to_date')).toHaveCount(0);
        expect(Number((await getEntity<PortalEntity>(api.context, 'quotes', quote.id)).amount)).toBe(100);
        await checkbox.uncheck();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/100\.00/);
    });

    test('rejects a reviewed optional quote through the existing confirmation dialog', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const optional_name = uniqueName('Optional repairs');
        const quote = await api.createEntityFromBlank<PortalEntity>('quotes', {
            ...documentDefaults(client),
            due_date: isoDate(30),
            discount: 0,
            tax_rate1: 0,
            tax_rate2: 0,
            tax_rate3: 0,
            line_items: [
                { product_key: 'Exterior painting', cost: 100, quantity: 1 },
                { product_key: optional_name, cost: 40, quantity: 1, is_optional: true },
            ],
        });
        await bulkAction(api.context, 'quotes', [quote.id], 'mark_sent');
        await page.goto(`/client/quotes/${quote.id}`);
        await dismissCookieConsent(page);
        await page.getByRole('checkbox', { name: optional_name, exact: true }).check();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/140\.00/);
        await page.getByRole('button', { name: 'Review quote' }).click();
        await page.waitForURL(new RegExp(`/client/quotes/${quote.id}$`));

        await page.locator('#reject-button').click();
        await expect(page.locator('#displayRejectModal')).toBeVisible();
        await page.locator('#reject_reason').fill('We are postponing the work.');
        await page.locator('#reject-close-button').click();
        await expect(page.locator('#approve-button')).toBeEnabled();
        await expect(page.locator('#reject-button')).toBeEnabled();
        expect(Number((await getEntity<PortalEntity>(api.context, 'quotes', quote.id)).status_id)).toBe(2);

        await page.locator('#reject-button').click();
        await page.locator('#reject-confirm-button').click();
        await expect(page).toHaveURL(/\/client\/quotes$/);

        const rejected = await getEntity<PortalEntity>(api.context, 'quotes', quote.id);
        expect(Number(rejected.status_id)).toBe(5);
        expect(Number(rejected.amount)).toBe(140);
    });

    test('continues with no optional additions and keeps every required line', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const { quote, names } = await createOptionsQuote(api, client);

        await page.goto(`/client/quotes/${quote.id}`);
        await dismissCookieConsent(page);
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}/options$`));

        // Only optional lines are selectable; the required line has no checkbox.
        await expect(page.getByRole('checkbox')).toHaveCount(2);
        await expect(page.getByRole('checkbox', { name: names.required, exact: true })).toHaveCount(0);
        await expect(page.getByTestId('quote-options-selected-count')).toHaveText('0 of 2 additions selected');

        await page.getByRole('button', { name: 'Review quote' }).click();
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}$`));
        await page.waitForLoadState('load');

        const reviewed = await expectQuote(api, quote, QUOTE_STATUS.SENT, 100, 1);
        expect(reviewed.line_items?.[0].product_key).toBe(names.required);

        await page.locator('#approve-button').click();
        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible();
        await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 100, 1);
    });

    test('resumes the saved selection when the approval page is reopened', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const { quote, names } = await createOptionsQuote(api, client);

        await selectOptionsAndReview(page, quote, [names.chimney], /140\.00/);
        await expect(page.getByText(CONFIRMED_TEXT)).toBeVisible();
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 140, 2);

        await page.goto(`/client/quotes/${quote.id}`);
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}$`));
        await expect(page.getByText(CONFIRMED_TEXT)).toBeVisible();
        await expect(page.getByTestId('quote-options-total')).toHaveText(/140\.00/);
        await expect(page.getByText(names.chimney, { exact: true })).toBeVisible();
        await expect(page.getByText(names.trim, { exact: true })).toHaveCount(0);
        await expect(page.locator('#approve-button')).toBeVisible();
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 140, 2);
    });

    test('restarts a reviewed selection after confirmation and allows a different choice', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const { quote, names } = await createOptionsQuote(api, client);

        await selectOptionsAndReview(page, quote, [names.chimney], /140\.00/);
        const restart_button = page.getByRole('button', { name: 'Restart selections' });
        await expect(restart_button).toBeVisible();

        // Dismissing the browser confirmation leaves the reviewed scope untouched.
        page.once('dialog', dialog => dialog.dismiss());
        await restart_button.click();
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}$`));
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 140, 2);

        page.once('dialog', dialog => dialog.accept());
        await restart_button.click();
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}/options$`));
        const restored = await expectQuote(api, quote, QUOTE_STATUS.SENT, 100, 3);
        expect(restored.line_items?.filter(item => item.is_optional)).toHaveLength(2);
        await expect(page.getByRole('checkbox', { name: names.chimney, exact: true })).not.toBeChecked();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/100\.00/);

        await page.getByRole('checkbox', { name: names.trim, exact: true }).check();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/120\.00/);
        await page.getByRole('button', { name: 'Review quote' }).click();
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}$`));
        await page.waitForLoadState('load');

        const reselected = await expectQuote(api, quote, QUOTE_STATUS.SENT, 120, 2);
        expect(reselected.line_items?.map(item => item.product_key)).toEqual([names.required, names.trim]);

        await page.locator('#approve-button').click();
        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible();
        await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 120, 2);
    });

    test('asks the client to review again when the quote changed after the options page loaded', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const { quote, names } = await createOptionsQuote(api, client);

        await page.goto(`/client/quotes/${quote.id}`);
        await dismissCookieConsent(page);
        await page.getByRole('checkbox', { name: names.chimney, exact: true }).check();
        await expect(page.getByTestId('quote-options-sticky-total')).toHaveText(/140\.00/);

        const current = await getEntity<OptionsQuote>(api.context, 'quotes', quote.id);
        const response = await api.context.request.put(`/api/v1/quotes/${quote.id}`, {
            data: {
                ...current,
                line_items: current.line_items?.map(item => item.product_key === names.chimney ? { ...item, cost: 50 } : item),
            },
        });
        expect(response.ok()).toBe(true);

        await page.getByRole('button', { name: 'Review quote' }).click();
        await expect(page.getByText(CHANGED_TEXT)).toBeVisible();
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}/options$`));
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 100, 3);

        await selectOptionsAndReview(page, quote, [names.chimney], /150\.00/);
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 150, 2);
    });

    test('saves the purchase order number entered while approving a reviewed quote', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { accept_client_input_quote_approval: true },
        });
        const { quote, names } = await createOptionsQuote(api, client);
        const po_number = 'PO-OPTIONS-123';

        await selectOptionsAndReview(page, quote, [names.chimney], /140\.00/);
        await page.locator('#approve-button').click();
        await expect(page.locator('#displayInputModal')).toBeVisible();
        await page.locator('#user_input').fill(po_number);
        await page.locator('#input-next-step').click();

        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible();
        const approved = await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 140, 2);
        expect(approved.po_number).toBe(po_number);
    });

    test('requires accepting terms before approving a reviewed quote', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { show_accept_quote_terms: true },
        });
        const { quote, names } = await createOptionsQuote(api, client, { terms: 'These are the Playwright quote terms.' });

        await selectOptionsAndReview(page, quote, [names.chimney], /140\.00/);
        await page.locator('#approve-button').click();
        await expect(page.locator('#displayTermsModal')).toBeVisible();
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 140, 2);
        await page.locator('#accept-terms-button').click();

        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible();
        await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 140, 2);
    });

    test('approves a reviewed quote with a required canvas signature', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { require_quote_signature: true },
        });
        const { quote, names } = await createOptionsQuote(api, client);

        await selectOptionsAndReview(page, quote, [names.chimney], /140\.00/);
        await page.locator('#approve-button').click();
        await expect(page.locator('#displaySignatureModal')).toBeVisible();
        await drawSignature(page);
        await page.locator('#signature-next-step').click();

        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible({ timeout: 30_000 });
        await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 140, 2);
    });

    test('converts only the selected scope into an invoice when auto-convert is enabled', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { auto_convert_quote: true },
        });
        const { quote, names } = await createOptionsQuote(api, client);

        await selectOptionsAndReview(page, quote, [names.trim], /120\.00/);
        await page.locator('#approve-button').click();
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('View Invoice');

        const converted = await getEntity<OptionsQuote>(api.context, 'quotes', quote.id);
        expect(converted.invoice_id).toBeTruthy();
        api.trackEntity('invoices', String(converted.invoice_id));
        const invoice = await getEntity<OptionsQuote>(api.context, 'invoices', String(converted.invoice_id));
        expect(Number(invoice.amount)).toBe(120);
        expect(invoice.line_items?.map(item => item.product_key)).toEqual([names.required, names.trim]);
    });
});

test.describe('Quote options with DocuNinja signing', () => {
    withDocuNinjaModules(true);

    // The remote DocuNinja service is not driven here. The signing surface is the
    // real Livewire Sign component; the signature is simulated with the same
    // event the embedded signer dispatches once a document is signed.
    const docuNinjaSettings = { require_quote_signature: true };

    /**
     * Reviews the quote, then confirms the portal itself treats DocuNinja as active.
     * The artisan gate reflects the CLI environment; the approval page's
     * `require-quote-signature` flag is only empty when the web server agrees
     * (it is `1` when the canvas signature pad would be used instead).
     */
    async function selectOptionsAndReviewWithDocuNinja(page: Page, quote: OptionsQuote, selected: string[], expected_total: RegExp) {
        await selectOptionsAndReview(page, quote, selected, expected_total);
        await expect(
            page.locator('meta[name="require-quote-signature"]'),
            'The web server is not treating DocuNinja as active (check its APP_ENV and the company enable_modules flag)',
        ).toHaveAttribute('content', '');
    }

    async function expectDocuNinjaSigning(page: Page) {
        await expect(page.locator('[data-ref="meta-title"]')).toContainText('Sign Now', { timeout: 30_000 });
    }

    async function captureDocuNinjaSignature(page: Page, quote: OptionsQuote) {
        // Let the loader settle (signer mounted, or unavailable) before signing.
        await page.locator('#sign, .alert-error').first()
            .waitFor({ state: 'visible', timeout: 30_000 }).catch(() => undefined);
        seedQuoteSigningDocument(quote.id);

        await waitForLivewire(page, async () => {
            await page.evaluate(() => {
                const livewire = (window as typeof window & { Livewire?: { dispatch: (event: string) => void } }).Livewire;

                if (!livewire) {
                    throw new Error('Livewire is unavailable');
                }

                livewire.dispatch('docuninja-signature-captured');
            });
        });
    }

    test('carries the reviewed scope into DocuNinja signing and approves it once signed', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, { settings: docuNinjaSettings });
        const { quote, names } = await createOptionsQuote(api, client);

        await selectOptionsAndReviewWithDocuNinja(page, quote, [names.chimney], /140\.00/);
        await expect(page.getByText(CONFIRMED_TEXT)).toBeVisible();
        // Restarting is not offered once DocuNinja signing is required.
        await expect(page.getByRole('button', { name: 'Restart selections' })).toHaveCount(0);

        await page.locator('#approve-button').click();
        await expectDocuNinjaSigning(page);
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 140, 2);

        await captureDocuNinjaSignature(page, quote);
        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible({ timeout: 30_000 });
        await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 140, 2);
    });

    test('resumes DocuNinja signing from the quote link without changing the saved scope', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, { settings: docuNinjaSettings });
        const { quote, names } = await createOptionsQuote(api, client);

        await selectOptionsAndReviewWithDocuNinja(page, quote, [names.chimney], /140\.00/);
        await page.locator('#approve-button').click();
        await expectDocuNinjaSigning(page);

        // Abandon signing and come back through the canonical quote URL.
        await page.goto(`/client/quotes/${quote.id}`);
        await expect(page).toHaveURL(new RegExp(`/client/quotes/${quote.id}$`));
        await expectDocuNinjaSigning(page);
        await expectQuote(api, quote, QUOTE_STATUS.SENT, 140, 2);

        await captureDocuNinjaSignature(page, quote);
        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible({ timeout: 30_000 });
        await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 140, 2);
    });

    test('saves the purchase order number before opening DocuNinja signing', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { ...docuNinjaSettings, accept_client_input_quote_approval: true },
        });
        const { quote, names } = await createOptionsQuote(api, client);
        const po_number = 'PO-DOCUNINJA-456';

        await selectOptionsAndReviewWithDocuNinja(page, quote, [names.chimney], /140\.00/);
        await page.locator('#approve-button').click();
        await expect(page.locator('#displayInputModal')).toBeVisible();
        await page.locator('#user_input').fill(po_number);
        await page.locator('#input-next-step').click();
        await expectDocuNinjaSigning(page);

        const pending = await expectQuote(api, quote, QUOTE_STATUS.SENT, 140, 2);
        expect(pending.po_number).toBe(po_number);

        await captureDocuNinjaSignature(page, quote);
        await expect(page.getByRole('heading', { name: 'Approved', exact: true })).toBeVisible({ timeout: 30_000 });
        const approved = await expectQuote(api, quote, QUOTE_STATUS.APPROVED, 140, 2);
        expect(approved.po_number).toBe(po_number);
    });

    test('converts only the selected scope into an invoice after DocuNinja signing', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { ...docuNinjaSettings, auto_convert_quote: true },
        });
        const { quote, names } = await createOptionsQuote(api, client);

        await selectOptionsAndReviewWithDocuNinja(page, quote, [names.trim], /120\.00/);
        await page.locator('#approve-button').click();
        await expectDocuNinjaSigning(page);

        await captureDocuNinjaSignature(page, quote);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('View Invoice', { timeout: 30_000 });

        const converted = await getEntity<OptionsQuote>(api.context, 'quotes', quote.id);
        expect(converted.invoice_id).toBeTruthy();
        api.trackEntity('invoices', String(converted.invoice_id));
        const invoice = await getEntity<OptionsQuote>(api.context, 'invoices', String(converted.invoice_id));
        expect(Number(invoice.amount)).toBe(120);
        expect(invoice.line_items?.map(item => item.product_key)).toEqual([names.required, names.trim]);
    });
});
