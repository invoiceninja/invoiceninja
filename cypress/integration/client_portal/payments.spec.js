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
            .find('span')
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

    it('should have required table elements', () => {
        cy.visit('/client/payments');

        cy.get('body')
            .find('table.payments-table > tbody > tr')
            .first()
            .find('a')
            .first()
            .should('contain.text', 'View')
            .click()
            .location()
            .should(location => {
                expect(location.pathname).to.eq('/client/payments/VolejRejNm');
            });
    });
})