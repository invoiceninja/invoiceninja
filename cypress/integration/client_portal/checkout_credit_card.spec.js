context('Checkout.com gateway test', () => {
    beforeEach(() => {
        cy.viewport('macbook-13');
    });

    it('should migrate & seed checkout', function () {
        // cy.artisan('migrate:fresh --seed');
        // cy.artisan('ninja:create-single-account');
        cy.clientLogin();

        cy.get('[data-cy=pay-now]').first().click();
        cy.get('[data-cy=pay-now-dropdown]').click();
        cy.get('[data-cy=pay-with-0]').click();

        cy.getWithinIframe('#checkout-frames-card-number').type('4242424242424242');
        cy.getWithinIframe('#checkout-frames-expiry-date').type('12/30');
        cy.getWithinIframe('#checkout-frames-cvv').type('100');

        cy.get('#pay-button').click();
    });
});
