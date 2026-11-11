import { type Page } from '@playwright/test';
import { type ApiContext, type CompanyGatewayEntity } from '../api-helpers';
import { type ApiFixture } from '../fixtures';
import { type PortalClient } from '../client-portal-helpers';
import { type PortalEntity } from '../portal-entity-helpers';

export const GatewayType = {
    CREDIT_CARD: 1,
    ACH: 2,
    PAYPAL: 3,
    SEPA: 9,
    DIRECT_DEBIT: 18,
    INSTANT_BANK_PAY: 21,
} as const;

export type GatewayTypeId = (typeof GatewayType)[keyof typeof GatewayType];

export const PaymentType = {
    ACH: 4,
    SEPA: 29,
    DIRECT_DEBIT: 42,
    BECS: 43,
    ACSS: 44,
    INSTANT_BANK_PAY: 45,
    BACS: 49,
} as const;

export interface GatewayAvailability {
    envConfigured: boolean;
    companyGatewayConfigured: boolean;
    companyGateway?: CompanyGatewayEntity;
    skipReason?: string;
}

export interface PaymentGatewayContext {
    client: PortalClient;
    invoice: PortalEntity;
    companyGateway: CompanyGatewayEntity;
}

export interface PaymentGatewayRunContext {
    api: ApiFixture;
    page: Page;
}
