describe('Ninja plan trial authorization', () => {
    beforeEach(function () {
        if (!Cypress.env('RUN_NINJA_TRIAL_BROWSER_TESTS')) {
            this.skip();
        }

        cy.visit('/client/login');
        cy.get('input[name=email]').type(
            Cypress.env('NINJA_TRIAL_CLIENT_EMAIL')
        );
        cy.get('input[name=password]').type(
            `${Cypress.env('NINJA_TRIAL_CLIENT_PASSWORD')}{enter}`
        );
    });

    it('shows the dedicated server error and preserves form context', function () {
        cy.intercept('POST', '**/client/ninja/trial_confirmation', {
            statusCode: 422,
            headers: {
                'content-type': 'application/json',
            },
            body: {
                message: 'Only credit cards are accepted.',
            },
        }).as('trialConfirmation');

        cy.visit('/client/plan', {
            onBeforeLoad(window) {
                window.Stripe = () => ({
                    elements: () => ({
                        create: () => ({
                            mount: () => {},
                        }),
                    }),
                    confirmCardPayment: () => Promise.resolve({
                        paymentIntent: {
                            id: 'pi_browser_test',
                            status: 'requires_capture',
                        },
                    }),
                });
            },
        });
        cy.get('input[name=first_name]').clear().type('Context');
        cy.get('input[name=last_name]').clear().type('Preserved');
        cy.get('#pay-now').click();
        cy.wait('@trialConfirmation');

        cy.contains('Only credit cards are accepted.');
        cy.get('input[name=first_name]').should('have.value', 'Context');
        cy.get('input[name=last_name]').should('have.value', 'Preserved');
        cy.get('#pay-now').should('not.be.disabled');
    });

    it('follows a same-origin success redirect', function () {
        cy.intercept('POST', '**/client/ninja/trial_confirmation', {
            statusCode: 200,
            headers: {
                'content-type': 'application/json',
            },
            body: {
                redirect_url: `${Cypress.config('baseUrl')}client/plan?trial-test-success=1`,
            },
        }).as('trialConfirmation');

        cy.visit('/client/plan', {
            onBeforeLoad(window) {
                window.Stripe = () => ({
                    elements: () => ({
                        create: () => ({
                            mount: () => {},
                        }),
                    }),
                    confirmCardPayment: () => Promise.resolve({
                        paymentIntent: {
                            id: 'pi_browser_test',
                            status: 'requires_capture',
                        },
                    }),
                });
            },
        });
        cy.get('#pay-now').click();
        cy.wait('@trialConfirmation');

        cy.location('search').should('contain', 'trial-test-success=1');
    });
});
