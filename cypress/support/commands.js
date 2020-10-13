// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })
const axios = require('axios');
const fixture = require('../fixtures/example.json');

Cypress.Commands.add('clientLogin', () => {
    cy.visit('/client/login');
    cy.get('#test_email')
        .invoke('val')
        .then((emailValue) => {
            cy.get('#test_password')
                .invoke('val')
                .then((passwordValue) => {
                    cy.get('#email')
                        .type(emailValue)
                        .should('have.value', emailValue);
                    cy.get('#password')
                        .type(passwordValue)
                        .should('have.value', passwordValue);
                    cy.get('#loginBtn')
                        .contains('Login')
                        .click();
                });
        });
});

Cypress.Commands.add('iframeLoaded', { prevSubject: 'element' }, ($iframe) => {
    const contentWindow = $iframe.prop('contentWindow');
    return new Promise((resolve) => {
        if (contentWindow) {
            resolve(contentWindow);
        } else {
            $iframe.on('load', () => {
                resolve(contentWindow);
            });
        }
    });
});

Cypress.Commands.add(
    'getInDocument',
    {
        prevSubject:
            'Permission denied to access property "document" on cross-origin object',
    },
    (document, selector) => Cypress.$(selector, document)
);

Cypress.Commands.add('getWithinIframe', (targetElement) =>
    cy
        .get('iframe')
        .iframeLoaded()
        .its('document')
        .getInDocument(targetElement)
);

Cypress.Commands.add('useGateway', (gateway) => {
    let body = {
        settings: {
            entity: 'App\\Models\\Client',
            industry_id: '',
            size_id: '',
            currency_id: '1',
            company_gateway_ids: gateway,
        },
    };

    let options = {
        headers: {
            'X-Api-Secret': 'superdoopersecrethere',
            'X-Api-Token':
                'S0x8behDk8HG8PI0i8RXdpf2AVud5b993pE8vata7xmm4RgW6u3NeGC8ibWIUjZv',
            'X-Requested-With': 'XMLHttpRequest',
        },
    };

    axios
        .put(
            `http://localhost:8000/api/v1/clients/${fixture.first}`,
            body,
            options
        )
        .then((response) => console.log(response))
        .catch((error) => console.log(error.message));
});
