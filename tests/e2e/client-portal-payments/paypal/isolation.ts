import {
    bulkAction,
    createEntityViaApi,
    ensureCompanyGatewayTypeEnabled,
    getCompanyGateway,
    isGatewayMethodEnabled,
    parseCompanyGatewayConfig,
    testCompanyGatewayWithRetry,
    updateCompanyGatewayRequirements,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../../api-helpers';
import { decodePrimaryKey } from '../../hash-helpers';
import {
    isolateCompanyGateway,
    isCompanyGatewayArchived,
    listAllCompanyGateways,
} from '../../gateways/gateway-isolation-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import { parsePayPalRestKeys, type PayPalRestKeys } from './env';
import {
    PAYPAL_REST_GATEWAY_TYPE_IDS,
    type PayPalRestGatewayTypeId,
} from './payment-methods';

export { parsePayPalRestKeys, type PayPalRestKeys } from './env';

export const PAYPAL_REST_GATEWAY_KEY = '80af24a6a691230bbec33e930ab40665';

/** Payment types enabled when scaffolding PayPal REST PPCP e2e (excludes legacy card). */
export const PAYPAL_REST_E2E_METHOD_TYPE_IDS = [3, 25, 28, 29] as const;

/** Legacy card-button funding — PayPal + card only; advanced cards disabled. */
export const PAYPAL_REST_LEGACY_CARD_E2E_METHOD_TYPE_IDS = [3, 1] as const;

export type PayPalRestE2eFeeProfile = 'ppcp' | 'legacy-card';

export function listPayPalRestCompanyGateways(
    gateways: CompanyGatewayEntity[],
): CompanyGatewayEntity[] {
    return gateways.filter(
        (gateway) => gateway.gateway_key === PAYPAL_REST_GATEWAY_KEY,
    );
}

/**
 * Prefer an active gateway; otherwise reuse the oldest archived match so we do
 * not create another company gateway row on every test run.
 */
export function selectCanonicalPayPalRestGateway(
    gateways: CompanyGatewayEntity[],
): CompanyGatewayEntity | undefined {
    const matches = listPayPalRestCompanyGateways(gateways);

    if (matches.length === 0) {
        return undefined;
    }

    const active = matches.filter(
        (gateway) => !isCompanyGatewayArchived(gateway),
    );
    const pool = active.length > 0 ? active : matches;

    return [...pool].sort((left, right) =>
        left.id.localeCompare(right.id),
    )[0];
}

function payPalRestGatewayHasE2eFees(
    gateway: CompanyGatewayEntity,
    feeProfile: PayPalRestE2eFeeProfile = 'ppcp',
): boolean {
    const enabledTypeIds =
        feeProfile === 'legacy-card'
            ? PAYPAL_REST_LEGACY_CARD_E2E_METHOD_TYPE_IDS
            : PAYPAL_REST_E2E_METHOD_TYPE_IDS;

    if (
        !enabledTypeIds.every((typeId) => isGatewayMethodEnabled(gateway, typeId))
    ) {
        return false;
    }

    if (feeProfile === 'legacy-card') {
        return [25, 28, 29].every(
            (typeId) => !isGatewayMethodEnabled(gateway, typeId),
        );
    }

    return !isGatewayMethodEnabled(gateway, 1);
}

/**
 * Leaves the gateway under test as the only one the portal offers.
 *
 * The gatewayGuard fixture puts back whatever this archives when the test ends.
 *
 * @see tests/e2e/fixtures.ts
 */
async function archiveNonCanonicalActiveGateways(
    api: ApiContext,
    canonicalGatewayId: string,
    gateways: CompanyGatewayEntity[],
): Promise<void> {
    const duplicateIds = gateways
        .filter(
            (gateway) =>
                gateway.id !== canonicalGatewayId &&
                !isCompanyGatewayArchived(gateway),
        )
        .map((gateway) => gateway.id);

    if (duplicateIds.length > 0) {
        await bulkAction(api, 'company_gateways', duplicateIds, 'archive');
    }
}

async function restorePayPalRestGatewayIfArchived(
    api: ApiContext,
    gateway: CompanyGatewayEntity,
): Promise<CompanyGatewayEntity> {
    if (!isCompanyGatewayArchived(gateway)) {
        return gateway;
    }

    await bulkAction(api, 'company_gateways', [gateway.id], 'restore');

    return getCompanyGateway(api, gateway.id);
}

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

function buildPayPalRestFeesAndLimits(
    feeProfile: PayPalRestE2eFeeProfile = 'ppcp',
): Record<string, Record<string, unknown>> {
    if (feeProfile === 'legacy-card') {
        const feesAndLimits: Record<string, Record<string, unknown>> = {};

        for (const typeId of PAYPAL_REST_LEGACY_CARD_E2E_METHOD_TYPE_IDS) {
            feesAndLimits[String(typeId)] = buildPayPalRestFeeLimitEntry();
        }

        for (const typeId of [25, 28, 29] as const) {
            feesAndLimits[String(typeId)] = {
                ...buildPayPalRestFeeLimitEntry(),
                is_enabled: false,
            };
        }

        return feesAndLimits;
    }

    const feesAndLimits: Record<string, Record<string, unknown>> = {};

    for (const typeId of PAYPAL_REST_E2E_METHOD_TYPE_IDS) {
        feesAndLimits[String(typeId)] = buildPayPalRestFeeLimitEntry();
    }

    // Advanced cards (29) replaces legacy card-button funding (1). Leaving
    // both enabled duplicates the "Credit Card" Pay Now option in the portal.
    feesAndLimits['1'] = {
        ...buildPayPalRestFeeLimitEntry(),
        is_enabled: false,
    };

    return feesAndLimits;
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
 */
export async function ensurePayPalRestCompanyGateway(
    api: ApiContext,
    keys: PayPalRestKeys,
    options: {
        gatewayTypeId?: PayPalRestGatewayTypeId;
        feeProfile?: PayPalRestE2eFeeProfile;
    } = {},
): Promise<CompanyGatewayEntity> {
    const gatewayTypeId = options.gatewayTypeId ?? 3;
    const feeProfile = options.feeProfile ?? 'ppcp';
    const feesAndLimitsForProfile = buildPayPalRestFeesAndLimits(feeProfile);
    const gateways = await listAllCompanyGateways(api);
    let gateway = selectCanonicalPayPalRestGateway(gateways);

    if (!gateway) {
        gateway = await createEntityViaApi<CompanyGatewayEntity>(
            api,
            'company_gateways',
            {
                gateway_key: PAYPAL_REST_GATEWAY_KEY,
                label: 'PayPal REST (Playwright)',
                config: buildPayPalRestConfigPayload(keys),
                fees_and_limits: feesAndLimitsForProfile,
            },
        );

        return ensureCompanyGatewayTypeEnabled(
            api,
            await getCompanyGateway(api, gateway.id),
            gatewayTypeId,
        );
    }

    await archiveNonCanonicalActiveGateways(api, gateway.id, gateways);
    gateway = await restorePayPalRestGatewayIfArchived(api, gateway);

    const configNeedsUpdate = payPalRestGatewayNeedsConfigUpdate(gateway, keys);
    const feesNeedUpdate = !payPalRestGatewayHasE2eFees(gateway, feeProfile);

    if (!configNeedsUpdate && !feesNeedUpdate) {
        gateway = await ensureCompanyGatewayTypeEnabled(
            api,
            gateway,
            gatewayTypeId,
        );

        return getCompanyGateway(api, gateway.id);
    }

    const mergedFeesAndLimits = {
        ...(gateway.fees_and_limits ?? {}),
        ...feesAndLimitsForProfile,
    };

    if (configNeedsUpdate) {
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

export interface ExclusiveGatewaySetupResult {
    availability: GatewayAvailability;
    restore?: () => Promise<void>;
    skipReason?: string;
}

export async function resolvePayPalRestCompanyGateway(
    api: ApiContext,
    gatewayKey: string = PAYPAL_REST_GATEWAY_KEY,
): Promise<CompanyGatewayEntity | undefined> {
    if (gatewayKey !== PAYPAL_REST_GATEWAY_KEY) {
        const gateways = await listAllCompanyGateways(api);

        return gateways.find((gateway) => gateway.gateway_key === gatewayKey);
    }

    const gateways = await listAllCompanyGateways(api);

    return selectCanonicalPayPalRestGateway(gateways);
}

export async function setupExclusivePayPalRestGatewayEnvironment(
    api: ApiContext,
    options: {
        envConfigured: boolean;
        gatewayTypeId: number;
        feeProfile?: PayPalRestE2eFeeProfile;
    },
): Promise<ExclusiveGatewaySetupResult> {
    const keys = parsePayPalRestKeys();
    const feeProfile = options.feeProfile ?? 'ppcp';

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
        freshGateway = await ensurePayPalRestCompanyGateway(api, keys, {
            gatewayTypeId: options.gatewayTypeId as PayPalRestGatewayTypeId,
            feeProfile,
        });
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
            companyGateway: await ensurePayPalRestTokenBillingOptIn(
                api,
                verifiedGateway,
            ),
        },
        restore,
    };
}

async function ensurePayPalRestTokenBillingOptIn(
    api: ApiContext,
    gateway: CompanyGatewayEntity,
): Promise<CompanyGatewayEntity> {
    if (gateway.token_billing === 'optin') {
        return gateway;
    }

    return updateCompanyGatewayRequirements(api, gateway, {
        token_billing: 'optin',
    });
}

export async function setupExclusivePayPalRestLegacyCardGatewayEnvironment(
    api: ApiContext,
    options: {
        envConfigured: boolean;
    },
): Promise<ExclusiveGatewaySetupResult> {
    return setupExclusivePayPalRestGatewayEnvironment(api, {
        envConfigured: options.envConfigured,
        gatewayTypeId: 1,
        feeProfile: 'legacy-card',
    });
}

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
        const gateway = await ensurePayPalRestCompanyGateway(api, keys, {
            gatewayTypeId: gatewayTypeId as PayPalRestGatewayTypeId,
        });
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
