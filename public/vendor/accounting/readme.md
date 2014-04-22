**accounting.js** is a tiny JavaScript library for number, money and currency parsing/formatting. It's lightweight and fully localisable, with zero dependencies, and works great as a nodeJS/npm and AMD/requireJS module. 

Check out the **[accounting.js homepage](http://josscrowcroft.github.com/accounting.js/)** for demos and documentation!

Please checkout/download the latest stable tag before using in production. [Bug reports](https://github.com/josscrowcroft/accounting.js/issues) and pull requests hugely welcome.

Made with love by [@josscrowcroft](http://twitter.com/josscrowcroft) and some excellent [contributors](https://github.com/josscrowcroft/accounting.js/contributors).

---

### Also try:

* **[money.js](http://josscrowcroft.github.com/money.js)** - a tiny (1kb) javascript currency conversion library, for web & nodeJS
* **[open exchange rates](http://josscrowcroft.github.com/open-exchange-rates/)** - free / open source hourly-updated currency conversion data for everybody

---

## Changelog

**v0.3.2** - Fix package.json dependencies (should be empty object) and tweak comments

**v0.3.0**

* Rewrote library structure similar to underscore.js for use as a **nodeJS/npm** and **AMD/requireJS** module - now you can go `npm install accounting`, and then `var accounting = require("accounting");` in your nodeJS scripts.
* **unformat** now only attempts to parse the number if it's not already a valid number. Also now aliased as `acounting.parse`
* Fixed an IE bug in the `defaults` method

**v0.2.2** - Fixed same issue as \#Num: #24 in **formatNumber**; switch to Google Closure Compiler for minified version.

**v0.2.1** - Fixed issue \#Num: #24 where locally-defined settings object was being modified by **formatMoney** (still an issue in **formatNumber**)

**v0.2**

* Rewrote formatting system for **formatMoney** and **formatColumn**, to allow easier/better control of string output
* Separate formats for negative and zero values now supported (optionally) via `accounting.settings.currency.format`
* Internal improvements and helper methods

**v0.1.4** - **formatMoney** recursively formats arrays; added Jasmine test suite (thx [millermedeiros](https://github.com/millermedeiros)!) and QUnit functionality/speed tests

**v0.1.3**

* Added configurable settings object for default formatting parameters.
* Added `format` parameter to control symbol and value position (default `"%s%v"`, or [symbol][value])
* Made methods more consistent in accepting an object as 2nd parameter, matching/overriding the library defaults

**v0.1.2**

* **formatColumn** works recursively on nested arrays, eg `accounting.formatColumn( [[1,12,123,1234], [1234,123,12,1]] )`, returns matching array with inner columns lined up
* Another fix for rounding in **formatNumber**: `.535` now correctly rounds to ".54" instead of ".53"

**v0.1.1**

* Added **toFixed** method (`accounting.toFixed(value, precision)`), which treats floats more like decimals for more accurate currency rounding - now, `0.615` rounds up to `$0.62` instead of `$0.61`
* Minified version preserves semicolons
* Fixed NaN errors when no value in **unformat**

**v0.1** - First version yo


---

### Here's a neat little preview of **formatColumn()**:

```html
 Original Number:   |  With accounting.js:    |  Different settings:    |    Symbol after value:
 -------------------+-------------------------+-------------------------+-----------------------
 123.5              |     $        123.50     |     HK$         124     |            123.50 GBP
 3456.615           |     $      3,456.62     |     HK$       3,457     |          3,456.62 GBP
 777888.99          |     $    777,888.99     |     HK$     777,889     |        777,888.99 GBP
 -5432              |     $     -5,432.00     |     HK$     (5,432)     |         -5,432.00 GBP
 -1234567           |     $ -1,234,567.00     |     HK$ (1,234,567)     |     -1,234,567.00 GBP
 0                  |     $          0.00     |     HK$          --     |              0.00 GBP
```

There's more demos and documentation on the **[accounting.js homepage](http://josscrowcroft.github.com/accounting.js/)**. Enjoy!
