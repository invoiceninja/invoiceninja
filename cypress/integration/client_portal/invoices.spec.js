context('Invoices', () => {
    beforeEach(() => {
        cy.clientLogin();
    });

    it('should show invoices page', () => {
        cy.visit('/client/invoices');
        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/invoices');
        });
    });

    it('should show invoices text', () => {
        cy.visit('/client/invoices');

        cy.get('body')
            .find('h3')
            .first()
            .should('contain.text', 'Invoices');
    });

    it('should show download and pay now buttons', () => {
        cy.visit('/client/invoices');

        cy.get('body')
            .find('button[value="download"]')
            .first()
            .should('contain.text', 'Download');

        cy.get('body')
            .find('button[value="payment"]')
            .first()
            .should('contain.text', 'Pay Now');
    });

    it('should have per page options dropdown', () => {
        cy.visit('/client/invoices');

        cy.get('body')
            .find('select')
            .first()
            .should('have.value', '10');
    });

    it('should have required table elements', () => {
        cy.visit('/client/invoices');

        cy.get('body')
            .find('table.invoices-table > tbody > tr')
            .first()
            .find('.button-link')
            .first()
            .should('contain.text', 'View')
            .click()
            .location().should(location => {
                expect(location.pathname).to.eq('/client/invoices/VolejRejNm');
            });
    });
});
