/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!**************************************************!*\
  !*** ./resources/js/clients/invoices/payment.js ***!
  \**************************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
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
var Payment = /*#__PURE__*/function () {
  function Payment(displayTerms, displaySignature) {
    _classCallCheck(this, Payment);
    this.shouldDisplayTerms = displayTerms;
    this.shouldDisplaySignature = displaySignature;
    this.termsAccepted = false;
    this.submitting = false;
  }
  _createClass(Payment, [{
    key: "handleMethodSelect",
    value: function handleMethodSelect(element) {
      var _this = this;
      document.getElementById("company_gateway_id").value = element.dataset.companyGatewayId;
      document.getElementById("payment_method_id").value = element.dataset.gatewayTypeId;
      if (this.shouldDisplaySignature && !this.shouldDisplayTerms) {
        if (this.signaturePad && this.signaturePad.isEmpty()) alert("Please sign");
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
      this.submitting = true;
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
      document.getElementById("signature-next-step").disabled = true;
      var displaySignatureModal = document.getElementById("displaySignatureModal");
      displaySignatureModal.removeAttribute("style");
      var signaturePad = new SignaturePad(document.getElementById("signature-pad"), {
        penColor: "rgb(0, 0, 0)"
      });
      signaturePad.onEnd = function () {
        document.getElementById("signature-next-step").disabled = false;
      };
      this.signaturePad = signaturePad;
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this2 = this;
      document.querySelectorAll(".dropdown-gateway-button").forEach(function (element) {
        element.addEventListener("click", function () {
          if (!_this2.submitting) {
            _this2.handleMethodSelect(element);
          }
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