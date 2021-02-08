context('Checkout.com: Credit card testing', () => {
    before(() => {
        cy.artisan('migrate:fresh --seed');
        cy.artisan('ninja:create-single-account checkout');
    });

    beforeEach(() => {
        cy.viewport('macbook-13');
        cy.clientLogin();
    });

    afterEach(() => {
        cy.visit('/client/logout');
    });

    it('should not be able to add payment method', function () {
        cy.visit('/client/payment_methods');

        cy.get('[data-cy=add-payment-method]').click();
        cy.get('[data-cy=add-credit-card-link]').click();

        cy.get('[data-ref=gateway-container]')
            .contains('Checkout.com can be can saved as payment method for future use, once you complete your first transaction. Don\'t forget to check "Store credit card details" during payment process.');
    });

    it('should pay with new card', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy.getWithinIframe('#checkout-frames-card-number').type('4658584090000001');
        cy.getWithinIframe('#checkout-frames-expiry-date').type('12/22');
        cy.getWithinIframe('#checkout-frames-cvv').type('257');

        cy.get('#pay-button').click();

        cy.url().should('contain', '/client/payments/VolejRejNm');
    });

    it('should pay with new card & save credit card for future use', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy.get('[name=token-billing-checkbox]').first().check();

        cy.getWithinIframe('#checkout-frames-card-number').type('4543474002249996');
        cy.getWithinIframe('#checkout-frames-expiry-date').type('12/22');
        cy.getWithinIframe('#checkout-frames-cvv').type('956');

        cy.get('#pay-button').click();

        cy.url().should('contain', '/client/payments/Wpmbk5ezJn');
    });

    it('should pay with saved card (token)', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy.get('[name=payment-type]').first().check();

        cy.get('#pay-now-with-token').click();

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
});
