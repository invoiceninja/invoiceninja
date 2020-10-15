describe('Credits', () => {
    beforeEach(() => {
        cy.clientLogin();
    });

    it('should show credits page', () => {
        cy.visit('/client/credits');
        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/credits');
        });
    });

    it('should show credits text', () => {
        cy.visit('/client/credits');

        cy.get('body')
            .find('span')
            .first()
            .should('contain.text', 'Credits');
    });

   /* it('should have required table elements', () => {
        cy.visit('/client/credits');

        cy.get('body')
            .find('table.credits-table > tbody > tr')
            .first()
            .find('a')
            .first()
            .should('contain.text', 'View')
            .click()
            .location()
            .should(location => {
                expect(location.pathname).to.eq('/client/credits/VolejRejNm');
            });
    });*/
});
