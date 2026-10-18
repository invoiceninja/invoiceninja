import { type APIRequestContext, type APIResponse } from '@playwright/test';
import {
    bulkAction,
    getEntity,
    type ApiContext,
    type ApiEntity,
} from './api-helpers';
import { runArtisan } from './artisan-helpers';
import { decodePrimaryKey } from './hash-helpers';
import {
    expect,
    test,
    uniqueName,
    type ApiFixture,
} from './fixtures';
import { isoDate, lineItem } from './portal-entity-helpers';

interface AllTimeEntity extends ApiEntity {
    id: string;
    created_at?: number | string;
    parameters?: Record<string, unknown>;
}

const calculatedFields = [
    'active_invoices',
    'outstanding_invoices',
    'completed_payments',
    'refunded_payments',
    'active_quotes',
    'unapproved_quotes',
    'logged_tasks',
    'invoiced_tasks',
    'paid_tasks',
    'logged_expenses',
    'pending_expenses',
    'invoiced_expenses',
    'invoice_paid_expenses',
] as const;

test.describe('all_time date ranges', () => {
    test('dashboard endpoints resolve the first chart record and calculated fields remain unbounded', async ({
        api,
    }) => {
        const initialGap = await calculatedFieldGap(api.context.request);
        const client = await createClient(api, 'all-time-dashboard');
        const invoice = await createSentInvoice(api, client.id, {
            date: '1902-01-03',
            cost: 75,
            label: 'all-time-dashboard-invoice',
        });

        await api.createEntity<AllTimeEntity>('payments', {
            client_id: client.id,
            amount: 25,
            date: '1901-01-02',
            invoices: [{ invoice_id: invoice.id, amount: 25 }],
            email_receipt: false,
        });
        await api.createEntityFromBlank<AllTimeEntity>('expenses', {
            client_id: client.id,
            amount: 11,
            date: '1900-01-01',
            public_notes: uniqueName('all-time-dashboard-expense'),
        });

        for (const endpoint of [
            'chart_summary',
            'totals',
            'chart_summary_v2',
            'totals_v2',
        ]) {
            await test.step(endpoint, async () => {
                const body = await postJson(
                    api.context.request,
                    `/api/v1/charts/${endpoint}`,
                    { date_range: 'all_time' },
                );

                expect(body.start_date).toBe('1900-01-01');
                expect(body.end_date).toBe(isoDate());
            });
        }

        expect((await calculatedFieldGap(api.context.request)) - initialGap).toBeCloseTo(
            75,
            2,
        );

        for (const field of calculatedFields) {
            await test.step(`calculated field: ${field}`, async () => {
                const response = await api.context.request.post(
                    '/api/v1/charts/calculated_fields',
                    {
                        data: {
                            date_range: 'all_time',
                            field,
                            calculation: 'count',
                            period: 'current',
                            currency_id: '999',
                            format: 'money',
                        },
                    },
                );

                await expectOk(response, `calculated field ${field}`);
                expect(Number(await response.json())).not.toBeNaN();
            });
        }
    });

    test('analytics and forecast endpoints resolve dates from their own records', async ({
        api,
    }) => {
        const client = await createClient(api, 'all-time-analytics');
        await createSentQuote(api, client.id, {
            date: '1900-01-02',
            cost: 250,
        });
        await api.createEntityFromBlank<AllTimeEntity>('expenses', {
            client_id: client.id,
            amount: 50,
            date: '1899-01-01',
            public_notes: uniqueName('all-time-forecast-expense'),
        });

        for (const endpoint of ['analytics_summary', 'analytics_totals']) {
            await test.step(endpoint, async () => {
                const body = await postJson(
                    api.context.request,
                    `/api/v1/charts/${endpoint}`,
                    { date_range: 'all_time' },
                );

                expect(body.start_date).toBe('1900-01-02');
                expect(body.end_date).toBe(isoDate());
            });
        }

        const forecast = await postJson(
            api.context.request,
            '/api/v1/charts/cashflow_forecast',
            {
                date_range: 'all_time',
                bucket_type: 'monthly',
            },
        );

        expect(forecast.start_date).toBe('1899-01-01');
        expect(forecast.end_date).toBe(isoDate());
        expect(Array.isArray(forecast.buckets)).toBe(true);
    });

    test('project analytics accepts all_time and burn-up starts at project creation', async ({
        api,
    }) => {
        const client = await createClient(api, 'all-time-project');
        const project = await api.createEntity<AllTimeEntity>('projects', {
            client_id: client.id,
            name: uniqueName('all-time-project'),
            public_notes: 'Project covered by the all-time Playwright test.',
        });
        await createSentInvoice(api, client.id, {
            date: isoDate(),
            cost: 125,
            label: 'all-time-project-invoice',
            projectId: project.id,
        });

        const analytics = await postJson(
            api.context.request,
            `/api/v1/charts/project_analytics/${project.id}`,
            { date_range: 'all_time' },
        );
        const burnUp = await postJson(
            api.context.request,
            `/api/v1/charts/project_burnup/${project.id}`,
            {
                date_range: 'all_time',
                bucket_type: 'monthly',
            },
        );

        expect(analytics.metadata.project_count).toBe(1);
        expect(burnUp.project.id).toBe(project.id);
        expect(burnUp.start_date).toBe(entityDate(project.created_at));
        expect(Number(burnUp.totals.invoiced_amount)).toBeCloseTo(125, 2);
    });

    test('generic and specialised reports include pre-2000 all-time data', async ({
        api,
    }) => {
        test.setTimeout(120_000);

        const client = await createClient(api, 'all-time-reports');
        const invoiceNumber = uniqueName('ALL-TIME-REPORT-INVOICE');
        const taxName = uniqueName('All Time Tax');
        const invoice = await createSentInvoice(api, client.id, {
            date: '1999-01-01',
            cost: 876.54,
            label: 'all-time-report-invoice',
            number: invoiceNumber,
            taxName,
            taxRate: 10,
        });

        await api.createEntity<AllTimeEntity>('payments', {
            client_id: client.id,
            amount: 100,
            date: '1999-01-02',
            invoices: [{ invoice_id: invoice.id, amount: 100 }],
            email_receipt: false,
        });
        await api.createEntityFromBlank<AllTimeEntity>('expenses', {
            client_id: client.id,
            amount: 43.21,
            date: '1998-12-31',
            public_notes: uniqueName('all-time-report-expense'),
        });

        const genericPayload = {
            date_range: 'all_time',
            report_keys: [] as string[],
            send_email: false,
            include_deleted: false,
        };

        const invoiceCsv = await requestReport(
            api.context,
            '/api/v1/reports/invoices',
            {
                ...genericPayload,
                report_keys: ['invoice.number'],
            },
        );
        const clientSalesCsv = await requestReport(
            api.context,
            '/api/v1/reports/client_sales_report',
            genericPayload,
        );
        const taxSummaryCsv = await requestReport(
            api.context,
            '/api/v1/reports/tax_summary_report',
            genericPayload,
        );
        const profitLossCsv = await requestReport(
            api.context,
            '/api/v1/reports/profitloss',
            {
                date_range: 'all_time',
                is_income_billed: true,
                is_expense_billed: true,
                include_tax: true,
                send_email: false,
            },
        );
        const boundedProfitLossCsv = await requestReport(
            api.context,
            '/api/v1/reports/profitloss',
            {
                date_range: 'custom',
                start_date: '2000-01-01',
                end_date: isoDate(),
                is_income_billed: true,
                is_expense_billed: true,
                include_tax: true,
                send_email: false,
            },
        );
        const taxPeriodXlsx = await requestReport(
            api.context,
            '/api/v1/reports/tax_period_report',
            {
                ...genericPayload,
                is_income_billed: true,
            },
        );

        expect(invoiceCsv.toString('utf8')).toContain(invoiceNumber);
        expect(clientSalesCsv.toString('utf8')).toContain(String(client.name));
        expect(taxSummaryCsv.toString('utf8')).toContain(invoiceNumber);
        expect(taxSummaryCsv.toString('utf8')).toContain(taxName);
        expect(
            profitLossRevenue(profitLossCsv) -
                profitLossRevenue(boundedProfitLossCsv),
        ).toBeGreaterThanOrEqual(876.54);
        expect(profitLossCsv.toString('utf8')).toContain('USD,43.21,0');
        expect(taxPeriodXlsx.subarray(0, 2).toString('utf8')).toBe('PK');
        expect(taxPeriodXlsx.length).toBeGreaterThan(1_000);
    });

    test('statement, report, and outstanding-task schedulers preserve and resolve all_time', async ({
        api,
    }) => {
        const client = await createClient(api, 'all-time-scheduler');
        await api.createEntity<AllTimeEntity>('payments', {
            client_id: client.id,
            amount: 20,
            date: '1901-01-02',
            email_receipt: false,
        });

        const emptyClient = await createClient(api, 'all-time-empty-statement');
        const taskStart = Math.floor(
            new Date('1900-01-01T12:00:00Z').getTime() / 1_000,
        );
        await api.createEntity<AllTimeEntity>('tasks', {
            client_id: client.id,
            description: uniqueName('all-time-task'),
            rate: 100,
            time_log: JSON.stringify([
                [taskStart, taskStart + 3_600, null, true],
            ]),
        });

        const statementScheduler = await createScheduler(api, 'email_statement', {
            clients: [client.id],
            date_range: 'all_time',
            show_payments_table: true,
            show_aging_table: true,
            status: 'all',
        });
        const emptyStatementScheduler = await createScheduler(
            api,
            'email_statement',
            {
                clients: [emptyClient.id],
                date_range: 'all_time',
                show_payments_table: true,
                show_aging_table: true,
                status: 'all',
            },
        );
        const reportScheduler = await createScheduler(api, 'email_report', {
            clients: [],
            date_range: 'all_time',
            report_name: 'invoice',
            report_keys: ['invoice.number'],
        });
        const taskScheduler = await createScheduler(
            api,
            'invoice_outstanding_tasks',
            {
                clients: [client.id],
                date_range: 'all_time',
                include_project_tasks: false,
                auto_send: false,
            },
        );

        for (const scheduler of [
            statementScheduler,
            emptyStatementScheduler,
            reportScheduler,
            taskScheduler,
        ]) {
            expect(scheduler.parameters?.date_range).toBe('all_time');
        }

        expect(
            resolveSchedulerDates(
                statementScheduler.id,
                'EmailStatementService',
                client.id,
            )[0],
        ).toBe('1901-01-02');
        expect(
            resolveSchedulerDates(
                emptyStatementScheduler.id,
                'EmailStatementService',
                emptyClient.id,
            )[0],
        ).toBe('2000-01-01');
        expect(
            resolveSchedulerDates(
                taskScheduler.id,
                'InvoiceOutstandingTasksService',
            )[0],
        ).toBe('1900-01-01');
    });
});

