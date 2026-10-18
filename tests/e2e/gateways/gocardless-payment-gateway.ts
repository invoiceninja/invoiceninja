import { expect, type Page } from '@playwright/test';
import {
    getCompanyGateway,
    type ApiContext,
    type CompanyGatewayEntity,
} from '../api-helpers';
import { dismissCookieConsent } from '../client-portal-helpers';
import {
    BasePaymentGateway,
    type GatewayExclusiveSetupOptions,
} from './base-payment-gateway';
import { GatewayType } from './types';

export class GoCardlessPaymentGateway extends BasePaymentGateway {
    readonly slug = 'gocardless';
    readonly displayName = 'GoCardless';
    readonly gatewayKey = 'b9886f9257f0c6ee7c302f1c74475f6c';
    readonly envVar = 'GOCARDLESS_KEYS';
    readonly gatewayTypeId = GatewayType.ACH;
    readonly supportsFullPayment = this.isSandboxConfigured();

    /** A fresh client can now create a mandate and payment in one portal flow. */
    readonly requiresStoredMandate = false;

    async assertCheckoutReady(page: Page): Promise<void> {
        await expect(
            page.locator('#gocardless-direct-debit-payment')
        ).toBeVisible();
        await expect(page.locator('#pay-now')).toBeVisible();
    }

    isSandboxConfigured(): boolean {
        try {
            const keys = JSON.parse(this.getEnvValue()) as {
                testMode?: boolean;
            };

            return keys.testMode === true;
        } catch {
            return false;
        }
    }

    protected envReadyForExclusiveSetup(): boolean {
        return this.isSandboxConfigured();
    }

    protected envSkipReason(): string {
        return 'GoCardless payment completion requires GOCARDLESS_KEYS JSON with testMode=true';
    }

    protected async syncGatewayCredentials(
        api: ApiContext,
        gateway: CompanyGatewayEntity,
        options: GatewayExclusiveSetupOptions = {},
    ): Promise<CompanyGatewayEntity> {
        const config = {
            ...(JSON.parse(this.getEnvValue()) as Record<string, unknown>),
            ...(options.configChanges ?? {}),
        };
        const response = await api.request.put(
            `/api/v1/company_gateways/${gateway.id}`,
            {
                data: {
                    gateway_key: gateway.gateway_key,
                    config: JSON.stringify(config),
                    fees_and_limits: gateway.fees_and_limits ?? {},
                },
            },
        );

        if (!response.ok()) {
            throw new Error(
                `Unable to apply the GoCardless sandbox configuration (${response.status()}): ${(await response.text()).slice(0, 300)}`,
            );
        }

        return getCompanyGateway(api, gateway.id);
    }

    async completePayment(
        page: Page,
        expected_country_code?: string,
        email = 'playwright@example.test'
    ): Promise<void> {
        await dismissCookieConsent(page);
        await page.locator('#pay-now').click();
        await this.completeHostedDirectDebit(
            page,
            expected_country_code,
            email
        );
    }

    async completeHostedDirectDebit(
        page: Page,
        expected_country_code?: string,
        email = 'playwright@example.test'
    ): Promise<void> {
        await page.waitForURL(/pay-sandbox\.gocardless\.com/, {
            timeout: 30_000,
        });
        await page.waitForLoadState('domcontentloaded');
        await expect(
            page.getByRole('form', { name: 'Customer Details Form' })
        ).toBeVisible({ timeout: 30_000 });
        const country_code = await page
            .locator('select[name="country_code"]')
            .inputValue();

        if (expected_country_code) {
            expect(country_code).toBe(expected_country_code);
        }
        const address = sandboxAddresses[country_code];

        if (!address) {
            throw new Error(
                `No GoCardless sandbox address configured for ${country_code}`
            );
        }

        await this.fillVisibleInput(page, 'given_name', 'Playwright');
        await this.fillVisibleInput(page, 'family_name', 'Portal');
        await this.fillVisibleInput(page, 'email', email);
        const manual_address = page.getByRole('button', {
            name: /enter your address manually/i,
        });

        if (await manual_address.isVisible().catch(() => false)) {
            await manual_address.click();
        }

        await this.fillVisibleInput(page, 'address_line1', '1 Test Street');
        await this.fillVisibleInput(page, 'city', address.city);
        await this.fillVisibleInput(page, 'postal_code', address.postal_code);
        await this.fillVisibleInput(page, 'region', address.region);
        await this.fillIdentityNumber(page, country_code);
        await page
            .getByRole('button', { name: 'Continue', exact: true })
            .click();
        await page.waitForURL(/\/collect-bank-account/, { timeout: 30_000 });
        await this.fillVisibleInput(
            page,
            'account_holder_name',
            'Playwright Portal'
        );
        await this.fillBankDetails(page, country_code);
        await page
            .getByRole('button', { name: 'Continue', exact: true })
            .click();
        await page.waitForURL(
            (url) => !url.pathname.endsWith('/collect-bank-account'),
            { timeout: 30_000 }
        );
        await expect(
            page.getByRole('heading', { name: /Confirm your details/i })
        ).toBeVisible({ timeout: 30_000 });
        await page
            .getByRole('button', {
                name: /Confirm this|Set up this|Authori[sz]e this/i,
            })
            .click();
        await this.returnToInvoiceNinja(page);
    }

