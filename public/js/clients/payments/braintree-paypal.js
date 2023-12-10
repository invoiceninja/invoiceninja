/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!***********************************************************!*\
  !*** ./resources/js/clients/payments/braintree-paypal.js ***!
  \***********************************************************/
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
var BraintreePayPal = /*#__PURE__*/function () {
  function BraintreePayPal() {
    _classCallCheck(this, BraintreePayPal);
  }
  _createClass(BraintreePayPal, [{
    key: "initBraintreeDataCollector",
    value: function initBraintreeDataCollector() {
      window.braintree.client.create({
        authorization: document.querySelector('meta[name=client-token]').content
      }, function (err, clientInstance) {
        window.braintree.dataCollector.create({
          client: clientInstance,
          paypal: true
        }, function (err, dataCollectorInstance) {
          if (err) {
            return;
          }
          document.querySelector('input[name=client-data]').value = dataCollectorInstance.deviceData;
        });
      });
    }
  }, {
    key: "handlePaymentWithToken",
    value: function handlePaymentWithToken() {
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (element) {
          document.getElementById('paypal-button').classList.add('hidden');
          document.getElementById('save-card--container').style.display = 'none';
          document.querySelector('input[name=token]').value = element.target.dataset.token;
          document.getElementById('pay-now-with-token').classList.remove('hidden');
          document.getElementById('pay-now').classList.add('hidden');
        });
      });
      var payNowWithToken = document.getElementById('pay-now-with-token');
      payNowWithToken.addEventListener('click', function (element) {
        payNowWithToken.disabled = true;
        payNowWithToken.querySelector('svg').classList.remove('hidden');
        payNowWithToken.querySelector('span').classList.add('hidden');
        document.getElementById('server-response').submit();
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      this.initBraintreeDataCollector();
      this.handlePaymentWithToken();
      braintree.client.create({
        authorization: document.querySelector('meta[name=client-token]').content
      }).then(function (clientInstance) {
        return braintree.paypalCheckout.create({
          client: clientInstance
        });
      }).then(function (paypalCheckoutInstance) {
        return paypalCheckoutInstance.loadPayPalSDK({
          vault: true
        }).then(function (paypalCheckoutInstance) {
          return paypal.Buttons({
            fundingSource: paypal.FUNDING.PAYPAL,
            createBillingAgreement: function createBillingAgreement() {
              return paypalCheckoutInstance.createPayment(BraintreePayPal.getPaymentDetails());
            },
            onApprove: function onApprove(data, actions) {
              return paypalCheckoutInstance.tokenizePayment(data).then(function (payload) {
                var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');
                if (tokenBillingCheckbox) {
                  document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.value;
                }
                document.querySelector('input[name=gateway_response]').value = JSON.stringify(payload);
                document.getElementById('server-response').submit();
              });
            },
            onCancel: function onCancel(data) {
              // ..
            },
            onError: function onError(err) {
              console.log(err.message);
              BraintreePayPal.handleErrorMessage(err.message);
            }
          }).render('#paypal-button');
        });
      })["catch"](function (err) {
        console.log(err.message);
        BraintreePayPal.handleErrorMessage(err.message);
      });
    }
  }], [{
    key: "getPaymentDetails",
    value: function getPaymentDetails() {
      return {
        flow: 'vault'
      };
    }
  }, {
    key: "handleErrorMessage",
    value: function handleErrorMessage(message) {
      var errorsContainer = document.getElementById('errors');
      errorsContainer.innerText = message;
      errorsContainer.hidden = false;
    }
  }]);
  return BraintreePayPal;
}();
new BraintreePayPal().handle();
/******/ })()
;