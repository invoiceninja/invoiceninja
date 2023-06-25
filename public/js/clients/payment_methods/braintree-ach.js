/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!***************************************************************!*\
  !*** ./resources/js/clients/payment_methods/braintree-ach.js ***!
  \***************************************************************/
var _document$querySelect;
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

window.braintree.client.create({
  authorization: (_document$querySelect = document.querySelector('meta[name="client-token"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content
}).then(function (clientInstance) {
  return braintree.usBankAccount.create({
    client: clientInstance
  });
}).then(function (usBankAccountInstance) {
  var _document$getElementB;
  (_document$getElementB = document.getElementById('authorize-bank-account')) === null || _document$getElementB === void 0 ? void 0 : _document$getElementB.addEventListener('click', function (e) {
    e.target.parentElement.disabled = true;
    document.getElementById('errors').hidden = true;
    document.getElementById('errors').textContent = '';
    var bankDetails = {
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
    };
    if (bankDetails.ownershipType === 'personal') {
      var name = document.getElementById('account-holder-name').value.split(' ', 2);
      bankDetails.firstName = name[0];
      bankDetails.lastName = name[1];
    } else {
      bankDetails.businessName = document.getElementById('account-holder-name').value;
    }
    usBankAccountInstance.tokenize({
      bankDetails: bankDetails,
      mandateText: 'By clicking ["Checkout"], I authorize Braintree, a service of PayPal, on behalf of [your business name here] (i) to verify my bank account information using bank information and consumer reports and (ii) to debit my bank account.'
    }).then(function (payload) {
      document.querySelector('input[name=nonce]').value = payload.nonce;
      document.getElementById('server_response').submit();
    })["catch"](function (error) {
      e.target.parentElement.disabled = false;
      document.getElementById('errors').textContent = "".concat(error.details.originalError.message, " ").concat(error.details.originalError.details.originalError[0].message);
      document.getElementById('errors').hidden = false;
    });
  });
})["catch"](function (err) {
  document.getElementById('errors').textContent = err.message;
  document.getElementById('errors').hidden = false;
});
/******/ })()
;