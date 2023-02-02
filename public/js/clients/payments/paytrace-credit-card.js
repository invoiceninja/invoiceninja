/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!***************************************************************!*\
  !*** ./resources/js/clients/payments/paytrace-credit-card.js ***!
  \***************************************************************/
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
var PayTraceCreditCard = /*#__PURE__*/function () {
  function PayTraceCreditCard() {
    var _document$querySelect;

    _classCallCheck(this, PayTraceCreditCard);

    this.clientKey = (_document$querySelect = document.querySelector('meta[name=paytrace-client-key]')) === null || _document$querySelect === void 0 ? void 0 : _document$querySelect.content;
  }

  _createClass(PayTraceCreditCard, [{
    key: "creditCardStyles",
    get: function get() {
      return {
        font_color: '#111827',
        border_color: 'rgba(210,214,220,1)',
        label_color: '#111827',
        label_size: '12pt',
        background_color: 'white',
        border_style: 'solid',
        font_size: '15pt',
        height: '30px',
        width: '100%'
      };
    }
  }, {
    key: "codeStyles",
    get: function get() {
      return {
        font_color: '#111827',
        border_color: 'rgba(210,214,220,1)',
        label_color: '#111827',
        label_size: '12pt',
        background_color: 'white',
        border_style: 'solid',
        font_size: '15pt',
        height: '30px',
        width: '300px'
      };
    }
  }, {
    key: "expStyles",
    get: function get() {
      return {
        font_color: '#111827',
        border_color: 'rgba(210,214,220,1)',
        label_color: '#111827',
        label_size: '12pt',
        background_color: 'white',
        border_style: 'solid',
        font_size: '15pt',
        height: '30px',
        width: '85px',
        type: 'dropdown'
      };
    }
  }, {
    key: "updatePayTraceLabels",
    value: function updatePayTraceLabels() {
      window.PTPayment.getControl('securityCode').label.text(document.querySelector('meta[name=ctrans-cvv]').content);
      window.PTPayment.getControl('creditCard').label.text(document.querySelector('meta[name=ctrans-card_number]').content);
      window.PTPayment.getControl('expiration').label.text(document.querySelector('meta[name=ctrans-expires]').content);
    }
  }, {
    key: "setupPayTrace",
    value: function setupPayTrace() {
      return window.PTPayment.setup({
        styles: {
          code: this.codeStyles,
          cc: this.creditCardStyles,
          exp: this.expStyles
        },
        authorization: {
          clientKey: this.clientKey
        }
      });
    }
  }, {
    key: "handlePaymentWithCreditCard",
    value: function handlePaymentWithCreditCard(event) {
      var _this = this;

      event.target.parentElement.disabled = true;
      document.getElementById('errors').hidden = true;
      window.PTPayment.validate(function (errors) {
        if (errors.length >= 1) {
          var errorsContainer = document.getElementById('errors');
          errorsContainer.textContent = errors[0].description;
          errorsContainer.hidden = false;
          return event.target.parentElement.disabled = false;
        }

        _this.ptInstance.process().then(function (response) {
          document.getElementById('HPF_Token').value = response.message.hpf_token;
          document.getElementById('enc_key').value = response.message.enc_key;
          var tokenBillingCheckbox = document.querySelector('input[name="token-billing-checkbox"]:checked');

          if (tokenBillingCheckbox) {
            document.querySelector('input[name="store_card"]').value = tokenBillingCheckbox.value;
          }

          document.getElementById('server_response').submit();
        })["catch"](function (error) {
          document.getElementById('errors').textContent = JSON.stringify(error);
          document.getElementById('errors').hidden = false;
          console.log(error);
        });
      });
    }
  }, {
    key: "handlePaymentWithToken",
    value: function handlePaymentWithToken(event) {
      event.target.parentElement.disabled = true;
      document.getElementById('server_response').submit();
    }
  }, {
    key: "handle",
    value: function handle() {
      var _document$getElementB,
          _this2 = this;

      Array.from(document.getElementsByClassName('toggle-payment-with-token')).forEach(function (element) {
        return element.addEventListener('click', function (element) {
          document.getElementById('paytrace--credit-card-container').classList.add('hidden');
          document.getElementById('save-card--container').style.display = 'none';
          document.querySelector('input[name=token]').value = element.target.dataset.token;
        });
      });
      (_document$getElementB = document.getElementById('toggle-payment-with-credit-card')) === null || _document$getElementB === void 0 ? void 0 : _document$getElementB.addEventListener('click', function (element) {
        document.getElementById('paytrace--credit-card-container').classList.remove('hidden');
        document.getElementById('save-card--container').style.display = 'grid';
        document.querySelector('input[name=token]').value = '';

        _this2.setupPayTrace().then(function (instance) {
          _this2.ptInstance = instance;

          _this2.updatePayTraceLabels();
        });
      });
      document.getElementById('pay-now').addEventListener('click', function (e) {
        if (document.querySelector('input[name=token]').value === '') {
          return _this2.handlePaymentWithCreditCard(e);
        }

        return _this2.handlePaymentWithToken(e);
      });
    }
  }]);

  return PayTraceCreditCard;
}();

new PayTraceCreditCard().handle();
/******/ })()
;