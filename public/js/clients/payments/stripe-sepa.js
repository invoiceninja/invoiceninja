/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!******************************************************!*\
  !*** ./resources/js/clients/payments/stripe-sepa.js ***!
  \******************************************************/
var _document$querySelect, _document$querySelect2, _document$querySelect3, _document$querySelect4;
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
var ProcessSEPA = /*#__PURE__*/function () {
  function ProcessSEPA(key, stripeConnect) {
    var _this = this;
    _classCallCheck(this, ProcessSEPA);
    _defineProperty(this, "setupStripe", function () {
      if (_this.stripeConnect) {
        _this.stripe = Stripe(_this.key, {
          stripeAccount: _this.stripeConnect
        });
      } else {
        _this.stripe = Stripe(_this.key);
      }
      var elements = _this.stripe.elements();
      var style = {
        base: {
          color: '#32325d',
          fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
          fontSmoothing: 'antialiased',
          fontSize: '16px',
          '::placeholder': {
            color: '#aab7c4'
          },
          ':-webkit-autofill': {
            color: '#32325d'
          }
        },
        invalid: {
          color: '#fa755a',
          iconColor: '#fa755a',
          ':-webkit-autofill': {
            color: '#fa755a'
          }
        }
      };
      var options = {
        style: style,
        supportedCountries: ['SEPA'],
        // If you know the country of the customer, you can optionally pass it to
        // the Element as placeholderCountry. The example IBAN that is being used
        // as placeholder reflects the IBAN format of that country.
        placeholderCountry: document.querySelector('meta[name="country"]').content
      };
      _this.iban = elements.create('iban', options);
      _this.iban.mount('#sepa-iban');
      document.getElementById('sepa-name').value = document.querySelector('meta[name=client_name]').content;
      document.getElementById('sepa-email-address').value = document.querySelector('meta[name=client_email]').content;
      return _this;
    });
    _defineProperty(this, "handle", function () {
      var errors = document.getElementById('errors');
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (element) {
          document.getElementById('stripe--payment-container').classList.add('hidden');
          document.getElementById('save-card--container').style.display = 'none';
          document.querySelector('input[name=token]').value = element.target.dataset.token;
        });
      });
      document.getElementById('toggle-payment-with-new-bank-account').addEventListener('click', function (element) {
        document.getElementById('stripe--payment-container').classList.remove('hidden');
        document.getElementById('save-card--container').style.display = 'grid';
        document.querySelector('input[name=token]').value = '';
      });
      document.getElementById('pay-now').addEventListener('click', function (e) {
        if (document.querySelector('input[name=token]').value.length !== 0) {
          document.getElementById('pay-now').disabled = true;
          document.querySelector('#pay-now > svg').classList.remove('hidden');
          document.querySelector('#pay-now > span').classList.add('hidden');
          _this.stripe.confirmSepaDebitPayment(document.querySelector('meta[name=pi-client-secret').content, {
            payment_method: document.querySelector('input[name=token]').value
          }).then(function (result) {
            if (result.error) {
              return _this.handleFailure(result.error.message);
            }
            return _this.handleSuccess(result);
          });
        } else {
          if (document.getElementById('sepa-name').value === '') {
            document.getElementById('sepa-name').focus();
            errors.textContent = document.querySelector('meta[name=translation-name-required]').content;
            errors.hidden = false;
            return;
          }
          if (document.getElementById('sepa-email-address').value === '') {
            document.getElementById('sepa-email-address').focus();
            errors.textContent = document.querySelector('meta[name=translation-email-required]').content;
            errors.hidden = false;
            return;
          }
          if (!document.getElementById('sepa-mandate-acceptance').checked) {
            errors.textContent = document.querySelector('meta[name=translation-terms-required]').content;
            errors.hidden = false;
            return;
          }
          document.getElementById('pay-now').disabled = true;
          document.querySelector('#pay-now > svg').classList.remove('hidden');
          document.querySelector('#pay-now > span').classList.add('hidden');
          _this.stripe.confirmSepaDebitPayment(document.querySelector('meta[name=pi-client-secret').content, {
            payment_method: {
              sepa_debit: _this.iban,
              billing_details: {
                name: document.getElementById('sepa-name').value,
                email: document.getElementById('sepa-email-address').value
              }
            }
          }).then(function (result) {
            if (result.error) {
              return _this.handleFailure(result.error.message);
            }
            return _this.handleSuccess(result);
          });
        }
      });
    });
    this.key = key;
    this.errors = document.getElementById('errors');
    this.stripeConnect = stripeConnect;
  }
  _createClass(ProcessSEPA, [{
    key: "handleSuccess",
    value: function handleSuccess(result) {
      document.querySelector('input[name="gateway_response"]').value = JSON.stringify(result.paymentIntent);
      var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');
      if (tokenBillingCheckbox) {
        document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.value;
      }
      document.getElementById('server-response').submit();
    }
  }, {
    key: "handleFailure",
    value: function handleFailure(message) {
      var errors = document.getElementById('errors');
      errors.textContent = '';
      errors.textContent = message;
      errors.hidden = false;
      document.getElementById('pay-now').disabled = false;
      document.querySelector('#pay-now > svg').classList.add('hidden');
      document.querySelector('#pay-now > span').classList.remove('hidden');
    }
  }]);
  return ProcessSEPA;
}();
var publishableKey = (_document$querySelect = (_document$querySelect2 = document.querySelector('meta[name="stripe-publishable-key"]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
var stripeConnect = (_document$querySelect3 = (_document$querySelect4 = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.content) !== null && _document$querySelect3 !== void 0 ? _document$querySelect3 : '';
new ProcessSEPA(publishableKey, stripeConnect).setupStripe().handle();
/******/ })()
;