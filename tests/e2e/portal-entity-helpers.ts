import {
    bulkAction,
    getEntity,
    type ApiContext,
    type ApiEntity,
} from './api-helpers';
import { type ApiFixture, uniqueName } from './fixtures';
import { type PortalClient } from './client-portal-helpers';

export interface PortalEntity extends ApiEntity {
    id: string;
    number?: string;
    status_id?: number | string;
    invitations?: Array<{ key: string }>;
    amount?: number;
    balance?: number;
    terms?: string;
}

export interface PortalLineItemOptions {
    label: string;
    cost: number;
    quantity?: number;
}

export interface SentDocumentOptions {
    label?: string;
    cost?: number;
    dueInDays?: number;
    terms?: string;
}

export function isoDate(daysFromToday = 0): string {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() + daysFromToday);

    return date.toISOString().slice(0, 10);
}

export function lineItem({ label, cost, quantity = 1 }: PortalLineItemOptions) {
    return {
        product_key: uniqueName(label),
        notes: `${label} created by Playwright`,
        cost,
        quantity,
    };
}

export function documentDefaults(client: PortalClient) {
    return {
        client_id: client.id,
        date: isoDate(),
    };
}

export function invitationKey(entity: PortalEntity): string {
    const key = entity.invitations?.[0]?.key;

    if (!key) {
        throw new Error(`Entity ${entity.id} did not return an invitation key.`);
    }

    return key;
}

export function expectInvitationUrl(
    entityPath: string,
    invitationPath: string,
): RegExp {
    return new RegExp(`(?:${entityPath}|${invitationPath})(?:\\?.*)?$`);
}

export async function createSentInvoice(
    api: ApiFixture,
    client: PortalClient,
    options: SentDocumentOptions = {},
): Promise<PortalEntity> {
    const label = options.label ?? 'portal-invoice';
    const invoice = await api.createEntityFromBlank<PortalEntity>('invoices', {
        ...documentDefaults(client),
        due_date: isoDate(options.dueInDays ?? 30),
        line_items: [
            lineItem({
                label,
                cost: options.cost ?? 10,
            }),
        ],
    });

    await markEntitySent(api.context, 'invoices', invoice.id);

    return getEntity<PortalEntity>(api.context, 'invoices', invoice.id);
}

export async function createSentQuote(
    api: ApiFixture,
    client: PortalClient,
    options: SentDocumentOptions = {},
): Promise<PortalEntity> {
    const label = options.label ?? 'portal-quote';
    const quote = await api.createEntityFromBlank<PortalEntity>('quotes', {
        ...documentDefaults(client),
        due_date: isoDate(options.dueInDays ?? 30),
        terms: options.terms,
        line_items: [
            lineItem({
                label,
                cost: options.cost ?? 13,
            }),
        ],
    });

    await markEntitySent(api.context, 'quotes', quote.id);

    return getEntity<PortalEntity>(api.context, 'quotes', quote.id);
}

export async function createSentCredit(
    api: ApiFixture,
    client: PortalClient,
    options: SentDocumentOptions = {},
): Promise<PortalEntity> {
    const label = options.label ?? 'portal-credit';
    const credit = await api.createEntityFromBlank<PortalEntity>('credits', {
        ...documentDefaults(client),
        line_items: [
            lineItem({
                label,
                cost: options.cost ?? 14,
            }),
        ],
    });

    await markEntitySent(api.context, 'credits', credit.id);

    return getEntity<PortalEntity>(api.context, 'credits', credit.id);
}

export async function createPortalPayment(
    api: ApiFixture,
    client: PortalClient,
    options: { amount?: number } = {},
): Promise<PortalEntity> {
    return api.createEntity<PortalEntity>('payments', {
        client_id: client.id,
        amount: options.amount ?? 12,
        date: isoDate(),
        email_receipt: 'false',
    });
}

export async function createRecurringInvoice(
    api: ApiFixture,
    client: PortalClient,
): Promise<PortalEntity> {
    const response = await api.context.request.post(
        '/api/v1/recurring_invoices?start=true',
        {
            data: {
                ...(await api.context.request.get('/api/v1/recurring_invoices/create').then(
                    async (blankResponse) => (await blankResponse.json()).data,
                )),
                client_id: client.id,
                date: isoDate(),
                frequency_id: '5',
                next_send_date: isoDate(30),
                remaining_cycles: -1,
                line_items: [lineItem({ label: 'portal-recurring', cost: 11 })],
            },
        },
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to create recurring invoice (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();
    const recurring = body.data as PortalEntity;
    api.trackEntity('recurring_invoices', recurring.id);

    return recurring;
}

export async function createPortalProject(
    api: ApiFixture,
    client: PortalClient,
): Promise<PortalEntity> {
    return api.createEntity<PortalEntity>('projects', {
        client_id: client.id,
        name: uniqueName('portal-project'),
        public_notes: 'Project created by Playwright.',
    });
}

export async function createPortalTask(
    api: ApiFixture,
    client: PortalClient,
    project?: PortalEntity,
): Promise<PortalEntity> {
    return api.createEntity<PortalEntity>('tasks', {
        client_id: client.id,
        project_id: project?.id,
        description: uniqueName('portal-task'),
        time_log: [],
    });
}

export async function uploadClientDocument(
    api: ApiFixture,
    client: PortalClient,
    filename = 'portal-upload.txt',
): Promise<PortalEntity> {
    const response = await api.context.request.post(
        `/api/v1/clients/${client.id}/documents`,
        {
            headers: {
                'X-Api-Token': api.context.headers['X-Api-Token'],
                'X-Requested-With': 'XMLHttpRequest',
            },
            multipart: {
                file: {
                    name: filename,
                    mimeType: 'text/plain',
                    buffer: Buffer.from(`Playwright upload ${uniqueName('doc')}`),
                },
            },
        },
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to upload document (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();
    const document = body.data as PortalEntity;

    if (document.id) {
        api.trackEntity('documents', document.id);
    }

    return document;
}

export async function markInvoicePaid(
    api: ApiFixture,
    client: PortalClient,
    options: { label: string; cost: number },
): Promise<PortalEntity> {
    const invoice = await createSentInvoice(api, client, options);

    await api.context.request.put(`/api/v1/invoices/${invoice.id}`, {
        data: {
            ...(await getEntity(api.context, 'invoices', invoice.id)),
            paid: true,
        },
    });

    return getEntity<PortalEntity>(api.context, 'invoices', invoice.id);
}

async function markEntitySent(
    api: ApiContext,
    entityType: string,
    id: string,
): Promise<void> {
    await bulkAction(api, entityType, [id], 'mark_sent');
}
