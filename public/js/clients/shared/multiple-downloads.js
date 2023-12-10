/******/ (() => { // webpackBootstrap
var __webpack_exports__ = {};
/*!***********************************************************!*\
  !*** ./resources/js/clients/shared/multiple-downloads.js ***!
  \***********************************************************/
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

var appendToElement = function appendToElement(parent, value) {
  var _parent = document.getElementById(parent);
  var _possibleElement = _parent.querySelector("input[value=\"".concat(value, "\"]"));
  if (_possibleElement) {
    return _possibleElement.remove();
  }
  var _temp = document.createElement('INPUT');
  _temp.setAttribute('name', 'file_hash[]');
  _temp.setAttribute('value', value);
  _temp.hidden = true;
  _parent.append(_temp);
};
window.appendToElement = appendToElement;
/******/ })()
;