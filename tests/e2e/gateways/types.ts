import { type Page } from '@playwright/test';
import {
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { type ApiFixture } from '../fixtures';
import { type PortalClient } from '../client-portal-helpers';
import { type PortalEntity } from '../portal-entity-helpers';

export const GatewayType = {
    CREDIT_CARD: 1,
    ACH: 2,
    PAYPAL: 3,
    SEPA: 4,
    DIRECT_DEBIT: 5,
} as const;

export type GatewayTypeId = (typeof GatewayType)[keyof typeof GatewayType];

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
