/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/html-tags/void.js":
/*!****************************************!*\
  !*** ./node_modules/html-tags/void.js ***!
  \****************************************/
/***/ ((module, __unused_webpack_exports, __webpack_require__) => {

"use strict";

module.exports = __webpack_require__(/*! ./html-tags-void.json */ "./node_modules/html-tags/html-tags-void.json");


/***/ }),

/***/ "./node_modules/stringify-attributes/node_modules/escape-goat/index.js":
/*!*****************************************************************************!*\
  !*** ./node_modules/stringify-attributes/node_modules/escape-goat/index.js ***!
  \*****************************************************************************/
/***/ ((__unused_webpack_module, exports) => {

"use strict";


const htmlEscape = string => string
	.replace(/&/g, '&amp;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#39;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;');

const htmlUnescape = htmlString => htmlString
	.replace(/&gt;/g, '>')
	.replace(/&lt;/g, '<')
	.replace(/&#0?39;/g, '\'')
	.replace(/&quot;/g, '"')
	.replace(/&amp;/g, '&');

exports.htmlEscape = (strings, ...values) => {
	if (typeof strings === 'string') {
		return htmlEscape(strings);
	}

	let output = strings[0];
	for (const [index, value] of values.entries()) {
		output = output + htmlEscape(String(value)) + strings[index + 1];
	}

	return output;
};

exports.htmlUnescape = (strings, ...values) => {
	if (typeof strings === 'string') {
		return htmlUnescape(strings);
	}

	let output = strings[0];
	for (const [index, value] of values.entries()) {
		output = output + htmlUnescape(String(value)) + strings[index + 1];
	}

	return output;
};


/***/ }),

/***/ "./node_modules/create-html-element/index.js":
/*!***************************************************!*\
  !*** ./node_modules/create-html-element/index.js ***!
  \***************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ createHtmlElement)
/* harmony export */ });
/* harmony import */ var stringify_attributes__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! stringify-attributes */ "./node_modules/stringify-attributes/index.js");
/* harmony import */ var html_tags_void_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! html-tags/void.js */ "./node_modules/html-tags/void.js");
/* harmony import */ var escape_goat__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! escape-goat */ "./node_modules/escape-goat/index.js");




const voidHtmlTags = new Set(html_tags_void_js__WEBPACK_IMPORTED_MODULE_1__);

function createHtmlElement(
	{
		name = 'div',
		attributes = {},
		html = '',
		text,
	} = {},
) {
	if (html && text) {
		throw new Error('The `html` and `text` options are mutually exclusive');
	}

	const content = text ? (0,escape_goat__WEBPACK_IMPORTED_MODULE_2__.htmlEscape)(text) : html;
	let result = `<${name}${(0,stringify_attributes__WEBPACK_IMPORTED_MODULE_0__["default"])(attributes)}>`;

	if (!voidHtmlTags.has(name)) {
		result += `${content}</${name}>`;
	}

	return result;
}


/***/ }),

/***/ "./node_modules/escape-goat/index.js":
/*!*******************************************!*\
  !*** ./node_modules/escape-goat/index.js ***!
  \*******************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "htmlEscape": () => (/* binding */ htmlEscape),
/* harmony export */   "htmlUnescape": () => (/* binding */ htmlUnescape)
/* harmony export */ });
const _htmlEscape = string => string
	.replace(/&/g, '&amp;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#39;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;');

const _htmlUnescape = htmlString => htmlString
	.replace(/&gt;/g, '>')
	.replace(/&lt;/g, '<')
	.replace(/&#0?39;/g, '\'')
	.replace(/&quot;/g, '"')
	.replace(/&amp;/g, '&');

function htmlEscape(strings, ...values) {
	if (typeof strings === 'string') {
		return _htmlEscape(strings);
	}

	let output = strings[0];
	for (const [index, value] of values.entries()) {
		output = output + _htmlEscape(String(value)) + strings[index + 1];
	}

	return output;
}

function htmlUnescape(strings, ...values) {
	if (typeof strings === 'string') {
		return _htmlUnescape(strings);
	}

	let output = strings[0];
	for (const [index, value] of values.entries()) {
		output = output + _htmlUnescape(String(value)) + strings[index + 1];
	}

	return output;
}


/***/ }),

