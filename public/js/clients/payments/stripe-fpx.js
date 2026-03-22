/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*****************************************************!*\
  !*** ./resources/js/clients/payments/stripe-fpx.js ***!
  \*****************************************************/
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
var ProcessFPXPay = /*#__PURE__*/function () {
  function ProcessFPXPay(key, stripeConnect) {
    var _this = this;
    _classCallCheck(this, ProcessFPXPay);
    _defineProperty(this, "setupStripe", function () {
      if (_this.stripeConnect) {
        // this.stripe.stripeAccount = this.stripeConnect;

        _this.stripe = Stripe(_this.key, {
          stripeAccount: _this.stripeConnect
        });
      } else {
        _this.stripe = Stripe(_this.key);
      }
      var elements = _this.stripe.elements();
      var style = {
        base: {
          // Add your base input styles here. For example:
          padding: '10px 12px',
          color: '#32325d',
          fontSize: '16px'
        }
      };
      _this.fpx = elements.create('fpxBank', {
        style: style,
        accountHolderType: 'individual'
      });
      _this.fpx.mount("#fpx-bank-element");
      return _this;
    });
    _defineProperty(this, "handle", function () {
      document.getElementById('pay-now').addEventListener('click', function (e) {
        document.getElementById('pay-now').disabled = true;
        document.querySelector('#pay-now > svg').classList.remove('hidden');
        document.querySelector('#pay-now > span').classList.add('hidden');
        _this.stripe.confirmFpxPayment(document.querySelector('meta[name=pi-client-secret').content, {
          payment_method: {
            fpx: _this.fpx
          },
          return_url: document.querySelector('meta[name="return-url"]').content
        }).then(function (result) {
          if (result.error) {
            _this.handleFailure(result.error.message);
          }
        });
        ;
      });
    });
    this.key = key;
    this.errors = document.getElementById('errors');
    this.stripeConnect = stripeConnect;
  }
  _createClass(ProcessFPXPay, [{
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
  return ProcessFPXPay;
}();
var publishableKey = (_document$querySelect = (_document$querySelect2 = document.querySelector('meta[name="stripe-publishable-key"]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
var stripeConnect = (_document$querySelect3 = (_document$querySelect4 = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.content) !== null && _document$querySelect3 !== void 0 ? _document$querySelect3 : '';
new ProcessFPXPay(publishableKey, stripeConnect).setupStripe().handle();
/******/ })()
;