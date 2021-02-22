import axios from 'axios';

const baseUrl = Cypress.config().baseUrl.endsWith('/')
    ? Cypress.config().baseUrl.slice(0, -1)
    : Cypress.config().baseUrl;

Cypress.Commands.add('createAdminAccount', () => {
    let body = {
        first_name: "Cypress",
        last_name: "Testing",
        email: "cypress_testing@example.com",
        password: "password",
        terms_of_service: true,
        privacy_policy: true,
        report_errors: true,
    };

    let headers = {
        "Content-Type": "application/json",
        "X-Requested-With": "XMLHttpRequest"
    };

    return axios.post(`${baseUrl}/api/v1/signup?first_load=true`, body, headers)
        .then(response => {
            console.log('Data from the request', response.data.data[0]);
            return response.data.data[0];
        })
        .catch(e => {
            throw "Unable to create an account for admin.";
        });
});

Cypress.Commands.add('createClientAccount', () => {
    // ..
});
