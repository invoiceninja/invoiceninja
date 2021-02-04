describe('Stripe: SOFORT testing', () => {
    before(() => {
        // cy.artisan('migrate:fresh --seed');
        // cy.artisan('ninja:create-single-account checkout');
    });

    beforeEach(() => {
        cy.viewport('macbook-13');
        cy.clientLogin();
    });

    afterEach(() => {
        cy.visit('/client/logout');
    });

    it('should be able to pay using SOFORT', function () {
        cy.visit('/client/invoices');

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-2]').click();

        cy.get('#pay-now').click();

        cy.get('.common-ButtonGroup > .common-Button--default').click();

        cy.url().should('contain', '/client/payments/');
    });
});
