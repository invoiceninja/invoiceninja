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
/******/ 	return __webpack_require__(__webpack_require__.s = 12);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payments/process.js":
/*!**************************************************!*\
  !*** ./resources/js/clients/payments/process.js ***!
  \**************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var ProcessStripePayment = /*#__PURE__*/function () {
  function ProcessStripePayment(key, usingToken) {
    _classCallCheck(this, ProcessStripePayment);

    this.key = key;
    this.usingToken = usingToken;
  }

  _createClass(ProcessStripePayment, [{
    key: "setupStripe",
    value: function setupStripe() {
      this.stripe = Stripe(this.key);
      this.elements = this.stripe.elements();
      return this;
    }
  }, {
    key: "createElement",
    value: function createElement() {
      this.cardElement = this.elements.create("card");
      return this;
    }
  }, {
    key: "mountCardElement",
    value: function mountCardElement() {
      this.cardElement.mount("#card-element");
      return this;
    }
  }, {
    key: "completePaymentUsingToken",
    value: function completePaymentUsingToken() {
      var _this = this;

      var payNowButton = document.getElementById('pay-now-with-token');
      document.getElementById('process-overlay').classList.remove('hidden');
      return;
      this.stripe.handleCardPayment(payNowButton.dataset.secret, {
        payment_method: payNowButton.dataset.token
      }).then(function (result) {
        if (result.error) {
          return _this.handleFailure(result.error.message);
        }

        return _this.handleSuccess(result);
      });
    }
  }, {
    key: "completePaymentWithoutToken",
    value: function completePaymentWithoutToken() {
      var _this2 = this;

      var payNowButton = document.getElementById("pay-now");
      var cardHolderName = document.getElementById("cardholder-name");
      document.getElementById('processing-overlay').classList.remove('hidden');
      this.stripe.handleCardPayment(payNowButton.dataset.secret, this.cardElement, {
        payment_method_data: {
          billing_details: {
            name: cardHolderName.value
          }
        }
      }).then(function (result) {
        document.getElementById('processing-overlay').classList.add('hidden');

        if (result.error) {
          return _this2.handleFailure(result.error.message);
        }

        return _this2.handleSuccess(result);
      });
    }
  }, {
    key: "handleSuccess",
    value: function handleSuccess(result) {
      document.querySelector('input[name="gateway_response"]').value = JSON.stringify(result.paymentIntent);
      var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]');

      if (tokenBillingCheckbox) {
        document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.checked;
      }

      document.getElementById("server-response").submit();
    }
  }, {
    key: "handleFailure",
    value: function handleFailure(message) {
      var errors = document.getElementById("errors");
      errors.textContent = "";
      errors.textContent = message;
      errors.hidden = false;
      this.payNowButton.querySelector('svg').classList.add('hidden');
      this.payNowButton.disabled = false;
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this3 = this;

      this.setupStripe();

      if (this.usingToken) {
        document.getElementById("pay-now-with-token").addEventListener("click", function () {
          return _this3.completePaymentUsingToken();
        });
      }

      if (!this.usingToken) {
        this.createElement().mountCardElement();
        document.getElementById("pay-now").addEventListener("click", function () {
          return _this3.completePaymentWithoutToken();
        });
      }
    }
  }]);

  return ProcessStripePayment;
}();

var publishableKey = document.querySelector('meta[name="stripe-publishable-key"]').content;
var usingToken = document.querySelector('meta[name="using-token"]').content;
new ProcessStripePayment(publishableKey, usingToken).handle();

/***/ }),

/***/ 12:
/*!********************************************************!*\
  !*** multi ./resources/js/clients/payments/process.js ***!
  \********************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /home/benjamin/Code/invoiceninja/resources/js/clients/payments/process.js */"./resources/js/clients/payments/process.js");


/***/ })

/******/ });