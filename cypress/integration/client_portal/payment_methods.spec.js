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
            .find('span')
            .first()
            .should('contain.text', 'Payment Method');
    });

    it('should add stripe credit card', () => {
        cy.visit('/client/payment_methods');

        cy.get('body')
            .find('#add-payment-method')
            .first()
            .should('contain.text', 'Add Payment Method')
            .click()

        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/payment_methods/create');
        });

        cy.wait(3000);

        cy.get('#cardholder-name').type('Invoice Ninja');

        cy.getWithinIframe('[name="cardnumber"]').type('4242424242424242');
        cy.getWithinIframe('[name="exp-date"]').type('2442');
        cy.getWithinIframe('[name="cvc"]').type('242');
        cy.getWithinIframe('[name="postal"]').type('12345');

        cy.get('#card-button').click();

        cy.location().should(location => {
            expect(location.pathname).to.eq('/client/payment_methods');
        });
    });

    it('should have per page options dropdown', () => {
        cy.visit('/client/payment_methods');

        cy.get('body')
            .find('select')
            .first()
            .should('have.value', '10');
    });
});
