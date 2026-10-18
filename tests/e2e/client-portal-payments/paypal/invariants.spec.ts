import { test, expect } from '@playwright/test';
import { type CompanyGatewayEntity } from '../../api-helpers';
import {
    isPayPalPaymentConfirmationUrl,
    merchantPaymentResponseUrlMatcher,
    PAYPAL_INVALID_SANDBOX_CARD_NUMBER,
    PAYPAL_SANDBOX_TEST_CARD,
    payPalSandboxBuyerCredentials,
    requiresPayPalUsFundingSandboxContext,
    withPayPalSandboxBuyerCountry,
} from './flow-helpers';
import {
    enabledPayPalRestPaymentMethods,
    isOptionalPayPalSandboxMethod,
    isPayPalSandboxPaymentMethod,
    listEnabledPayPalRestMethodIds,
    PAYPAL_E2E_PAYMENT_COMPLETION_METHOD_IDS,
    PAYPAL_OPTIONAL_SANDBOX_METHOD_IDS,
    PAYPAL_REST_PAYMENT_METHODS,
    PAYPAL_SANDBOX_PAYMENT_METHOD_IDS,
    payPalE2ePaymentCompletionSkipReason,
    payPalRestPaymentMethodByTypeId,
    supportsPayPalE2ePaymentCompletion,
} from './payment-methods';
import { PayPalPaymentGateway } from './payment-gateway';
import {
    listPayPalRestCompanyGateways,
    PAYPAL_REST_GATEWAY_KEY,
    selectCanonicalPayPalRestGateway,
} from './isolation';

const paypal = new PayPalPaymentGateway();

function gatewayWithFees(
    enabledTypeIds: number[],
    disabledTypeIds: number[] = [],
): CompanyGatewayEntity {
    const fees_and_limits: Record<string, { is_enabled: boolean }> = {};

    for (const typeId of enabledTypeIds) {
        fees_and_limits[String(typeId)] = { is_enabled: true };
    }

    for (const typeId of disabledTypeIds) {
        fees_and_limits[String(typeId)] = { is_enabled: false };
    }

    return {
        id: 'test-gateway',
        gateway_key: '80af24a6a691230bbec33e930ab40665',
        fees_and_limits,
    };
}

