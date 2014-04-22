// DATA_TEMPLATE: empty_table
oTest.fnStart( "fnServerParams" );


$(document).ready( function () {
	/* Check the default */
	var json = {};
	var oTable = $('#example').dataTable( {
			"sAjaxSource": "../data_sources/param.php"
		} ).on('xhr', function (e, settings, o) {
			json = o;
		} );
	
	oTest.fnWaitTest(
		"jQuery anti-cache parameter was sent",
		null,
		function () {
			return json.get && json.get._;
		}
	);
	
	oTest.fnWaitTest(
		"No other parameters sent",
		null,
		function () {
			return 1 === $.map( json.get, function (val) {
				return val;
			} ).length;
		}
	);
	
	oTest.fnWaitTest(
		"Send additional parameters",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"sAjaxSource": "../data_sources/param.php",
					"fnServerParams": function ( data ) {
						data.push( { name: 'test', value: 'unit' } );
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.test === 'unit';
		}
	);
	
	oTest.fnTest(
		"jQuery anti-cache parameter was still sent",
		null,
		function () {
			return json.get._;
		}
	);
	
	oTest.fnWaitTest(
		"Send multiple parameters",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"sAjaxSource": "../data_sources/param.php",
					"fnServerParams": function ( data ) {
						data.push( { name: 'test', value: 'unit' } );
						data.push( { name: 'tapestry', value: 'king' } );
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.test === 'unit' && json.get.tapestry === 'king';
		}
	);
	
	oTest.fnComplete();
} );
