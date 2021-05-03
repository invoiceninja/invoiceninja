context('Checkout.com: Credit card testing', () => {
    beforeEach(() => {
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
            .contains('This payment method can be can saved for future use, once you complete your first transaction. Don\'t forget to check "Store credit card details" during payment process.');
    });

    it('should pay with new card', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy
            .get('#braintree-hosted-field-number')
            .wait(5000)
            .iframeLoaded()
            .its('document')
            .getInDocument('#credit-card-number')
            .type(4111111111111111)

        cy
            .get('#braintree-hosted-field-expirationDate')
            .wait(5000)
            .iframeLoaded()
            .its('document')
            .getInDocument('#expiration')
            .type(1224)

        cy.get('#pay-now').click();

        cy.url().should('contain', '/client/payments/VolejRejNm');
    });
});
