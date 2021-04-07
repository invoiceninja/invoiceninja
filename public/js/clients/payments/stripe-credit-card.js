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
/******/ 	return __webpack_require__(__webpack_require__.s = 11);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payments/stripe-credit-card.js":
/*!*************************************************************!*\
  !*** ./resources/js/clients/payments/stripe-credit-card.js ***!
  \*************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

var _document$querySelect, _document$querySelect2, _document$querySelect3;

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var StripeCreditCard = /*#__PURE__*/function () {
  function StripeCreditCard(key, secret, onlyAuthorization) {
    _classCallCheck(this, StripeCreditCard);

    this.key = key;
    this.secret = secret;
    this.onlyAuthorization = onlyAuthorization;
  }

  _createClass(StripeCreditCard, [{
    key: "setupStripe",
    value: function setupStripe() {
      this.stripe = Stripe(this.key);
      this.elements = this.stripe.elements();
      return this;
    }
  }, {
    key: "createElement",
    value: function createElement() {
      this.cardElement = this.elements.create('card');
      return this;
    }
  }, {
    key: "mountCardElement",
    value: function mountCardElement() {
      this.cardElement.mount('#card-element');
      return this;
    }
  }, {
    key: "completePaymentUsingToken",
    value: function completePaymentUsingToken() {
      var _this = this;

      var token = document.querySelector('input[name=token]').value;
      var payNowButton = document.getElementById('pay-now');
      this.payNowButton = payNowButton;
      this.payNowButton.disabled = true;
      this.payNowButton.querySelector('svg').classList.remove('hidden');
      this.payNowButton.querySelector('span').classList.add('hidden');
      this.stripe.handleCardPayment(this.secret, {
        payment_method: token
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

      var payNowButton = document.getElementById('pay-now');
      this.payNowButton = payNowButton;
      this.payNowButton.disabled = true;
      this.payNowButton.querySelector('svg').classList.remove('hidden');
      this.payNowButton.querySelector('span').classList.add('hidden');
      var cardHolderName = document.getElementById('cardholder-name');
      this.stripe.handleCardPayment(this.secret, this.cardElement, {
        payment_method_data: {
          billing_details: {
            name: cardHolderName.value
          }
        }
      }).then(function (result) {
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
      var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');

      if (tokenBillingCheckbox) {
        document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.value;
      }

      document.getElementById('server-response').submit();
    }
  }, {
    key: "handleFailure",
    value: function handleFailure(message) {
      var errors = document.getElementById('errors');
      errors.textContent = '';
      errors.textContent = message;
      errors.hidden = false;
      this.payNowButton.disabled = false;
      this.payNowButton.querySelector('svg').classList.add('hidden');
      this.payNowButton.querySelector('span').classList.remove('hidden');
    }
  }, {
    key: "handleAuthorization",
    value: function handleAuthorization() {
      var _this3 = this;

      var cardHolderName = document.getElementById('cardholder-name');
      var payNowButton = document.getElementById('authorize-card');
      this.payNowButton = payNowButton;
      this.payNowButton.disabled = true;
      this.payNowButton.querySelector('svg').classList.remove('hidden');
      this.payNowButton.querySelector('span').classList.add('hidden');
      this.stripe.handleCardSetup(this.secret, this.cardElement, {
        payment_method_data: {
          billing_details: {
            name: cardHolderName.value
          }
        }
      }).then(function (result) {
        if (result.error) {
          return _this3.handleFailure(result);
        }

        return _this3.handleSuccessfulAuthorization(result);
      });
    }
  }, {
    key: "handleSuccessfulAuthorization",
    value: function handleSuccessfulAuthorization(result) {
      document.getElementById('gateway_response').value = JSON.stringify(result.setupIntent);
      document.getElementById('server_response').submit();
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this4 = this;

      this.setupStripe();

      if (this.onlyAuthorization) {
        this.createElement().mountCardElement();
        document.getElementById('authorize-card').addEventListener('click', function () {
          return _this4.handleAuthorization();
        });
      } else {
        Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
          return element.addEventListener('click', function (element) {
            document.getElementById('stripe--payment-container').classList.add('hidden');
            document.getElementById('save-card--container').style.display = 'none';
            document.querySelector('input[name=token]').value = element.target.dataset.token;
          });
        });
        document.getElementById('toggle-payment-with-credit-card').addEventListener('click', function (element) {
          document.getElementById('stripe--payment-container').classList.remove('hidden');
          document.getElementById('save-card--container').style.display = 'grid';
          document.querySelector('input[name=token]').value = "";
        });
        this.createElement().mountCardElement();
        document.getElementById('pay-now').addEventListener('click', function () {
          var tokenInput = document.querySelector('input[name=token]');

          if (tokenInput.value) {
            return _this4.completePaymentUsingToken();
          }

          return _this4.completePaymentWithoutToken();
        });
      }
    }
  }]);

  return StripeCreditCard;
}();

var publishableKey = (_document$querySelect = document.querySelector('meta[name="stripe-publishable-key"]').content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
var secret = (_document$querySelect2 = document.querySelector('meta[name="stripe-secret"]').content) !== null && _document$querySelect2 !== void 0 ? _document$querySelect2 : '';
var onlyAuthorization = (_document$querySelect3 = document.querySelector('meta[name="only-authorization"]').content) !== null && _document$querySelect3 !== void 0 ? _document$querySelect3 : '';
new StripeCreditCard(publishableKey, secret, onlyAuthorization).handle();

/***/ }),

/***/ 11:
/*!*******************************************************************!*\
  !*** multi ./resources/js/clients/payments/stripe-credit-card.js ***!
  \*******************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/payments/stripe-credit-card.js */"./resources/js/clients/payments/stripe-credit-card.js");


/***/ })

/******/ });