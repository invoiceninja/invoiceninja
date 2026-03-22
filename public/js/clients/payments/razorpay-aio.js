/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*******************************************************!*\
  !*** ./resources/js/clients/payments/razorpay-aio.js ***!
  \*******************************************************/
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

var options = JSON.parse((_document$querySelect = document.querySelector('meta[name=razorpay-options]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content);
options.handler = function (response) {
  document.getElementById('razorpay_payment_id').value = response.razorpay_payment_id;
  document.getElementById('razorpay_signature').value = response.razorpay_signature;
  document.getElementById('server-response').submit();
};
options.modal = {
  ondismiss: function ondismiss() {
    payNowButton.disabled = false;
  }
};
var razorpay = new Razorpay(options);
var payNowButton = document.getElementById('pay-now');
payNowButton.onclick = function (event) {
  payNowButton.disabled = true;
  razorpay.open();
};
/******/ })()
;