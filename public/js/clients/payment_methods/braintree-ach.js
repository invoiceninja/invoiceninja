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
/******/ 	return __webpack_require__(__webpack_require__.s = 24);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payment_methods/braintree-ach.js":
/*!***************************************************************!*\
  !*** ./resources/js/clients/payment_methods/braintree-ach.js ***!
  \***************************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

var _document$querySelect;

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
window.braintree.client.create({
  authorization: (_document$querySelect = document.querySelector('meta[name="client-token"]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content
}).then(function (clientInstance) {
  return braintree.usBankAccount.create({
    client: clientInstance
  });
}).then(function (usBankAccountInstance) {
  var _document$getElementB;

  (_document$getElementB = document.getElementById('authorize-bank-account')) === null || _document$getElementB === void 0 ? void 0 : _document$getElementB.addEventListener('click', function (e) {
    e.target.parentElement.disabled = true;
    document.getElementById('errors').hidden = true;
    document.getElementById('errors').textContent = '';
    var bankDetails = {
      accountNumber: document.getElementById('account-number').value,
      routingNumber: document.getElementById('routing-number').value,
      accountType: document.querySelector('input[name="account-type"]:checked').value,
      ownershipType: document.querySelector('input[name="ownership-type"]:checked').value,
      billingAddress: {
        streetAddress: document.getElementById('billing-street-address').value,
        extendedAddress: document.getElementById('billing-extended-address').value,
        locality: document.getElementById('billing-locality').value,
        region: document.getElementById('billing-region').value,
        postalCode: document.getElementById('billing-postal-code').value
      }
    };

    if (bankDetails.ownershipType === 'personal') {
      var name = document.getElementById('account-holder-name').value.split(' ', 2);
      bankDetails.firstName = name[0];
      bankDetails.lastName = name[1];
    } else {
      bankDetails.businessName = document.getElementById('account-holder-name').value;
    }

    usBankAccountInstance.tokenize({
      bankDetails: bankDetails,
      mandateText: 'By clicking ["Checkout"], I authorize Braintree, a service of PayPal, on behalf of [your business name here] (i) to verify my bank account information using bank information and consumer reports and (ii) to debit my bank account.'
    }).then(function (payload) {
      document.querySelector('input[name=nonce]').value = payload.nonce;
      document.getElementById('server_response').submit();
    })["catch"](function (error) {
      e.target.parentElement.disabled = false;
      document.getElementById('errors').textContent = "".concat(error.details.originalError.message, " ").concat(error.details.originalError.details.originalError[0].message);
      document.getElementById('errors').hidden = false;
    });
  });
})["catch"](function (err) {
  document.getElementById('errors').textContent = "".concat(error.details.originalError.message, " ").concat(error.details.originalError.details.originalError[0].message);
  document.getElementById('errors').hidden = false;
});

/***/ }),

/***/ 24:
/*!*********************************************************************!*\
  !*** multi ./resources/js/clients/payment_methods/braintree-ach.js ***!
  \*********************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/payment_methods/braintree-ach.js */"./resources/js/clients/payment_methods/braintree-ach.js");


/***/ })

/******/ });