async function createClient(
    api: ApiFixture,
    label: string,
): Promise<AllTimeEntity & { name: string }> {
    const name = uniqueName(label);

    return api.createEntity<AllTimeEntity & { name: string }>('clients', {
        name,
        contacts: [
            {
                first_name: 'All Time',
                last_name: 'Playwright',
                email: `${name}@example.com`,
            },
        ],
    });
}

async function createSentInvoice(
    api: ApiFixture,
    clientId: string,
    options: {
        date: string;
        cost: number;
        label: string;
        number?: string;
        projectId?: string;
        taxName?: string;
        taxRate?: number;
    },
): Promise<AllTimeEntity> {
    const item = {
        ...lineItem({ label: options.label, cost: options.cost }),
        ...(options.taxName
            ? {
                  tax_name1: options.taxName,
                  tax_rate1: options.taxRate ?? 0,
              }
            : {}),
    };
    const invoice = await api.createEntityFromBlank<AllTimeEntity>('invoices', {
        client_id: clientId,
        project_id: options.projectId,
        number: options.number,
        date: options.date,
        due_date: isoDate(30),
        line_items: [item],
    });

    await bulkAction(api.context, 'invoices', [invoice.id], 'mark_sent');

    return getEntity<AllTimeEntity>(api.context, 'invoices', invoice.id);
}

