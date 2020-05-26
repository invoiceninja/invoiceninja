context('Payment methods', () => {
    beforeEach(() => {
        cy.clientLogin();
    });

    it('should show payment methods page', () => {
        cy.visit('/client/payment_methods');
        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/payment_methods');
        });
    });

    it('should show payment methods text', () => {
        cy.visit('/client/payment_methods');

        cy.get('body')
            .find('h3')
            .first()
            .should('contain.text', 'Payment Method');
    });

    it('should show add payment method button', () => {
        cy.visit('/client/payment_methods');

        cy.get('body')
            .find('a.button.button-primary')
            .first()
            .should('contain.text', 'Add Payment Method');
    });

    it('should have per page options dropdown', () => {
        cy.visit('/client/payment_methods');

        cy.get('body')
            .find('select')
            .first()
            .should('have.value', '10');
    });
});
