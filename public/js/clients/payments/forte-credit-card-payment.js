/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!********************************************************************!*\
  !*** ./resources/js/clients/payments/forte-credit-card-payment.js ***!
  \********************************************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperty(obj, key, value) { key = _toPropertyKey(key); if (key in obj) { Object.defineProperty(obj, key, { value: value, enumerable: true, configurable: true, writable: true }); } else { obj[key] = value; } return obj; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var ForteAuthorizeCard = /*#__PURE__*/_createClass(function ForteAuthorizeCard(apiLoginId) {
  var _this = this;
  _classCallCheck(this, ForteAuthorizeCard);
  _defineProperty(this, "handleAuthorization", function () {
    var myCard = $('#my-card');
    var data = {
      api_login_id: _this.apiLoginId,
      card_number: myCard.CardJs('cardNumber').replace(/[^\d]/g, ''),
      expire_year: myCard.CardJs('expiryYear').replace(/[^\d]/g, ''),
      expire_month: myCard.CardJs('expiryMonth').replace(/[^\d]/g, ''),
      cvv: document.getElementById('cvv').value.replace(/[^\d]/g, '')
    };
    var payNowButton = document.getElementById('pay-now');
    if (payNowButton) {
      document.getElementById('pay-now').disabled = true;
      document.querySelector('#pay-now > svg').classList.remove('hidden');
      document.querySelector('#pay-now > span').classList.add('hidden');
    }
    forte.createToken(data).success(_this.successResponseHandler).error(_this.failedResponseHandler);
    return false;
  });
  _defineProperty(this, "successResponseHandler", function (response) {
    document.getElementById('payment_token').value = response.onetime_token;
    document.getElementById('card_brand').value = response.card_type;
    document.getElementById('server_response').submit();
    return false;
  });
  _defineProperty(this, "failedResponseHandler", function (response) {
    var errors = '<div class="alert alert-failure mb-4"><ul><li>' + response.response_description + '</li></ul></div>';
    document.getElementById('forte_errors').innerHTML = errors;
    document.getElementById('pay-now').disabled = false;
    document.querySelector('#pay-now > svg').classList.add('hidden');
    document.querySelector('#pay-now > span').classList.remove('hidden');
    return false;
  });
  _defineProperty(this, "handle", function () {
    var payNowButton = document.getElementById('pay-now');
    if (payNowButton) {
      payNowButton.addEventListener('click', function (e) {
        _this.handleAuthorization();
      });
    }
    return _this;
  });
  this.apiLoginId = apiLoginId;
  this.cardHolderName = document.getElementById('cardholder_name');
});
var apiLoginId = document.querySelector('meta[name="forte-api-login-id"]').content;

/** @handle */
new ForteAuthorizeCard(apiLoginId).handle();
/******/ })()
;