async function createSentQuote(
    api: ApiFixture,
    clientId: string,
    options: { date: string; cost: number },
): Promise<AllTimeEntity> {
    const quote = await api.createEntityFromBlank<AllTimeEntity>('quotes', {
        client_id: clientId,
        date: options.date,
        due_date: isoDate(30),
        line_items: [
            lineItem({ label: 'all-time-analytics-quote', cost: options.cost }),
        ],
    });

    await bulkAction(api.context, 'quotes', [quote.id], 'mark_sent');

    return getEntity<AllTimeEntity>(api.context, 'quotes', quote.id);
}

async function createScheduler(
    api: ApiFixture,
    template: string,
    parameters: Record<string, unknown>,
): Promise<AllTimeEntity> {
    return api.createEntity<AllTimeEntity>('task_schedulers', {
        name: uniqueName(`all-time-${template}`),
        frequency_id: 5,
        next_run: isoDate(),
        template,
        parameters,
    });
}

async function postJson(
    request: APIRequestContext,
    path: string,
    data: Record<string, unknown>,
): Promise<any> {
    const response = await request.post(path, { data });

    await expectOk(response, path);

    return response.json();
}

async function calculatedFieldGap(request: APIRequestContext): Promise<number> {
    const allTimeTotal = Number(
        await postJson(request, '/api/v1/charts/calculated_fields', {
            date_range: 'all_time',
            field: 'active_invoices',
            calculation: 'sum',
            period: 'current',
        }),
    );
    const boundedTotal = Number(
        await postJson(request, '/api/v1/charts/calculated_fields', {
            date_range: 'custom',
            start_date: '2000-01-01',
            end_date: isoDate(),
            field: 'active_invoices',
            calculation: 'sum',
            period: 'current',
        }),
    );

    return allTimeTotal - boundedTotal;
}

