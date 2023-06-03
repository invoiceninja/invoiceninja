/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!********************************************************************!*\
  !*** ./resources/js/clients/payment_methods/wepay-bank-account.js ***!
  \********************************************************************/
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
var WePayBank = /*#__PURE__*/function () {
  function WePayBank() {
    _classCallCheck(this, WePayBank);
  }
  _createClass(WePayBank, [{
    key: "initializeWePay",
    value: function initializeWePay() {
      var _document$querySelect;
      var environment = (_document$querySelect = document.querySelector('meta[name="wepay-environment"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content;
      WePay.set_endpoint(environment === 'staging' ? 'stage' : 'production');
      return this;
    }
  }, {
    key: "showBankPopup",
    value: function showBankPopup() {
      var _document$querySelect2, _document$querySelect3;
      WePay.bank_account.create({
        client_id: (_document$querySelect2 = document.querySelector('meta[name=wepay-client-id]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content,
        email: (_document$querySelect3 = document.querySelector('meta[name=contact-email]')) === null || _document$querySelect3 === void 0 ? void 0 : _document$querySelect3.content,
        options: {
          avoidMicrodeposits: true
        }
      }, function (data) {
        if (data.error) {
          errors.textContent = '';
          errors.textContent = data.error_description;
          errors.hidden = false;
        } else {
          document.querySelector('input[name="bank_account_id"]').value = data.bank_account_id;
          document.getElementById('server_response').submit();
        }
      }, function (data) {
        if (data.error) {
          errors.textContent = '';
          errors.textContent = data.error_description;
          errors.hidden = false;
        }
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      this.initializeWePay().showBankPopup();
    }
  }]);
  return WePayBank;
}();
document.addEventListener('DOMContentLoaded', function () {
  new WePayBank().handle();
});
/******/ })()
;