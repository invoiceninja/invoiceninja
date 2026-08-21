import {
    bulkAction,
    createEntityViaApi,
    ensureCompanyGatewayTypeEnabled,
    getCompanyGateway,
    isGatewayMethodEnabled,
    listCompanyGateways,
    parseCompanyGatewayConfig,
    testCompanyGateway,
    testCompanyGatewayWithRetry,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { decodePrimaryKey } from '../hash-helpers';
import { parsePayPalRestKeys, type PayPalRestKeys } from '../paypal-env';
import {
    PAYPAL_REST_GATEWAY_TYPE_IDS,
    type PayPalRestGatewayTypeId,
} from './paypal-payment-methods';
import { type GatewayAvailability } from './types';

export { parsePayPalRestKeys, type PayPalRestKeys } from '../paypal-env';

export const PAYPAL_REST_GATEWAY_KEY = '80af24a6a691230bbec33e930ab40665';

function buildPayPalRestConfigPayload(keys: PayPalRestKeys): string {
    return JSON.stringify({
        clientId: keys.clientId,
        secret: keys.secret,
        signature: '',
        testMode: keys.testMode,
    });
}

function buildPayPalRestFeeLimitEntry(): Record<string, unknown> {
    return {
        min_limit: -1,
        max_limit: -1,
        fee_amount: 0,
        fee_percent: 0,
        fee_tax_name1: '',
        fee_tax_name2: '',
        fee_tax_name3: '',
        fee_tax_rate1: 0,
        fee_tax_rate2: 0,
        fee_tax_rate3: 0,
        fee_cap: 0,
        adjust_fee_percent: false,
        is_enabled: true,
    };
}

function buildAllPayPalRestFeesAndLimits(): Record<
    string,
    Record<string, unknown>
> {
    const feesAndLimits: Record<string, Record<string, unknown>> = {};

    for (const typeId of PAYPAL_REST_GATEWAY_TYPE_IDS) {
        feesAndLimits[String(typeId)] = buildPayPalRestFeeLimitEntry();
    }

    return feesAndLimits;
}

function buildPayPalRestFeesAndLimits(
    gatewayTypeId: number,
): Record<string, Record<string, unknown>> {
    return {
        [String(gatewayTypeId)]: buildPayPalRestFeeLimitEntry(),
    };
}

function payPalRestGatewayNeedsConfigUpdate(
    gateway: CompanyGatewayEntity,
    keys: PayPalRestKeys,
): boolean {
    const config = parseCompanyGatewayConfig(gateway);
    const clientId = String(config.clientId ?? '').trim();
    const secret = String(config.secret ?? '').trim();
    const testMode = Boolean(config.testMode);

    return (
        !clientId ||
        !secret ||
        clientId !== keys.clientId ||
        secret !== keys.secret ||
        testMode !== keys.testMode
    );
}

/**
 * Ensure the current company has a PayPal REST gateway wired to PAYPAL_REST_KEYS.
 * Creates or updates the gateway via API so tests do not depend on pre-seeded ids
 * or alternate account lanes.
 */
export async function ensurePayPalRestCompanyGateway(
    api: ApiContext,
    keys: PayPalRestKeys,
    gatewayTypeId: PayPalRestGatewayTypeId = 3,
): Promise<CompanyGatewayEntity> {
    const gateways = await listAllCompanyGateways(api);
    let gateway = gateways.find(
        (entry) => entry.gateway_key === PAYPAL_REST_GATEWAY_KEY,
    );

    if (!gateway) {
        gateway = await createEntityViaApi<CompanyGatewayEntity>(
            api,
            'company_gateways',
            {
                gateway_key: PAYPAL_REST_GATEWAY_KEY,
                label: 'PayPal REST (Playwright)',
                config: buildPayPalRestConfigPayload(keys),
                fees_and_limits: buildAllPayPalRestFeesAndLimits(),
            },
        );

        return ensureCompanyGatewayTypeEnabled(
            api,
            await getCompanyGateway(api, gateway.id),
            gatewayTypeId,
        );
    }

    const mergedFeesAndLimits = {
        ...(gateway.fees_and_limits ?? {}),
        ...buildAllPayPalRestFeesAndLimits(),
    };

    if (payPalRestGatewayNeedsConfigUpdate(gateway, keys)) {
        const response = await api.request.put(
            `/api/v1/company_gateways/${gateway.id}`,
            {
                data: {
                    gateway_key: PAYPAL_REST_GATEWAY_KEY,
                    config: buildPayPalRestConfigPayload(keys),
                    fees_and_limits: mergedFeesAndLimits,
                },
            },
        );

        if (!response.ok()) {
            throw new Error(
                `Failed to update PayPal REST company gateway (${response.status()}): ${(await response.text()).slice(0, 300)}`,
            );
        }

        gateway = await getCompanyGateway(api, gateway.id);
    } else {
        const response = await api.request.put(
            `/api/v1/company_gateways/${gateway.id}`,
            {
                data: {
                    gateway_key: PAYPAL_REST_GATEWAY_KEY,
                    fees_and_limits: mergedFeesAndLimits,
                },
            },
        );

        if (!response.ok()) {
            throw new Error(
                `Failed to enable PayPal REST payment methods (${response.status()}): ${(await response.text()).slice(0, 300)}`,
            );
        }

        gateway = await getCompanyGateway(api, gateway.id);
    }

    gateway = await ensureCompanyGatewayTypeEnabled(api, gateway, gatewayTypeId);

    return getCompanyGateway(api, gateway.id);
}

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

export interface GatewayConfigurationCheck {
    configured: boolean;
    skipReason?: string;
}

export function checkPayPalRestGatewayConfiguration(
    gateway: CompanyGatewayEntity,
    gatewayTypeId: number,
    keys: PayPalRestKeys | null = parsePayPalRestKeys(),
): GatewayConfigurationCheck {
    if (gateway.gateway_key !== PAYPAL_REST_GATEWAY_KEY) {
        return {
            configured: false,
            skipReason: `Expected PayPal REST gateway key, got ${gateway.gateway_key}`,
        };
    }

    const config = parseCompanyGatewayConfig(gateway);
    let clientId = String(config.clientId ?? '').trim();
    let secret = String(config.secret ?? '').trim();

    if ((!clientId || !secret) && keys) {
        clientId ||= keys.clientId;
        secret ||= keys.secret;
    }

    if (!clientId || !secret) {
        return {
            configured: false,
            skipReason:
                'PayPal REST company gateway is missing clientId or secret in config',
        };
    }

    if (!isGatewayMethodEnabled(gateway, gatewayTypeId)) {
        return {
            configured: false,
            skipReason: `PayPal REST gateway ${decodePrimaryKey(gateway.id)} does not have payment type ${gatewayTypeId} enabled in fees_and_limits`,
        };
    }

    return { configured: true };
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

export async function resolvePayPalRestCompanyGateway(
    api: ApiContext,
    gatewayKey: string = PAYPAL_REST_GATEWAY_KEY,
): Promise<CompanyGatewayEntity | undefined> {
    const gateways = await listAllCompanyGateways(api);
    const activeMatch = gateways.find(
        (gateway) =>
            gateway.gateway_key === gatewayKey &&
            !isCompanyGatewayArchived(gateway),
    );

    if (activeMatch) {
        return activeMatch;
    }

    return gateways.find((gateway) => gateway.gateway_key === gatewayKey);
}

export async function setupExclusivePayPalRestGatewayEnvironment(
    api: ApiContext,
    options: {
        envConfigured: boolean;
        gatewayTypeId: number;
    },
): Promise<ExclusiveGatewaySetupResult> {
    const keys = parsePayPalRestKeys();

    if (!options.envConfigured || !keys) {
        return {
            availability: {
                envConfigured: false,
                companyGatewayConfigured: false,
            },
            skipReason:
                'PayPal REST: set PAYPAL_REST_KEYS (JSON with clientId, secret, testMode) to run this test',
        };
    }

    let freshGateway: CompanyGatewayEntity;

    try {
        freshGateway = await ensurePayPalRestCompanyGateway(
            api,
            keys,
            options.gatewayTypeId as PayPalRestGatewayTypeId,
        );
    } catch (error) {
        const message =
            error instanceof Error ? error.message : String(error);

        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
            },
            skipReason: `PayPal REST: failed to scaffold company gateway — ${message}`,
        };
    }
    const configuration = checkPayPalRestGatewayConfiguration(
        freshGateway,
        options.gatewayTypeId,
        keys,
    );

    if (!configuration.configured) {
        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
                companyGateway: freshGateway,
            },
            skipReason: configuration.skipReason,
        };
    }

    const { gateway, restore } = await isolateCompanyGateway(
        api,
        freshGateway,
        options.gatewayTypeId,
    );

    const authTest = await testCompanyGatewayWithRetry(api, gateway.id);

    if (!authTest.ok) {
        await restore();

        return {
            availability: {
                envConfigured: true,
                companyGatewayConfigured: false,
                companyGateway: gateway,
            },
            skipReason: `PayPal REST gateway ${decodePrimaryKey(gateway.id)} failed API auth test: ${authTest.message}`,
        };
    }

    const verifiedGateway = await getCompanyGateway(api, gateway.id);

    return {
        availability: {
            envConfigured: true,
            companyGatewayConfigured: true,
            companyGateway: verifiedGateway,
        },
        restore,
    };
}

