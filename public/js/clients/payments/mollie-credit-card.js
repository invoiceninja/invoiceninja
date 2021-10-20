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
/******/ 	return __webpack_require__(__webpack_require__.s = 22);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payments/mollie-credit-card.js":
/*!*************************************************************!*\
  !*** ./resources/js/clients/payments/mollie-credit-card.js ***!
  \*************************************************************/
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
 * @license https://www.elastic.co/licensing/elastic-license
 */
var _Mollie = /*#__PURE__*/function () {
  function _Mollie() {
    var _document$querySelect, _document$querySelect2;

    _classCallCheck(this, _Mollie);

    this.mollie = Mollie((_document$querySelect = document.querySelector('meta[name=mollie-profileId]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content, {
      testmode: (_document$querySelect2 = document.querySelector('meta[name=mollie-testmode]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content,
      locale: 'en_US'
    });
  }

  _createClass(_Mollie, [{
    key: "createCardHolderInput",
    value: function createCardHolderInput() {
      var cardHolder = this.mollie.createComponent('cardHolder');
      cardHolder.mount('#card-holder');
      var cardHolderError = document.getElementById('card-holder-error');
      cardHolder.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          cardHolderError.textContent = event.error;
        } else {
          cardHolderError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "createCardNumberInput",
    value: function createCardNumberInput() {
      var cardNumber = this.mollie.createComponent('cardNumber');
      cardNumber.mount('#card-number');
      var cardNumberError = document.getElementById('card-number-error');
      cardNumber.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          cardNumberError.textContent = event.error;
        } else {
          cardNumberError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "createExpiryDateInput",
    value: function createExpiryDateInput() {
      var expiryDate = this.mollie.createComponent('expiryDate');
      expiryDate.mount('#expiry-date');
      var expiryDateError = document.getElementById('expiry-date-error');
      expiryDate.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          expiryDateError.textContent = event.error;
        } else {
          expiryDateError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "createCvvInput",
    value: function createCvvInput() {
      var verificationCode = this.mollie.createComponent('verificationCode');
      verificationCode.mount('#cvv');
      var verificationCodeError = document.getElementById('cvv-error');
      verificationCode.addEventListener('change', function (event) {
        if (event.error && event.touched) {
          verificationCodeError.textContent = event.error;
        } else {
          verificationCodeError.textContent = '';
        }
      });
      return this;
    }
  }, {
    key: "handlePayNowButton",
    value: function handlePayNowButton() {
      document.getElementById('pay-now').disabled = true;

      if (document.querySelector('input[name=token]').value !== '') {
        document.querySelector('input[name=gateway_response]').value = '';
        return document.getElementById('server-response').submit();
      }

      this.mollie.createToken().then(function (result) {
        var token = result.token;
        var error = result.error;

        if (error) {
          document.getElementById('pay-now').disabled = false;
          var errorsContainer = document.getElementById('errors');
          errorsContainer.innerText = error.message;
          errorsContainer.hidden = false;
          return;
        }

        var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');

        if (tokenBillingCheckbox) {
          document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.value;
        }

        document.querySelector('input[name=gateway_response]').value = token;
        document.querySelector('input[name=token]').value = '';
        document.getElementById('server-response').submit();
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this = this;

      this.createCardHolderInput().createCardNumberInput().createExpiryDateInput().createCvvInput();
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (element) {
          document.getElementById('mollie--payment-container').classList.add('hidden');
          document.getElementById('save-card--container').style.display = 'none';
          document.querySelector('input[name=token]').value = element.target.dataset.token;
        });
      });
      document.getElementById('toggle-payment-with-credit-card').addEventListener('click', function (element) {
        document.getElementById('mollie--payment-container').classList.remove('hidden');
        document.getElementById('save-card--container').style.display = 'grid';
        document.querySelector('input[name=token]').value = '';
      });
      document.getElementById('pay-now').addEventListener('click', function () {
        return _this.handlePayNowButton();
      });
    }
  }]);

  return _Mollie;
}();

new _Mollie().handle();

/***/ }),

/***/ 22:
/*!*******************************************************************!*\
  !*** multi ./resources/js/clients/payments/mollie-credit-card.js ***!
  \*******************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/payments/mollie-credit-card.js */"./resources/js/clients/payments/mollie-credit-card.js");


/***/ })

/******/ });