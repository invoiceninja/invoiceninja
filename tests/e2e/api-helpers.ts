import {
    request as playwrightRequest,
    type APIRequestContext,
} from '@playwright/test';

export type EntityType = string;
export type ApiEntity = Record<string, unknown> & { id?: string };

export interface ApiContext {
    baseUrl: string;
    headers: Record<string, string>;
    request: APIRequestContext;
}

export interface CompanyEntity extends ApiEntity {
    id: string;
    company_key: string;
    client_can_register: boolean;
    enabled_modules?: number;
}

export interface ClientEntity extends ApiEntity {
    id: string;
    name?: string;
    settings?: Record<string, unknown>;
    contacts?: Array<Record<string, unknown>>;
}

export interface ApiUser extends ApiEntity {
    id: string;
    company_user?: {
        notifications?: {
            email?: string[];
            [key: string]: unknown;
        };
        [key: string]: unknown;
    };
}

export async function createMultipartApiContext(
    api: ApiContext,
): Promise<APIRequestContext> {
    return playwrightRequest.newContext({
        baseURL: api.baseUrl,
        extraHTTPHeaders: {
            'X-Api-Token': api.headers['X-Api-Token'],
            'X-Requested-With': 'XMLHttpRequest',
        },
    });
}

export async function createApiContext(
    apiUrl: string,
    email = 'user@example.com',
    password = 'password',
): Promise<ApiContext> {
    const loginContext = await playwrightRequest.newContext({ baseURL: apiUrl });
    const response = await loginContext.post('/api/v1/login', {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        data: { email, password },
    });

    if (!response.ok()) {
        const message = await response.text();
        await loginContext.dispose();
        throw new Error(
            `API login failed (${response.status()}): ${message.slice(0, 300)}`,
        );
    }

    const body = await response.json();
    const token =
        body.data?.[0]?.token?.token ??
        body.data?.token?.token ??
        body.token;

    await loginContext.dispose();

    if (!token) {
        throw new Error('Could not extract an API token from the login response.');
    }

    const headers = {
        'X-Api-Token': String(token),
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/json',
    };

    return {
        baseUrl: apiUrl,
        headers,
        request: await playwrightRequest.newContext({
            baseURL: apiUrl,
            extraHTTPHeaders: headers,
        }),
    };
}

