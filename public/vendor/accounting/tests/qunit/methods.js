$(document).ready(function() {

	module("Library Methods");

	test("accounting.unformat()", function() {
		equals(accounting.unformat("$12,345,678.90 USD"), 12345678.9, 'Can unformat currency to float');
		equals(accounting.unformat(1234567890), 1234567890, 'Returns same value when passed an integer');
		equals(accounting.unformat("string"), 0, 'Returns 0 for a string with no numbers');
		equals(accounting.unformat({joss:1}), 0, 'Returns 0 for object');
	});

	test("accounting.toFixed()", function() {
		equals(accounting.toFixed(54321, 5), "54321.00000", 'Performs basic float zero-padding');
		equals(accounting.toFixed(0.615, 2), "0.62", 'Rounds 0.615 to "0.62" instead of "0.61"');
	});

	test("accounting.formatNumber()", function() {
		// Check custom precision and separators:
		equals(accounting.formatNumber(4999.99, 2, ".", ","), "4.999,99", 'Custom precision and decimal/thousand separators are a-ok')
		
		// check usage with options object parameter:
		equal(accounting.formatNumber(5318008, {
			precision : 3,
			thousand : "__",
			decimal : "--"
		}), "5__318__008--000", 'Correctly handles custom precision and separators passed in via second param options object');

		
		// check rounding:
		equals(accounting.formatNumber(0.615, 2), "0.62", 'Rounds 0.615 up to "0.62" with precision of 2');
		
		// manually and recursively formatted arrays should have same values:
		var numbers = [8008135, [1234, 5678], 1000];
		var formattedManually = [accounting.formatNumber(8008135), [accounting.formatNumber(1234), accounting.formatNumber(5678)], accounting.formatNumber(1000)];
		var formattedRecursively = accounting.formatNumber(numbers);
		equals(formattedRecursively.toString(), formattedManually.toString(), 'can recursively format multi-dimensional arrays');
	});


	test("accounting.formatMoney()", function() {
		equals(accounting.formatMoney(12345678), "$12,345,678.00", "Default usage with default parameters is ok");
		equals(accounting.formatMoney(4999.99, "$ ", 2, ".", ","), "$ 4.999,99", 'custom formatting via straight params works ok');
		equals(accounting.formatMoney(-500000, "£ ", 0), "£ -500,000", 'negative values, custom params, works ok');
		equals(accounting.formatMoney(5318008, { symbol: "GBP",  format: "%v %s" }), "5,318,008.00 GBP", "`format` parameter is observed in string output");
		equals(accounting.formatMoney(1000, { format: "test %v 123 %s test" }), "test 1,000.00 123 $ test", "`format` parameter is observed in string output, despite being rather strange");
		
		// Format param is an object:
		var format = {
			pos: "%s %v",
			neg: "%s (%v)",
			zero:"%s  --"
		}
		equals(accounting.formatMoney(0, { symbol: "GBP",  format:format}), "GBP  --", "`format` parameter provided given as an object with `zero` format, correctly observed in string output");
		equals(accounting.formatMoney(-1000, { symbol: "GBP",  format:format}), "GBP (1,000.00)", "`format` parameter provided given as an object with `neg` format, correctly observed in string output");
		equals(accounting.formatMoney(1000, { symbol: "GBP",  format:{neg:"--%v %s"}}), "GBP1,000.00", "`format` parameter provided, but only `neg` value provided - positive value should be formatted by default format (%s%v)");
		
		accounting.settings.currency.format = "%s%v";
		accounting.formatMoney(0, {format:""});
		equals(typeof accounting.settings.currency.format, "object", "`settings.currency.format` default string value should be reformatted to an object, the first time it is used");
	});


	test("accounting.formatColumn()", function() {
		// standard usage:
		var list = [123, 12345];
		equals(accounting.formatColumn(list, "$ ", 0).toString(), (["$    123", "$ 12,345"]).toString(), "formatColumn works as expected");


		// multi-dimensional array (formatColumn should be applied recursively):
		var list = [[1, 100], [900, 9]];
		equals(accounting.formatColumn(list).toString(), ([["$  1.00", "$100.00"], ["$900.00", "$  9.00"]]).toString(), "formatcolumn works on multi-dimensional array");


		// random numbers, must come back same length:
		var column = accounting.formatColumn([Math.random(), Math.random() * 1000, Math.random() * 10000000]);
		ok((column[0].length === column[2].length && column[1].length === column[2].length), "formatColumn() with 3 random numbers returned strings of matching length");


		// random numbers, must come back same length:
		var column = accounting.formatColumn([Math.random(), Math.random() * 1000, Math.random() * 10000000], {
			format: '(%v] --++== %s',
			thousand: ')(',
			decimal: ')[',
			precision: 3
		});
		ok((column[0].length === column[2].length && column[1].length === column[2].length), "formatColumn() with 3 random numbers returned strings of matching length, even with a weird custom `format` parameter");



	});
	
});
