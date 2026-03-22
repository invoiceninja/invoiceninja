/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

import Axios from 'axios';

class Setup {
    constructor() {
        this.checkDbButton = document.getElementById('test-db-connection');
        this.checkDbAlert = document.getElementById('database-response');
    }

    handleDatabaseCheck() {
        let url = document.querySelector('meta[name=setup-db-check]').content,
            data = {};

        if (document.querySelector('input[name="db_host"]')) {
            data = {
                db_host: document.querySelector('input[name="db_host"]').value,
                db_port: document.querySelector('input[name="db_port"]').value,
                db_database: document.querySelector('input[name="db_database"]')
                    .value,
                db_username: document.querySelector('input[name="db_username"]')
                    .value,
                db_password: document.querySelector('input[name="db_password"]')
                    .value,
            };
        }

        this.checkDbButton.disabled = true;

        Axios.post(url, data)
            .then((response) =>{
                    this.handleSuccess(this.checkDbAlert, 'account-wrapper');
                    this.handleSuccess(this.checkDbAlert, 'submit-wrapper');
                }
            )
            .catch((e) =>
                this.handleFailure(this.checkDbAlert, e.response.data.message)
            ).finally(() => this.checkDbButton.disabled = false);
    }


    handleSuccess(element, nextStep = null) {
        element.classList.remove('alert-failure');
        element.innerText = 'Success!';
        element.classList.add('alert-success');

        if (nextStep) {
            document.getElementById(nextStep).classList.remove('hidden');
            document
                .getElementById(nextStep)
                .scrollIntoView({behavior: 'smooth', block: 'center'});
        }
    }

    handleFailure(element, message = null) {
        element.classList.remove('alert-success');
        element.innerText = message
            ? message
            : "Oops, looks like something isn't correct!";
        element.classList.add('alert-failure');
    }

    handle() {
        this.checkDbButton.addEventListener('click', () =>
            this.handleDatabaseCheck()
        );
    }
}

new Setup().handle();
