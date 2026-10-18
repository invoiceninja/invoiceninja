import { expect, type Page } from '@playwright/test';
import { getEntity, type ApiContext } from '../../api-helpers';
import { type PortalEntity } from '../../portal-entity-helpers';
import { BasePaymentGateway } from '../../gateways/base-payment-gateway';
import {
    assertPayPalMethodCheckoutReady,
    completePayPalSandboxPayment,
    payPalSandboxBuyerCredentials,
    waitForPayPalMerchantPaymentComplete,
} from './flow-helpers';
import {
    ensurePayPalRestGatewayAvailability,
    parsePayPalRestKeys,
    PAYPAL_REST_GATEWAY_KEY,
    resolvePayPalRestCompanyGateway,
    setupExclusivePayPalRestGatewayEnvironment,
    setupExclusivePayPalRestLegacyCardGatewayEnvironment,
} from './isolation';
import {
    enabledPayPalRestPaymentMethods,
    payPalRestPaymentMethodByTypeId,
    type PayPalRestPaymentMethod,
} from './payment-methods';
import {
    navigateToPortalGatewayCheckout,
    preparePortalPaymentContext,
    type PortalPaymentFlow,
} from '../../gateways/payment-flow-helpers';
import {
    GatewayType,
    type GatewayAvailability,
    type PaymentGatewayContext,
} from '../../gateways/types';

/**
 * PayPal Express (`PayPal_Express` / key 38f2…) no longer has a payment driver
 * class in this codebase, so the portal never offers it. Prefer PayPal REST
 * when a company gateway exists; otherwise skip cleanly.
 */
export class PayPalPaymentGateway extends BasePaymentGateway {
    readonly slug = 'paypal';
    readonly displayName = 'PayPal REST';
    readonly gatewayKey = PAYPAL_REST_GATEWAY_KEY;
    readonly envVar = 'PAYPAL_REST_KEYS';
    readonly gatewayTypeId = GatewayType.PAYPAL;
    /**
     * The sandbox buyer login is what makes completion possible, and it is optional
     * configuration - without it the wallet flow cannot be driven.
     */
    readonly supportsFullPayment = payPalSandboxBuyerCredentials(
        parsePayPalRestKeys() ?? ({} as never),
    ) !== null;
    readonly requiresGatewayIsolation = true;

    /** Checkout is the PayPal SDK surface, not the portal's own payment summary. */
    readonly rendersFeeSummary = false;

    isEnvConfigured(): boolean {
        return parsePayPalRestKeys() !== null;
    }

    async findCompanyGateway(api: ApiContext) {
        return resolvePayPalRestCompanyGateway(api, this.gatewayKey);
    }

    async checkAvailability(api: ApiContext): Promise<GatewayAvailability> {
        return ensurePayPalRestGatewayAvailability(api, this.gatewayTypeId);
    }

    async setupExclusiveTestEnvironment(api: ApiContext): Promise<{
        availability: GatewayAvailability;
        skipReason?: string;
    }> {
        const setup = await setupExclusivePayPalRestGatewayEnvironment(api, {
            envConfigured: this.isEnvConfigured(),
            gatewayTypeId: this.gatewayTypeId,
            feeProfile: 'ppcp',
        });

        if (setup.restore) {
            this.setGatewayIsolationRestore(setup.restore);
        }

        return {
            availability: setup.availability,
            skipReason: setup.skipReason,
        };
    }

    async setupLegacyCardExclusiveTestEnvironment(
        api: ApiContext,
    ): Promise<{
        availability: GatewayAvailability;
        skipReason?: string;
    }> {
        const setup = await setupExclusivePayPalRestLegacyCardGatewayEnvironment(
            api,
            {
                envConfigured: this.isEnvConfigured(),
            },
        );

        if (setup.restore) {
            this.setGatewayIsolationRestore(setup.restore);
        }

        return {
            availability: setup.availability,
            skipReason: setup.skipReason,
        };
    }

