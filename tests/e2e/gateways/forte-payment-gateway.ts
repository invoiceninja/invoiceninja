import { expect, type Page } from '@playwright/test';
import {
    getCompanyGateway,
    listCompanyGateways,
    parseCompanyGatewayConfig,
    type ApiContext,
} from '../api-helpers';
import { BasePaymentGateway } from './base-payment-gateway';
import { type GatewayAvailability, GatewayType } from './types';

export class FortePaymentGateway extends BasePaymentGateway {
    readonly slug = 'forte';
    readonly displayName = 'Forte';
    readonly gatewayKey = 'kivcvjexxvdiyqtj3mju5d6yhpeht2xs';
    readonly envVar = 'FORTE_KEYS';
    readonly gatewayTypeId = GatewayType.CREDIT_CARD;
    readonly supportsFullPayment = false;

    /**
     * Forte credentials already live on the company gateway. Unlike gateways that are
     * scaffolded from environment variables, this suite only runs when an active,
     * configured Forte gateway is already available to the portal.
     */
    async checkAvailability(api: ApiContext): Promise<GatewayAvailability> {
        const companyGateway = (await listCompanyGateways(api)).find(
            (gateway) =>
                gateway.gateway_key === this.gatewayKey &&
                !gateway.archived_at &&
                !gateway.is_deleted,
        );

        if (!companyGateway) {
            return {
                envConfigured: true,
                companyGatewayConfigured: false,
                skipReason: 'Forte: no active company gateway is configured',
            };
        }

        const creditCard =
            companyGateway.fees_and_limits?.[String(this.gatewayTypeId)];

        if (!creditCard?.is_enabled) {
            return {
                envConfigured: true,
                companyGatewayConfigured: false,
                skipReason: 'Forte: credit card payments are not enabled',
            };
        }

        const configuredGateway = await getCompanyGateway(
            api,
            companyGateway.id,
        );
        const config = parseCompanyGatewayConfig(configuredGateway);
        const requiredFields = [
            'apiLoginId',
            'apiAccessId',
            'secureKey',
            'organizationId',
            'locationId',
        ];
        const missingFields = requiredFields.filter(
            (field) => String(config[field] ?? '').trim().length === 0,
        );

        if (missingFields.length > 0) {
            return {
                envConfigured: true,
                companyGatewayConfigured: false,
                skipReason: `Forte: company gateway is missing ${missingFields.join(', ')}`,
            };
        }

        return {
            envConfigured: true,
            companyGatewayConfigured: true,
            companyGateway: configuredGateway,
        };
    }

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(page.locator('#forte--credit-card-container')).toBeVisible({
            timeout: 30_000,
        });
        await expect(
            page.locator('meta[name="forte-api-login-id"]'),
        ).toHaveAttribute('content', /.+/);
        await expect(page.locator('#cardholder_name')).toBeVisible();
        await expect(page.locator('#number')).toBeVisible();
        await expect(page.locator('#date')).toBeVisible();
        await expect(page.locator('#cvv')).toBeVisible();
        await expect(page.locator('#pay-now')).toBeVisible();
        await expect
            .poll(
                () =>
                    page.evaluate(() => {
                        const forteWindow = window as typeof window & {
                            forte?: { createToken?: unknown };
                        };

                        return (
                            typeof forteWindow.forte?.createToken === 'function'
                        );
                    }),
                { timeout: 30_000 },
            )
            .toBe(true);
    }
}
