/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

import Axios from "axios";

class Setup {
    constructor() {
        this.checkDbButton = document.getElementById("test-db-connection");
        this.checkDbAlert = document.getElementById("database-response");
        this.checkDbEndpoint = document.querySelector(
            'meta[name="test-db-endpoint"]'
        ).content;

        this.checkSmtpButton = document.getElementById("test-smtp-connection");
        this.checkSmtpAlert = document.getElementById("smtp-response");
        this.checkSmtpEndpoint = document.querySelector(
            'meta[name="test-smtp-endpoint"]'
        ).content;
    }

    handleDatabaseCheck() {

        let data = {
            host: document.querySelector('input[name="database_host"]').value,
            database: document.querySelector('input[name="database_db"]').value,
            username: document.querySelector('input[name="database_username"]').value,
            password: document.querySelector('input[name="database_password"]').value,
        }

        Axios.post(this.checkDbEndpoint, data)
            .then(response => this.handleSuccess(this.checkDbAlert))
            .catch(e => this.handleFailure(this.checkDbAlert));
    }

    handleSmtpCheck() {

        let data = {
            driver: document.querySelector('select[name="smtp_driver"]').value,
            from_name: document.querySelector('input[name="email_from_name"]').value,
            from_address: document.querySelector('input[name="email_from_address"]').value,
            username: document.querySelector('input[name="smtp_username"]').value,
            host: document.querySelector('input[name="smtp_host"]').value,
            port: document.querySelector('input[name="smtp_port"]').value,
            encryption: document.querySelector('select[name="smpt_encryption"]').value,
            password: document.querySelector('input[name="smtp_password"]').value,
        }

        Axios.post(this.checkSmtpEndpoint, data)
            .then(response => this.handleSuccess(this.checkSmtpAlert))
            .catch(e => this.handleFailure(this.checkSmtpAlert));
    }

    handleSuccess(element) {
        element.classList.remove("alert-failure");
        element.innerText = "Success!";
        element.classList.add("alert-success");
    }

    handleFailure(element) {
        element.classList.remove("alert-success");
        element.innerText = "Oops, looks like something isn't correct!";
        element.classList.add("alert-failure");
    }

    handle() {
        this.checkDbButton.addEventListener("click", () =>
            this.handleDatabaseCheck()
        );

        this.checkSmtpButton.addEventListener("click", () =>
            this.handleSmtpCheck()
        );
    }
}

new Setup().handle();