async function expectOk(response: APIResponse, context: string): Promise<void> {
    if (response.ok()) {
        return;
    }

    throw new Error(
        `${context} failed (${response.status()}): ${(await response.text()).slice(0, 500)}`,
    );
}

async function requestReport(
    api: ApiContext,
    path: string,
    data: Record<string, unknown>,
): Promise<Buffer> {
    const response = await api.request.post(path, { data });

    await expectOk(response, path);

    const body = (await response.json()) as { message?: string };

    if (!body.message) {
        throw new Error(`${path} did not return a report preview hash.`);
    }

    const deadline = Date.now() + 45_000;

    while (Date.now() < deadline) {
        const preview = await api.request.post(
            `/api/v1/reports/preview/${body.message}`,
        );

        if (preview.ok()) {
            const encoded = await preview.json();

            if (typeof encoded !== 'string') {
                throw new Error(`${path} preview did not return base64 content.`);
            }

            return Buffer.from(encoded, 'base64');
        }

        if (preview.status() !== 409) {
            await expectOk(preview, `${path} preview`);
        }

        await new Promise((resolve) => setTimeout(resolve, 500));
    }

    throw new Error(`${path} preview was not ready within 45 seconds.`);
}

function resolveSchedulerDates(
    schedulerHashedId: string,
    service: 'EmailStatementService' | 'InvoiceOutstandingTasksService',
    clientHashedId?: string,
): string[] {
    const schedulerId = decodePrimaryKey(schedulerHashedId);
    const clientArgument = clientHashedId
        ? `, \\App\\Models\\Client::findOrFail(${decodePrimaryKey(clientHashedId)})`
        : '';
    const output = runArtisan(
        `$scheduler = \\App\\Models\\Scheduler::findOrFail(${schedulerId});` +
            `$service = new \\App\\Services\\Scheduler\\${service}($scheduler);` +
            `$method = new \\ReflectionMethod($service, "calculateStartAndEndDates");` +
            `echo json_encode($method->invoke($service${clientArgument}));`,
    );

    return JSON.parse(output) as string[];
}

function entityDate(value: number | string | undefined): string {
    if (value === undefined) {
        throw new Error('Project response did not include created_at.');
    }

    const date =
        typeof value === 'number'
            ? new Date(value * 1_000)
            : /^\d+$/.test(value)
              ? new Date(Number(value) * 1_000)
              : new Date(value);

    if (Number.isNaN(date.getTime())) {
        throw new Error(`Could not parse entity date: ${value}`);
    }

    return date.toISOString().slice(0, 10);
}

function profitLossRevenue(report: Buffer): number {
    const match = report.toString('utf8').match(/Total Revenue.*?\$([\d,]+\.\d{2})/s);

    if (!match) {
        throw new Error('Profit and loss report did not contain total revenue.');
    }

    return Number(match[1].replaceAll(',', ''));
}
