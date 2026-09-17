import {
    isGatewayMethodEnabled,
    type CompanyGatewayEntity,
} from '../../api-helpers';

/** Gateway type ids exposed by `PayPalRestPaymentDriver::gatewayTypes()`. */
export const PAYPAL_REST_GATEWAY_TYPE_IDS = [3, 1, 25, 28, 29] as const;

export type PayPalRestGatewayTypeId =
    (typeof PAYPAL_REST_GATEWAY_TYPE_IDS)[number];

export interface PayPalRestPaymentMethod {
    gatewayTypeId: PayPalRestGatewayTypeId;
    slug: string;
    label: string;
    /** PayPal SDK `data-funding-source` for button-based checkout views. */
    fundingSource?: string;
    supportsSandboxPayment: boolean;
    /**
     * False when automated browser completion is blocked (e.g. Venmo bot challenge).
     * Checkout UI tests may still run.
     */
    supportsE2ePaymentCompletion: boolean;
    checkoutKind: 'buttons' | 'advanced-cards';
    /** Human-readable note when e2e payment completion is not supported. */
    e2ePaymentCompletionSkipReason?: string;
}

export const PAYPAL_REST_PAYMENT_METHODS: PayPalRestPaymentMethod[] = [
    {
        gatewayTypeId: 3,
        slug: 'paypal',
        label: 'PayPal',
        fundingSource: 'paypal',
        supportsSandboxPayment: true,
        supportsE2ePaymentCompletion: true,
        checkoutKind: 'buttons',
    },
    {
        gatewayTypeId: 25,
        slug: 'venmo',
        label: 'Venmo',
        fundingSource: 'venmo',
        supportsSandboxPayment: false,
        supportsE2ePaymentCompletion: false,
        e2ePaymentCompletionSkipReason:
            'Venmo checkout presents a PayPal bot challenge that cannot be completed in automated e2e',
        checkoutKind: 'buttons',
    },
    {
        gatewayTypeId: 28,
        slug: 'paylater',
        label: 'Pay Later',
        fundingSource: 'paylater',
        supportsSandboxPayment: true,
        supportsE2ePaymentCompletion: true,
        checkoutKind: 'buttons',
    },
    {
        gatewayTypeId: 29,
        slug: 'advanced-cards',
        label: 'PayPal Advanced Cards',
        supportsSandboxPayment: true,
        supportsE2ePaymentCompletion: true,
        checkoutKind: 'advanced-cards',
    },
];

/** Legacy card-button funding (type 1). Tested in a separate e2e fixture without advanced cards. */
export const PAYPAL_REST_LEGACY_CARD_METHOD: PayPalRestPaymentMethod = {
    gatewayTypeId: 1,
    slug: 'card',
    label: 'Credit Card',
    fundingSource: 'card',
    supportsSandboxPayment: true,
    supportsE2ePaymentCompletion: true,
    checkoutKind: 'buttons',
};

/**
 * Mirrors `PayPalBasePaymentDriver::gatewayTypes()` — advanced cards (29)
 * replaces legacy card (1) when both are enabled.
 */
export function listEnabledPayPalRestMethodIds(
    gateway: CompanyGatewayEntity,
): PayPalRestGatewayTypeId[] {
    const enabled = PAYPAL_REST_GATEWAY_TYPE_IDS.filter((typeId) =>
        isGatewayMethodEnabled(gateway, typeId),
    );

    if (enabled.includes(1) && enabled.includes(29)) {
        return enabled.filter((typeId) => typeId !== 1);
    }

    return enabled;
}

export function enabledPayPalRestPaymentMethods(
    gateway: CompanyGatewayEntity,
): PayPalRestPaymentMethod[] {
    const enabledIds = new Set(listEnabledPayPalRestMethodIds(gateway));
    const methods = PAYPAL_REST_PAYMENT_METHODS.filter((method) =>
        enabledIds.has(method.gatewayTypeId),
    );

    if (enabledIds.has(PAYPAL_REST_LEGACY_CARD_METHOD.gatewayTypeId)) {
        methods.push(PAYPAL_REST_LEGACY_CARD_METHOD);
    }

    return methods.sort(
        (left, right) => left.gatewayTypeId - right.gatewayTypeId,
    );
}

export function payPalRestPaymentMethodByTypeId(
    gatewayTypeId: number,
): PayPalRestPaymentMethod | undefined {
    return (
        PAYPAL_REST_PAYMENT_METHODS.find(
            (method) => method.gatewayTypeId === gatewayTypeId,
        ) ??
        (gatewayTypeId === PAYPAL_REST_LEGACY_CARD_METHOD.gatewayTypeId
            ? PAYPAL_REST_LEGACY_CARD_METHOD
            : undefined)
    );
}

/** PayPal REST methods that must complete a sandbox payment in e2e. */
export const PAYPAL_SANDBOX_PAYMENT_METHOD_IDS = [1, 3, 25, 28, 29] as const;

/** Methods that must run the full sandbox payment completion e2e. */
export const PAYPAL_E2E_PAYMENT_COMPLETION_METHOD_IDS = [1, 3, 28, 29] as const;

/**
 * Pay Later can still be unavailable for specific transaction amounts in sandbox.
 * Venmo and Pay Later both require buyer-country=US on the JS SDK in sandbox.
 */
export const PAYPAL_OPTIONAL_SANDBOX_METHOD_IDS = [] as const;

export function supportsPayPalE2ePaymentCompletion(
    method: PayPalRestPaymentMethod,
): boolean {
    return method.supportsE2ePaymentCompletion;
}

export function payPalE2ePaymentCompletionSkipReason(
    method: PayPalRestPaymentMethod,
): string {
    return (
        method.e2ePaymentCompletionSkipReason ??
        `${method.label} does not support automated e2e payment completion`
    );
}

export const PAYPAL_FUNDING_BUTTON_LABELS: Partial<
    Record<string, readonly string[]>
> = {
    paypal: ['PayPal', 'Pay with PayPal'],
    card: ['Debit or Credit Card', 'Credit Card', 'Pay with Debit or Credit Card'],
    venmo: ['Venmo', 'Pay with Venmo'],
    paylater: ['Pay Later', 'Pay Pal Pay Later', 'Pay in 4'],
};

export function isPayPalSandboxPaymentMethod(
    method: PayPalRestPaymentMethod,
): boolean {
    return PAYPAL_SANDBOX_PAYMENT_METHOD_IDS.includes(
        method.gatewayTypeId as (typeof PAYPAL_SANDBOX_PAYMENT_METHOD_IDS)[number],
    );
}

export function isOptionalPayPalSandboxMethod(
    method: PayPalRestPaymentMethod,
): boolean {
    return PAYPAL_OPTIONAL_SANDBOX_METHOD_IDS.includes(
        method.gatewayTypeId as (typeof PAYPAL_OPTIONAL_SANDBOX_METHOD_IDS)[number],
    );
}

export function payPalSandboxPaymentMethods(
    gateway: CompanyGatewayEntity,
): PayPalRestPaymentMethod[] {
    const enabledIds = new Set(listEnabledPayPalRestMethodIds(gateway));

    return PAYPAL_REST_PAYMENT_METHODS.filter(
        (method) =>
            PAYPAL_SANDBOX_PAYMENT_METHOD_IDS.includes(
                method.gatewayTypeId as (typeof PAYPAL_SANDBOX_PAYMENT_METHOD_IDS)[number],
            ) && enabledIds.has(method.gatewayTypeId),
    );
}
