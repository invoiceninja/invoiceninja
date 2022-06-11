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

        this.checkSmtpButton = document.getElementById('test-smtp-connection');
        this.checkSmtpAlert = document.getElementById('smtp-response');

        this.checkPdfButton = document.getElementById('test-pdf');
        this.checkPdfAlert = document.getElementById('test-pdf-response');
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
            .then((response) =>
                this.handleSuccess(this.checkDbAlert, 'mail-wrapper')
            )
            .catch((e) =>
                this.handleFailure(this.checkDbAlert, e.response.data.message)
            ).finally(() => this.checkDbButton.disabled = false);
    }

    handleSmtpCheck() {
        let url = document.querySelector('meta[name=setup-email-check]').content;

        let data = {
            mail_driver: document.querySelector('select[name="mail_driver"]')
                .value,
            mail_name: document.querySelector('input[name="mail_name"]').value,
            mail_address: document.querySelector('input[name="mail_address"]')
                .value,
            mail_username: document.querySelector('input[name="mail_username"]')
                .value,
            mail_host: document.querySelector('input[name="mail_host"]').value,
            mail_port: document.querySelector('input[name="mail_port"]').value,
            encryption: document.querySelector('select[name="encryption"]')
                .value,
            mail_password: document.querySelector('input[name="mail_password"]')
                .value,
        };

        this.checkSmtpButton.disabled = true;

        if (data.mail_driver === 'log') {
            this.handleSuccess(this.checkSmtpAlert, 'account-wrapper');
            this.handleSuccess(this.checkSmtpAlert, 'submit-wrapper');

            return (this.checkSmtpButton.disabled = false);
        }

        Axios.post(url, data)
            .then((response) => {
                this.handleSuccess(this.checkSmtpAlert, 'account-wrapper');
                this.handleSuccess(this.checkSmtpAlert, 'submit-wrapper');
            })
            .catch((e) =>
                this.handleFailure(this.checkSmtpAlert, e.response.data.message)
            )
            .finally(() => (this.checkSmtpButton.disabled = false));
    }

    handleTestPdfCheck() {
        let url = document.querySelector('meta[name=setup-pdf-check]').content;
        this.checkPdfButton.disabled = true;

        Axios.post(url, {})
            .then((response) => {
                try {
                    //let win = window.open(response.data.url, '_blank');
                    //win.focus();

                    return this.handleSuccess(
                        this.checkPdfAlert,
                        'database-wrapper'
                    );
                } catch (error) {
                    this.handleSuccess(this.checkPdfAlert, 'database-wrapper');
                    this.checkPdfAlert.textContent = `Success! PDF was generated succesfully.`;
                }
            })
            .catch((error) => {
                console.log(error);
                this.handleFailure(this.checkPdfAlert);
            })
            .finally(() => (this.checkPdfButton.disabled = false));
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

        this.checkSmtpButton.addEventListener('click', () =>
            this.handleSmtpCheck()
        );

        this.checkPdfButton.addEventListener('click', () =>
            this.handleTestPdfCheck()
        );
    }
}

new Setup().handle();
