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

/***/ "./resources/js/clients/invoices/payment.js":
/*!**************************************************!*\
  !*** ./resources/js/clients/invoices/payment.js ***!
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
var Payment = /*#__PURE__*/function () {
  function Payment(displayTerms, displaySignature) {
    _classCallCheck(this, Payment);

    this.shouldDisplayTerms = displayTerms;
    this.shouldDisplaySignature = displaySignature;
    this.termsAccepted = false;
  }

  _createClass(Payment, [{
    key: "handleMethodSelect",
    value: function handleMethodSelect(element) {
      var _this = this;

      document.getElementById("company_gateway_id").value = element.dataset.companyGatewayId;
      document.getElementById("payment_method_id").value = element.dataset.gatewayTypeId;

      if (this.shouldDisplaySignature && !this.shouldDisplayTerms) {
        this.displayTerms();
        document.getElementById("accept-terms-button").addEventListener("click", function () {
          _this.termsAccepted = true;

          _this.submitForm();
        });
      }

      if (!this.shouldDisplaySignature && this.shouldDisplayTerms) {
        this.displaySignature();
        document.getElementById("signature-next-step").addEventListener("click", function () {
          document.querySelector('input[name="signature"').value = _this.signaturePad.toDataURL();

          _this.submitForm();
        });
      }

      if (this.shouldDisplaySignature && this.shouldDisplayTerms) {
        this.displaySignature();
        document.getElementById("signature-next-step").addEventListener("click", function () {
          _this.displayTerms();

          document.getElementById("accept-terms-button").addEventListener("click", function () {
            document.querySelector('input[name="signature"').value = _this.signaturePad.toDataURL();
            _this.termsAccepted = true;

            _this.submitForm();
          });
        });
      }

      if (!this.shouldDisplaySignature && !this.shouldDisplayTerms) {
        this.submitForm();
      }
    }
  }, {
    key: "submitForm",
    value: function submitForm() {
      document.getElementById("payment-form").submit();
    }
  }, {
    key: "displayTerms",
    value: function displayTerms() {
      var displayTermsModal = document.getElementById("displayTermsModal");
      displayTermsModal.removeAttribute("style");
    }
  }, {
    key: "displaySignature",
    value: function displaySignature() {
      var displaySignatureModal = document.getElementById("displaySignatureModal");
      displaySignatureModal.removeAttribute("style");
      var signaturePad = new SignaturePad(document.getElementById("signature-pad"), {
        penColor: "rgb(0, 0, 0)"
      });
      this.signaturePad = signaturePad;
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this2 = this;

      document.querySelectorAll(".dropdown-gateway-button").forEach(function (element) {
        element.addEventListener("click", function () {
          return _this2.handleMethodSelect(element);
        });
      });
    }
  }]);

  return Payment;
}();

var signature = document.querySelector('meta[name="require-invoice-signature"]').content;
var terms = document.querySelector('meta[name="show-invoice-terms"]').content;
new Payment(Boolean(+signature), Boolean(+terms)).handle();

/***/ }),

/***/ 6:
/*!********************************************************!*\
  !*** multi ./resources/js/clients/invoices/payment.js ***!
  \********************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /home/benjamin/Code/invoiceninja/resources/js/clients/invoices/payment.js */"./resources/js/clients/invoices/payment.js");


/***/ })

/******/ });