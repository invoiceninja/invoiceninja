/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 18);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payments/braintree-paypal.js":
/*!***********************************************************!*\
  !*** ./resources/js/clients/payments/braintree-paypal.js ***!
  \***********************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

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
            onCancel: function onCancel(data) {// ..
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

/***/ }),

/***/ 18:
/*!*****************************************************************!*\
  !*** multi ./resources/js/clients/payments/braintree-paypal.js ***!
  \*****************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/payments/braintree-paypal.js */"./resources/js/clients/payments/braintree-paypal.js");


/***/ })

/******/ });