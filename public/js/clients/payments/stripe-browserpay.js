/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!************************************************************!*\
  !*** ./resources/js/clients/payments/stripe-browserpay.js ***!
  \************************************************************/
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
var StripeBrowserPay = /*#__PURE__*/function () {
  function StripeBrowserPay() {
    var _document$querySelect;
    _classCallCheck(this, StripeBrowserPay);
    this.clientSecret = (_document$querySelect = document.querySelector('meta[name=stripe-pi-client-secret]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content;
  }
  _createClass(StripeBrowserPay, [{
    key: "init",
    value: function init() {
      var _document$querySelect3;
      var config = {};
      if (document.querySelector('meta[name=stripe-account-id]')) {
        var _document$querySelect2;
        config.apiVersion = '2020-08-27';
        config.stripeAccount = (_document$querySelect2 = document.querySelector('meta[name=stripe-account-id]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content;
      }
      this.stripe = Stripe((_document$querySelect3 = document.querySelector('meta[name=stripe-publishable-key]')) === null || _document$querySelect3 === void 0 ? void 0 : _document$querySelect3.content, config);
      this.elements = this.stripe.elements();
      return this;
    }
  }, {
    key: "createPaymentRequest",
    value: function createPaymentRequest() {
      this.paymentRequest = this.stripe.paymentRequest(JSON.parse(document.querySelector('meta[name=payment-request-data').content));
      return this;
    }
  }, {
    key: "createPaymentRequestButton",
    value: function createPaymentRequestButton() {
      this.paymentRequestButton = this.elements.create('paymentRequestButton', {
        paymentRequest: this.paymentRequest
      });
    }
  }, {
    key: "handlePaymentRequestEvents",
    value: function handlePaymentRequestEvents(stripe, clientSecret) {
      document.querySelector('#errors').hidden = true;
      this.paymentRequest.on('paymentmethod', function (ev) {
        stripe.confirmCardPayment(clientSecret, {
          payment_method: ev.paymentMethod.id
        }, {
          handleActions: false
        }).then(function (confirmResult) {
          if (confirmResult.error) {
            document.querySelector('#errors').innerText = confirmResult.error.message;
            document.querySelector('#errors').hidden = false;
            ev.complete('fail');
          } else {
            ev.complete('success');
            if (confirmResult.paymentIntent.status === 'requires_action') {
              stripe.confirmCardPayment(clientSecret).then(function (result) {
                if (result.error) {
                  ev.complete('fail');
                  document.querySelector('#errors').innerText = result.error.message;
                  document.querySelector('#errors').hidden = false;
                } else {
                  document.querySelector('input[name="gateway_response"]').value = JSON.stringify(result.paymentIntent);
                  document.getElementById('server-response').submit();
                }
              });
            } else {
              document.querySelector('input[name="gateway_response"]').value = JSON.stringify(confirmResult.paymentIntent);
              document.getElementById('server-response').submit();
            }
          }
        });
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this = this;
      this.init().createPaymentRequest().createPaymentRequestButton();
      this.paymentRequest.canMakePayment().then(function (result) {
        var _document$querySelect4;
        if (result) {
          return _this.paymentRequestButton.mount('#payment-request-button');
        }
        document.querySelector('#errors').innerHTML = JSON.parse((_document$querySelect4 = document.querySelector('meta[name=no-available-methods]')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.content);
        document.querySelector('#errors').hidden = false;
      });
      this.handlePaymentRequestEvents(this.stripe, this.clientSecret);
    }
  }]);
  return StripeBrowserPay;
}();
new StripeBrowserPay().handle();
/******/ })()
;