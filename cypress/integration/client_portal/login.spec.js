context('Login', () => {
    beforeEach(() => {
        cy.visit('/client/login');
    });

    it('should type into login form elements', () => {
        cy.get('#test_email')
            .invoke('val')
            .then(emailValue => {
                cy.get('#email')
                    .type(emailValue)
                    .should('have.value', emailValue);
            });

        cy.get('#test_password')
            .invoke('val')
            .then(passwordValue => {
                cy.get('#password')
                    .type(passwordValue)
                    .should('have.value', passwordValue);
            });
    });

    it('should login into client portal', () => {
        cy.get('#test_email')
            .invoke('val')
            .then(emailValue => {
                cy.get('#test_password')
                    .invoke('val')
                    .then(passwordValue => {
                        cy.get('#email')
                            .type(emailValue)
                            .should('have.value', emailValue);
                        cy.get('#password')
                            .type(passwordValue)
                            .should('have.value', passwordValue);
                        cy.get('#loginBtn')
                            .contains('Login')
                            .click();
                        cy.location().should(location => {
                            expect(location.pathname).to.eq(
                                '/client/invoices'
                            );
                        });
                    });
            });
    });
});
