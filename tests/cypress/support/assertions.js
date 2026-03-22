Cypress.Commands.add('assertRedirect', path => {
    cy.location('pathname').should('eq', `/${path}`.replace(/^\/\//, '/'));
});
