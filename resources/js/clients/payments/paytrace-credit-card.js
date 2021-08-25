/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

class PayTraceCreditCard {
    constructor() {
        this.clientKey = document.querySelector(
            'meta[name=paytrace-client-key]'
        )?.content;
    }

    get creditCardStyles() {
        return {
            font_color: '#111827',
            border_color: 'rgba(210,214,220,1)',
            label_color: '#111827',
            label_size: '12pt',
            background_color: 'white',
            border_style: 'solid',
            font_size: '15pt',
            height: '30px',
            width: '100%',
        };
    }

    get codeStyles() {
        return {
            font_color: '#111827',
            border_color: 'rgba(210,214,220,1)',
            label_color: '#111827',
            label_size: '12pt',
            background_color: 'white',
            border_style: 'solid',
            font_size: '15pt',
            height: '30px',
            width: '300px',
        };
    }

    get expStyles() {
        return {
            font_color: '#111827',
            border_color: 'rgba(210,214,220,1)',
            label_color: '#111827',
            label_size: '12pt',
            background_color: 'white',
            border_style: 'solid',
            font_size: '15pt',
            height: '30px',
            width: '85px',
            type: 'dropdown',
        };
    }

    updatePayTraceLabels() {
        window.PTPayment.getControl('securityCode').label.text(
            document.querySelector('meta[name=ctrans-cvv]').content
        );

        window.PTPayment.getControl('creditCard').label.text(
            document.querySelector('meta[name=ctrans-card_number]').content
        );

        window.PTPayment.getControl('expiration').label.text(
            document.querySelector('meta[name=ctrans-expires]').content
        );
    }

    setupPayTrace() {
        return window.PTPayment.setup({
            styles: {
                code: this.codeStyles,
                cc: this.creditCardStyles,
                exp: this.expStyles,
            },
            authorization: {
                clientKey: this.clientKey,
            },
        });
    }

    handlePaymentWithCreditCard(event) {
        event.target.parentElement.disabled = true;
        document.getElementById('errors').hidden = true;

        window.PTPayment.validate((errors) => {
            if (errors.length >= 1) {
                let errorsContainer = document.getElementById('errors');

                errorsContainer.textContent = errors[0].description;
                errorsContainer.hidden = false;

                return (event.target.parentElement.disabled = false);
            }

            this.ptInstance
                .process()
                .then((response) => {
                    document.getElementById('HPF_Token').value =
                        response.message.hpf_token;
                    document.getElementById('enc_key').value =
                        response.message.enc_key;

                    let tokenBillingCheckbox = document.querySelector(
                        'input[name="token-billing-checkbox"]:checked'
                    );

                    if (tokenBillingCheckbox) {
                        document.querySelector(
                            'input[name="store_card"]'
                        ).value = tokenBillingCheckbox.value;
                    }

                    document.getElementById('server_response').submit();
                })
                .catch((error) => {
                    document.getElementById(
                        'errors'
                    ).textContent = JSON.stringify(error);
                    document.getElementById('errors').hidden = false;

                    console.log(error);
                });
        });
    }

    handlePaymentWithToken(event) {
        event.target.parentElement.disabled = true;

        document.getElementById('server_response').submit();
    }

    handle() {
        Array.from(
            document.getElementsByClassName('toggle-payment-with-token')
        ).forEach((element) =>
            element.addEventListener('click', (element) => {
                document
                    .getElementById('paytrace--credit-card-container')
                    .classList.add('hidden');
                document.getElementById(
                    'save-card--container'
                ).style.display = 'none';
                document.querySelector('input[name=token]').value =
                    element.target.dataset.token;
            })
        );

        document
            .getElementById('toggle-payment-with-credit-card')
            ?.addEventListener('click', (element) => {
                document
                    .getElementById('paytrace--credit-card-container')
                    .classList.remove('hidden');
                document.getElementById(
                    'save-card--container'
                ).style.display = 'grid';
                document.querySelector('input[name=token]').value = '';

                this.setupPayTrace().then((instance) => {
                    this.ptInstance = instance;
                    this.updatePayTraceLabels();
                });
            });

        document
            .getElementById('pay-now')
            .addEventListener('click', (e) => {
                if (
                    document.querySelector('input[name=token]').value === ''
                ) {
                    return this.handlePaymentWithCreditCard(e);
                }

                return this.handlePaymentWithToken(e);
            });
    }
}

new PayTraceCreditCard().handle();
