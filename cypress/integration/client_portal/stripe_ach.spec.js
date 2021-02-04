describe('Stripe: ACH testing', () => {
    before(() => {
        // cy.artisan('migrate:fresh --seed');
        // cy.artisan('ninja:create-single-account checkout');
    });

    beforeEach(() => {
        cy.viewport('macbook-13');
        cy.clientLogin();
    });

    afterEach(() => {
        cy.visit('/client/logout');
    });

    it('should be able to add bank account & verify it', function () {
        cy.visit('/client/payment_methods');

        cy.get('[data-cy=add-payment-method]').click();
        cy.get('[data-cy=add-bank-account-link]').click();

        cy.get('#account-holder-name').type('Invoice Ninja Rocks');
        cy.get('#country').select('US');
        cy.get('#currency').select('USD');
        cy.get('#routing-number').type('110000000');
        cy.get('#account-number').type('000123456789');
        cy.get('#accept-terms').check();

        cy.get('#save-button').click();

        cy.url().should('contain', 'method=2');

        cy.get('[data-cy=verification-1st]').type('32');
        cy.get('[data-cy=verification-2nd]').type('45');

        cy.get('#pay-now').click();

        cy.get('body').contains('Verification completed successfully');
    });

    it('should be able to pay the invoice', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-1]').click();

        cy.get('[name=payment-type]').first().check();

        cy.get('#pay-now').click();

        cy.url().should('contain', '/client/payments/');
    });

    it('should be able to remove payment method', function () {
        cy.visit('/client/payment_methods');

        cy.get('[data-cy=view-payment-method]').click();

        cy.get('#open-delete-popup').click();

        cy.get('[data-cy=confirm-payment-removal]').click();

        cy.url().should('contain', '/client/payment_methods');

        cy.get('body').contains('Payment method has been successfully removed.');
    });
});
