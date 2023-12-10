/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*****************************************************!*\
  !*** ./resources/js/clients/payments/stripe-ach.js ***!
  \*****************************************************/
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
var AuthorizeACH = /*#__PURE__*/function () {
  function AuthorizeACH() {
    var _this = this,
      _document$querySelect;
    _classCallCheck(this, AuthorizeACH);
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
    _defineProperty(this, "getFormData", function () {
      return {
        country: document.getElementById('country').value,
        currency: document.getElementById('currency').value,
        routing_number: document.getElementById('routing-number').value,
        account_number: document.getElementById('account-number').value,
        account_holder_name: document.getElementById('account-holder-name').value,
        account_holder_type: document.querySelector('input[name="account-holder-type"]:checked').value
      };
    });
    _defineProperty(this, "handleError", function (message) {
      document.getElementById('save-button').disabled = false;
      document.querySelector('#save-button > svg').classList.add('hidden');
      document.querySelector('#save-button > span').classList.remove('hidden');
      _this.errors.textContent = '';
      _this.errors.textContent = message;
      _this.errors.hidden = false;
    });
    _defineProperty(this, "handleSuccess", function (response) {
      document.getElementById('gateway_response').value = JSON.stringify(response);
      document.getElementById('server_response').submit();
    });
    _defineProperty(this, "handleSubmit", function (e) {
      if (!document.getElementById('accept-terms').checked) {
        errors.textContent = "You must accept the mandate terms prior to making payment.";
        errors.hidden = false;
        return;
      }
      document.getElementById('save-button').disabled = true;
      document.querySelector('#save-button > svg').classList.remove('hidden');
      document.querySelector('#save-button > span').classList.add('hidden');
      e.preventDefault();
      _this.errors.textContent = '';
      _this.errors.hidden = true;
      _this.stripe.createToken('bank_account', _this.getFormData()).then(function (result) {
        if (result.hasOwnProperty('error')) {
          return _this.handleError(result.error.message);
        }
        return _this.handleSuccess(result);
      });
    });
    this.errors = document.getElementById('errors');
    this.key = document.querySelector('meta[name="stripe-publishable-key"]').content;
    this.stripe_connect = (_document$querySelect = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content;
  }
  _createClass(AuthorizeACH, [{
    key: "handle",
    value: function handle() {
      var _this2 = this;
      document.getElementById('save-button').addEventListener('click', function (e) {
        return _this2.handleSubmit(e);
      });
    }
  }]);
  return AuthorizeACH;
}();
new AuthorizeACH().setupStripe().handle();
/******/ })()
;