import { expect, type Page } from '@playwright/test';
import { listCompanyGateways, type ApiContext } from '../api-helpers';
import { BasePaymentGateway } from './base-payment-gateway';
import { GatewayType, type GatewayAvailability } from './types';

/**
 * PayPal Express (`PayPal_Express` / key 38f2…) no longer has a payment driver
 * class in this codebase, so the portal never offers it. Prefer PayPal REST
 * when a company gateway exists; otherwise skip cleanly.
 */
export class PayPalPaymentGateway extends BasePaymentGateway {
    readonly slug = 'paypal';
    readonly displayName = 'PayPal REST';
    readonly gatewayKey = '80af24a6a691230bbec33e930ab40665';
    readonly envVar = 'PAYPAL_KEYS';
    readonly gatewayTypeId = GatewayType.PAYPAL;
    readonly supportsFullPayment = false;

    private readonly legacyExpressKey = '38f2c48af60c7dd69e04248cbb24c36e';

    async checkAvailability(api: ApiContext): Promise<GatewayAvailability> {
        if (!this.isEnvConfigured()) {
            return {
                envConfigured: false,
                companyGatewayConfigured: false,
                skipReason: `${this.displayName}: set ${this.envVar} to run this test`,
            };
        }

        const companyGateway = await this.findCompanyGateway(api);

        if (companyGateway) {
            return {
                envConfigured: true,
                companyGatewayConfigured: true,
                companyGateway,
            };
        }

        const gateways = await listCompanyGateways(api);
        const hasLegacyExpress = gateways.some(
            (gateway) => gateway.gateway_key === this.legacyExpressKey,
        );

        return {
            envConfigured: true,
            companyGatewayConfigured: false,
            skipReason: hasLegacyExpress
                ? 'PayPal Express driver was removed; seed a PayPal REST company gateway to cover PayPal'
                : `${this.displayName}: no enabled company gateway for key ${this.gatewayKey}`,
        };
    }

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(
            page
                .locator('#paypal-payment')
                .or(page.locator('#paypal-button-container'))
                .first(),
        ).toBeVisible({ timeout: 30_000 });
    }
}
