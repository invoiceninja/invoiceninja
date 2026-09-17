import { test } from '../../fixtures';
import { getCompanyGateway } from '../../api-helpers';
import { type PortalPaymentFlow } from '../../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import {
    definePayPalRffPaymentMethodSuite,
    type PayPalRffPaymentMethodSuiteState,
} from './rff-payments-method-suite';
import { PayPalPaymentGateway } from './payment-gateway';
import {
    PAYPAL_REST_LEGACY_CARD_METHOD,
    PAYPAL_REST_PAYMENT_METHODS,
    type PayPalRestPaymentMethod,
} from './payment-methods';

const paypal = new PayPalPaymentGateway();

test.use({
    headless:
        process.env.PLAYWRIGHT_HEADLESS === '1' || process.env.CI === 'true',
});

test.describe.configure({ mode: 'serial', timeout: 300_000 });

function definePayPalPpcpRffFlowSuite(paymentFlow: PortalPaymentFlow): void {
    test.describe(`PayPal REST RFF payment methods (PPCP) — ${paymentFlow} flow`, () => {
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

            if (availability.companyGateway) {
                availability = {
                    ...availability,
                    companyGateway: await getCompanyGateway(
                        workerApi,
                        availability.companyGateway.id,
                    ),
                };
            }

            if (!setupSkipReason && enabledMethods.length === 0) {
                setupSkipReason =
                    'PayPal REST gateway has no enabled payment methods in fees_and_limits';
            }
        }, 120_000);

        test.afterAll(async () => {
            await paypal.restoreExclusiveGateway();
        });

        definePayPalRffPaymentMethodSuite({
            paypal,
            methods: PAYPAL_REST_PAYMENT_METHODS,
            paymentFlow,
            setupSuite: (): PayPalRffPaymentMethodSuiteState => ({
                availability,
                enabledMethods,
                setupSkipReason,
            }),
        });
    });
}

function definePayPalLegacyCardRffFlowSuite(
    paymentFlow: PortalPaymentFlow,
): void {
    test.describe(`PayPal REST legacy card RFF payment — ${paymentFlow} flow`, () => {
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

            if (availability.companyGateway) {
                availability = {
                    ...availability,
                    companyGateway: await getCompanyGateway(
                        workerApi,
                        availability.companyGateway.id,
                    ),
                };
            }

            if (!setupSkipReason && enabledMethods.length === 0) {
                setupSkipReason =
                    'PayPal REST legacy card gateway has no enabled payment methods in fees_and_limits';
            }
        }, 120_000);

        test.afterAll(async () => {
            await paypal.restoreExclusiveGateway();
        });

        definePayPalRffPaymentMethodSuite({
            paypal,
            methods: [PAYPAL_REST_LEGACY_CARD_METHOD],
            paymentFlow,
            setupSuite: (): PayPalRffPaymentMethodSuiteState => ({
                availability,
                enabledMethods,
                setupSkipReason,
            }),
        });
    });
}

for (const paymentFlow of ['default', 'smooth'] as const) {
    definePayPalPpcpRffFlowSuite(paymentFlow);
    definePayPalLegacyCardRffFlowSuite(paymentFlow);
}
