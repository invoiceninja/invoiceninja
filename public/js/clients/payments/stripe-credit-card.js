/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*************************************************************!*\
  !*** ./resources/js/clients/payments/stripe-credit-card.js ***!
  \*************************************************************/
var _document$querySelect2, _document$querySelect3, _document$querySelect4, _document$querySelect5, _document$querySelect6, _document$querySelect7, _document$querySelect8, _document$querySelect9;
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
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
var StripeCreditCard = /*#__PURE__*/function () {
  function StripeCreditCard(key, secret, onlyAuthorization, stripeConnect) {
    _classCallCheck(this, StripeCreditCard);
    this.key = key;
    this.secret = secret;
    this.onlyAuthorization = onlyAuthorization;
    this.stripeConnect = stripeConnect;
  }
  _createClass(StripeCreditCard, [{
    key: "setupStripe",
    value: function setupStripe() {
      if (this.stripeConnect) {
        this.stripe = Stripe(this.key, {
          stripeAccount: this.stripeConnect
        });
      } else {
        this.stripe = Stripe(this.key);
      }
      this.elements = this.stripe.elements();
      return this;
    }
  }, {
    key: "createElement",
    value: function createElement() {
      var _document$querySelect;
      this.cardElement = this.elements.create('card', {
        hidePostalCode: ((_document$querySelect = document.querySelector('meta[name=stripe-require-postal-code]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content) === "0",
        value: {
          postalCode: document.querySelector('meta[name=client-postal-code]').content
        }
      });
      return this;
    }
  }, {
    key: "mountCardElement",
    value: function mountCardElement() {
      this.cardElement.mount('#card-element');
      return this;
    }
  }, {
    key: "completePaymentUsingToken",
    value: function completePaymentUsingToken() {
      var _this = this;
      var token = document.querySelector('input[name=token]').value;
      var payNowButton = document.getElementById('pay-now');
      this.payNowButton = payNowButton;
      this.payNowButton.disabled = true;
      this.payNowButton.querySelector('svg').classList.remove('hidden');
      this.payNowButton.querySelector('span').classList.add('hidden');
      this.stripe.handleCardPayment(this.secret, {
        payment_method: token
      }).then(function (result) {
        if (result.error) {
          return _this.handleFailure(result.error.message);
        }
        return _this.handleSuccess(result);
      });
    }
  }, {
    key: "completePaymentWithoutToken",
    value: function completePaymentWithoutToken() {
      var _this2 = this;
      var payNowButton = document.getElementById('pay-now');
      this.payNowButton = payNowButton;
      this.payNowButton.disabled = true;
      this.payNowButton.querySelector('svg').classList.remove('hidden');
      this.payNowButton.querySelector('span').classList.add('hidden');
      var cardHolderName = document.getElementById('cardholder-name');
      this.stripe.handleCardPayment(this.secret, this.cardElement, {
        payment_method_data: {
          billing_details: {
            name: cardHolderName.value
          }
        }
      }).then(function (result) {
        if (result.error) {
          return _this2.handleFailure(result.error.message);
        }
        return _this2.handleSuccess(result);
      });
    }
  }, {
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
      this.payNowButton.disabled = false;
      this.payNowButton.querySelector('svg').classList.add('hidden');
      this.payNowButton.querySelector('span').classList.remove('hidden');
    }
  }, {
    key: "handleAuthorization",
    value: function handleAuthorization() {
      var _this3 = this;
      var cardHolderName = document.getElementById('cardholder-name');
      var payNowButton = document.getElementById('authorize-card');
      this.payNowButton = payNowButton;
      this.payNowButton.disabled = true;
      this.payNowButton.querySelector('svg').classList.remove('hidden');
      this.payNowButton.querySelector('span').classList.add('hidden');
      this.stripe.handleCardSetup(this.secret, this.cardElement, {
        payment_method_data: {
          billing_details: {
            name: cardHolderName.value
          }
        }
      }).then(function (result) {
        if (result.error) {
          return _this3.handleFailure(result.error.message);
        }
        return _this3.handleSuccessfulAuthorization(result);
      });
    }
  }, {
    key: "handleSuccessfulAuthorization",
    value: function handleSuccessfulAuthorization(result) {
      document.getElementById('gateway_response').value = JSON.stringify(result.setupIntent);
      document.getElementById('server_response').submit();
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this4 = this;
      this.setupStripe();
      if (this.onlyAuthorization) {
        this.createElement().mountCardElement();
        document.getElementById('authorize-card').addEventListener('click', function () {
          return _this4.handleAuthorization();
        });
      } else {
        Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
          return element.addEventListener('click', function (element) {
            document.getElementById('stripe--payment-container').classList.add('hidden');
            document.getElementById('save-card--container').style.display = 'none';
            document.querySelector('input[name=token]').value = element.target.dataset.token;
          });
        });
        document.getElementById('toggle-payment-with-credit-card').addEventListener('click', function (element) {
          document.getElementById('stripe--payment-container').classList.remove('hidden');
          document.getElementById('save-card--container').style.display = 'grid';
          document.querySelector('input[name=token]').value = "";
        });
        this.createElement().mountCardElement();
        document.getElementById('pay-now').addEventListener('click', function () {
          try {
            var tokenInput = document.querySelector('input[name=token]');
            if (tokenInput.value) {
              return _this4.completePaymentUsingToken();
            }
            return _this4.completePaymentWithoutToken();
          } catch (error) {
            console.log(error.message);
          }
        });
      }
    }
  }]);
  return StripeCreditCard;
}();
var publishableKey = (_document$querySelect2 = (_document$querySelect3 = document.querySelector('meta[name="stripe-publishable-key"]')) === null || _document$querySelect3 === void 0 ? void 0 : _document$querySelect3.content) !== null && _document$querySelect2 !== void 0 ? _document$querySelect2 : '';
var secret = (_document$querySelect4 = (_document$querySelect5 = document.querySelector('meta[name="stripe-secret"]')) === null || _document$querySelect5 === void 0 ? void 0 : _document$querySelect5.content) !== null && _document$querySelect4 !== void 0 ? _document$querySelect4 : '';
var onlyAuthorization = (_document$querySelect6 = (_document$querySelect7 = document.querySelector('meta[name="only-authorization"]')) === null || _document$querySelect7 === void 0 ? void 0 : _document$querySelect7.content) !== null && _document$querySelect6 !== void 0 ? _document$querySelect6 : '';
var stripeConnect = (_document$querySelect8 = (_document$querySelect9 = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect9 === void 0 ? void 0 : _document$querySelect9.content) !== null && _document$querySelect8 !== void 0 ? _document$querySelect8 : '';
var s = new StripeCreditCard(publishableKey, secret, onlyAuthorization, stripeConnect);
s.handle();
Livewire.on('passed-required-fields-check', function () {
  return s.handle();
});
/******/ })()
;