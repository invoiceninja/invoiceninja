import { test } from '../../fixtures';
import { type GatewayAvailability } from '../../gateways/types';
import {
    definePayPalRestMethodSuite,
    type PayPalRestMethodSuiteState,
} from './payments-method-suite';
import { PayPalPaymentGateway } from './payment-gateway';
import {
    PAYPAL_REST_LEGACY_CARD_METHOD,
    PAYPAL_REST_PAYMENT_METHODS,
    type PayPalRestPaymentMethod,
} from './payment-methods';
import { definePayPalAdvancedCardVaultSuite } from './vault-suite';

const paypal = new PayPalPaymentGateway();

// PayPal SDK blocks headless wallet checkout. Force headed for this file
// (VS Code extension does not always inherit playwright.config use.headless).
test.use({
    headless:
        process.env.PLAYWRIGHT_HEADLESS === '1' || process.env.CI === 'true',
});

test.describe.configure({ mode: 'serial', timeout: 300_000 });

test.describe('PayPal REST payment methods (PPCP)', () => {
    let availability: GatewayAvailability;
    let enabledMethods: PayPalRestPaymentMethod[];
    let setupSkipReason: string | undefined;

    test.beforeAll(async ({ workerApi }) => {
        const setup = await paypal.setupExclusiveTestEnvironment(workerApi);

        if (setup.skipReason) {
            setupSkipReason = setup.skipReason;
        }

        availability = setup.availability;
        enabledMethods = paypal.enabledPaymentMethods(availability);

        if (!setupSkipReason && enabledMethods.length === 0) {
            setupSkipReason =
                'PayPal REST gateway has no enabled payment methods in fees_and_limits';
        }
    }, 120_000);

    test.afterAll(async () => {
        await paypal.restoreExclusiveGateway();
    });

    definePayPalRestMethodSuite({
        paypal,
        methods: PAYPAL_REST_PAYMENT_METHODS,
        setupSuite: (): PayPalRestMethodSuiteState => ({
            availability,
            enabledMethods,
            setupSkipReason,
        }),
    });
});

test.describe('PayPal REST legacy card funding', () => {
    let availability: GatewayAvailability;
    let enabledMethods: PayPalRestPaymentMethod[];
    let setupSkipReason: string | undefined;

    test.beforeAll(async ({ workerApi }) => {
        const setup =
            await paypal.setupLegacyCardExclusiveTestEnvironment(workerApi);

        if (setup.skipReason) {
            setupSkipReason = setup.skipReason;
        }

        availability = setup.availability;
        enabledMethods = paypal.enabledPaymentMethods(availability);

        if (!setupSkipReason && enabledMethods.length === 0) {
            setupSkipReason =
                'PayPal REST legacy card gateway has no enabled payment methods in fees_and_limits';
        }
    }, 120_000);

    test.afterAll(async () => {
        await paypal.restoreExclusiveGateway();
    });

    definePayPalRestMethodSuite({
        paypal,
        methods: [PAYPAL_REST_LEGACY_CARD_METHOD],
        setupSuite: (): PayPalRestMethodSuiteState => ({
            availability,
            enabledMethods,
            setupSkipReason,
        }),
    });
});

test.describe('PayPal REST advanced card vaulting', () => {
    definePayPalAdvancedCardVaultSuite(paypal);
});
