/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!************************************************************!*\
  !*** ./resources/js/clients/payments/wepay-credit-card.js ***!
  \************************************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
var _document$querySelect;
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

var action = (_document$querySelect = document.querySelector('meta[name="wepay-action"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content;
var WePayCreditCard = /*#__PURE__*/function () {
  function WePayCreditCard() {
    var action = arguments.length > 0 && arguments[0] !== undefined ? arguments[0] : 'payment';
    _classCallCheck(this, WePayCreditCard);
    this.action = action;
    this.errors = document.getElementById('errors');
  }
  _createClass(WePayCreditCard, [{
    key: "initializeWePay",
    value: function initializeWePay() {
      var _document$querySelect2;
      var environment = (_document$querySelect2 = document.querySelector('meta[name="wepay-environment"]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content;
      WePay.set_endpoint(environment === 'staging' ? 'stage' : 'production');
      return this;
    }
  }, {
    key: "validateCreditCardFields",
    value: function validateCreditCardFields() {
      this.myCard = $('#my-card');
      if (document.getElementById('cardholder_name') === "") {
        document.getElementById('cardholder_name').focus();
        this.errors.textContent = "Cardholder name required.";
        this.errors.hidden = false;
        return;
      } else if (this.myCard.CardJs('cardNumber').replace(/[^\d]/g, '') === "") {
        document.getElementById('card_number').focus();
        this.errors.textContent = "Card number required.";
        this.errors.hidden = false;
        return;
      } else if (this.myCard.CardJs('cvc').replace(/[^\d]/g, '') === "") {
        document.getElementById('cvv').focus();
        this.errors.textContent = "CVV number required.";
        this.errors.hidden = false;
        return;
      } else if (this.myCard.CardJs('expiryMonth').replace(/[^\d]/g, '') === "") {
        // document.getElementById('expiry_month').focus();
        this.errors.textContent = "Expiry Month number required.";
        this.errors.hidden = false;
        return;
      } else if (this.myCard.CardJs('expiryYear').replace(/[^\d]/g, '') === "") {
        // document.getElementById('expiry_year').focus();
        this.errors.textContent = "Expiry Year number required.";
        this.errors.hidden = false;
        return;
      }
      return true;
    }
  }, {
    key: "handleAuthorization",
    value: function handleAuthorization() {
      var _this = this;
      if (!this.validateCreditCardFields()) {
        return;
      }
      var cardButton = document.getElementById('card_button');
      cardButton.disabled = true;
      cardButton.querySelector('svg').classList.remove('hidden');
      cardButton.querySelector('span').classList.add('hidden');
      WePay.credit_card.create({
        client_id: document.querySelector('meta[name=wepay-client-id]').content,
        user_name: document.getElementById('cardholder_name').value,
        email: document.querySelector('meta[name=contact-email]').content,
        cc_number: this.myCard.CardJs('cardNumber').replace(/[^\d]/g, ''),
        cvv: this.myCard.CardJs('cvc').replace(/[^\d]/g, ''),
        expiration_month: this.myCard.CardJs('expiryMonth').replace(/[^\d]/g, ''),
        expiration_year: this.myCard.CardJs('expiryYear').replace(/[^\d]/g, ''),
        address: {
          country: document.querySelector(['meta[name=country_code']).content,
          postal_code: document.querySelector(['meta[name=client-postal-code']).content
        }
      }, function (data) {
        if (data.error) {
          cardButton = document.getElementById('card_button');
          cardButton.disabled = false;
          cardButton.querySelector('svg').classList.add('hidden');
          cardButton.querySelector('span').classList.remove('hidden');
          _this.errors.textContent = '';
          _this.errors.textContent = data.error_description;
          _this.errors.hidden = false;
        } else {
          document.querySelector('input[name="credit_card_id"]').value = data.credit_card_id;
          document.getElementById('server_response').submit();
        }
      });
    }
  }, {
    key: "completePaymentUsingToken",
    value: function completePaymentUsingToken(token) {
      document.querySelector('input[name="credit_card_id"]').value = null;
      document.querySelector('input[name="token"]').value = token;
      document.getElementById('server-response').submit();
    }
  }, {
    key: "completePaymentWithoutToken",
    value: function completePaymentWithoutToken() {
      var _this2 = this;
      if (!this.validateCreditCardFields()) {
        this.payNowButton = document.getElementById('pay-now');
        this.payNowButton.disabled = false;
        this.payNowButton.querySelector('svg').classList.add('hidden');
        this.payNowButton.querySelector('span').classList.remove('hidden');
        return;
      }
      WePay.credit_card.create({
        client_id: document.querySelector('meta[name=wepay-client-id]').content,
        user_name: document.getElementById('cardholder_name').value,
        email: document.querySelector('meta[name=contact-email]').content,
        cc_number: this.myCard.CardJs('cardNumber').replace(/[^\d]/g, ''),
        cvv: this.myCard.CardJs('cvc').replace(/[^\d]/g, ''),
        expiration_month: this.myCard.CardJs('expiryMonth').replace(/[^\d]/g, ''),
        expiration_year: this.myCard.CardJs('expiryYear').replace(/[^\d]/g, ''),
        address: {
          country: document.querySelector(['meta[name=country_code']).content,
          postal_code: document.querySelector(['meta[name=client-postal-code']).content
        }
      }, function (data) {
        if (data.error) {
          _this2.payNowButton.disabled = false;
          _this2.payNowButton.querySelector('svg').classList.add('hidden');
          _this2.payNowButton.querySelector('span').classList.remove('hidden');
          _this2.errors.textContent = '';
          _this2.errors.textContent = data.error_description;
          _this2.errors.hidden = false;
        } else {
          document.querySelector('input[name="credit_card_id"]').value = data.credit_card_id;
          document.querySelector('input[name="token"]').value = null;
          document.getElementById('server-response').submit();
        }
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this3 = this;
      this.initializeWePay();
      if (this.action === 'authorize') {
        document.getElementById('card_button').addEventListener('click', function () {
          return _this3.handleAuthorization();
        });
      } else if (this.action === 'payment') {
        Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
          return element.addEventListener('click', function (e) {
            document.getElementById('save-card--container').style.display = 'none';
            document.getElementById('wepay--credit-card-container').style.display = 'none';
            document.getElementById('token').value = e.target.dataset.token;
          });
        });
        document.getElementById('toggle-payment-with-credit-card').addEventListener('click', function (e) {
          document.getElementById('save-card--container').style.display = 'grid';
          document.getElementById('wepay--credit-card-container').style.display = 'flex';
          document.getElementById('token').value = null;
        });
        document.getElementById('pay-now').addEventListener('click', function () {
          _this3.payNowButton = document.getElementById('pay-now');
          _this3.payNowButton.disabled = true;
          _this3.payNowButton.querySelector('svg').classList.remove('hidden');
          _this3.payNowButton.querySelector('span').classList.add('hidden');
          var tokenInput = document.querySelector('input[name=token]');
          var storeCard = document.querySelector('input[name=token-billing-checkbox]:checked');
          if (storeCard) {
            document.getElementById("store_card").value = storeCard.value;
          }
          if (tokenInput.value) {
            return _this3.completePaymentUsingToken(tokenInput.value);
          }
          return _this3.completePaymentWithoutToken();
        });
      }
    }
  }]);
  return WePayCreditCard;
}();
new WePayCreditCard(action).handle();
/******/ })()
;