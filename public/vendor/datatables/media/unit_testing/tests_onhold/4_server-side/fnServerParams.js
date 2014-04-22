// DATA_TEMPLATE: empty_table
oTest.fnStart( "fnServerParams" );


$(document).ready( function () {
	/* Check the default */
	var json = {};
	var oTable = $('#example').dataTable( {
			"bServerSide": true,
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
		"Default SSP parameters were sent",
		null,
		function () {
			return 36 === $.map( json.get, function (val) {
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
					"bServerSide": true,
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
		"Default parameters were still sent",
		null,
		function () {
			return 37 === $.map( json.get, function (val) {
				return val;
			} ).length;
		}
	);
	
	oTest.fnWaitTest(
		"Send multiple parameters",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"bServerSide": true,
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
	
	oTest.fnWaitTest(
		"Delete parameters",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"bServerSide": true,
					"sAjaxSource": "../data_sources/param.php",
					"fnServerParams": function ( data ) {
						var remove = function ( a, param ) {
							for ( var i=0 ; i<a.length ; i++ ) {
								if ( a[i].name === param ) {
									a.splice( i, 1 );
									return;
								}
							}
						};
						remove( data, 'bRegex_0' );
						remove( data, 'bRegex_1' );
						remove( data, 'bRegex_2' );
						remove( data, 'bRegex_3' );
						remove( data, 'bRegex_4' );
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && 31 === $.map( json.get, function (val) {
				return val;
			} ).length;
		}
	);
	
	oTest.fnComplete();
} );
