import { test as base } from '@playwright/test';
import { loadPlaywrightEnvironment } from './environment';
import {
    bulkAction,
    createApiContext,
    createEntityFromBlankViaApi,
    createEntityViaApi,
    getCompany,
    getUsers,
    listCompanyGateways,
    updateCompany,
    updateUser,
    type ApiContext,
    type ApiEntity,
    type ApiUser,
    type CompanyEntity,
    type EntityType,
} from './api-helpers';
import { accountForParallelIndex, type TestAccount } from './accounts';

loadPlaywrightEnvironment();

interface TrackedEntity {
    type: EntityType;
    id: string;
}

export interface ApiFixture {
    context: ApiContext;
    createEntity: <T extends ApiEntity = ApiEntity>(
        type: EntityType,
        data: Record<string, unknown>,
        options?: { cleanup?: boolean },
    ) => Promise<T>;
    createEntityFromBlank: <T extends ApiEntity = ApiEntity>(
        type: EntityType,
        overrides: Record<string, unknown>,
        options?: { cleanup?: boolean },
    ) => Promise<T>;
    trackEntity: (type: EntityType, id: string) => void;
}

export interface CompanyGuardFixture {
    update: (fields: Partial<CompanyEntity>) => Promise<CompanyEntity>;
}

export interface NotificationGuardFixture {
    suppressPaymentEmails: () => Promise<void>;
}

export const test = base.extend<
    {
        api: ApiFixture;
        companyGuard: CompanyGuardFixture;
        gatewayGuard: void;
        notificationGuard: NotificationGuardFixture;
    },
    { account: TestAccount; workerApi: ApiContext }
>({
    account: [
        async ({}, use, workerInfo) => {
            await use(accountForParallelIndex(workerInfo.parallelIndex));
        },
        { scope: 'worker' },
    ],

    // Login once per worker. Give this its own budget: Playwright charges
    // worker-fixture time to the first test, which otherwise fails as
    // "setting up context" when remote API login is slow.
    workerApi: [
        async ({ account }, use) => {
            const context = await createApiContext(
                account.apiUrl,
                account.ownerEmail,
                account.password,
            );

            await use(context);
            await context.request.dispose();
        },
        { scope: 'worker', timeout: 60_000 },
    ],

    api: async ({ workerApi }, use) => {
        const tracked: TrackedEntity[] = [];

        await use({
            context: workerApi,

            async createEntity(type, data, options = {}) {
                const entity = await createEntityViaApi(workerApi, type, data);

                if (options.cleanup !== false && entity.id) {
                    tracked.push({ type, id: String(entity.id) });
                }

                return entity;
            },

            async createEntityFromBlank(type, overrides, options = {}) {
                const entity = await createEntityFromBlankViaApi(
                    workerApi,
                    type,
                    overrides,
                );

                if (options.cleanup !== false && entity.id) {
                    tracked.push({ type, id: String(entity.id) });
                }

                return entity;
            },

            trackEntity(type, id) {
                tracked.push({ type, id });
            },
        });

        await cleanupTrackedEntities(workerApi, tracked);
    },

    /**
     * Puts back any company gateway a test archived.
     *
     * Isolating the gateway under test - archiving every other one so the portal offers
     * a single option - is the pattern here. Without this, the archive outlives the test
     * and every later spec finds nothing to pay with.
     */
    gatewayGuard: [
        async ({ workerApi }, use) => {
            const before = (await listCompanyGateways(workerApi))
                .filter((gateway) => !gateway.archived_at && !gateway.is_deleted)
                .map((gateway) => gateway.id);

            await use();

            const archived = (await listCompanyGateways(workerApi))
                .filter(
                    (gateway) =>
                        Boolean(gateway.archived_at) &&
                        before.includes(gateway.id),
                )
                .map((gateway) => gateway.id);

            if (archived.length > 0) {
                await bulkAction(
                    workerApi,
                    'company_gateways',
                    archived,
                    'restore',
                );
            }
        },
        { auto: true },
    ],

    companyGuard: async ({ api }, use) => {
        let original: CompanyEntity | undefined;

        await use({
            async update(fields) {
                original ??= structuredClone(await getCompany(api.context));

                return updateCompany(api.context, {
                    ...original,
                    ...fields,
                });
            },
        });

        if (original) {
            await updateCompany(api.context, original);
        }
    },

    notificationGuard: async ({ api }, use) => {
        const originals: ApiUser[] = [];

        await use({
            async suppressPaymentEmails() {
                const paymentNotifications = new Set([
                    'all_notifications',
                    'all_user_notifications',
                    'payment_manual',
                    'payment_manual_all',
                    'payment_manual_user',
                ]);
                const users = await getUsers(api.context);

                for (const user of users) {
                    const emailNotifications =
                        user.company_user?.notifications?.email;

                    if (
                        !emailNotifications?.some((notification) =>
                            paymentNotifications.has(notification),
                        )
                    ) {
                        continue;
                    }

                    originals.push(structuredClone(user));
                    await updateUser(api.context, {
                        ...user,
                        company_user: {
                            ...user.company_user,
                            notifications: {
                                ...user.company_user?.notifications,
                                email: emailNotifications.filter(
                                    (notification) =>
                                        !paymentNotifications.has(notification),
                                ),
                            },
                        },
                    });
                }
            },
        });

        for (const user of originals) {
            await updateUser(api.context, user);
        }
    },
});

export function uniqueName(prefix: string): string {
    const random = Math.random().toString(36).slice(2, 7);

    return `${prefix}-${Date.now()}-${random}`;
}

async function cleanupTrackedEntities(
    context: ApiContext,
    tracked: TrackedEntity[],
): Promise<void> {
    const idsByType = new Map<EntityType, Set<string>>();

    for (const entity of tracked) {
        const ids = idsByType.get(entity.type) ?? new Set<string>();
        ids.add(entity.id);
        idsByType.set(entity.type, ids);
    }

    // Reverse creation order so dependent resources are removed first.
    for (const [type, ids] of [...idsByType.entries()].reverse()) {
        try {
            await bulkAction(context, type, [...ids], 'archive');
            await bulkAction(context, type, [...ids], 'delete');
        } catch (error) {
            console.warn(`Failed to clean up tracked ${type}: ${error}`);
        }
    }
}

export { expect } from '@playwright/test';
