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
/******/ 	return __webpack_require__(__webpack_require__.s = 6);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payments/stripe-sofort.js":
/*!********************************************************!*\
  !*** ./resources/js/clients/payments/stripe-sofort.js ***!
  \********************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var ProcessSOFORT = function ProcessSOFORT(key) {
  var _this = this;

  _classCallCheck(this, ProcessSOFORT);

  _defineProperty(this, "setupStripe", function () {
    _this.stripe = Stripe(_this.key);
    return _this;
  });

  _defineProperty(this, "handle", function () {
    var data = {
      type: 'sofort',
      amount: document.querySelector('meta[name="amount"]').content,
      currency: 'eur',
      redirect: {
        return_url: document.querySelector('meta[name="return-url"]').content
      },
      sofort: {
        country: document.querySelector('meta[name="country"').content
      }
    };
    document.getElementById('pay-now').addEventListener('click', function (e) {
      document.getElementById('pay-now-button').disabled = true;
      document.querySelector('#pay-now-button > svg').classList.remove('hidden');
      document.querySelector('#pay-now-button > span').classList.add('hidden');

      _this.stripe.createSource(data).then(function (result) {
        if (result.hasOwnProperty('source')) {
          return window.location = result.source.redirect.url;
        }

        document.getElementById('pay-now-button').disabled = false;
        document.querySelector('#pay-now-button > svg').classList.add('hidden');
        document.querySelector('#pay-now-button > span').classList.remove('hidden');
        this.errors.textContent = '';
        this.errors.textContent = result.error.message;
        this.errors.hidden = false;
        document.getElementById('pay-now').disabled = false;
      });
    });
  });

  this.key = key;
  this.errors = document.getElementById('errors');
};

var publishableKey = document.querySelector('meta[name="stripe-publishable-key"]').content;
new ProcessSOFORT(publishableKey).setupStripe().handle();

/***/ }),

/***/ 6:
/*!**************************************************************!*\
  !*** multi ./resources/js/clients/payments/stripe-sofort.js ***!
  \**************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/resources/js/clients/payments/stripe-sofort.js */"./resources/js/clients/payments/stripe-sofort.js");


/***/ })

/******/ });