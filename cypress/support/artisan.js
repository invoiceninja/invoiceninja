Cypress.Commands.add('artisan', (cmd) => {
    let environment = Cypress.env('runningEnvironment');
    let prefix = environment === 'docker' ? 'docker-compose run --rm artisan' : 'php artisan';

    return cy.exec(`${prefix} ${cmd}`);
});
