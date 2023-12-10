/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!*************************************************!*\
  !*** ./resources/js/clients/statements/view.js ***!
  \*************************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _classCallCheck(instance, Constructor) { if (!(instance instanceof Constructor)) { throw new TypeError("Cannot call a class as a function"); } }
function _defineProperties(target, props) { for (var i = 0; i < props.length; i++) { var descriptor = props[i]; descriptor.enumerable = descriptor.enumerable || false; descriptor.configurable = true; if ("value" in descriptor) descriptor.writable = true; Object.defineProperty(target, _toPropertyKey(descriptor.key), descriptor); } }
function _createClass(Constructor, protoProps, staticProps) { if (protoProps) _defineProperties(Constructor.prototype, protoProps); if (staticProps) _defineProperties(Constructor, staticProps); Object.defineProperty(Constructor, "prototype", { writable: false }); return Constructor; }
function _toPropertyKey(arg) { var key = _toPrimitive(arg, "string"); return _typeof(key) === "symbol" ? key : String(key); }
function _toPrimitive(input, hint) { if (_typeof(input) !== "object" || input === null) return input; var prim = input[Symbol.toPrimitive]; if (prim !== undefined) { var res = prim.call(input, hint || "default"); if (_typeof(res) !== "object") return res; throw new TypeError("@@toPrimitive must return a primitive value."); } return (hint === "string" ? String : Number)(input); }
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */
var Statement = /*#__PURE__*/function () {
  function Statement() {
    _classCallCheck(this, Statement);
    this.url = new URL(document.querySelector('meta[name=pdf-url]').content);
    this.startDate = '';
    this.endDate = '';
    this.showPaymentsTable = false;
    this.showAgingTable = false;
    this.status = '';
  }
  _createClass(Statement, [{
    key: "bindEventListeners",
    value: function bindEventListeners() {
      var _this = this;
      ['#date-from', '#date-to', '#show-payments-table', '#show-aging-table', '#status'].forEach(function (selector) {
        document.querySelector(selector).addEventListener('change', function (event) {
          return _this.handleValueChange(event);
        });
      });
    }
  }, {
    key: "handleValueChange",
    value: function handleValueChange(event) {
      if (event.target.type === 'checkbox') {
        this[event.target.dataset.field] = event.target.checked;
      } else {
        this[event.target.dataset.field] = event.target.value;
      }
      this.updatePdf();
    }
  }, {
    key: "composedUrl",
    get: function get() {
      this.url.search = '';
      if (this.startDate.length > 0) {
        this.url.searchParams.append('start_date', this.startDate);
      }
      if (this.endDate.length > 0) {
        this.url.searchParams.append('end_date', this.endDate);
      }
      this.url.searchParams.append('status', document.getElementById("status").value);
      this.url.searchParams.append('show_payments_table', +this.showPaymentsTable);
      this.url.searchParams.append('show_aging_table', +this.showAgingTable);
      return this.url.href;
    }
  }, {
    key: "updatePdf",
    value: function updatePdf() {
      document.querySelector('meta[name=pdf-url]').content = this.composedUrl;
      var iframe = document.querySelector('#pdf-iframe');
      if (iframe) {
        iframe.src = this.composedUrl;
      }
      document.querySelector('meta[name=pdf-url]').dispatchEvent(new Event('change'));
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this2 = this;
      this.bindEventListeners();
      document.querySelector('#pdf-download').addEventListener('click', function () {
        var url = new URL(_this2.composedUrl);
        url.searchParams.append('download', 1);
        window.location.href = url.href;
      });
    }
  }]);
  return Statement;
}();
new Statement().handle();
/******/ })()
;