/******/ (function(modules) { // webpackBootstrap
/******/ 	// The module cache
/******/ 	var installedModules = {};
/******/
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/
/******/ 		// Check if module is in cache
/******/ 		if(installedModules[moduleId]) {
/******/ 			return installedModules[moduleId].exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = installedModules[moduleId] = {
/******/ 			i: moduleId,
/******/ 			l: false,
/******/ 			exports: {}
/******/ 		};
/******/
/******/ 		// Execute the module function
/******/ 		modules[moduleId].call(module.exports, module, module.exports, __webpack_require__);
/******/
/******/ 		// Flag the module as loaded
/******/ 		module.l = true;
/******/
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/
/******/
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = modules;
/******/
/******/ 	// expose the module cache
/******/ 	__webpack_require__.c = installedModules;
/******/
/******/ 	// define getter function for harmony exports
/******/ 	__webpack_require__.d = function(exports, name, getter) {
/******/ 		if(!__webpack_require__.o(exports, name)) {
/******/ 			Object.defineProperty(exports, name, { enumerable: true, get: getter });
/******/ 		}
/******/ 	};
/******/
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = function(exports) {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/
/******/ 	// create a fake namespace object
/******/ 	// mode & 1: value is a module id, require it
/******/ 	// mode & 2: merge all properties of value into the ns
/******/ 	// mode & 4: return value when already ns object
/******/ 	// mode & 8|1: behave like require
/******/ 	__webpack_require__.t = function(value, mode) {
/******/ 		if(mode & 1) value = __webpack_require__(value);
/******/ 		if(mode & 8) return value;
/******/ 		if((mode & 4) && typeof value === 'object' && value && value.__esModule) return value;
/******/ 		var ns = Object.create(null);
/******/ 		__webpack_require__.r(ns);
/******/ 		Object.defineProperty(ns, 'default', { enumerable: true, value: value });
/******/ 		if(mode & 2 && typeof value != 'string') for(var key in value) __webpack_require__.d(ns, key, function(key) { return value[key]; }.bind(null, key));
/******/ 		return ns;
/******/ 	};
/******/
/******/ 	// getDefaultExport function for compatibility with non-harmony modules
/******/ 	__webpack_require__.n = function(module) {
/******/ 		var getter = module && module.__esModule ?
/******/ 			function getDefault() { return module['default']; } :
/******/ 			function getModuleExports() { return module; };
/******/ 		__webpack_require__.d(getter, 'a', getter);
/******/ 		return getter;
/******/ 	};
/******/
/******/ 	// Object.prototype.hasOwnProperty.call
/******/ 	__webpack_require__.o = function(object, property) { return Object.prototype.hasOwnProperty.call(object, property); };
/******/
/******/ 	// __webpack_public_path__
/******/ 	__webpack_require__.p = "/";
/******/
/******/
/******/ 	// Load entry module and return exports
/******/ 	return __webpack_require__(__webpack_require__.s = 16);
/******/ })
/************************************************************************/
/******/ ({

/***/ "./node_modules/create-html-element/index.js":
/*!***************************************************!*\
  !*** ./node_modules/create-html-element/index.js ***!
  \***************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

const stringifyAttributes = __webpack_require__(/*! stringify-attributes */ "./node_modules/stringify-attributes/index.js");
const htmlTags = __webpack_require__(/*! html-tags/void */ "./node_modules/create-html-element/node_modules/html-tags/void.js");
const escapeGoat = __webpack_require__(/*! escape-goat */ "./node_modules/escape-goat/index.js");

const voidHtmlTags = new Set(htmlTags);

module.exports = options => {
	options = Object.assign({
		name: 'div',
		attributes: {},
		html: ''
	}, options);

	if (options.html && options.text) {
		throw new Error('The `html` and `text` options are mutually exclusive');
	}

	const content = options.text ? escapeGoat.escape(options.text) : options.html;
	let result = `<${options.name}${stringifyAttributes(options.attributes)}>`;

	if (!voidHtmlTags.has(options.name)) {
		result += `${content}</${options.name}>`;
	}

	return result;
};


/***/ }),

/***/ "./node_modules/create-html-element/node_modules/html-tags/html-tags-void.json":
/*!*************************************************************************************!*\
  !*** ./node_modules/create-html-element/node_modules/html-tags/html-tags-void.json ***!
  \*************************************************************************************/
/*! exports provided: 0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, default */
/***/ (function(module) {

module.exports = JSON.parse("[\"area\",\"base\",\"br\",\"col\",\"embed\",\"hr\",\"img\",\"input\",\"link\",\"menuitem\",\"meta\",\"param\",\"source\",\"track\",\"wbr\"]");

/***/ }),

/***/ "./node_modules/create-html-element/node_modules/html-tags/void.js":
/*!*************************************************************************!*\
  !*** ./node_modules/create-html-element/node_modules/html-tags/void.js ***!
  \*************************************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

module.exports = __webpack_require__(/*! ./html-tags-void.json */ "./node_modules/create-html-element/node_modules/html-tags/html-tags-void.json");