    async assertPaymentSucceeded(page: Page): Promise<void> {
        await page.waitForURL((url) => isCompletedPaymentPage(url.pathname), {
            timeout: 60_000,
        });
    }

    async completeHostedInstantBankPayment(
        page: Page,
        expected_country_code: string
    ): Promise<void> {
        await page.waitForURL(/pay-sandbox\.gocardless\.com/, {
            timeout: 30_000,
        });
        await expect(page.locator('main')).toContainText(/payment|bank/i, {
            timeout: 30_000,
        });
        const email = page.getByRole('textbox', { name: 'Email address' });
        await email.fill('playwright@example.test');
        const first_name = page.getByRole('textbox', { name: 'First name' });

        if (await first_name.isVisible().catch(() => false)) {
            const country = page.locator('select[name="country_code"]');

            if (await country.isVisible().catch(() => false)) {
                expect(await country.inputValue()).toBe(expected_country_code);
            }

            await first_name.fill('Playwright');
            await page
                .getByRole('textbox', { name: 'Last name' })
                .fill('Portal');
            const manual_address = page.getByRole('button', {
                name: /enter your address manually/i,
            });

            if (await manual_address.isVisible().catch(() => false)) {
                await manual_address.click();
            }
            const address = sandboxAddresses[expected_country_code];
            await this.fillVisibleInput(page, 'address_line1', '1 Test Street');
            await this.fillVisibleInput(page, 'city', address.city);
            await this.fillVisibleInput(
                page,
                'postal_code',
                address.postal_code
            );
            await this.fillVisibleInput(page, 'region', address.region);
            await page
                .getByRole('button', { name: 'Continue', exact: true })
                .click();
        }

        const choose_bank = page.getByRole('button', { name: 'Choose bank' });
        const success_bank = page
            .getByRole('button', {
                name: /Success Bank - Authorises the payment request|Read Refund Account Bank/i,
            })
            .first();
        const manual_login = page.getByRole('button', {
            name: /Continue to bank website/i,
        });

        await expect
            .poll(
                async () =>
                    (await success_bank.isVisible().catch(() => false)) ||
                    (await choose_bank.isVisible().catch(() => false)) ||
                    (await manual_login.isVisible().catch(() => false)),
                { timeout: 30_000 }
            )
            .toBe(true);

        if (await choose_bank.isVisible().catch(() => false)) {
            await choose_bank.click();
        }

        if (!(await manual_login.isVisible().catch(() => false))) {
            await expect(success_bank).toBeVisible({ timeout: 30_000 });
            await success_bank.click();
        }

        const bank_details = page.getByRole('heading', {
            name: 'Your bank details',
        });

        await expect
            .poll(
                async () =>
                    (await bank_details.isVisible().catch(() => false)) ||
                    !page.url().includes('gocardless.com') ||
                    /\/post-auth|\/success/.test(
                        new URL(page.url()).pathname
                    ) ||
                    (await manual_login.isVisible().catch(() => false)),
                { timeout: 30_000 }
            )
            .toBe(true);

        if (await bank_details.isVisible().catch(() => false)) {
            await this.fillVisibleInput(
                page,
                'account_holder_name',
                'Playwright Portal'
            );
            await this.fillBankDetails(page, expected_country_code);
            await page
                .getByRole('button', { name: 'Continue', exact: true })
                .click();
            await page.waitForURL(
                (url) => !url.pathname.endsWith('/bank-pay'),
                { timeout: 30_000 }
            );
            const confirm = page.getByRole('button', {
                name: 'Confirm and continue',
            });
            await expect(confirm).toBeVisible({ timeout: 30_000 });
            const terms = page.getByRole('checkbox', {
                name: /Payer Terms of Use/i,
            });
            await page.getByText(/^I confirm I have read/).click();
            await expect(terms).toBeChecked();
            await confirm.click();
            const desktop = page.getByRole('button', {
                name: /continue on desktop/i,
            });
            await expect(desktop).toBeVisible({ timeout: 30_000 });
            await desktop.click();
            await page.waitForURL(
                (url) => !url.pathname.endsWith('/bank-connect'),
                { timeout: 30_000 }
            );
        }

        if (await manual_login.isVisible().catch(() => false)) {
            await manual_login.click();
        }

        await this.returnToInvoiceNinja(page);
    }

