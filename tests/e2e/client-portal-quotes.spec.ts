import {
    createAndLogInClient,
    dismissCookieConsent,
    drawSignature,
    expectPortalPage,
    selectEntityTableRow,
    waitForLivewire,
} from './client-portal-helpers';
import { getEntity } from './api-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createSentQuote,
    expectInvitationUrl,
    invitationKey,
    type PortalEntity,
} from './portal-entity-helpers';

const QUOTE_STATUS = {
    SENT: 2,
    APPROVED: 3,
    REJECTED: 5,
} as const;

test.describe('Client portal quotes', () => {
    test('lists a sent quote and opens it from the table', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-list'),
            cost: 55,
        });

        await expectPortalPage(page, '/client/quotes', 'Quotes');
        await expect(
            page.locator('.quotes-table').getByText(quote.number ?? ''),
        ).toBeVisible();
        await expect(page.locator('.quotes-table').getByText('Pending')).toBeVisible();

        await page
            .locator('.quotes-table tbody tr')
            .filter({ hasText: quote.number ?? '' })
            .getByRole('link', { name: 'View' })
            .click();

        await expect(page).toHaveURL(
            new RegExp(`/client/quotes/${quote.id}(?:\\?.*)?$`),
        );
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(/Quote/);
        await expect(page.getByRole('heading', { name: /Approve \/ Reject/i })).toBeVisible();
        await expect(page.locator('#approve-button')).toBeVisible();
        await expect(page.locator('#reject-button')).toBeVisible();
    });

    test('approves a quote from the detail page', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-approve'),
            cost: 88,
        });

        await page.goto(`/client/quotes/${quote.id}`);
        await page.locator('#approve-button').click();

        await expect(page.getByRole('heading', { name: 'Approved' })).toBeVisible();
        await expect(page.locator('#approve-button')).toHaveCount(0);
        await expect(page.locator('#reject-button')).toHaveCount(0);

        const updated = await getEntity<PortalEntity>(
            api.context,
            'quotes',
            quote.id,
        );
        expect(Number(updated.status_id)).toBe(QUOTE_STATUS.APPROVED);
    });

    test('approves a quote with a required canvas signature', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { require_quote_signature: true },
        });
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-signature'),
            cost: 64,
        });

        await page.goto(`/client/quotes/${quote.id}`);
        await dismissCookieConsent(page);
        await page.locator('#approve-button').click();

        const signatureModal = page.locator('#displaySignatureModal');
        if (!(await signatureModal.isVisible().catch(() => false))) {
            // DocuNinja may replace the canvas signature pad.
            test.skip(
                true,
                'Signature pad was not shown (DocuNinja may be active)',
            );
        }

        await expect(signatureModal).toBeVisible();
        await drawSignature(page);
        await page.locator('#signature-next-step').click();

        await expect(page.getByRole('heading', { name: 'Approved' })).toBeVisible({
            timeout: 30_000,
        });

        const updated = await getEntity<PortalEntity>(
            api.context,
            'quotes',
            quote.id,
        );
        expect(Number(updated.status_id)).toBe(QUOTE_STATUS.APPROVED);
    });

    test('rejects a quote from the detail page', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-reject'),
            cost: 66,
        });
        const reason = 'Budget not approved for this quarter';

        await page.goto(`/client/quotes/${quote.id}`);
        await page.locator('#reject-button').click();
        await expect(page.locator('#displayRejectModal')).toBeVisible();
        await page.locator('#reject_reason').fill(reason);
        await page.locator('#reject-confirm-button').click();

        await expect(page).toHaveURL(/\/client\/quotes(?:\/|$)/);
        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('Quotes');

        const updated = await getEntity<PortalEntity>(
            api.context,
            'quotes',
            quote.id,
        );
        expect(Number(updated.status_id)).toBe(QUOTE_STATUS.REJECTED);

        await page.goto(`/client/quotes/${quote.id}`);
        await expect(page.getByRole('heading', { name: 'Rejected' })).toBeVisible();
        await expect(page.locator('#approve-button')).toHaveCount(0);
    });

    test('approves a quote from the list bulk action', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-bulk-approve'),
            cost: 99,
        });

        await page.goto('/client/quotes');
        await dismissCookieConsent(page);
        await selectEntityTableRow(page, '.quotes-table', quote.number ?? '');
        await page
            .locator('form[action*="quotes"]')
            .getByRole('button', { name: 'Approve', exact: true })
            .click();

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('Approve');
        await dismissCookieConsent(page);
        await page.waitForLoadState('networkidle');
        await page.locator('#approve-form').evaluate((form: HTMLFormElement) => {
            form.submit();
        });

        await expect(page.getByRole('heading', { name: 'Approved' })).toBeVisible({
            timeout: 30_000,
        });

        const updated = await getEntity<PortalEntity>(
            api.context,
            'quotes',
            quote.id,
        );
        expect(Number(updated.status_id)).toBe(QUOTE_STATUS.APPROVED);
    });

    test('rejects a quote from the list bulk action', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-bulk-reject'),
            cost: 77,
        });

        await page.goto('/client/quotes');
        await dismissCookieConsent(page);
        await selectEntityTableRow(page, '.quotes-table', quote.number ?? '');
        await page
            .locator('form[action*="quotes"]')
            .getByRole('button', { name: 'Reject', exact: true })
            .click();

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText('Reject');
        await dismissCookieConsent(page);
        await page.waitForLoadState('networkidle');
        await page.locator('#reject-button').click();
        await expect(page.locator('#displayRejectModal')).toBeVisible();
        await page.locator('#reject_reason').fill('Declined via bulk action');
        await page.locator('#reject-confirm-button').click();

        await expect(page).toHaveURL(/\/client\/quotes(?:\/|$)/);

        const updated = await getEntity<PortalEntity>(
            api.context,
            'quotes',
            quote.id,
        );
        expect(Number(updated.status_id)).toBe(QUOTE_STATUS.REJECTED);
    });

    test('rejects a quote from bulk action without a reason', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-bulk-reject-empty'),
        });

        await page.goto('/client/quotes');
        await dismissCookieConsent(page);
        await selectEntityTableRow(page, '.quotes-table', quote.number ?? '');
        await page
            .locator('form[action*="quotes"]')
            .getByRole('button', { name: 'Reject', exact: true })
            .click();
        await dismissCookieConsent(page);
        await page.waitForLoadState('networkidle');
        await page.locator('#reject-button').click();
        await expect(page.locator('#displayRejectModal')).toBeVisible();
        await page.locator('#reject-confirm-button').click();

        await expect(page).toHaveURL(/\/client\/quotes(?:\/|$)/);
        const updated = await getEntity<PortalEntity>(
            api.context,
            'quotes',
            quote.id,
        );
        expect(Number(updated.status_id)).toBe(QUOTE_STATUS.REJECTED);
    });

    test('filters the quote list by rejected status', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const pending = await createSentQuote(api, client, {
            label: uniqueName('quote-filter-pending'),
        });
        const rejected = await createSentQuote(api, client, {
            label: uniqueName('quote-filter-rejected'),
        });

        await page.goto(`/client/quotes/${rejected.id}`);
        await dismissCookieConsent(page);
        await page.locator('#reject-button').click();
        await page.locator('#reject_reason').fill('Filtered out');
        await page.locator('#reject-confirm-button').click();
        await expect(page).toHaveURL(/\/client\/quotes(?:\/|$)/);

        await page.goto('/client/quotes');
        await dismissCookieConsent(page);
        await page.locator('#sent-checkbox').uncheck();
        await page.locator('#approved-checkbox').uncheck();
        await page.locator('#expired-checkbox').uncheck();
        await waitForLivewire(page, async () => {
            await page.locator('#rejected-checkbox').check();
        });

        await expect(
            page.locator('.quotes-table').getByText(rejected.number ?? '', {
                exact: true,
            }),
        ).toBeVisible({ timeout: 15_000 });
        await expect(
            page.locator('.quotes-table tbody tr').filter({
                hasText: pending.number ?? '',
            }),
        ).toHaveCount(0);
    });

    test('approves a quote with terms acceptance enabled', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { show_accept_quote_terms: true },
        });
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-terms'),
            terms: 'These are the Playwright quote terms.',
        });

        await page.goto(`/client/quotes/${quote.id}`);
        await page.locator('#approve-button').click();
        await expect(page.locator('#displayTermsModal')).toBeVisible();
        await expect(page.locator('[data-ref="entity-terms"]')).toContainText(
            'Playwright quote terms',
        );
        await page.locator('#accept-terms-button').click();

        await expect(page.getByRole('heading', { name: 'Approved' })).toBeVisible();
    });

    test('approves a quote after entering a purchase order number', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { accept_client_input_quote_approval: true },
        });
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-po'),
        });
        const poNumber = 'PO-PLAYWRIGHT-123';

        await page.goto(`/client/quotes/${quote.id}`);
        await page.locator('#approve-button').click();
        await expect(page.locator('#displayInputModal')).toBeVisible();
        await page.locator('#user_input').fill(poNumber);
        await page.locator('#input-next-step').click();

        await expect(page.getByRole('heading', { name: 'Approved' })).toBeVisible();

        const updated = await getEntity<PortalEntity>(
            api.context,
            'quotes',
            quote.id,
        );
        expect(updated.po_number).toBe(poNumber);
    });

    test('converts an approved quote into an invoice when auto-convert is enabled', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page, {
            settings: { auto_convert_quote: true },
        });
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-convert'),
            cost: 120,
        });

        await page.goto(`/client/quotes/${quote.id}`);
        await page.locator('#approve-button').click();

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(
            'View Invoice',
        );
        await expect(page.getByRole('button', { name: /\$120\.00/ })).toBeVisible();
    });

    test('shows an expired quote status on the detail page', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-expired'),
            dueInDays: -30,
        });

        await page.goto(`/client/quotes/${quote.id}`);
        await expect(page.getByRole('heading', { name: 'Expired' })).toBeVisible();
        await expect(page.locator('#approve-button')).toHaveCount(0);
    });

    test('shows a custom message on pending quotes', async ({ api, page }) => {
        const message = 'Please review this quote carefully.';
        const client = await createAndLogInClient(api, page, {
            settings: { custom_message_unapproved_quote: message },
        });
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-custom-message'),
        });

        await page.goto(`/client/quotes/${quote.id}`);
        await expect(page.getByText(message)).toBeVisible();
    });

    test('filters the quote list to approved quotes', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const pending = await createSentQuote(api, client, {
            label: uniqueName('quote-approved-filter-pending'),
        });
        const approved = await createSentQuote(api, client, {
            label: uniqueName('quote-approved-filter-target'),
        });

        await page.goto(`/client/quotes/${approved.id}`);
        await page.locator('#approve-button').click();
        await expect(page.getByRole('heading', { name: 'Approved' })).toBeVisible();

        await page.goto('/client/quotes');
        await page.locator('#sent-checkbox').uncheck();
        await page.locator('#approved-checkbox').check();

        await expect(
            page.locator('.quotes-table').getByText(approved.number ?? ''),
        ).toBeVisible();
        await expect(
            page.locator('.quotes-table').getByText(pending.number ?? ''),
        ).toHaveCount(0);
    });

    test('opens a quote from its invitation link', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-invitation'),
        });

        await page.goto(`/client/quote/${invitationKey(quote)}`);
        await expect(page).toHaveURL(
            expectInvitationUrl(
                `/client/quotes/${quote.id}`,
                `/client/quote/${invitationKey(quote)}`,
            ),
        );
        await expect(page.getByRole('heading', { name: /Approve \/ Reject/i })).toBeVisible();
    });

    test('filters the quote list to expired quotes', async ({ api, page }) => {
        const client = await createAndLogInClient(api, page);
        const current = await createSentQuote(api, client, {
            label: uniqueName('quote-filter-current'),
            dueInDays: 30,
        });
        const expired = await createSentQuote(api, client, {
            label: uniqueName('quote-filter-expired'),
            dueInDays: -30,
        });

        await page.goto('/client/quotes');
        await page.locator('#sent-checkbox').uncheck();
        await page.locator('#approved-checkbox').uncheck();
        await page.locator('#rejected-checkbox').uncheck();
        await page.locator('#expired-checkbox').check();

        await expect(
            page.locator('.quotes-table').getByText(expired.number ?? ''),
        ).toBeVisible();
        await expect(
            page.locator('.quotes-table').getByText(current.number ?? ''),
        ).toHaveCount(0);
    });

    test('downloads selected quotes from the list bulk action', async ({
        api,
        page,
    }) => {
        const client = await createAndLogInClient(api, page);
        const quote = await createSentQuote(api, client, {
            label: uniqueName('quote-bulk-download'),
            cost: 61,
        });

        await page.goto('/client/quotes');
        await selectEntityTableRow(page, '.quotes-table', quote.number ?? '');
        await page
            .locator('form[action*="quotes"]')
            .getByRole('button', { name: 'Download', exact: true })
            .click();

        await expect(page.locator('[data-ref="meta-title"]')).toHaveText(/Quote/);
        await expect(page.getByText(quote.number ?? '')).toBeVisible();

        const downloadPromise = page.waitForEvent('download');
        await page
            .locator('#bulkActions')
            .getByRole('button', { name: 'Download', exact: true })
            .click();
        const download = await downloadPromise;
        expect(download.suggestedFilename()).toMatch(/\.pdf$/i);
    });
});