/**
 * Scaffold/update PayPal REST from PAYPAL_REST_KEYS without archiving other
 * gateways or running the auth test — enough for Pay Now dropdown smoke tests.
 */
export async function ensurePayPalRestGatewayAvailability(
    api: ApiContext,
    gatewayTypeId: number = 3,
): Promise<GatewayAvailability> {
    const keys = parsePayPalRestKeys();

    if (!keys) {
        return {
            envConfigured: false,
            companyGatewayConfigured: false,
            skipReason:
                'PayPal REST: set PAYPAL_REST_KEYS (JSON with clientId, secret, testMode) to run this test',
        };
    }

    try {
        const gateway = await ensurePayPalRestCompanyGateway(
            api,
            keys,
            gatewayTypeId as PayPalRestGatewayTypeId,
        );
        const configuration = checkPayPalRestGatewayConfiguration(
            gateway,
            gatewayTypeId,
            keys,
        );

        if (!configuration.configured) {
            return {
                envConfigured: true,
                companyGatewayConfigured: false,
                companyGateway: gateway,
                skipReason: configuration.skipReason,
            };
        }

        return {
            envConfigured: true,
            companyGatewayConfigured: true,
            companyGateway: gateway,
        };
    } catch (error) {
        const message =
            error instanceof Error ? error.message : String(error);

        return {
            envConfigured: true,
            companyGatewayConfigured: false,
            skipReason: `PayPal REST: failed to scaffold company gateway — ${message}`,
        };
    }
}
