/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!******************************************************!*\
  !*** ./resources/js/clients/payments/stripe-bacs.js ***!
  \******************************************************/
var _document$querySelect, _document$querySelect2, _document$querySelect3, _document$querySelect4, _document$querySelect5, _document$querySelect6;
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
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
var ProcessBACS = /*#__PURE__*/_createClass(function ProcessBACS(key, stripeConnect) {
  var _this = this;
  _classCallCheck(this, ProcessBACS);
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
  _defineProperty(this, "payment_data", void 0);
  _defineProperty(this, "handle", function () {
    if (_this.onlyAuthorization) {
      document.getElementById('authorize-bacs').addEventListener('click', function (e) {
        document.getElementById('authorize-bacs').disabled = true;
        document.querySelector('#authorize-bacs > svg').classList.remove('hidden');
        document.querySelector('#authorize-bacs > span').classList.add('hidden');
        location.href = document.querySelector('meta[name=stripe-redirect-url]').content;
      });
    } else {
      _this.payNowButton = document.getElementById('pay-now');
      document.getElementById('pay-now').addEventListener('click', function (e) {
        _this.payNowButton.disabled = true;
        _this.payNowButton.querySelector('svg').classList.remove('hidden');
        _this.payNowButton.querySelector('span').classList.add('hidden');
        document.getElementById('server-response').submit();
      });
      _this.payment_data = Array.from(document.getElementsByClassName('toggle-payment-with-token'));
      if (_this.payment_data.length > 0) {
        _this.payment_data.forEach(function (element) {
          return element.addEventListener('click', function (element) {
            document.querySelector('input[name=token]').value = element.target.dataset.token;
          });
        });
      } else {
        _this.errors.textContent = document.querySelector('meta[name=translation-payment-method-required]').content;
        _this.errors.hidden = false;
        _this.payNowButton.disabled = true;
        _this.payNowButton.querySelector('span').classList.remove('hidden');
        _this.payNowButton.querySelector('svg').classList.add('hidden');
      }
    }
  });
  this.key = key;
  this.errors = document.getElementById('errors');
  this.stripeConnect = stripeConnect;
  this.onlyAuthorization = onlyAuthorization;
});
var publishableKey = (_document$querySelect = (_document$querySelect2 = document.querySelector('meta[name="stripe-publishable-key"]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
var stripeConnect = (_document$querySelect3 = (_document$querySelect4 = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.content) !== null && _document$querySelect3 !== void 0 ? _document$querySelect3 : '';
var onlyAuthorization = (_document$querySelect5 = (_document$querySelect6 = document.querySelector('meta[name="only-authorization"]')) === null || _document$querySelect6 === void 0 ? void 0 : _document$querySelect6.content) !== null && _document$querySelect5 !== void 0 ? _document$querySelect5 : '';
new ProcessBACS(publishableKey, stripeConnect).setupStripe().handle();
/******/ })()
;