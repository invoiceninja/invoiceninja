/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!****************************************************************!*\
  !*** ./resources/js/clients/payments/braintree-credit-card.js ***!
  \****************************************************************/
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var BraintreeCreditCard = /*#__PURE__*/function () {
  function BraintreeCreditCard() {
    _classCallCheck(this, BraintreeCreditCard);
  }

  _createClass(BraintreeCreditCard, [{
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
    key: "mountBraintreePaymentWidget",
    value: function mountBraintreePaymentWidget() {
      window.braintree.dropin.create({
        authorization: document.querySelector('meta[name=client-token]').content,
        container: '#dropin-container'
      }, this.handleCallback);
    }
  }, {
    key: "handleCallback",
    value: function handleCallback(error, dropinInstance) {
      if (error) {
        console.error(error);
        return;
      }

      var payNow = document.getElementById('pay-now');
      payNow.addEventListener('click', function () {
        dropinInstance.requestPaymentMethod(function (error, payload) {
          if (error) {
            return console.error(error);
          }

          payNow.disabled = true;
          payNow.querySelector('svg').classList.remove('hidden');
          payNow.querySelector('span').classList.add('hidden');
          document.querySelector('input[name=gateway_response]').value = JSON.stringify(payload);
          var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');

          if (tokenBillingCheckbox) {
            document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.value;
          }

          document.getElementById('server-response').submit();
        });
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      this.initBraintreeDataCollector();
      this.mountBraintreePaymentWidget();
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (element) {
          document.getElementById('dropin-container').classList.add('hidden');
          document.getElementById('save-card--container').style.display = 'none';
          document.querySelector('input[name=token]').value = element.target.dataset.token;
          document.getElementById('pay-now-with-token').classList.remove('hidden');
          document.getElementById('pay-now').classList.add('hidden');
        });
      });
      document.getElementById('toggle-payment-with-credit-card').addEventListener('click', function (element) {
        document.getElementById('dropin-container').classList.remove('hidden');
        document.getElementById('save-card--container').style.display = 'grid';
        document.querySelector('input[name=token]').value = "";
        document.getElementById('pay-now-with-token').classList.add('hidden');
        document.getElementById('pay-now').classList.remove('hidden');
      });
      var payNowWithToken = document.getElementById('pay-now-with-token');
      payNowWithToken.addEventListener('click', function (element) {
        payNowWithToken.disabled = true;
        payNowWithToken.querySelector('svg').classList.remove('hidden');
        payNowWithToken.querySelector('span').classList.add('hidden');
        document.getElementById('server-response').submit();
      });
    }
  }]);

  return BraintreeCreditCard;
}();

new BraintreeCreditCard().handle();
/******/ })()
;