export async function createEntityViaApi<T extends ApiEntity = ApiEntity>(
    api: ApiContext,
    entityType: EntityType,
    data: Record<string, unknown>,
): Promise<T> {
    const response = await api.request.post(`/api/v1/${entityType}`, { data });

    if (!response.ok()) {
        throw new Error(
            `Failed to create ${entityType} (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return body.data as T;
}

export async function createEntityFromBlankViaApi<
    T extends ApiEntity = ApiEntity,
>(
    api: ApiContext,
    entityType: EntityType,
    overrides: Record<string, unknown>,
): Promise<T> {
    const blankResponse = await api.request.get(
        `/api/v1/${entityType}/create`,
    );

    if (!blankResponse.ok()) {
        throw new Error(
            `Failed to fetch blank ${entityType} (${blankResponse.status()}): ${(await blankResponse.text()).slice(0, 300)}`,
        );
    }

    const body = await blankResponse.json();

    return createEntityViaApi<T>(api, entityType, {
        ...body.data,
        ...overrides,
    });
}

export async function bulkAction(
    api: ApiContext,
    entityType: EntityType,
    ids: string[],
    action: string,
    extra: Record<string, unknown> = {},
): Promise<void> {
    if (ids.length === 0) {
        return;
    }

    const response = await api.request.post(`/api/v1/${entityType}/bulk`, {
        data: { action, ids, ...extra },
    });

    if (!response.ok()) {
        throw new Error(
            `Bulk ${action} failed for ${entityType} (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }
}

export async function getCompany(
    api: ApiContext,
): Promise<CompanyEntity> {
    const response = await api.request.get('/api/v1/companies');

    if (!response.ok()) {
        throw new Error(`Failed to fetch company (${response.status()}).`);
    }

    const body = await response.json();
    const company = body.data?.[0];

    if (!company?.id) {
        throw new Error('The API account did not return a company.');
    }

    return company as CompanyEntity;
}

export async function updateClient(
    api: ApiContext,
    client: ClientEntity,
    changes: Record<string, unknown> = {},
): Promise<ClientEntity> {
    const { settings: settingsChanges, ...rest } = changes;

    const response = await api.request.put(`/api/v1/clients/${client.id}`, {
        data: {
            ...client,
            ...rest,
            settings: {
                ...(client.settings ?? {}),
                ...(settingsChanges as Record<string, unknown> | undefined),
            },
        },
    });

    if (!response.ok()) {
        throw new Error(
            `Failed to update client ${client.id} (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return body.data as ClientEntity;
}

export async function updateCompany(
    api: ApiContext,
    company: CompanyEntity,
): Promise<CompanyEntity> {
    const response = await api.request.put(`/api/v1/companies/${company.id}`, {
        data: company,
    });

    if (!response.ok()) {
        throw new Error(
            `Failed to update company (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return body.data as CompanyEntity;
}

export async function getUsers(api: ApiContext): Promise<ApiUser[]> {
    const response = await api.request.get(
        '/api/v1/users?include=company_user&per_page=100',
    );

    if (!response.ok()) {
        throw new Error(`Failed to fetch users (${response.status()}).`);
    }

    const body = await response.json();

    return (body.data ?? []) as ApiUser[];
}

export async function updateUser(
    api: ApiContext,
    user: ApiUser,
): Promise<ApiUser> {
    const response = await api.request.put(
        `/api/v1/users/${user.id}?include=company_user`,
        { data: user },
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to update user ${user.id} (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return body.data as ApiUser;
}

export async function getEntity<T extends ApiEntity = ApiEntity>(
    api: ApiContext,
    entityType: EntityType,
    id: string,
): Promise<T> {
    const response = await api.request.get(`/api/v1/${entityType}/${id}`);

    if (!response.ok()) {
        throw new Error(
            `Failed to fetch ${entityType}/${id} (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return body.data as T;
}

export interface FeesAndLimitsEntry {
    is_enabled?: boolean;
    min_limit?: number;
    max_limit?: number;
}

export interface CompanyGatewayEntity extends ApiEntity {
    id: string;
    gateway_key: string;
    fees_and_limits?: Record<string, FeesAndLimitsEntry>;
}

export async function listCompanyGateways(
    api: ApiContext,
): Promise<CompanyGatewayEntity[]> {
    const response = await api.request.get(
        '/api/v1/company_gateways?per_page=100',
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to fetch company gateways (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return (body.data ?? []) as CompanyGatewayEntity[];
}

export function isGatewayMethodEnabled(
    gateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): boolean {
    const limits = gateway.fees_and_limits;

    if (!limits) {
        return true;
    }

    const entry = limits[String(gatewayTypeId)];

    if (!entry) {
        return true;
    }

    return entry.is_enabled !== false;
}

export function findCompanyGatewayByKey(
    gateways: CompanyGatewayEntity[],
    gatewayKey: string,
    gatewayTypeId: number,
): CompanyGatewayEntity | undefined {
    return gateways.find((gateway) => gateway.gateway_key === gatewayKey);
}

/**
 * Portal `getPaymentMethods()` only offers a gateway type when
 * `fees_and_limits.{type}` exists and `is_enabled` is true. Empty `{}` means
 * the gateway never appears in the Pay Now dropdown.
 */
export async function ensureCompanyGatewayTypeEnabled(
    api: ApiContext,
    gateway: CompanyGatewayEntity,
    gatewayTypeId: number,
): Promise<CompanyGatewayEntity> {
    const typeKey = String(gatewayTypeId);
    const current = gateway.fees_and_limits?.[typeKey];

    if (current?.is_enabled) {
        return gateway;
    }

    const feesAndLimits = {
        ...(gateway.fees_and_limits ?? {}),
        [typeKey]: {
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
            ...(current ?? {}),
        },
    };

    const response = await api.request.put(
        `/api/v1/company_gateways/${gateway.id}`,
        {
            data: {
                ...gateway,
                fees_and_limits: feesAndLimits,
            },
        },
    );

    if (!response.ok()) {
        throw new Error(
            `Failed to enable gateway type ${gatewayTypeId} on ${gateway.gateway_key} (${response.status()}): ${(await response.text()).slice(0, 300)}`,
        );
    }

    const body = await response.json();

    return (body.data ?? {
        ...gateway,
        fees_and_limits: feesAndLimits,
    }) as CompanyGatewayEntity;
}