    enabledPaymentMethods(
        availability: GatewayAvailability,
    ): PayPalRestPaymentMethod[] {
        const gateway = availability.companyGateway;

        if (!gateway) {
            return [];
        }

        return enabledPayPalRestPaymentMethods(gateway);
    }

    async preparePaymentContext(
        api: import('../../fixtures').ApiFixture,
        page: Page,
        availability: GatewayAvailability,
        paymentFlow: PortalPaymentFlow = 'default',
    ): Promise<PaymentGatewayContext> {
        const companyGateway = availability.companyGateway;

        if (!companyGateway) {
            throw new Error(
                `${this.displayName} checkout requires a configured company gateway`,
            );
        }

        return preparePortalPaymentContext(
            api,
            page,
            companyGateway,
            paymentFlow,
        );
    }

    async navigateToMethodCheckout(
        page: Page,
        context: PaymentGatewayContext,
        method: PayPalRestPaymentMethod,
        paymentFlow: PortalPaymentFlow = 'default',
    ): Promise<void> {
        await navigateToPortalGatewayCheckout(
            page,
            context.companyGateway,
            method.gatewayTypeId,
            paymentFlow,
            context.invoice,
            method.label,
        );
    }

    async assertCheckoutReady(page: Page): Promise<void> {
        const method = payPalRestPaymentMethodByTypeId(this.gatewayTypeId);

        if (!method) {
            await expect(page.locator('#paypal-button-container')).toBeVisible({
                timeout: 30_000,
            });

            return;
        }

        await assertPayPalMethodCheckoutReady(page, method);
    }

    async assertMethodCheckoutReady(
        page: Page,
        method: PayPalRestPaymentMethod,
    ): Promise<void> {
        await assertPayPalMethodCheckoutReady(page, method);
    }

    hasSandboxBuyerCredentials(): boolean {
        const keys = parsePayPalRestKeys();

        if (!keys) {
            return false;
        }

        return payPalSandboxBuyerCredentials(keys) !== null;
    }

    methodSupportsSandboxPayment(method: PayPalRestPaymentMethod): boolean {
        if (method.checkoutKind === 'advanced-cards') {
            return true;
        }

        if (
            method.checkoutKind === 'buttons' &&
            method.fundingSource === 'card'
        ) {
            return true;
        }

        if (!method.fundingSource) {
            return false;
        }

        return this.hasSandboxBuyerCredentials();
    }

    async assertPaymentSucceeded(page: Page): Promise<void> {
        await waitForPayPalMerchantPaymentComplete(page);
    }

    async assertInvoicePaid(
        api: ApiContext,
        invoice: PortalEntity,
    ): Promise<void> {
        await expect
            .poll(async () => {
                const refreshed = await getEntity<PortalEntity>(
                    api,
                    'invoices',
                    invoice.id,
                );

                return {
                    balance: Number(refreshed.balance ?? 0),
                    statusId: Number(refreshed.status_id),
                };
            }, { timeout: 60_000 })
            .toEqual({ balance: 0, statusId: 4 });
    }

    async completePayment(page: Page): Promise<void> {
        const method = payPalRestPaymentMethodByTypeId(this.gatewayTypeId);

        if (!method) {
            throw new Error('Unknown PayPal REST payment method');
        }

        await this.completeMethodPayment(page, method);
    }

    async completeMethodPayment(
        page: Page,
        method: PayPalRestPaymentMethod,
    ): Promise<void> {
        const keys = parsePayPalRestKeys();

        if (
            method.checkoutKind === 'advanced-cards' ||
            (method.checkoutKind === 'buttons' &&
                method.fundingSource === 'card')
        ) {
            await completePayPalSandboxPayment(
                page,
                method,
                { email: '', password: '' },
            );

            return;
        }

        const buyer = keys ? payPalSandboxBuyerCredentials(keys) : null;

        if (!buyer) {
            throw new Error(
                'PayPal sandbox buyer credentials missing from PAYPAL_REST_KEYS',
            );
        }

        await completePayPalSandboxPayment(page, method, buyer);
    }

}
