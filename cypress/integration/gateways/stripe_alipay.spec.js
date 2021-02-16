import axios from "axios";

describe('Stripe: Alipay testing', () => {
    before(() => {
        cy.artisan('migrate:fresh --seed');
        cy.artisan('ninja:create-single-account checkout');
    });

    beforeEach(() => {
        let headers = {
            'X-API-Token': 'company-token-test',
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json; charset=utf-8',
        };

        let gatewaysBody = {
            "gateway_key": "d14dd26a37cecc30fdd65700bfb55b23",
            "accepted_credit_cards": 0,
            "require_shipping_address": true,
            "require_billing_address": true,
            "require_client_name": false,
            "require_client_phone": false,
            "require_contact_name": false,
            "require_contact_email": false,
            "require_cvv": true,
            "update_details": true,
            "fees_and_limits": {
                "1": {
                    "min_limit": -1,
                    "max_limit": -1,
                    "fee_amount": 0,
                    "fee_percent": 0,
                    "fee_cap": 0,
                    "fee_tax_rate1": 0,
                    "fee_tax_name1": "",
                    "fee_tax_rate2": 0,
                    "fee_tax_name2": "",
                    "fee_tax_rate3": 0,
                    "fee_tax_name3": "",
                    "adjust_fee_percent": false,
                    "is_enabled": true
                },
                "2": {
                    "min_limit": -1,
                    "max_limit": -1,
                    "fee_amount": 0,
                    "fee_percent": 0,
                    "fee_cap": 0,
                    "fee_tax_rate1": 0,
                    "fee_tax_name1": "",
                    "fee_tax_rate2": 0,
                    "fee_tax_name2": "",
                    "fee_tax_rate3": 0,
                    "fee_tax_name3": "",
                    "adjust_fee_percent": false,
                    "is_enabled": true
                },
                "6": {
                    "min_limit": -1,
                    "max_limit": -1,
                    "fee_amount": 0,
                    "fee_percent": 0,
                    "fee_cap": 0,
                    "fee_tax_rate1": 0,
                    "fee_tax_name1": "",
                    "fee_tax_rate2": 0,
                    "fee_tax_name2": "",
                    "fee_tax_rate3": 0,
                    "fee_tax_name3": "",
                    "adjust_fee_percent": false,
                    "is_enabled": true
                }
            },
            "system_logs": [],
            "custom_value1": "",
            "custom_value2": "",
            "custom_value3": "",
            "custom_value4": "",
            "config": "{\"apiKey\":\"sk_test_Yorqvz45sZWSSUmvCfoKF8e6\",\"publishableKey\":\"pk_test_P1riKDKD0pdNTkHwBWEZ8DR0\",\"enable_ach\":\"0\",\"enable_sofort\":\"0\",\"enable_apple_pay\":\"0\",\"enable_alipay\":\"1\"}",
            "token_billing": "off",
            "test_mode": true,
            "label": "Stripe",
            "created_at": 1612791181,
            "updated_at": 1612792176,
            "archived_at": 0,
            "id": "VolejRejNm",
            "loadedAt": 1612792176934,
            "require_postal_code": false,
            "is_deleted": false
        };
        let clientBody = {
            "group_settings_id": "",
            "name": "Batz LLC",
            "display_name": "Batz LLC",
            "balance": 8323.7,
            "credit_balance": 0,
            "paid_to_date": 0,
            "client_hash": "DxrMypcMdnYJvfebfeoXUi2Iyear6LkNq7Twi0H9",
            "address1": "45804",
            "address2": "47988 Rex Mall",
            "city": "New Macberg",
            "state": "Florida",
            "postal_code": "43089-5809",
            "country_id": "840",
            "phone": "",
            "private_notes": "Neque libero eos adipisci quae. Non voluptas quaerat ea nisi repudiandae in. Voluptatem error aut est distinctio perspiciatis quis.",
            "public_notes": "",
            "website": "https://www.wintheiser.com/non-velit-nisi-culpa-sit-optio-omnis-ipsum-pariatur",
            "industry_id": "",
            "size_id": "",
            "vat_number": "157764830",
            "id_number": "",
            "number": "0001",
            "shipping_address1": "5181",
            "shipping_address2": "66797 Jedediah Isle Suite 479",
            "shipping_city": "Lake Rosariomouth",
            "shipping_state": "Nevada",
            "shipping_postal_code": "31693",
            "shipping_country_id": "4",
            "settings": {
                "currency_id": "1"
            },
            "last_login": 0,
            "custom_value1": "",
            "custom_value2": "",
            "custom_value3": "",
            "custom_value4": "",
            "contacts": [
                {
                    "first_name": "Rita",
                    "last_name": "Pouros",
                    "email": "user@example.com",
                    "password": "**********",
                    "phone": "+1-331-663-8498",
                    "contact_key": "hNQkBU6RM6tG2pwu4J7dCfuq2ZdH6Q8anEvKnyoL",
                    "is_primary": true,
                    "send_email": true,
                    "custom_value1": "",
                    "custom_value2": "",
                    "custom_value3": "",
                    "custom_value4": "",
                    "last_login": 0,
                    "link": "https://localhost:8080/client/key_login/hNQkBU6RM6tG2pwu4J7dCfuq2ZdH6Q8anEvKnyoL",
                    "created_at": 1612792539,
                    "updated_at": 1612792539,
                    "archived_at": 0,
                    "id": "VolejRejNm"
                },
                {
                    "first_name": "Danika",
                    "last_name": "Hauck",
                    "email": "bbrakus@example.net",
                    "password": "**********",
                    "phone": "662-968-5275 x48146",
                    "contact_key": "4hWqvVUv2bwYIOb25rWmQhbhadnl5yneTzglGZ32",
                    "is_primary": false,
                    "send_email": true,
                    "custom_value1": "",
                    "custom_value2": "",
                    "custom_value3": "",
                    "custom_value4": "",
                    "last_login": 0,
                    "link": "https://localhost:8080/client/key_login/4hWqvVUv2bwYIOb25rWmQhbhadnl5yneTzglGZ32",
                    "created_at": 1612792539,
                    "updated_at": 1612792539,
                    "archived_at": 0,
                    "id": "Wpmbk5ezJn"
                }
            ],
            "activities": [],
            "ledger": [],
            "gateway_tokens": [],
            "documents": [],
            "system_logs": [],
            "created_at": 1612792539,
            "updated_at": 1612792565,
            "archived_at": 0,
            "id": "VolejRejNm",
            "isChanged": true,
            "is_deleted": false,
            "user_id": "VolejRejNm",
            "assigned_user_id": ""
        };

        axios.put('https://localhost:8080/api/v1/company_gateways/VolejRejNm', gatewaysBody, {headers})
        axios.put('https://localhost:8080/api/v1/clients/VolejRejNm', clientBody, {headers}); // Set country  to US.

        cy.viewport('macbook-13');
        cy.clientLogin();
    });

    afterEach(() => {
        cy.visit('/client/logout').visit('/client/login');
    });

    it('should be able to pay using Alipay', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-2]').click();

        cy.get('#pay-now').click();

        cy.get('.common-ButtonGroup > .common-Button--default').click();

        cy.url().should('contain', '/client/payments/');
    });
});
