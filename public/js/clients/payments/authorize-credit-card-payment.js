/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!************************************************************************!*\
  !*** ./resources/js/clients/payments/authorize-credit-card-payment.js ***!
  \************************************************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
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
 * @license https://www.elastic.co/licensing/elastic-license 
 */
var AuthorizeAuthorizeCard = /*#__PURE__*/function () {
  function AuthorizeAuthorizeCard(publicKey, loginId) {
    var _this = this;
    _classCallCheck(this, AuthorizeAuthorizeCard);
    _defineProperty(this, "handleAuthorization", function () {
      if (cvvRequired == "1" && document.getElementById("cvv").value.length < 3) {
        var $errors = $('#errors');
        $errors.show().html("<p>CVV is required</p>");
        document.getElementById('pay-now').disabled = false;
        document.querySelector('#pay-now > svg').classList.add('hidden');
        document.querySelector('#pay-now > span').classList.remove('hidden');
        return;
      }
      var myCard = $('#my-card');
      var authData = {};
      authData.clientKey = _this.publicKey;
      authData.apiLoginID = _this.loginId;
      var cardData = {};
      cardData.cardNumber = myCard.CardJs('cardNumber').replace(/[^\d]/g, '');
      cardData.month = myCard.CardJs('expiryMonth').replace(/[^\d]/g, '');
      cardData.year = myCard.CardJs('expiryYear').replace(/[^\d]/g, '');
      cardData.cardCode = document.getElementById("cvv").value.replace(/[^\d]/g, '');
      var secureData = {};
      secureData.authData = authData;
      secureData.cardData = cardData;
      // If using banking information instead of card information,
      // send the bankData object instead of the cardData object.
      //
      // secureData.bankData = bankData;
      var payNowButton = document.getElementById('pay-now');
      if (payNowButton) {
        document.getElementById('pay-now').disabled = true;
        document.querySelector('#pay-now > svg').classList.remove('hidden');
        document.querySelector('#pay-now > span').classList.add('hidden');
      }
      Accept.dispatchData(secureData, _this.responseHandler);
      return false;
    });
    _defineProperty(this, "responseHandler", function (response) {
      if (response.messages.resultCode === "Error") {
        var i = 0;
        var $errors = $('#errors'); // get the reference of the div
        $errors.show().html("<p>" + response.messages.message[i].code + ": " + response.messages.message[i].text + "</p>");
        document.getElementById('pay-now').disabled = false;
        document.querySelector('#pay-now > svg').classList.add('hidden');
        document.querySelector('#pay-now > span').classList.remove('hidden');
      } else if (response.messages.resultCode === "Ok") {
        document.getElementById("dataDescriptor").value = response.opaqueData.dataDescriptor;
        document.getElementById("dataValue").value = response.opaqueData.dataValue;
        var storeCard = document.querySelector('input[name=token-billing-checkbox]:checked');
        if (storeCard) {
          document.getElementById("store_card").value = storeCard.value;
        }
        document.getElementById("server_response").submit();
      }
      return false;
    });
    _defineProperty(this, "handle", function () {
      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (e) {
          document.getElementById('save-card--container').style.display = 'none';
          document.getElementById('authorize--credit-card-container').style.display = 'none';
          document.getElementById('token').value = e.target.dataset.token;
        });
      });
      var payWithCreditCardToggle = document.getElementById('toggle-payment-with-credit-card');
      if (payWithCreditCardToggle) {
        payWithCreditCardToggle.addEventListener('click', function () {
          document.getElementById('save-card--container').style.display = 'grid';
          document.getElementById('authorize--credit-card-container').style.display = 'flex';
          document.getElementById('token').value = null;
        });
      }
      var payNowButton = document.getElementById('pay-now');
      if (payNowButton) {
        payNowButton.addEventListener('click', function (e) {
          var token = document.getElementById('token');
          token.value ? _this.handlePayNowAction(token.value) : _this.handleAuthorization();
        });
      }
      return _this;
    });
    this.publicKey = publicKey;
    this.loginId = loginId;
    this.cardHolderName = document.getElementById("cardholder_name");
  }
  _createClass(AuthorizeAuthorizeCard, [{
    key: "handlePayNowAction",
    value: function handlePayNowAction(token_hashed_id) {
      document.getElementById('pay-now').disabled = true;
      document.querySelector('#pay-now > svg').classList.remove('hidden');
      document.querySelector('#pay-now > span').classList.add('hidden');
      document.getElementById("token").value = token_hashed_id;
      document.getElementById("server_response").submit();
    }
  }]);
  return AuthorizeAuthorizeCard;
}();
var publicKey = document.querySelector('meta[name="authorize-public-key"]').content;
var loginId = document.querySelector('meta[name="authorize-login-id"]').content;
var cvvRequired = document.querySelector('meta[name="authnet-require-cvv"]').content;

/** @handle */
new AuthorizeAuthorizeCard(publicKey, loginId).handle();
/******/ })()
;