import type { Page, Response } from '@playwright/test';
import { getEntity } from './api-helpers';
import {
    allowPdfBlobs,
    createAndLogInClient,
} from './client-portal-helpers';
import { expect, test, uniqueName } from './fixtures';
import {
    createRecurringInvoice,
    createSentCredit,
    createSentInvoice,
    createSentQuote,
    invitationKey,
    type PortalEntity,
} from './portal-entity-helpers';

type ClientPdfEntityType =
    | 'invoice'
    | 'quote'
    | 'credit'
    | 'recurring_invoice';

test.describe('Client portal PDF previews', () => {
    test('resolves PDFs for every supported client entity type', async ({
        api,
        page,
    }) => {
        test.setTimeout(180_000);

        const client = await createAndLogInClient(api, page);
        const invoice = await createSentInvoice(api, client, {
            label: uniqueName('pdf-preview-invoice'),
        });
        const quote = await createSentQuote(api, client, {
            label: uniqueName('pdf-preview-quote'),
        });
        const credit = await createSentCredit(api, client, {
            label: uniqueName('pdf-preview-credit'),
        });
        const recurring = await createRecurringInvoice(api, client);
        const recurringWithInvitations = await getEntity<PortalEntity>(
            api.context,
            'recurring_invoices',
            recurring.id,
        );

        await allowPdfBlobs(page);

        await test.step('invoice iframe resolves a PDF', async () => {
            await openAndExpectPdfPreview(
                page,
                `/client/invoices/${invoice.id}`,
                'invoice',
                invitationKey(invoice),
            );
        });

        await test.step('quote iframe resolves a PDF', async () => {
            await openAndExpectPdfPreview(
                page,
                `/client/quotes/${quote.id}`,
                'quote',
                invitationKey(quote),
            );
        });

        await test.step('credit iframe resolves a PDF', async () => {
            await openAndExpectPdfPreview(
                page,
                `/client/credits/${credit.id}`,
                'credit',
                invitationKey(credit),
            );
        });

        await test.step(
            'recurring invoice preview route resolves a PDF',
            async () => {
                await requestAndExpectPdf(
                    page,
                    'recurring_invoice',
                    invitationKey(recurringWithInvitations),
                );
            },
        );
    });
});

async function openAndExpectPdfPreview(
    page: Page,
    pagePath: string,
    entityType: ClientPdfEntityType,
    invitationKey: string,
): Promise<void> {
    const previewPath = pdfPreviewPath(entityType, invitationKey);
    const responsePromise = waitForPdfResponse(page, previewPath);
    const navigation = await page.goto(pagePath, {
        waitUntil: 'domcontentloaded',
    });

    expect(navigation, `${pagePath} should return a response`).not.toBeNull();
    expect(navigation?.ok(), `${pagePath} should load successfully`).toBe(true);

    await expect(page.locator('#pdf-iframe')).toHaveAttribute(
        'src',
        `${previewPath}#zoom=100`,
        { timeout: 30_000 },
    );

    expectPdfResponse(await responsePromise, previewPath);
    await requestPathAndExpectPdf(page, previewPath);
}

async function requestAndExpectPdf(
    page: Page,
    entityType: ClientPdfEntityType,
    invitationKey: string,
): Promise<void> {
    const previewPath = pdfPreviewPath(entityType, invitationKey);

    await requestPathAndExpectPdf(page, previewPath);
}

async function requestPathAndExpectPdf(
    page: Page,
    previewPath: string,
): Promise<void> {
    const response = await page.context().request.get(previewPath, {
        timeout: 60_000,
    });

    expect(response.ok(), `${previewPath} should resolve successfully`).toBe(
        true,
    );
    expect(new URL(response.url()).search).toBe('');
    expect(response.headers()['content-type']).toContain('application/pdf');
    expect(response.headers()['content-disposition']).toBe('inline');
    expect((await response.body()).subarray(0, 5).toString()).toBe('%PDF-');
}

function waitForPdfResponse(
    page: Page,
    previewPath: string,
): Promise<Response> {
    return page.waitForResponse(
        (response) => {
            const url = new URL(response.url());

            return (
                url.pathname === previewPath &&
                response.request().resourceType() === 'document'
            );
        },
        { timeout: 60_000 },
    );
}

function expectPdfResponse(
    response: Response,
    previewPath: string,
): void {
    const url = new URL(response.url());

    expect(response.ok(), `${previewPath} should resolve successfully`).toBe(
        true,
    );
    expect(url.search, `${previewPath} should not require a signature`).toBe('');
    expect(response.headers()['content-type']).toContain('application/pdf');
    expect(response.headers()['content-disposition']).toBe('inline');
}

function pdfPreviewPath(
    entityType: ClientPdfEntityType,
    invitationKey: string,
): string {
    return `/client/showBlob/${entityType}/${invitationKey}`;
}
