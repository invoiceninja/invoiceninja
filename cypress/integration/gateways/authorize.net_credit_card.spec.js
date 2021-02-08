/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

describe('Authorize.net: Credit card test', () => {
    before(() => {
        cy.artisan('migrate:fresh --seed');
        cy.artisan('ninja:create-single-account authorizenet');
    });

    beforeEach(() => {
        cy.viewport('macbook-13');
        cy.wait(5000);
        cy.clientLogin();
    });

    afterEach(() => {
        cy.visit('/client/logout').visit('/client/login');
    });

    it('should pay with new card', function () {
        cy.visit('/client/invoices').then((contentWindow) => {
            cy.get('[data-cy=pay-now]').first().click()
                .get('[data-cy=pay-now-dropdown]').click()
                .get('[data-cy=pay-with-0]').click();

            cy.get('#card_number').type('4007000000027')
                .get('#cardholder_name').type('Invoice Ninja Rocks')
                .get('[class=expiry]').type('12/28')
                .get('[name=cvc]').type('100');

            cy.get('#pay-now').click();
        });

        cy.location('pathname', {timeout: 60000}).should('include', '/client/payments/VolejRejNm');
    });

    it('should pay with new card & save credit card for future use', function () {
        cy.visit('/client/invoices').then((contentWindow) => {
            cy.get('[data-cy=pay-now]').first().click()
                .get('[data-cy=pay-now-dropdown]').click()
                .get('[data-cy=pay-with-0]').click();

            cy.get('#card_number').type('4007000000027')
                .get('#cardholder_name').type('Invoice Ninja Rocks')
                .get('[class=expiry]').type('12/28')
                .get('[name=cvc]').type('100');

            cy.get('[name=token-billing-checkbox]').first().check();

            cy.get('#pay-now').click();
        });

        cy.location('pathname', {timeout: 60000}).should('include', '/client/payments/Wpmbk5ezJn');
    });

    it('should pay with saved card (token)', function () {
        cy.visit('/client/invoices')
            .get('[data-cy=pay-now]').first().click()
            .get('[data-cy=pay-now-dropdown]').click()
            .get('[data-cy=pay-with-0]').click();

        cy.get('[name=payment-type]').first().check();

        cy.get('#pay-now').click();

        cy.wait(2000);

        cy.location('pathname', {timeout: 60000}).should('include', '/client/payments/Opnel5aKBz');
    });


    it('should be able to remove payment method', function () {
        cy.visit('/client/payment_methods')
            .get('[data-cy=view-payment-method]').click();

        cy.get('#open-delete-popup').click();

        cy.get('[data-cy=confirm-payment-removal]').click();

        cy.url().should('contain', '/client/payment_methods');

        cy.get('body').contains('Payment method has been successfully removed.');
    });

    it('should be able to add credit card (standalone)', function () {
        cy.visit('/client/payment_methods')
            .get('[data-cy=add-payment-method]').click()
            .get('[data-cy=add-credit-card-link]').click();

        cy.get('#card_number').type('4007000000027')
            .get('#cardholder_name').type('Invoice Ninja Rocks')
            .get('[class=expiry]').type('12/28')
            .get('[name=cvc]').type('100');

        cy.get('#card_button').click();

        cy.location('pathname', {timeout: 60000}).should('include', '/client/payment_methods');
    });
});
