describe('Checkout Credit Card Payments', () => {
    beforeEach(() => cy.clientLogin());

    it('should be able to complete payment using checkout credit card', () => {
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

        cy.wait(8000);

        cy.get('.cko-pay-now.show')
            .first()
            .click();

        cy.wait(3000);

        cy.getWithinIframe('[data-checkout="card-number"]').type(
            '4242424242424242'
        );
        cy.getWithinIframe('[data-checkout="expiry-month"]').type('12');
        cy.getWithinIframe('[data-checkout="expiry-year"]').type('30');
        cy.getWithinIframe('[data-checkout="cvv"]').type('100');

        cy.getWithinIframe('.form-submit')
            .first()
            .click();

        cy.wait(5000);
        cy.url().should('contain', '/client/payments');
    });
});
