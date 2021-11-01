/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!********************************************************!*\
  !*** ./resources/js/clients/payments/stripe-sofort.js ***!
  \********************************************************/
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
var ProcessSOFORT = function ProcessSOFORT(key, _stripeConnect) {
  var _this = this;

  _classCallCheck(this, ProcessSOFORT);

  _defineProperty(this, "setupStripe", function () {
    _this.stripe = Stripe(_this.key);
    if (_this.stripeConnect) _this.stripe.stripeAccount = stripeConnect;
    return _this;
  });

  _defineProperty(this, "handle", function () {
    document.getElementById('pay-now').addEventListener('click', function (e) {
      document.getElementById('pay-now').disabled = true;
      document.querySelector('#pay-now > svg').classList.remove('hidden');
      document.querySelector('#pay-now > span').classList.add('hidden');

      _this.stripe.confirmSofortPayment(document.querySelector('meta[name=pi-client-secret').content, {
        payment_method: {
          sofort: {
            country: document.querySelector('meta[name="country"]').content
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
new ProcessSOFORT(publishableKey, stripeConnect).setupStripe().handle();
/******/ })()
;