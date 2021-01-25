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
/******/ 	return __webpack_require__(__webpack_require__.s = 8);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payments/checkout-credit-card.js":
/*!***************************************************************!*\
  !*** ./resources/js/clients/payments/checkout-credit-card.js ***!
  \***************************************************************/
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
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
      document.querySelector('input[name=token]').value = e.target.dataset.token;
    }
  }, {
    key: "handlePaymentUsingCreditCard",
    value: function handlePaymentUsingCreditCard(e) {
      var _document$querySelect;

      document.getElementById('checkout--container').classList.remove('hidden');
      document.getElementById('pay-now-with-token--container').classList.add('hidden');
      var payButton = document.getElementById('pay-button');
      var publicKey = (_document$querySelect = document.querySelector('meta[name="public-key"]').content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
      var form = document.getElementById('payment-form');
      Frames.init(publicKey);
      Frames.addEventHandler(Frames.Events.CARD_VALIDATION_CHANGED, function (event) {
        payButton.disabled = !Frames.isCardValid();
      });
      Frames.addEventHandler(Frames.Events.CARD_TOKENIZED, function (event) {
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

/***/ }),

/***/ 8:
/*!*********************************************************************!*\
  !*** multi ./resources/js/clients/payments/checkout-credit-card.js ***!
  \*********************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/payments/checkout-credit-card.js */"./resources/js/clients/payments/checkout-credit-card.js");


/***/ })

/******/ });