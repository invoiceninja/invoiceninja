/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!********************************************************!*\
  !*** ./resources/js/clients/purchase_orders/accept.js ***!
  \********************************************************/
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
var Accept = /*#__PURE__*/function () {
  function Accept(displaySignature, displayTerms) {
    _classCallCheck(this, Accept);
    this.shouldDisplaySignature = displaySignature;
    this.shouldDisplayTerms = displayTerms;
    this.termsAccepted = false;
  }
  _createClass(Accept, [{
    key: "submitForm",
    value: function submitForm() {
      document.getElementById('approve-form').submit();
    }
  }, {
    key: "displaySignature",
    value: function displaySignature() {
      var displaySignatureModal = document.getElementById('displaySignatureModal');
      displaySignatureModal.removeAttribute('style');
      var signaturePad = new SignaturePad(document.getElementById('signature-pad'), {
        penColor: 'rgb(0, 0, 0)'
      });
      signaturePad.onEnd = function () {
        document.getElementById("signature-next-step").disabled = false;
      };
      this.signaturePad = signaturePad;
    }
  }, {
    key: "displayTerms",
    value: function displayTerms() {
      var displayTermsModal = document.getElementById("displayTermsModal");
      displayTermsModal.removeAttribute("style");
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this = this;
      document.getElementById("signature-next-step").disabled = true;
      document.getElementById("close-button").addEventListener('click', function () {
        var approveButton = document.getElementById("approve-button");
        if (approveButton) approveButton.disabled = false;
      });
      document.getElementById('approve-button').addEventListener('click', function () {
        console.log("accepted ");
        if (_this.shouldDisplaySignature && _this.shouldDisplayTerms) {
          _this.displaySignature();
          document.getElementById('signature-next-step').addEventListener('click', function () {
            _this.displayTerms();
            document.getElementById('accept-terms-button').addEventListener('click', function () {
              document.querySelector('input[name="signature"').value = _this.signaturePad.toDataURL();
              _this.termsAccepted = true;
              _this.submitForm();
            });
          });
        }
        if (_this.shouldDisplaySignature && !_this.shouldDisplayTerms) {
          _this.displaySignature();
          document.getElementById('signature-next-step').addEventListener('click', function () {
            document.querySelector('input[name="signature"').value = _this.signaturePad.toDataURL();
            _this.submitForm();
          });
        }
        if (!_this.shouldDisplaySignature && _this.shouldDisplayTerms) {
          _this.displayTerms();
          document.getElementById('accept-terms-button').addEventListener('click', function () {
            _this.termsAccepted = true;
            _this.submitForm();
          });
        }
        if (!_this.shouldDisplaySignature && !_this.shouldDisplayTerms) {
          _this.submitForm();
        }
      });
    }
  }]);
  return Accept;
}();
var signature = document.querySelector('meta[name="require-purchase_order-signature"]').content;
var terms = document.querySelector('meta[name="show-purchase_order-terms"]').content;
new Accept(Boolean(+signature), Boolean(+terms)).handle();
/******/ })()
;