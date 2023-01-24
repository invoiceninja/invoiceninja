describe('Test Login Page', () => {

    it('Shows the Password Reset Pasge.', () =>  {


        cy.visit('/client/password/reset');
        cy.contains('Password Recovery');

        cy.get('input[name=email]').type('cypress@example.com{enter}');
        cy.contains('We have e-mailed your password reset link!');
        
        cy.visit('/client/password/reset');
        cy.contains('Password Recovery');

        cy.get('input[name=email]').type('nono@example.com{enter}');
        cy.contains("We can't find a user with that e-mail address.");

    });

    it('Shows the login page.', () => {
        
        cy.visit('/client/login');
        cy.contains('Client Portal');

        cy.get('input[name=email]').type('cypress@example.com');
        cy.get('input[name=password]').type('password{enter}');
        cy.url().should('include', '/invoices');

        cy.visit('/client/recurring_invoices').contains('Recurring Invoices');
        cy.visit('/client/payments').contains('Payments');
        cy.visit('/client/quotes').contains('Quotes');
        cy.visit('/client/credits').contains('Credits');
        cy.visit('/client/payment_methods').contains('Payment Methods');
        cy.visit('/client/documents').contains('Documents');
        cy.visit('/client/statement').contains('Statement');
        cy.visit('/client/subscriptions').contains('Subscriptions');

        cy.get('[data-ref="client-profile-dropdown"]').click();
        cy.get('[data-ref="client-profile-dropdown-settings"]').click();
        cy.contains('Client Information');
    });

});