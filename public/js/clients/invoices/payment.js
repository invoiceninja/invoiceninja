/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!**************************************************!*\
  !*** ./resources/js/clients/invoices/payment.js ***!
  \**************************************************/
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }

function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, descriptor.key, descriptor); } }

function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); return Constructor; }

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
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
/******/ })()
;