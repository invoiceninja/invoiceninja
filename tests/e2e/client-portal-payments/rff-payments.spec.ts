import { test } from '../fixtures';
import {
    completeRequiredClientInfoAndUnblockCheckout,
} from '../gateways/rff-payment-flow-helpers';
import {
    navigateToGatewayCheckoutWithoutRequiredClientInfo,
    prepareIncompleteClientPaymentContext,
} from '../gateways/payment-flow-helpers';
import { paymentGateways } from './registry';

test.describe.configure({ timeout: 300_000 });

for (const gateway of paymentGateways) {
    if (gateway.slug === 'paypal') {
        continue;
    }

    test.describe(`${gateway.displayName} required client info payment`, () => {
        test(
            gateway.supportsFullPayment
                ? 'completes payment after required client info'
                : 'unblocks checkout after required client info',
            async ({ api, page, notificationGuard }) => {
                const availability = await gateway.checkAvailability(api.context);
                gateway.skipUnlessAvailable(availability);

                test.setTimeout(gateway.supportsFullPayment ? 300_000 : 120_000);

                const context = await prepareIncompleteClientPaymentContext(
                    api,
                    page,
                    availability.companyGateway!,
                );

                try {
                    await navigateToGatewayCheckoutWithoutRequiredClientInfo(
                        page,
                        context.companyGateway,
                        gateway.gatewayTypeId,
                        context.invoice,
                    );
                    await completeRequiredClientInfoAndUnblockCheckout(page);
                    await gateway.assertCheckoutReady(page);

                    if (gateway.supportsFullPayment) {
                        await notificationGuard.suppressPaymentEmails();
                        await gateway.completePayment(page);
                        await gateway.assertPaymentSucceeded(page);
                    }
                } finally {
                    await context.restoreGatewayRequirements();
                }
            },
        );
    });
}
