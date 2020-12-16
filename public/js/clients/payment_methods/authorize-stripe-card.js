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
/******/ 	return __webpack_require__(__webpack_require__.s = 1);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payment_methods/authorize-stripe-card.js":
/*!***********************************************************************!*\
  !*** ./resources/js/clients/payment_methods/authorize-stripe-card.js ***!
  \***********************************************************************/
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
var AuthorizeStripeCard = /*#__PURE__*/function () {
  function AuthorizeStripeCard(key) {
    _classCallCheck(this, AuthorizeStripeCard);

    this.key = key;
    this.cardHolderName = document.getElementById('cardholder-name');
    this.cardButton = document.getElementById('card-button');
    this.clientSecret = this.cardButton.dataset.secret;
  }

  _createClass(AuthorizeStripeCard, [{
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
    key: "handleStripe",
    value: function handleStripe(stripe, cardHolderName) {
      var _this = this;

      this.cardButton.disabled = true;
      this.cardButton.querySelector('span').classList.add('hidden');
      this.cardButton.querySelector('svg').classList.remove('hidden');
      stripe.handleCardSetup(this.clientSecret, this.cardElement, {
        payment_method_data: {
          billing_details: {
            name: cardHolderName.value
          }
        }
      }).then(function (result) {
        if (result.error) {
          return _this.handleFailure(result);
        }

        return _this.handleSuccess(result);
      });
    }
  }, {
    key: "handleFailure",
    value: function handleFailure(result) {
      this.cardButton.disabled = false;
      this.cardButton.querySelector('span').classList.remove('hidden');
      this.cardButton.querySelector('svg').classList.add('hidden');
      var errors = document.getElementById('errors');
      errors.textContent = '';
      errors.textContent = result.error.message;
      errors.hidden = false;
    }
  }, {
    key: "handleSuccess",
    value: function handleSuccess(result) {
      document.getElementById('gateway_response').value = JSON.stringify(result.setupIntent);
      document.getElementById('is_default').value = document.getElementById('proxy_is_default').checked;
      document.getElementById('server_response').submit();
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this2 = this;

      this.setupStripe().createElement().mountCardElement();
      this.cardButton.addEventListener('click', function () {
        _this2.handleStripe(_this2.stripe, _this2.cardHolderName);
      });
      return this;
    }
  }]);

  return AuthorizeStripeCard;
}();

var publishableKey = document.querySelector('meta[name="stripe-publishable-key"]').content;
/** @handle */

new AuthorizeStripeCard(publishableKey).handle();

/***/ }),

/***/ 1:
/*!*****************************************************************************!*\
  !*** multi ./resources/js/clients/payment_methods/authorize-stripe-card.js ***!
  \*****************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /home/benjamin/Development/invoiceninja/resources/js/clients/payment_methods/authorize-stripe-card.js */"./resources/js/clients/payment_methods/authorize-stripe-card.js");


/***/ })

/******/ });