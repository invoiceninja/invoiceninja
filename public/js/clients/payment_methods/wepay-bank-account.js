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
/******/ 	return __webpack_require__(__webpack_require__.s = 20);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payment_methods/wepay-bank-account.js":
/*!********************************************************************!*\
  !*** ./resources/js/clients/payment_methods/wepay-bank-account.js ***!
  \********************************************************************/
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
var WePayBank = /*#__PURE__*/function () {
  function WePayBank() {
    _classCallCheck(this, WePayBank);
  }

  _createClass(WePayBank, [{
    key: "initializeWePay",
    value: function initializeWePay() {
      var _document$querySelect;

      var environment = (_document$querySelect = document.querySelector('meta[name="wepay-environment"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content;
      WePay.set_endpoint(environment === 'staging' ? 'stage' : 'production');
      return this;
    }
  }, {
    key: "showBankPopup",
    value: function showBankPopup() {
      var _document$querySelect2, _document$querySelect3;

      WePay.bank_account.create({
        client_id: (_document$querySelect2 = document.querySelector('meta[name=wepay-client-id]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content,
        email: (_document$querySelect3 = document.querySelector('meta[name=contact-email]')) === null || _document$querySelect3 === void 0 ? void 0 : _document$querySelect3.content
      }, function (data) {
        if (data.error) {
          errors.textContent = '';
          errors.textContent = data.error_description;
          errors.hidden = false;
        } else {
          document.querySelector('input[name="bank_account_id"]').value = data.bank_account_id;
          document.getElementById('server_response').submit();
        }
      }, function (data) {
        if (data.error) {
          errors.textContent = '';
          errors.textContent = data.error_description;
          errors.hidden = false;
        }
      });
    }
  }, {
    key: "handle",
    value: function handle() {
      this.initializeWePay().showBankPopup();
    }
  }]);

  return WePayBank;
}();

document.addEventListener('DOMContentLoaded', function () {
  new WePayBank().handle();
});

/***/ }),

/***/ 20:
/*!**************************************************************************!*\
  !*** multi ./resources/js/clients/payment_methods/wepay-bank-account.js ***!
  \**************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/payment_methods/wepay-bank-account.js */"./resources/js/clients/payment_methods/wepay-bank-account.js");


/***/ })

/******/ });