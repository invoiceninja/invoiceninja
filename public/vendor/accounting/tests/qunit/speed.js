(function() {

	var numbers = [];
	for (var i=0; i<1000; i++) numbers.push((Math.random() * (1000*i)));
	var strings = $.map(numbers, function(num){ return accounting.formatMoney(num*1000, "HK$ "); });

	JSLitmus.test('unformat()', function(count) {
		var i = 0;
		while ( count-- ) {
			accounting.unformat(strings[i])
			i++;
			i > strings.length && (i = 0);
		}
	});

	JSLitmus.test('unformat(array)', function(count) {
		var i = 0;
		while ( count-- ) {
			accounting.unformat([strings[i], strings[i+1]]);
			i += 2;
			i > numbers.length && (i = 0);
        }
	});

	JSLitmus.test('toFixed()', function(count) {
		while ( count-- ) {
			accounting.toFixed(count*1000, 2);
		}
	});

	JSLitmus.test('formatNumber()', function(count) {
		var i = 0;
		while ( count-- ) {
			accounting.formatNumber(numbers[i]);
			i++;
			i > numbers.length && (i = 0);
		}
	});

	JSLitmus.test('formatNumber(array)', function(count) {
		var i = 0;
		while ( count-- ) {
            accounting.formatNumber([numbers[i], numbers[i+1]]);
			i += 2;
			i > numbers.length && (i = 0);
        }
	});

	JSLitmus.test('formatMoney()', function(count) {
		var i = 0;
		while ( count-- ) {
			accounting.formatMoney(numbers[i]);
			i++;
			i > numbers.length && (i = 0);
		}
	});

	JSLitmus.test('formatMoney(array)', function(count) {
		var i = 0;
		while ( count-- ) {
			accounting.formatMoney([numbers[i], numbers[i+1]]);
			i += 2;
			i > numbers.length && (i = 0);
        }
	});

	JSLitmus.test('formatColumn()', function(count) {
		var i = 0;
		while ( count-- ) {
			accounting.formatColumn([numbers[i], numbers[i+1]]);
			i += 2;
			i > numbers.length && (i = 0);
		}
	});

})();