/***/ "./node_modules/linkify-urls/index.js":
/*!********************************************!*\
  !*** ./node_modules/linkify-urls/index.js ***!
  \********************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ linkifyUrls)
/* harmony export */ });
/* harmony import */ var create_html_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! create-html-element */ "./node_modules/create-html-element/index.js");


// Capture the whole URL in group 1 to keep `String#split()` support
const urlRegex = () => (/((?<!\+)https?:\/\/(?:www\.)?(?:[-\w.]+?[.@][a-zA-Z\d]{2,}|localhost)(?:[-\w.:%+~#*$!?&/=@]*?(?:,(?!\s))*?)*)/g);

// Get `<a>` element as string
const linkify = (href, options) => (0,create_html_element__WEBPACK_IMPORTED_MODULE_0__["default"])({
	name: 'a',
	attributes: {
		href: '',
		...options.attributes,
		href, // eslint-disable-line no-dupe-keys
	},
	text: typeof options.value === 'undefined' ? href : undefined,
	html: typeof options.value === 'undefined' ? undefined
		: (typeof options.value === 'function' ? options.value(href) : options.value),
});

// Get DOM node from HTML
const domify = html => document.createRange().createContextualFragment(html);

const getAsString = (string, options) => string.replace(urlRegex(), match => linkify(match, options));

const getAsDocumentFragment = (string, options) => {
	const fragment = document.createDocumentFragment();
	for (const [index, text] of Object.entries(string.split(urlRegex()))) {
		if (index % 2) { // URLs are always in odd positions
			fragment.append(domify(linkify(text, options)));
		} else if (text.length > 0) {
			fragment.append(text);
		}
	}

	return fragment;
};

function linkifyUrls(string, options) {
	options = {
		attributes: {},
		type: 'string',
		...options,
	};

	if (options.type === 'string') {
		return getAsString(string, options);
	}

	if (options.type === 'dom') {
		return getAsDocumentFragment(string, options);
	}

	throw new TypeError('The type option must be either `dom` or `string`');
}


/***/ }),

/***/ "./node_modules/stringify-attributes/index.js":
/*!****************************************************!*\
  !*** ./node_modules/stringify-attributes/index.js ***!
  \****************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ stringifyAttributes)
/* harmony export */ });
/* harmony import */ var escape_goat__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! escape-goat */ "./node_modules/stringify-attributes/node_modules/escape-goat/index.js");


function stringifyAttributes(attributes) {
	const handledAttributes = [];

	for (let [key, value] of Object.entries(attributes)) {
		if (value === false) {
			continue;
		}

		if (Array.isArray(value)) {
			value = value.join(' ');
		}

		let attribute = (0,escape_goat__WEBPACK_IMPORTED_MODULE_0__.htmlEscape)(key);

		if (value !== true) {
			attribute += `="${(0,escape_goat__WEBPACK_IMPORTED_MODULE_0__.htmlEscape)(String(value))}"`;
		}

		handledAttributes.push(attribute);
	}

	return handledAttributes.length > 0 ? ' ' + handledAttributes.join(' ') : '';
}


/***/ }),

/***/ "./node_modules/html-tags/html-tags-void.json":
/*!****************************************************!*\
  !*** ./node_modules/html-tags/html-tags-void.json ***!
  \****************************************************/
/***/ ((module) => {

"use strict";
module.exports = JSON.parse('["area","base","br","col","embed","hr","img","input","link","menuitem","meta","param","source","track","wbr"]');

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry need to be wrapped in an IIFE because it need to be isolated against other modules in the chunk.
(() => {
/*!**********************************************!*\
  !*** ./resources/js/clients/linkify-urls.js ***!
  \**********************************************/
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license 
 */

var linkifyUrls = __webpack_require__(/*! linkify-urls */ "./node_modules/linkify-urls/index.js");
document.querySelectorAll('[data-ref=entity-terms]').forEach(function (text) {
  if (linkifyUrls === 'function') {
    text.innerHTML = linkifyUrls(text.innerText, {
      attributes: {
        target: '_blank',
        "class": 'text-primary'
      }
    });
  }
});
})();

/******/ })()
;