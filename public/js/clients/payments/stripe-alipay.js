/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!********************************************************!*\
  !*** ./resources/js/clients/payments/stripe-alipay.js ***!
  \********************************************************/
var _document$querySelect, _document$querySelect2, _document$querySelect3, _document$querySelect4;

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */
var ProcessAlipay = /*#__PURE__*/_createClass(function ProcessAlipay(key, stripeConnect) {
  var _this = this;

  _classCallCheck(this, ProcessAlipay);

  _defineProperty(this, "setupStripe", function () {
    if (_this.stripeConnect) {
      // this.stripe.stripeAccount = this.stripeConnect;
      _this.stripe = Stripe(_this.key, {
        stripeAccount: _this.stripeConnect
      });
    } else {
      _this.stripe = Stripe(_this.key);
    }

    return _this;
  });

  _defineProperty(this, "handle", function () {
    var data = {
      type: 'alipay',
      amount: document.querySelector('meta[name="amount"]').content,
      currency: document.querySelector('meta[name="currency"]').content,
      redirect: {
        return_url: document.querySelector('meta[name="return-url"]').content
      }
    };
    document.getElementById('pay-now').addEventListener('click', function (e) {
      document.getElementById('pay-now').disabled = true;
      document.querySelector('#pay-now > svg').classList.add('hidden');
      document.querySelector('#pay-now > span').classList.remove('hidden');

      _this.stripe.createSource(data).then(function (result) {
        if (result.hasOwnProperty('source')) {
          return window.location = result.source.redirect.url;
        }

        document.getElementById('pay-now').disabled = false;
        document.querySelector('#pay-now > svg').classList.remove('hidden');
        document.querySelector('#pay-now > span').classList.add('hidden');
        this.errors.textContent = '';
        this.errors.textContent = result.error.message;
        this.errors.hidden = false;
      });
    });
  });

  this.key = key;
  this.stripeConnect = stripeConnect;
  this.errors = document.getElementById('errors');
});

var publishableKey = (_document$querySelect = (_document$querySelect2 = document.querySelector('meta[name="stripe-publishable-key"]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
var stripeConnect = (_document$querySelect3 = (_document$querySelect4 = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.content) !== null && _document$querySelect3 !== void 0 ? _document$querySelect3 : '';
new ProcessAlipay(publishableKey, stripeConnect).setupStripe().handle();
/******/ })()
;