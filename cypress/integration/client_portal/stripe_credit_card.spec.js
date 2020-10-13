describe('Stripe Credit Card Payments', () => {
    beforeEach(() => cy.clientLogin());

    it('should be able to add credit card using Stripe', () => {
        cy.visit('/client/payment_methods');

        cy.get('[data-cy=add-payment-method]').click();
        cy.get('[data-cy=add-credit-card-link]').click();

        cy.get('#cardholder-name').type('Invoice Ninja');

        cy.getWithinIframe('[name="cardnumber"]').type('4242424242424242');
        cy.getWithinIframe('[name="exp-date"]').type('1230');
        cy.getWithinIframe('[name="cvc"]').type('100');
        cy.getWithinIframe('[name="postal"]').type('12345');

        cy.get('#card-button').click();

        cy.get('#errors').should('be.empty');

        cy.location('pathname').should('eq', '/client/payment_methods');
    });

    it('should be able to complete payment with added credit card', () => {
        cy.visit('/client/invoices');

        cy.get('#unpaid-checkbox').click();

        cy.get('[data-cy=pay-now')
            .first()
            .click();

        cy.location('pathname').should('eq', '/client/invoices/payment');

        cy.get('[data-cy=payment-methods-dropdown').click();

        cy.get('[data-cy=payment-method')
            .first()
            .click();

        cy.get('#pay-now-with-token').click();

        cy.url().should('contain', '/client/payments');
    });
});
