/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!************************************************************!*\
  !*** ./resources/js/clients/payments/forte-ach-payment.js ***!
  \************************************************************/
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

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
var ForteAuthorizeACH = function ForteAuthorizeACH(apiLoginId) {
  var _this = this;

  _classCallCheck(this, ForteAuthorizeACH);

  _defineProperty(this, "handleAuthorization", function () {
    var account_number = document.getElementById('account-number').value;
    var routing_number = document.getElementById('routing-number').value;
    var data = {
      api_login_id: _this.apiLoginId,
      account_number: account_number,
      routing_number: routing_number,
      account_type: 'checking'
    };
    var payNowButton = document.getElementById('pay-now');

    if (payNowButton) {
      document.getElementById('pay-now').disabled = true;
      document.querySelector('#pay-now > svg').classList.remove('hidden');
      document.querySelector('#pay-now > span').classList.add('hidden');
    } // console.log(data);


    forte.createToken(data).success(_this.successResponseHandler).error(_this.failedResponseHandler);
    return false;
  });

  _defineProperty(this, "successResponseHandler", function (response) {
    document.getElementById('payment_token').value = response.onetime_token;
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
};

var apiLoginId = document.querySelector('meta[name="forte-api-login-id"]').content;
/** @handle */

new ForteAuthorizeACH(apiLoginId).handle();
/******/ })()
;