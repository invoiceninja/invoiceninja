/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*************************************************************************!*\
  !*** ./resources/js/clients/payment_methods/authorize-checkout-card.js ***!
  \*************************************************************************/
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
var CheckoutCreditCardAuthorization = /*#__PURE__*/function () {
  function CheckoutCreditCardAuthorization() {
    _classCallCheck(this, CheckoutCreditCardAuthorization);
    this.button = document.querySelector('#pay-button');
  }
  _createClass(CheckoutCreditCardAuthorization, [{
    key: "init",
    value: function init() {
      this.frames = Frames.init(document.querySelector('meta[name=public-key]').content);
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this = this;
      this.init();
      Frames.addEventHandler(Frames.Events.CARD_VALIDATION_CHANGED, function (event) {
        _this.button.disabled = !Frames.isCardValid();
      });
      Frames.addEventHandler(Frames.Events.CARD_TOKENIZED, function (event) {
        document.querySelector('input[name="gateway_response"]').value = JSON.stringify(event);
        document.getElementById('server_response').submit();
      });
      document.querySelector('#authorization-form').addEventListener('submit', function (event) {
        _this.button.disabled = true;
        event.preventDefault();
        Frames.submitCard();
      });
    }
  }]);
  return CheckoutCreditCardAuthorization;
}();
new CheckoutCreditCardAuthorization().handle();
/******/ })()
;