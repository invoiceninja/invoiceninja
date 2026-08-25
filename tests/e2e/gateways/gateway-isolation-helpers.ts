import {
    bulkAction,
    ensureCompanyGatewayTypeEnabled,
    listCompanyGateways,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { decodePrimaryKey } from '../hash-helpers';

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
