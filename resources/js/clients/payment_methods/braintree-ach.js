/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

import { instant, wait } from '../wait';

function boot() {
    window.braintree.client.create({
        authorization: document.querySelector('meta[name="client-token"]')?.content
    }).then(function (clientInstance) {
        return braintree.usBankAccount.create({
            client: clientInstance
        });
    }).then(function (usBankAccountInstance) {
        document
            .getElementById('authorize-bank-account')
            ?.addEventListener('click', (e) => {
                e.target.parentElement.disabled = true;
    
                document.getElementById('errors').hidden = true;
                document.getElementById('errors').textContent = '';
    
                let bankDetails = {
                    accountNumber: document.getElementById('account-number').value,
                    routingNumber: document.getElementById('routing-number').value,
                    accountType: document.querySelector('input[name="account-type"]:checked').value,
                    ownershipType: document.querySelector('input[name="ownership-type"]:checked').value,
                    billingAddress: {
                        streetAddress: document.getElementById('billing-street-address').value,
                        extendedAddress: document.getElementById('billing-extended-address').value,
                        locality: document.getElementById('billing-locality').value,
                        region: document.getElementById('billing-region').value,
                        postalCode: document.getElementById('billing-postal-code').value
                    }
                }
    
                if (bankDetails.ownershipType === 'personal') {
                    let name = document.getElementById('account-holder-name').value.split(' ', 2);
    
                    bankDetails.firstName = name[0];
                    bankDetails.lastName = name[1];
                } else {
                    bankDetails.businessName = document.getElementById('account-holder-name').value;
                }
    
                usBankAccountInstance.tokenize({
                    bankDetails,
                    mandateText: 'By clicking ["Checkout"], I authorize Braintree, a service of PayPal, on behalf of [your business name here] (i) to verify my bank account information using bank information and consumer reports and (ii) to debit my bank account.'
                }).then(function (payload) {
                    document.querySelector('input[name=nonce]').value = payload.nonce;
                    document.getElementById('server_response').submit();
                })
                    .catch(function (error) {
                        e.target.parentElement.disabled = false;
    
                        document.getElementById('errors').textContent = `${error.details.originalError.message} ${error.details.originalError.details.originalError[0].message}`;
                        document.getElementById('errors').hidden = false;
                    });
            });
    }).catch(function (err) {
    
        document.getElementById('errors').textContent = err.message;
        document.getElementById('errors').hidden = false;
    
    });
}

instant() ? boot() : wait('#braintree-ach-authorize').then(() => boot());