    private async fillBankDetails(
        page: Page,
        country_code: string
    ): Promise<void> {
        const details = sandboxBankDetails[country_code];

        if (!details) {
            throw new Error(
                `No GoCardless sandbox details configured for ${country_code}`
            );
        }

        for (let attempt = 0; attempt < 12; attempt++) {
            const missing_detail = await this.firstMissingDetail(page, details);

            if (!missing_detail) {
                await page.waitForTimeout(500);

                if (!(await this.firstMissingDetail(page, details))) {
                    return;
                }

                continue;
            }

            await this.fillVisibleInput(
                page,
                missing_detail[0],
                missing_detail[1]
            );
        }

        throw new Error(
            `GoCardless did not retain the ${country_code} sandbox bank details`
        );
    }

    private async fillIdentityNumber(
        page: Page,
        country_code: string
    ): Promise<void> {
        const identity_number = sandboxIdentityNumbers[country_code];

        if (!identity_number) {
            return;
        }

        const input_name =
            country_code === 'DK'
                ? 'danish_identity_number'
                : 'swedish_identity_number';

        await this.fillVisibleInput(page, input_name, identity_number);
    }

    private async fillVisibleInput(
        page: Page,
        name: string,
        value: string
    ): Promise<void> {
        const field = page.locator(`[name="${name}"]`);

        if (!(await field.isVisible().catch(() => false))) {
            return;
        }

        if (await field.evaluate((element) => element.tagName === 'SELECT')) {
            await field.selectOption(value);
        } else {
            await field.fill(value);
        }

        await field.blur();
        await expect
            .poll(async () => normaliseFieldValue(await field.inputValue()))
            .toBe(normaliseFieldValue(value));
        await page.waitForTimeout(250);
    }

    private async firstMissingDetail(
        page: Page,
        details: Record<string, string>
    ): Promise<[string, string] | null> {
        for (const [name, value] of Object.entries(details)) {
            const field = page.locator(`[name="${name}"]`);

            if (
                (await field.isVisible().catch(() => false)) &&
                normaliseFieldValue(await field.inputValue()) !==
                    normaliseFieldValue(value)
            ) {
                return [name, value];
            }
        }

        return null;
    }

    private async returnToInvoiceNinja(page: Page): Promise<void> {
        const return_control = page
            .getByRole('button', { name: /return|continue/i })
            .or(page.getByRole('link', { name: /return|continue/i }));
        await expect
            .poll(
                async () =>
                    !page.url().includes('gocardless.com') ||
                    (await return_control.isVisible().catch(() => false)),
                { timeout: 60_000 }
            )
            .toBe(true);

        if (await return_control.isVisible().catch(() => false)) {
            await return_control.click();
        }
    }
}

function normaliseFieldValue(value: string): string {
    return value.replace(/[\s-]/g, '').toUpperCase();
}

function isCompletedPaymentPage(pathname: string): boolean {
    return /^\/client\/payments\/(?!process(?:\/|$))[^/]+\/?$/.test(pathname);
}

const sandboxBankDetails: Record<string, Record<string, string>> = {
    AU: { branch_code: '082082', account_number: '012345678' },
    CA: {
        bank_code: '0003',
        branch_code: '00006',
        account_number: '1234567',
        pad_category: 'personal',
    },
    DK: {
        bank_code: '345',
        account_number: '3179681',
    },
    GB: { branch_code: '200000', account_number: '55779911' },
    NZ: {
        bank_code: '12',
        branch_code: '3113',
        account_number: '0003869',
        account_number_suffix: '00',
    },
    DE: { iban: 'DE89370400440532013000' },
    FR: { iban: 'FR1420041010050500013M02606' },
    IE: { iban: 'IE64IRCE92050112345678' },
    SE: {
        branch_code: '5491',
        account_number: '0000003',
    },
    US: {
        account_type: 'checking',
        bank_code: '026073150',
        account_number: '2715500356',
    },
};

const sandboxIdentityNumbers: Record<string, string> = {
    DK: '0101701234',
    SE: '198112289874',
};

const sandboxAddresses: Record<
    string,
    { city: string; postal_code: string; region: string }
> = {
    AU: { city: 'Sydney', postal_code: '2000', region: 'NSW' },
    CA: { city: 'Toronto', postal_code: 'M5V 2T6', region: 'ON' },
    DE: { city: 'Berlin', postal_code: '10115', region: '' },
    DK: { city: 'Copenhagen', postal_code: '2100', region: '' },
    FR: { city: 'Paris', postal_code: '75001', region: '' },
    GB: { city: 'London', postal_code: 'SW1A 1AA', region: '' },
    IE: { city: 'Dublin', postal_code: 'D02 X285', region: '' },
    NZ: { city: 'Auckland', postal_code: '1010', region: '' },
    SE: { city: 'Stockholm', postal_code: '111 22', region: '' },
    US: { city: 'New York', postal_code: '10001', region: 'NY' },
};
