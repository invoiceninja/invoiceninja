context('Recurring invoices', () => {
    beforeEach(() => {
        cy.clientLogin();
    });

    it('should show recurring invoices page', () => {
        cy.visit('/client/recurring_invoices');

        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/recurring_invoices');
        });
    });

    it('should show reucrring invoices text', () => {
        cy.visit('/client/recurring_invoices');

        cy.get('body')
            .find('[data-ref=meta-title]')
            .first()
            .should('contain.text', 'Recurring Invoices');
    });

    it('should have per page options dropdown', () => {
        cy.visit('/client/recurring_invoices');

        cy.get('body')
            .find('select')
            .first()
            .should('have.value', '10');
    });
});
