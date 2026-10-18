import { test } from '../../fixtures';
import { getCompanyGateway } from '../../api-helpers';
import { type PortalPaymentFlow } from '../../gateways/payment-flow-helpers';
import { type GatewayAvailability } from '../../gateways/types';
import { definePayPalRequiredClientInfoSuite } from './required-client-info-suite';
import { PayPalPaymentGateway } from './payment-gateway';

const paypal = new PayPalPaymentGateway();

test.use({
    headless:
        process.env.PLAYWRIGHT_HEADLESS === '1' || process.env.CI === 'true',
});

test.describe.configure({ timeout: 120_000 });

function definePayPalRequiredClientInfoFlowSuite(
    paymentFlow: PortalPaymentFlow,
): void {
    test.describe(`PayPal REST required client info — ${paymentFlow} flow`, () => {
        let availability: GatewayAvailability;
        let setupSkipReason: string | undefined;

        test.beforeAll(async ({ workerApi }) => {
            const setup = await paypal.setupExclusiveTestEnvironment(workerApi);

            if (setup.skipReason) {
                setupSkipReason = setup.skipReason;
            }

            availability = setup.availability;

            if (availability.companyGateway) {
                availability = {
                    ...availability,
                    companyGateway: await getCompanyGateway(
                        workerApi,
                        availability.companyGateway.id,
                    ),
                };
            }
        }, 120_000);

        test.afterAll(async () => {
            await paypal.restoreExclusiveGateway();
        });

        definePayPalRequiredClientInfoSuite({
            paymentFlow,
            setupSuite: () => ({
                availability,
                setupSkipReason,
            }),
        });
    });
}

for (const paymentFlow of ['default', 'smooth'] as const) {
    definePayPalRequiredClientInfoFlowSuite(paymentFlow);
}
