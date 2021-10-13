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
/******/ 	return __webpack_require__(__webpack_require__.s = 28);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./resources/js/clients/payments/stripe-sepa.js":
/*!******************************************************!*\
  !*** ./resources/js/clients/payments/stripe-sepa.js ***!
  \******************************************************/
/*! no static exports found */
/***/ (function(module, exports) {

var _document$querySelect, _document$querySelect2, _document$querySelect3, _document$querySelect4;

function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

function _defineProperty(obj, key, value) { if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var ProcessSEPA = /*#__PURE__*/function () {
  function ProcessSEPA(key, _stripeConnect) {
    var _this = this;

    _classCallCheck(this, ProcessSEPA);

    _defineProperty(this, "setupStripe", function () {
      _this.stripe = Stripe(_this.key);
      if (_this.stripeConnect) _this.stripe.stripeAccount = stripeConnect;

      var elements = _this.stripe.elements();

      var style = {
        base: {
          color: "#32325d",
          fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
          fontSmoothing: "antialiased",
          fontSize: "16px",
          "::placeholder": {
            color: "#aab7c4"
          },
          ":-webkit-autofill": {
            color: "#32325d"
          }
        },
        invalid: {
          color: "#fa755a",
          iconColor: "#fa755a",
          ":-webkit-autofill": {
            color: "#fa755a"
          }
        }
      };
      var options = {
        style: style,
        supportedCountries: ["SEPA"],
        // If you know the country of the customer, you can optionally pass it to
        // the Element as placeholderCountry. The example IBAN that is being used
        // as placeholder reflects the IBAN format of that country.
        placeholderCountry: document.querySelector('meta[name="country"]').content
      };
      _this.iban = elements.create("iban", options);

      _this.iban.mount("#sepa-iban");

      return _this;
    });

    _defineProperty(this, "handle", function () {
      var errors = document.getElementById('errors');
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (element) {
          document.getElementById('stripe--payment-container').classList.add('hidden');
          document.getElementById('save-card--container').style.display = 'none';
          document.querySelector('input[name=token]').value = element.target.dataset.token;
        });
      });
      document.getElementById('toggle-payment-with-new-bank-account').addEventListener('click', function (element) {
        document.getElementById('stripe--payment-container').classList.remove('hidden');
        document.getElementById('save-card--container').style.display = 'grid';
        document.querySelector('input[name=token]').value = "";
      });
      document.getElementById('pay-now').addEventListener('click', function (e) {
        if (document.querySelector('input[name=token]').value.length !== 0) {
          document.querySelector('#errors').hidden = true;
          document.getElementById('pay-now').disabled = true;
          document.querySelector('#pay-now > svg').classList.remove('hidden');
          document.querySelector('#pay-now > span').classList.add('hidden');

          _this.stripe.confirmSepaDebitSetup(document.querySelector('meta[name=si-client-secret').content, {
            payment_method: document.querySelector('input[name=token]').value
          }).then(function (result) {
            if (result.error) {
              console.error(error);
              return;
            }

            document.querySelector('input[name="gateway_response"]').value = JSON.stringify(result.setupIntent);
            return document.querySelector('#server-response').submit();
          })["catch"](function (error) {
            errors.textContent = error;
            errors.hidden = false;
          });

          return;
        }

        if (document.getElementById('sepa-name').value === "") {
          document.getElementById('sepa-name').focus();
          errors.textContent = "Name required.";
          errors.hidden = false;
          return;
        }

        if (document.getElementById('sepa-email-address').value === "") {
          document.getElementById('sepa-email-address').focus();
          errors.textContent = "Email required.";
          errors.hidden = false;
          return;
        }

        if (!document.getElementById('sepa-mandate-acceptance').checked) {
          errors.textContent = "Accept Terms";
          errors.hidden = false;
          console.log("Terms");
          return;
        }

        document.getElementById('pay-now').disabled = true;
        document.querySelector('#pay-now > svg').classList.remove('hidden');
        document.querySelector('#pay-now > span').classList.add('hidden');

        _this.stripe.confirmSepaDebitPayment(document.querySelector('meta[name=pi-client-secret').content, {
          payment_method: {
            sepa_debit: _this.iban,
            billing_details: {
              name: document.getElementById("sepa-name").value,
              email: document.getElementById("sepa-email-address").value
            }
          }
        }).then(function (result) {
          if (result.error) {
            return _this.handleFailure(result.error.message);
          }

          return _this.handleSuccess(result);
        });
      });
    });

    this.key = key;
    this.errors = document.getElementById('errors');
    this.stripeConnect = _stripeConnect;
  }

  _createClass(ProcessSEPA, [{
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
      document.getElementById('pay-now').disabled = false;
      document.querySelector('#pay-now > svg').classList.add('hidden');
      document.querySelector('#pay-now > span').classList.remove('hidden');
    }
  }]);

  return ProcessSEPA;
}();

var publishableKey = (_document$querySelect = (_document$querySelect2 = document.querySelector('meta[name="stripe-publishable-key"]')) === null || _document$querySelect2 === void 0 ? void 0 : _document$querySelect2.content) !== null && _document$querySelect !== void 0 ? _document$querySelect : '';
var stripeConnect = (_document$querySelect3 = (_document$querySelect4 = document.querySelector('meta[name="stripe-account-id"]')) === null || _document$querySelect4 === void 0 ? void 0 : _document$querySelect4.content) !== null && _document$querySelect3 !== void 0 ? _document$querySelect3 : '';
new ProcessSEPA(publishableKey, stripeConnect).setupStripe().handle();

/***/ }),

/***/ 28:
/*!************************************************************!*\
  !*** multi ./resources/js/clients/payments/stripe-sepa.js ***!
  \************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/payments/stripe-sepa.js */"./resources/js/clients/payments/stripe-sepa.js");


/***/ })

/******/ });