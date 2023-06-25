/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*************************************************************!*\
  !*** ./resources/js/clients/payments/mollie-credit-card.js ***!
  \*************************************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
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
var _Mollie = /*#__PURE__*/function () {
  function _Mollie() {
    var _document$querySelect, _document$querySelect2;
    _classCallCheck(this, _Mollie);
    this.mollie = Mollie((_document$querySelect = document.querySelector('meta[name=mollie-profileId]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content, {
      testmode: (_document$querySelect2 = document.querySelector('meta[name=mollie-testmode]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content,
      locale: 'en_US'
    });
  }
  _createClass(_Mollie, [{
    key: "createCardHolderInput",
    value: function createCardHolderInput() {
      var cardHolder = this.mollie.createComponent('cardHolder');
      cardHolder.mount('#card-holder');
      var cardHolderError = document.getElementById('card-holder-error');
      cardHolder.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          cardHolderError.textContent = event.error;
        } else {
          cardHolderError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "createCardNumberInput",
    value: function createCardNumberInput() {
      var cardNumber = this.mollie.createComponent('cardNumber');
      cardNumber.mount('#card-number');
      var cardNumberError = document.getElementById('card-number-error');
      cardNumber.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          cardNumberError.textContent = event.error;
        } else {
          cardNumberError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "createExpiryDateInput",
    value: function createExpiryDateInput() {
      var expiryDate = this.mollie.createComponent('expiryDate');
      expiryDate.mount('#expiry-date');
      var expiryDateError = document.getElementById('expiry-date-error');
      expiryDate.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          expiryDateError.textContent = event.error;
        } else {
          expiryDateError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "createCvvInput",
    value: function createCvvInput() {
      var verificationCode = this.mollie.createComponent('verificationCode');
      verificationCode.mount('#cvv');
      var verificationCodeError = document.getElementById('cvv-error');
      verificationCode.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          verificationCodeError.textContent = event.error;
        } else {
          verificationCodeError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "handlePayNowButton",
    value: function handlePayNowButton() {
      document.getElementById('pay-now').disabled = true;
      if (document.querySelector('input[name=token]').value !== '') {
        document.querySelector('input[name=gateway_response]').value = '';
        return document.getElementById('server-response').submit();
      }
      this.mollie.createToken().then(function (result) {
        var token = result.token;
        var error = result.error;
        if (error) {
          document.getElementById('pay-now').disabled = false;
          var errorsContainer = document.getElementById('errors');
          errorsContainer.innerText = error.message;
          errorsContainer.hidden = false;
          return;
        }
        var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');
        if (tokenBillingCheckbox) {
          document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.value;
        }
        document.querySelector('input[name=gateway_response]').value = token;
        document.querySelector('input[name=token]').value = '';
        document.getElementById('server-response').submit();
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this = this;
      this.createCardHolderInput().createCardNumberInput().createExpiryDateInput().createCvvInput();
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (element) {
          document.getElementById('mollie--payment-container').classList.add('hidden');
          document.getElementById('save-card--container').style.display = 'none';
          document.querySelector('input[name=token]').value = element.target.dataset.token;
        });
      });
      document.getElementById('toggle-payment-with-credit-card').addEventListener('click', function (element) {
        document.getElementById('mollie--payment-container').classList.remove('hidden');
        document.getElementById('save-card--container').style.display = 'grid';
        document.querySelector('input[name=token]').value = '';
      });
      document.getElementById('pay-now').addEventListener('click', function () {
        return _this.handlePayNowButton();
      });
    }
  }]);
  return _Mollie;
}();
new _Mollie().handle();
/******/ })()
;