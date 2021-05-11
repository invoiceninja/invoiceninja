context('Payments', () => {
    beforeEach(() => {
        cy.clientLogin();
    });

    it('should show payments page', () => {
        cy.visit('/client/payments');
        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/payments');
        });
    });

    it('should show payments text', () => {
        cy.visit('/client/payments');

        cy.get('body')
            .find('[data-ref=meta-title]')
            .first()
            .should('contain.text', 'Payments');
    });

    it('should have per page options dropdown', () => {
        cy.visit('/client/payments');

        cy.get('body')
            .find('select')
            .first()
            .should('have.value', '10');
    });
});
