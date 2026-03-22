/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!***********************************************************!*\
  !*** ./resources/js/clients/invoices/action-selectors.js ***!
  \***********************************************************/
function _typeof(obj) { "@babel/helpers - typeof"; return _typeof = "function" == typeof Symbol && "symbol" == typeof Symbol.iterator ? function (obj) { return typeof obj; } : function (obj) { return obj && "function" == typeof Symbol && obj.constructor === Symbol && obj !== Symbol.prototype ? "symbol" : typeof obj; }, _typeof(obj); }
function _createForOfIteratorHelper(o, allowArrayLike) { var it = typeof Symbol !== "undefined" && o[Symbol.iterator] || o["@@iterator"]; if (!it) { if (Array.isArray(o) || (it = _unsupportedIterableToArray(o)) || allowArrayLike && o && typeof o.length === "number") { if (it) o = it; var i = 0; var F = function F() {}; return { s: F, n: function n() { if (i >= o.length) return { done: true }; return { done: false, value: o[i++] }; }, e: function e(_e) { throw _e; }, f: F }; } throw new TypeError("Invalid attempt to iterate non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method."); } var normalCompletion = true, didErr = false, err; return { s: function s() { it = it.call(o); }, n: function n() { var step = it.next(); normalCompletion = step.done; return step; }, e: function e(_e2) { didErr = true; err = _e2; }, f: function f() { try { if (!normalCompletion && it["return"] != null) it["return"](); } finally { if (didErr) throw err; } } }; }
function _unsupportedIterableToArray(o, minLen) { if (!o) return; if (typeof o === "string") return _arrayLikeToArray(o, minLen); var n = Object.prototype.toString.call(o).slice(8, -1); if (n === "Object" && o.constructor) n = o.constructor.name; if (n === "Map" || n === "Set") return Array.from(o); if (n === "Arguments" || /^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)) return _arrayLikeToArray(o, minLen); }
function _arrayLikeToArray(arr, len) { if (len == null || len > arr.length) len = arr.length; for (var i = 0, arr2 = new Array(len); i < len; i++) arr2[i] = arr[i]; return arr2; }
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
var ActionSelectors = /*#__PURE__*/function () {
  function ActionSelectors() {
    _classCallCheck(this, ActionSelectors);
    this.parentElement = document.querySelector('.form-check-parent');
    this.parentForm = document.getElementById('bulkActions');
  }
  _createClass(ActionSelectors, [{
    key: "watchCheckboxes",
    value: function watchCheckboxes(parentElement) {
      var _this = this;
      document.querySelectorAll('.child-hidden-input').forEach(function (element) {
        return element.remove();
      });
      document.querySelectorAll('.form-check-child').forEach(function (child) {
        if (parentElement.checked) {
          child.checked = parentElement.checked;
          _this.processChildItem(child, document.getElementById('bulkActions'));
        } else {
          child.checked = false;
          document.querySelectorAll('.child-hidden-input').forEach(function (element) {
            return element.remove();
          });
        }
      });
    }
  }, {
    key: "processChildItem",
    value: function processChildItem(element, parent) {
      var options = arguments.length > 2 && arguments[2] !== undefined ? arguments[2] : {};
      if (options.hasOwnProperty('single')) {
        document.querySelectorAll('.child-hidden-input').forEach(function (element) {
          return element.remove();
        });
      }
      if (element.checked === false) {
        var inputs = document.querySelectorAll('input.child-hidden-input');
        var _iterator = _createForOfIteratorHelper(inputs),
          _step;
        try {
          for (_iterator.s(); !(_step = _iterator.n()).done;) {
            var i = _step.value;
            if (i.value == element.dataset.value) i.remove();
          }
        } catch (err) {
          _iterator.e(err);
        } finally {
          _iterator.f();
        }
        return;
      }
      var _temp = document.createElement('INPUT');
      _temp.setAttribute('name', 'invoices[]');
      _temp.setAttribute('value', element.dataset.value);
      _temp.setAttribute('class', 'child-hidden-input');
      _temp.hidden = true;
      parent.append(_temp);
    }
  }, {
    key: "handle",
    value: function handle() {
      var _this2 = this;
      this.parentElement.addEventListener('click', function () {
        _this2.watchCheckboxes(_this2.parentElement);
      });
      var _iterator2 = _createForOfIteratorHelper(document.querySelectorAll('.form-check-child')),
        _step2;
      try {
        var _loop = function _loop() {
          var child = _step2.value;
          child.addEventListener('click', function () {
            _this2.processChildItem(child, _this2.parentForm);
          });
        };
        for (_iterator2.s(); !(_step2 = _iterator2.n()).done;) {
          _loop();
        }
      } catch (err) {
        _iterator2.e(err);
      } finally {
        _iterator2.f();
      }
    }
  }]);
  return ActionSelectors;
}();
/** @handle **/
new ActionSelectors().handle();
/******/ })()
;