test.describe('PayPal REST invariants', () => {
    test('sandbox payment methods include legacy card funding', () => {
        expect(PAYPAL_SANDBOX_PAYMENT_METHOD_IDS).toEqual([1, 3, 25, 28, 29]);
    });

    test('sandbox payment methods are PayPal, Venmo, Pay Later, and Advanced Cards', () => {
        expect(
            PAYPAL_SANDBOX_PAYMENT_METHOD_IDS.filter((typeId) => typeId !== 1),
        ).toEqual([3, 25, 28, 29]);
    });

    test('e2e payment completion excludes Venmo due to bot challenge', () => {
        expect(PAYPAL_E2E_PAYMENT_COMPLETION_METHOD_IDS).toEqual([1, 3, 28, 29]);

        const venmo = payPalRestPaymentMethodByTypeId(25)!;

        expect(supportsPayPalE2ePaymentCompletion(venmo)).toBe(false);
        expect(payPalE2ePaymentCompletionSkipReason(venmo)).toMatch(/bot challenge/i);
    });

    test('suppresses legacy card when advanced cards are enabled', () => {
        const gateway = gatewayWithFees([1, 3, 25, 28, 29]);

        expect(listEnabledPayPalRestMethodIds(gateway)).toEqual([3, 25, 28, 29]);
        expect(listEnabledPayPalRestMethodIds(gateway)).not.toContain(1);
    });

    test('e2e payment method matrix uses advanced cards instead of legacy card funding', () => {
        expect(PAYPAL_REST_PAYMENT_METHODS.map((method) => method.gatewayTypeId)).toEqual([
            3, 25, 28, 29,
        ]);
    });

    test('keeps legacy card when advanced cards are disabled', () => {
        const gateway = gatewayWithFees([1, 3], [25, 28, 29]);

        expect(listEnabledPayPalRestMethodIds(gateway)).toEqual([3, 1]);
    });

    test('enabled payment methods mirror fees_and_limits', () => {
        const gateway = gatewayWithFees([3, 29], [25, 28]);

        const enabled = enabledPayPalRestPaymentMethods(gateway).map(
            (method) => method.gatewayTypeId,
        );

        expect(enabled).toEqual([3, 29]);
    });

    test('defaults missing fee entries to enabled and suppresses legacy card when advanced cards default on', () => {
        const gateway: CompanyGatewayEntity = {
            id: 'test-gateway',
            gateway_key: '80af24a6a691230bbec33e930ab40665',
        };

        expect(listEnabledPayPalRestMethodIds(gateway)).toEqual([
            3, 25, 28, 29,
        ]);
    });

    test('buyer credentials require email and password', () => {
        expect(
            payPalSandboxBuyerCredentials({
                clientId: 'id',
                secret: 'secret',
                testMode: true,
                buyerEmail: 'buyer@example.com',
                buyerPassword: 'secret',
            }),
        ).toEqual({
            email: 'buyer@example.com',
            password: 'secret',
        });

        expect(
            payPalSandboxBuyerCredentials({
                clientId: 'id',
                secret: 'secret',
                testMode: true,
                buyerEmail: 'buyer@example.com',
            }),
        ).toBeNull();

        expect(
            payPalSandboxBuyerCredentials({
                clientId: 'id',
                secret: 'secret',
                testMode: true,
                buyerPassword: 'secret',
            }),
        ).toBeNull();
    });

    test('wallet sandbox completion requires buyer credentials', () => {
        const wallet = payPalRestPaymentMethodByTypeId(3)!;

        expect(
            paypal.methodSupportsSandboxPayment(wallet),
        ).toBe(paypal.hasSandboxBuyerCredentials());
    });

    test('advanced cards sandbox completion does not require buyer credentials', () => {
        const advancedCards = payPalRestPaymentMethodByTypeId(29)!;

        expect(paypal.methodSupportsSandboxPayment(advancedCards)).toBe(true);
    });

    test('legacy card sandbox completion does not require buyer credentials', () => {
        const legacyCard = payPalRestPaymentMethodByTypeId(1)!;

        expect(paypal.methodSupportsSandboxPayment(legacyCard)).toBe(true);
    });

    test('advanced cards invalid sandbox card number passes Luhn but is not a PayPal test PAN', () => {
        expect(PAYPAL_INVALID_SANDBOX_CARD_NUMBER).toBe('4242424242424242');
    });

    test('advanced cards sandbox success card uses official PayPal static Visa test PAN', () => {
        expect(PAYPAL_SANDBOX_TEST_CARD.number).toBe('4012888888881881');
    });

    test('optional sandbox methods are not configured by default', () => {
        expect(PAYPAL_OPTIONAL_SANDBOX_METHOD_IDS).toEqual([]);

        for (const method of PAYPAL_REST_PAYMENT_METHODS) {
            expect(isOptionalPayPalSandboxMethod(method)).toBe(false);
        }
    });

    test('US funding sandbox context applies to Venmo and Pay Later', () => {
        expect(
            requiresPayPalUsFundingSandboxContext(
                payPalRestPaymentMethodByTypeId(25)!,
            ),
        ).toBe(true);
        expect(
            requiresPayPalUsFundingSandboxContext(
                payPalRestPaymentMethodByTypeId(28)!,
            ),
        ).toBe(true);
        expect(
            requiresPayPalUsFundingSandboxContext(
                payPalRestPaymentMethodByTypeId(3)!,
            ),
        ).toBe(false);
    });

    test('Venmo sandbox SDK URLs include buyer-country=US', () => {
        expect(
            withPayPalSandboxBuyerCountry(
                'https://www.paypal.com/sdk/js?client-id=test&enable-funding=venmo',
            ),
        ).toContain('buyer-country=US');
    });

    test('sandbox payment method helper matches the four REST methods', () => {
        for (const method of PAYPAL_REST_PAYMENT_METHODS) {
            const expected = [3, 25, 28, 29].includes(method.gatewayTypeId);

            expect(isPayPalSandboxPaymentMethod(method)).toBe(expected);
        }
    });

    test('merchant payment response matcher accepts portal callback routes', () => {
        expect(
            merchantPaymentResponseUrlMatcher(
                'https://example.test/client/payments/process/response',
            ),
        ).toBe(true);
        expect(
            merchantPaymentResponseUrlMatcher(
                'https://example.test/client/payments/show/abc123',
            ),
        ).toBe(false);
    });

    test('payment confirmation URL excludes checkout process route', () => {
        expect(
            isPayPalPaymentConfirmationUrl(
                'https://example.test/client/payments/process',
            ),
        ).toBe(false);
        expect(
            isPayPalPaymentConfirmationUrl(
                'https://example.test/client/payments/Wnbr9eMYqd',
            ),
        ).toBe(true);
    });

    test('selects an active PayPal REST gateway before archived duplicates', () => {
        const gateways: CompanyGatewayEntity[] = [
            {
                id: 'archived',
                gateway_key: PAYPAL_REST_GATEWAY_KEY,
                is_deleted: true,
                archived_at: 1,
            },
            {
                id: 'active',
                gateway_key: PAYPAL_REST_GATEWAY_KEY,
                is_deleted: false,
            },
        ];

        expect(selectCanonicalPayPalRestGateway(gateways)?.id).toBe('active');
        expect(listPayPalRestCompanyGateways(gateways)).toHaveLength(2);
    });

    test('reuses the lowest-id PayPal REST gateway when multiple are active', () => {
        const gateways: CompanyGatewayEntity[] = [
            {
                id: 'hashed-2',
                gateway_key: PAYPAL_REST_GATEWAY_KEY,
            },
            {
                id: 'hashed-1',
                gateway_key: PAYPAL_REST_GATEWAY_KEY,
            },
        ];

        expect(selectCanonicalPayPalRestGateway(gateways)?.id).toBe('hashed-1');
    });
});
