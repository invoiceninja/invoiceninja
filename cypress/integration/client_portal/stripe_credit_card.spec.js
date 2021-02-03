describe('Stripe: Credit card testing', () => {
    before(() => {
        cy.artisan('migrate:fresh --seed');
        cy.artisan('ninja:create-single-account stripe');
    });

    beforeEach(() => {
        cy.viewport('macbook-13');
        cy.clientLogin();
    });

    afterEach(() => {
        cy.visit('/client/logout');
    });

    it('should pay with new card', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy.get('#cardholder-name').type('Invoice Ninja Rocks');
        cy.getWithinIframe('[name=cardnumber]').type('4242424242424242');
        cy.getWithinIframe('[name=exp-date]').type('04/24');
        cy.getWithinIframe('[name=cvc]').type('242');
        cy.getWithinIframe('[name=postal]').type('42424');

        cy.get('#pay-now').click();

        cy.url().should('contain', '/client/payments/VolejRejNm');
    });

    it('should pay with new card & save credit card for future use', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy.get('#cardholder-name').type('Invoice Ninja Rocks');
        cy.getWithinIframe('[name=cardnumber]').type('4242424242424242');
        cy.getWithinIframe('[name=exp-date]').type('04/24');
        cy.getWithinIframe('[name=cvc]').type('242');
        cy.getWithinIframe('[name=postal]').type('42424');

        cy.get('[name=token-billing-checkbox]').first().check();

        cy.get('#pay-now').click();

        cy.url().should('contain', '/client/payments/Wpmbk5ezJn');
    });

    it('should pay with saved card (token)', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy.get('[name=payment-type]').first().check();

        cy.get('#pay-now').click();

        cy.url().should('contain', '/client/payments/Opnel5aKBz');
    });

    it('should be able to remove payment method', function () {
        cy.visit('/client/payment_methods');

        cy.get('[data-cy=view-payment-method]').click();

        cy.get('#open-delete-popup').click();

        cy.get('[data-cy=confirm-payment-removal]').click();

        cy.url().should('contain', '/client/payment_methods');

        cy.get('body').contains('Payment method has been successfully removed.');
    });

    it('should be able to add credit card (standalone)', function () {
        cy.visit('/client/payment_methods');

        cy.get('[data-cy=add-payment-method]').click();
        cy.get('[data-cy=add-credit-card-link]').click();

        cy.get('#cardholder-name').type('Invoice Ninja Rocks');
        cy.getWithinIframe('[name=cardnumber]').type('4242424242424242');
        cy.getWithinIframe('[name=exp-date]').type('04/24');
        cy.getWithinIframe('[name=cvc]').type('242');
        cy.getWithinIframe('[name=postal]').type('42424');

        cy.get('#authorize-card').click();

        cy.url().should('contain', '/client/payment_methods');
    });
});
