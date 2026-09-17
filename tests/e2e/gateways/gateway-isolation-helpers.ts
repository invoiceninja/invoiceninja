import {
    bulkAction,
    ensureCompanyGatewayTypeEnabled,
    getCompanyGateway,
    listCompanyGateways,
    testCompanyGatewayWithRetry,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { decodePrimaryKey } from '../hash-helpers';
import { type GatewayAvailability } from './types';

export function findCompanyGatewayByRawId(
    gateways: CompanyGatewayEntity[],
    rawId: string,
): CompanyGatewayEntity | undefined {
    const normalized = String(rawId);

    return gateways.find(
        (gateway) => decodePrimaryKey(gateway.id) === normalized,
    );
}

export function isCompanyGatewayArchived(
    gateway: CompanyGatewayEntity,
): boolean {
    return (
        Boolean(gateway.is_deleted) ||
        (typeof gateway.archived_at === 'number' && gateway.archived_at > 0)
    );
}

export async function listActiveCompanyGateways(
    api: ApiContext,
): Promise<CompanyGatewayEntity[]> {
    const gateways = await listCompanyGateways(api);

    return gateways.filter((gateway) => !isCompanyGatewayArchived(gateway));
}

export async function listAllCompanyGateways(
    api: ApiContext,
): Promise<CompanyGatewayEntity[]> {
    const [active, archived] = await Promise.all([
        listActiveCompanyGateways(api),
        listCompanyGateways(api, { isDeleted: true }),
    ]);

    const merged = new Map<string, CompanyGatewayEntity>();

    for (const gateway of [...active, ...archived]) {
        merged.set(gateway.id, gateway);
    }

    return [...merged.values()];
}

export interface GatewayIsolationResult {
    gateway: CompanyGatewayEntity;
    restore: () => Promise<void>;
}

export interface ExclusiveGatewaySetupResult {
    availability: GatewayAvailability;
    restore?: () => Promise<void>;
    skipReason?: string;
}

export interface EnvGatewaySetupOptions {
    displayName: string;
    gatewayKey: string;
    gatewayTypeId: number;
    envConfigured: boolean;
    envSkipReason?: string;
    skipIsolation?: boolean;
    skipAuthTest?: boolean;
    scaffoldGateway: (
        api: ApiContext,
    ) => Promise<CompanyGatewayEntity | undefined>;
    syncGateway: (
        api: ApiContext,
        gateway: CompanyGatewayEntity,
    ) => Promise<CompanyGatewayEntity>;
}

/**
 * Scaffold a company gateway from env credentials, sync stale config, leave it as
 * the only active gateway, and verify API auth — the same preflight PayPal REST runs
 * before its isolated end-to-end specs.
 */
export async function setupExclusiveEnvGatewayEnvironment(
    api: ApiContext,
    options: EnvGatewaySetupOptions,
): Promise<ExclusiveGatewaySetupResult> {
    if (!options.envConfigured) {
        return {
            availability: {
                envConfigured: false,
                companyGatewayConfigured: false,
            },
            skipReason:
                options.envSkipReason ??
                `${options.displayName}: environment credentials are not configured`,
        };
    }

    let gateway: CompanyGatewayEntity | undefined;

    try {
        gateway = await options.scaffoldGateway(api);

        if (gateway) {
            gateway = await options.syncGateway(api, gateway);
        }
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);

        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
            },
            skipReason: `${options.displayName}: failed to scaffold company gateway — ${message}`,
        };
    }

    if (!gateway) {
        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
            },
            skipReason: `${options.displayName}: no company gateway for key ${options.gatewayKey}`,
        };
    }

    let enabledGateway: CompanyGatewayEntity;

    try {
        enabledGateway = await ensureCompanyGatewayTypeEnabled(
            api,
            gateway,
            options.gatewayTypeId,
        );
    } catch (error) {
        const message = error instanceof Error ? error.message : String(error);

        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
                companyGateway: gateway,
            },
            skipReason: `${options.displayName}: failed to enable payment type ${options.gatewayTypeId} — ${message}`,
        };
    }

    if (options.skipIsolation) {
        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: true,
                companyGateway: enabledGateway,
            },
        };
    }

    const { gateway: isolatedGateway, restore } = await isolateCompanyGateway(
        api,
        enabledGateway,
        options.gatewayTypeId,
    );

    if (!options.skipAuthTest) {
        const authTest = await testCompanyGatewayWithRetry(
            api,
            isolatedGateway.id,
        );

        if (!authTest.ok) {
            await restore();

            return {
                availability: {
                    envConfigured: true,
                    companyGatewayConfigured: false,
                    companyGateway: isolatedGateway,
                },
                skipReason: `${options.displayName} gateway ${decodePrimaryKey(isolatedGateway.id)} failed API auth test: ${authTest.message}`,
            };
        }
    }

    return {
        availability: {
            envConfigured: true,
            companyGatewayConfigured: true,
            companyGateway: await getCompanyGateway(api, isolatedGateway.id),
        },
        restore,
    };
}

async function assertOnlyTargetGatewayIsActive(
    api: ApiContext,
    targetGatewayId: string,
): Promise<void> {
    const activeGateways = await listActiveCompanyGateways(api);

    if (activeGateways.length !== 1) {
        throw new Error(
            `Expected exactly one active company gateway (${targetGatewayId}), found ${activeGateways.length}: ${activeGateways.map((gateway) => `${decodePrimaryKey(gateway.id)}:${gateway.gateway_key}`).join(', ')}`,
        );
    }

    if (activeGateways[0].id !== targetGatewayId) {
        throw new Error(
            `Expected active gateway ${targetGatewayId}, found ${activeGateways[0].id}`,
        );
    }
}

/**
 * Leave only the target gateway active for checkout and restore prior state
 * when the returned callback runs.
 */
export async function isolateCompanyGateway(
    api: ApiContext,
    targetGateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): Promise<GatewayIsolationResult> {
    const allGateways = await listAllCompanyGateways(api);
    const activeGateways = await listActiveCompanyGateways(api);
    const archivedBeforeTest = new Map<string, boolean>();

    for (const gateway of allGateways) {
        archivedBeforeTest.set(gateway.id, isCompanyGatewayArchived(gateway));
    }

    const gatewayIdsToArchive = activeGateways
        .filter((gateway) => gateway.id !== targetGateway.id)
        .map((gateway) => gateway.id);

    const gatewayIdsToRestore =
        archivedBeforeTest.get(targetGateway.id) === true
            ? [targetGateway.id]
            : [];

    if (gatewayIdsToRestore.length > 0) {
        await bulkAction(api, 'company_gateways', gatewayIdsToRestore, 'restore');
    }

    if (gatewayIdsToArchive.length > 0) {
        await bulkAction(api, 'company_gateways', gatewayIdsToArchive, 'archive');
    }

    const enabledGateway = await ensureCompanyGatewayTypeEnabled(
        api,
        targetGateway,
        gatewayTypeId,
    );

    await assertOnlyTargetGatewayIsActive(api, enabledGateway.id);

    const restore = async (): Promise<void> => {
        if (gatewayIdsToArchive.length > 0) {
            await bulkAction(
                api,
                'company_gateways',
                gatewayIdsToArchive,
                'restore',
            );
        }

        if (gatewayIdsToRestore.length > 0) {
            await bulkAction(
                api,
                'company_gateways',
                gatewayIdsToRestore,
                'archive',
            );
        }
    };

    return {
        gateway: enabledGateway,
        restore,
    };
}
