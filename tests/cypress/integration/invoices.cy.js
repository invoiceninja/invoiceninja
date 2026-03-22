describe('Test Invoices', () => {

    it('Show Invoice List.', () => {

        cy.visit('/client/login');
        cy.contains('Client Portal');

        cy.get('input[name=email]').type('cypress@example.com');
        cy.get('input[name=password]').type('password{enter}');
        cy.url().should('include', '/invoices');

        cy.get('[dusk="pay-now"]').first().click();
        cy.url().should('include', '/invoices/payment');

        cy.get('[dusk="pay-now-dropdown"]').first().click();
        cy.get('[dusk="pay-with-0"]').first().click();

        cy.url().should('include', '/payments/process');

        cy.get('input[name=client_address_line_1]').clear().type('5 Wallaby Way');
        cy.get('input[name=client_city]').clear().type('Perth');
        cy.get('input[name=client_state]').clear().type('WA');
        cy.get('#client_country').select("840");
        
        cy.get('input[name=client_shipping_address_line_1]').clear().type('5 Wallaby Way');
        cy.get('input[name=client_shipping_city]').clear().type('Perth');
        cy.get('input[name=client_shipping_state]').clear().type('WA');
        cy.get('#client_country').select("840");
        
        cy.contains('Continue').click();

        cy.get('#cardholder-name').type('Cypress Test');

        cy.get("iframe").then($iframe => {
            const $body = $iframe.contents().find("body");
            cy.wrap($body)
                .find("input[placeholder='Card number']")
                .type("4242424242424242");
            cy.wrap($body)
                .find("input[placeholder='MM / YY']")
                .type("1225");
            cy.wrap($body)
                .find("input[placeholder='CVC']")
                .type("100");
        });

        cy.get('#pay-now').click();
        cy.url().should('include', '/payments');

        
    });

  
});
