import { test, type Page } from '@playwright/test';
import {
    ensureCompanyGatewayForKey,
    ensureCompanyGatewayTypeEnabled,
    findCompanyGatewayByKey,
    listCompanyGateways,
    syncCompanyGatewayConfigFromEnv,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { type ApiFixture } from '../fixtures';
import {
    isolateCompanyGateway,
    setupExclusiveEnvGatewayEnvironment,
} from './gateway-isolation-helpers';
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

export interface GatewayExclusiveSetupOptions {
    gatewayTypeId?: number;
    skipIsolation?: boolean;
    skipAuthTest?: boolean;
    configChanges?: Record<string, unknown>;
}

export abstract class BasePaymentGateway {
    abstract readonly slug: string;
    abstract readonly displayName: string;
    abstract readonly gatewayKey: string;
    abstract readonly envVar: string;
    abstract readonly gatewayTypeId: GatewayTypeId;
    abstract readonly supportsFullPayment: boolean;

    /** End-to-end specs scaffold, isolate, and auth-test the target gateway first. */
    readonly requiresGatewayIsolation: boolean = true;

    /**
     * Whether checkout renders the application's own payment summary, where the gateway
     * fee and the fee inclusive total are visible in the page. Wallet gateways render
     * their own SDK surface instead.
     */
    readonly rendersFeeSummary: boolean = true;

    /**
     * Whether the portal only offers this gateway once the client has an authorised
     * mandate. Direct debit cannot be paid from a fresh client, so a checkout test has
     * to set the mandate up first.
     */
    readonly requiresStoredMandate: boolean = false;

    private restoreGatewayIsolation?: () => Promise<void>;

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

    protected envReadyForExclusiveSetup(): boolean {
        return this.isEnvConfigured();
    }

    protected envSkipReason(): string {
        return `${this.displayName}: set ${this.envVar} to run this test`;
    }

    protected async scaffoldCompanyGateway(
        api: ApiContext,
    ): Promise<CompanyGatewayEntity | undefined> {
        return ensureCompanyGatewayForKey(
            api,
            this.gatewayKey,
            this.envVar,
        );
    }

    protected async syncGatewayCredentials(
        api: ApiContext,
        gateway: CompanyGatewayEntity,
        _options: GatewayExclusiveSetupOptions = {},
    ): Promise<CompanyGatewayEntity> {
        return syncCompanyGatewayConfigFromEnv(api, gateway, this.envVar);
    }

    /**
     * API-driven setup run before each isolated-gateway test: resolve the target
     * gateway, verify configuration, archive all others, and verify auth.
     */
    async setupExclusiveTestEnvironment(
        api: ApiContext,
        options: GatewayExclusiveSetupOptions = {},
    ): Promise<{
        availability: GatewayAvailability;
        skipReason?: string;
    }> {
        const gatewayTypeId = options.gatewayTypeId ?? this.gatewayTypeId;

        if (this.envReadyForExclusiveSetup()) {
            const setup = await setupExclusiveEnvGatewayEnvironment(api, {
                displayName: this.displayName,
                gatewayKey: this.gatewayKey,
                gatewayTypeId,
                envConfigured: true,
                envSkipReason: this.envSkipReason(),
                skipIsolation: options.skipIsolation,
                skipAuthTest: options.skipAuthTest,
                scaffoldGateway: (apiContext) =>
                    this.scaffoldCompanyGateway(apiContext),
                syncGateway: (apiContext, gateway) =>
                    this.syncGatewayCredentials(apiContext, gateway, options),
            });

            if (setup.restore) {
                this.setGatewayIsolationRestore(setup.restore);
            }

            return {
                availability: setup.availability,
                skipReason: setup.skipReason,
            };
        }

        const availability = await this.checkAvailability(api);

        if (
            !availability.envConfigured ||
            !availability.companyGatewayConfigured ||
            !availability.companyGateway
        ) {
            return {
                availability,
                skipReason: availability.skipReason,
            };
        }

        if (options.skipIsolation) {
            return { availability };
        }

        const { gateway, restore } = await isolateCompanyGateway(
            api,
            availability.companyGateway,
            gatewayTypeId,
        );

        this.setGatewayIsolationRestore(restore);

        return {
            availability: {
                ...availability,
                companyGateway: gateway,
            },
        };
    }

    async restoreExclusiveGateway(): Promise<void> {
        if (this.restoreGatewayIsolation) {
            await this.restoreGatewayIsolation();
            this.restoreGatewayIsolation = undefined;
        }
    }

    protected setGatewayIsolationRestore(restore: () => Promise<void>): void {
        this.restoreGatewayIsolation = restore;
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
            context.invoice,
        );
    }

    abstract assertCheckoutReady(page: Page): Promise<void>;

    async completePayment(_page: Page): Promise<void> {
        throw new Error(
            `${this.displayName} does not implement full payment completion`,
        );
    }

    async assertPaymentSucceeded(page: Page): Promise<void> {
        await page.waitForURL(/\/client\/payments\/(?!process)/, {
            timeout: 60_000,
        });
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
