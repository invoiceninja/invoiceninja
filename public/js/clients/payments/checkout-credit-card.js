/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!***************************************************************!*\
  !*** ./resources/js/clients/payments/checkout-credit-card.js ***!
  \***************************************************************/
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
var CheckoutCreditCard = /*#__PURE__*/function () {
  function CheckoutCreditCard() {
    _classCallCheck(this, CheckoutCreditCard);
    this.tokens = [];
  }
  _createClass(CheckoutCreditCard, [{
    key: "mountFrames",
    value: function mountFrames() {
      console.log('Mount checkout frames..');
    }
  }, {
    key: "handlePaymentUsingToken",
    value: function handlePaymentUsingToken(e) {
      document.getElementById('checkout--container').classList.add('hidden');
      document.getElementById('pay-now-with-token--container').classList.remove('hidden');
      document.getElementById('save-card--container').style.display = 'none';
      document.querySelector('input[name=token]').value = e.target.dataset.token;
    }
  }, {
    key: "handlePaymentUsingCreditCard",
    value: function handlePaymentUsingCreditCard(e) {
      var _document$querySelect;
      document.getElementById('checkout--container').classList.remove('hidden');
      document.getElementById('pay-now-with-token--container').classList.add('hidden');
      document.getElementById('save-card--container').style.display = 'grid';
      document.querySelector('input[name=token]').value = '';
      var payButton = document.getElementById('pay-button');
      var publicKey = (_document$querySelect = document.querySelector('meta[name="public-key"]').content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
      var form = document.getElementById('payment-form');
      Frames.init(publicKey);
      Frames.addEventHandler(Frames.Events.CARD_VALIDATION_CHANGED, function (event) {
        payButton.disabled = !Frames.isCardValid();
      });
      Frames.addEventHandler(Frames.Events.CARD_TOKENIZATION_FAILED, function (event) {
        pay.button.disabled = false;
      });
      Frames.addEventHandler(Frames.Events.CARD_TOKENIZED, function (event) {
        payButton.disabled = true;
        document.querySelector('input[name="gateway_response"]').value = JSON.stringify(event);
        document.querySelector('input[name="store_card"]').value = document.querySelector('input[name=token-billing-checkbox]:checked').value;
        document.getElementById('server-response').submit();
      });
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        Frames.submitCard();
      });
    }
  }, {
    key: "completePaymentUsingToken",
    value: function completePaymentUsingToken(e) {
      var btn = document.getElementById('pay-now-with-token');
      btn.disabled = true;
      btn.querySelector('svg').classList.remove('hidden');
      btn.querySelector('span').classList.add('hidden');
      document.getElementById('server-response').submit();
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this = this;
      this.handlePaymentUsingCreditCard();
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', _this.handlePaymentUsingToken);
      });
      document.getElementById('toggle-payment-with-credit-card').addEventListener('click', this.handlePaymentUsingCreditCard);
      document.getElementById('pay-now-with-token').addEventListener('click', this.completePaymentUsingToken);
    }
  }]);
  return CheckoutCreditCard;
}();
new CheckoutCreditCard().handle();
/******/ })()
;