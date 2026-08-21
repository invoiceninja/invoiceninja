import {
    isGatewayMethodEnabled,
    type CompanyGatewayEntity,
} from '../api-helpers';

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
    checkoutKind: 'buttons' | 'advanced-cards';
}

export const PAYPAL_REST_PAYMENT_METHODS: PayPalRestPaymentMethod[] = [
    {
        gatewayTypeId: 3,
        slug: 'paypal',
        label: 'PayPal',
        fundingSource: 'paypal',
        supportsSandboxPayment: true,
        checkoutKind: 'buttons',
    },
    {
        gatewayTypeId: 25,
        slug: 'venmo',
        label: 'Venmo',
        fundingSource: 'venmo',
        supportsSandboxPayment: true,
        checkoutKind: 'buttons',
    },
    {
        gatewayTypeId: 28,
        slug: 'paylater',
        label: 'Pay Later',
        fundingSource: 'paylater',
        supportsSandboxPayment: true,
        checkoutKind: 'buttons',
    },
    {
        gatewayTypeId: 29,
        slug: 'advanced-cards',
        label: 'PayPal Advanced Cards',
        supportsSandboxPayment: true,
        checkoutKind: 'advanced-cards',
    },
    {
        gatewayTypeId: 1,
        slug: 'card',
        label: 'Credit Card',
        fundingSource: 'card',
        supportsSandboxPayment: false,
        checkoutKind: 'buttons',
    },
];

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

    return PAYPAL_REST_PAYMENT_METHODS.filter((method) =>
        enabledIds.has(method.gatewayTypeId),
    );
}

export function payPalRestPaymentMethodByTypeId(
    gatewayTypeId: number,
): PayPalRestPaymentMethod | undefined {
    return PAYPAL_REST_PAYMENT_METHODS.find(
        (method) => method.gatewayTypeId === gatewayTypeId,
    );
}

/** PayPal REST methods that must complete a sandbox payment in e2e. */
export const PAYPAL_SANDBOX_PAYMENT_METHOD_IDS = [3, 25, 28, 29] as const;

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