/***/ }),

/***/ "./node_modules/escape-goat/index.js":
/*!*******************************************!*\
  !*** ./node_modules/escape-goat/index.js ***!
  \*******************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";


exports.escape = input => input
	.replace(/&/g, '&amp;')
	.replace(/"/g, '&quot;')
	.replace(/'/g, '&#39;')
	.replace(/</g, '&lt;')
	.replace(/>/g, '&gt;');

exports.unescape = input => input
	.replace(/&gt;/g, '>')
	.replace(/&lt;/g, '<')
	.replace(/&#39;/g, '\'')
	.replace(/&quot;/g, '"')
	.replace(/&amp;/g, '&');

exports.escapeTag = function (input) {
	let output = input[0];
	for (let i = 1; i < arguments.length; i++) {
		output = output + exports.escape(arguments[i]) + input[i];
	}
	return output;
};

exports.unescapeTag = function (input) {
	let output = input[0];
	for (let i = 1; i < arguments.length; i++) {
		output = output + exports.unescape(arguments[i]) + input[i];
	}
	return output;
};


/***/ }),

/***/ "./node_modules/linkify-urls/index.js":
/*!********************************************!*\
  !*** ./node_modules/linkify-urls/index.js ***!
  \********************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

const createHtmlElement = __webpack_require__(/*! create-html-element */ "./node_modules/create-html-element/index.js");

// Capture the whole URL in group 1 to keep `String#split()` support
const urlRegex = () => (/((?<!\+)(?:https?(?::\/\/))(?:www\.)?(?:[a-zA-Z\d-_.]+(?:(?:\.|@)[a-zA-Z\d]{2,})|localhost)(?:(?:[-a-zA-Z\d:%_+.~#*$!?&//=@]*)(?:[,](?![\s]))*)*)/g);

// Get `<a>` element as string
const linkify = (href, options) => createHtmlElement({
	name: 'a',
	attributes: {
		href: '',
		...options.attributes,
		href // eslint-disable-line no-dupe-keys
	},
	text: typeof options.value === 'undefined' ? href : undefined,
	html: typeof options.value === 'undefined' ? undefined :
		(typeof options.value === 'function' ? options.value(href) : options.value)
});

// Get DOM node from HTML
const domify = html => document.createRange().createContextualFragment(html);

const getAsString = (string, options) => {
	return string.replace(urlRegex(), match => linkify(match, options));
};

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

module.exports = (string, options) => {
	options = {
		attributes: {},
		type: 'string',
		...options
	};

	if (options.type === 'string') {
		return getAsString(string, options);
	}

	if (options.type === 'dom') {
		return getAsDocumentFragment(string, options);
	}

	throw new Error('The type option must be either `dom` or `string`');
};


/***/ }),

/***/ "./node_modules/stringify-attributes/index.js":
/*!****************************************************!*\
  !*** ./node_modules/stringify-attributes/index.js ***!
  \****************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

"use strict";

const escapeGoat = __webpack_require__(/*! escape-goat */ "./node_modules/escape-goat/index.js");

module.exports = input => {
	const attributes = [];

	for (const key of Object.keys(input)) {
		let value = input[key];

		if (value === false) {
			continue;
		}

		if (Array.isArray(value)) {
			value = value.join(' ');
		}

		let attribute = escapeGoat.escape(key);

		if (value !== true) {
			attribute += `="${escapeGoat.escape(String(value))}"`;
		}

		attributes.push(attribute);
	}

	return attributes.length > 0 ? ' ' + attributes.join(' ') : '';
};


/***/ }),

/***/ "./resources/js/clients/linkify-urls.js":
/*!**********************************************!*\
  !*** ./resources/js/clients/linkify-urls.js ***!
  \**********************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
var linkifyUrls = __webpack_require__(/*! linkify-urls */ "./node_modules/linkify-urls/index.js");

document.querySelectorAll('[data-ref=entity-terms]').forEach(function (text) {
  text.innerHTML = linkifyUrls(text.innerText, {
    attributes: {
      target: '_blank',
      "class": 'text-primary'
    }
  });
});

/***/ }),

/***/ 16:
/*!****************************************************!*\
  !*** multi ./resources/js/clients/linkify-urls.js ***!
  \****************************************************/
/*! no static exports found */
/***/ (function(module, exports, __webpack_require__) {

module.exports = __webpack_require__(/*! /var/www/html/resources/js/clients/linkify-urls.js */"./resources/js/clients/linkify-urls.js");


/***/ })

/******/ });