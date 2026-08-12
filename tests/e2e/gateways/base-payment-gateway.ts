import { test, type Page } from '@playwright/test';
import {
    ensureCompanyGatewayTypeEnabled,
    findCompanyGatewayByKey,
    listCompanyGateways,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { type ApiFixture } from '../fixtures';
import {
    navigateToGatewayCheckout,
    prepareDefaultPaymentContext,
} from './payment-flow-helpers';
import {
    type GatewayAvailability,
    type GatewayTypeId,
    type PaymentGatewayContext,
    type PaymentGatewayRunContext,
} from './types';

export abstract class BasePaymentGateway {
    abstract readonly slug: string;
    abstract readonly displayName: string;
    abstract readonly gatewayKey: string;
    abstract readonly envVar: string;
    abstract readonly gatewayTypeId: GatewayTypeId;
    abstract readonly supportsFullPayment: boolean;

    getEnvValue(): string {
        return process.env[this.envVar]?.trim() ?? '';
    }

    isEnvConfigured(): boolean {
        return this.getEnvValue().length > 0;
    }

    async findCompanyGateway(
        api: ApiContext,
    ): Promise<CompanyGatewayEntity | undefined> {
        const gateways = await listCompanyGateways(api);

        return findCompanyGatewayByKey(
            gateways,
            this.gatewayKey,
            this.gatewayTypeId,
        );
    }

    async checkAvailability(api: ApiContext): Promise<GatewayAvailability> {
        if (!this.isEnvConfigured()) {
            return {
                envConfigured: false,
                companyGatewayConfigured: false,
                skipReason: `${this.displayName}: set ${this.envVar} to run this test`,
            };
        }

        const companyGateway = await this.findCompanyGateway(api);

        if (!companyGateway) {
            return {
                envConfigured: true,
                companyGatewayConfigured: false,
                skipReason: `${this.displayName}: no company gateway for key ${this.gatewayKey}`,
            };
        }

        // Empty fees_and_limits means the portal omits the gateway entirely.
        const enabledGateway = await ensureCompanyGatewayTypeEnabled(
            api,
            companyGateway,
            this.gatewayTypeId,
        );

        return {
            envConfigured: true,
            companyGatewayConfigured: true,
            companyGateway: enabledGateway,
        };
    }

    skipUnlessAvailable(availability: GatewayAvailability): void {
        if (
            !availability.envConfigured ||
            !availability.companyGatewayConfigured
        ) {
            test.skip(true, availability.skipReason);
        }
    }

    async preparePaymentContext(
        api: ApiFixture,
        page: Page,
        availability: GatewayAvailability,
    ): Promise<PaymentGatewayContext> {
        const companyGateway = availability.companyGateway;

        if (!companyGateway) {
            throw new Error(
                `${this.displayName} checkout requires a configured company gateway`,
            );
        }

        return prepareDefaultPaymentContext(api, page, companyGateway);
    }

    async navigateToCheckout(
        page: Page,
        context: PaymentGatewayContext,
    ): Promise<void> {
        await navigateToGatewayCheckout(
            page,
            context.companyGateway,
            this.gatewayTypeId,
        );
    }

    abstract assertCheckoutReady(page: Page): Promise<void>;

    async completePayment(_page: Page): Promise<void> {
        throw new Error(
            `${this.displayName} does not implement full payment completion`,
        );
    }

    async assertPaymentSucceeded(page: Page): Promise<void> {
        await page.waitForURL(/\/client\/payments\//, { timeout: 60_000 });
    }

    async runEndToEnd({
        api,
        page,
        availability,
    }: PaymentGatewayRunContext & {
        availability: GatewayAvailability;
    }): Promise<void> {
        const context = await this.preparePaymentContext(
            api,
            page,
            availability,
        );
        await this.navigateToCheckout(page, context);
        await this.assertCheckoutReady(page);

        if (this.supportsFullPayment) {
            await this.completePayment(page);
            await this.assertPaymentSucceeded(page);
        }
    }
}
