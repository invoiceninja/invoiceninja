describe('Quotes', () => {
    beforeEach(() => {
        cy.clientLogin();
    });

    it('should show quotes page', () => {
        cy.visit('/client/quotes');
        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/quotes');
        });
    });

    it('should show quotes text', () => {
        cy.visit('/client/quotes');

        cy.get('body')
            .find('[data-ref=meta-title]')
            .first()
            .should('contain.text', 'Quotes');
    });

    it('should show download and approve buttons', () => {
        cy.visit('/client/quotes');

        cy.get('body')
            .find('button[value="download"]')
            .first()
            .should('contain.text', 'Download');

        cy.get('body')
            .find('button[value="approve"]')
            .first()
            .should('contain.text', 'Approve');
    });

    it('should have per page options dropdown', () => {
        cy.visit('/client/quotes');

        cy.get('body')
            .find('select')
            .first()
            .should('have.value', '10');
    });

    it('should have required table elements', () => {
        cy.visit('/client/quotes');

        cy.get('body')
            .find('table.quotes-table > tbody > tr')
            .first()
            .find('.button-link')
            .first()
            .should('contain.text', 'View')
            .click()
            .location()
            .should(location => {
                expect(location.pathname).to.eq('/client/quotes/VolejRejNm');
            });
    });

    it('should filter table content', () => {
        cy.visit('/client/quotes');

        cy.get('body')
            .find('#draft-checkbox')
            .check();

        cy.get('body')
            .find('table.quotes-table > tbody > tr')
            .first()
            .should('not.contain', 'Sent');
    });
});
