/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*********************************************************!*\
  !*** ./resources/js/clients/payments/stripe-giropay.js ***!
  \*********************************************************/
var _document$querySelect, _document$querySelect2, _document$querySelect3, _document$querySelect4;

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var ProcessGiroPay = function ProcessGiroPay(key, _stripeConnect) {
  var _this = this;

  _classCallCheck(this, ProcessGiroPay);

  _defineProperty(this, "setupStripe", function () {
    _this.stripe = Stripe(_this.key);
    if (_this.stripeConnect) _this.stripe.stripeAccount = stripeConnect;
    return _this;
  });

  _defineProperty(this, "handle", function () {
    document.getElementById('pay-now').addEventListener('click', function (e) {
      var errors = document.getElementById('errors');

      if (!document.getElementById('giropay-mandate-acceptance').checked) {
        errors.textContent = document.querySelector('meta[name=translation-terms-required]').content;
        errors.hidden = false;
        console.log("Terms");
        return;
      }

      document.getElementById('pay-now').disabled = true;
      document.querySelector('#pay-now > svg').classList.remove('hidden');
      document.querySelector('#pay-now > span').classList.add('hidden');

      _this.stripe.confirmGiropayPayment(document.querySelector('meta[name=pi-client-secret').content, {
        payment_method: {
          billing_details: {
            name: document.getElementById("giropay-name").value
          }
        },
        return_url: document.querySelector('meta[name="return-url"]').content
      });
    });
  });

  this.key = key;
  this.errors = document.getElementById('errors');
  this.stripeConnect = _stripeConnect;
};

var publishableKey = (_document$querySelect = (_document$querySelect2 = document.querySelector('meta[name="stripe-publishable-key"]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
var stripeConnect = (_document$querySelect3 = (_document$querySelect4 = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.content) !== null && _document$querySelect3 !== void 0 ? _document$querySelect3 : '';
new ProcessGiroPay(publishableKey, stripeConnect).setupStripe().handle();
/******/ })()
;