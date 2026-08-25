import { expect, type Page } from '@playwright/test';
import { BasePaymentGateway } from './base-payment-gateway';
import { GatewayType } from './types';

export class GoCardlessPaymentGateway extends BasePaymentGateway {
    readonly slug = 'gocardless';
    readonly displayName = 'GoCardless';
    readonly gatewayKey = 'b9886f9257f0c6ee7c302f1c74475f6c';
    readonly envVar = 'GOCARDLESS_KEYS';
    readonly gatewayTypeId = GatewayType.DIRECT_DEBIT;
    readonly supportsFullPayment = false;

    /** Direct debit needs an authorised mandate before the portal offers it. */
    readonly requiresStoredMandate = true;

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(page.locator('#pay-now')).toBeVisible();
        await expect(
            page.locator('input[name="company_gateway_id"]'),
        ).toHaveValue(/.+/);
    }
}
