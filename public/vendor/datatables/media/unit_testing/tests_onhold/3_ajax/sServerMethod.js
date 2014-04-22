// DATA_TEMPLATE: empty_table
oTest.fnStart( "sServerMethod" );


$(document).ready( function () {
	/* Check the default */
	var oTable = $('#example').dataTable( {
		"sAjaxSource": "../data_sources/method.php?method=get"
	} );
	
	oTest.fnWaitTest(
		"Default method was GET",
		null,
		function () {
			// A valid request will place a single row in the table
			return $('tbody td').eq(0).html() === '1';
		}
	);
	
	oTest.fnWaitTest(
		"Can make a POST request",
		function () {
			oSession.fnRestore();
			$('#example').dataTable( {
				"sAjaxSource": "../data_sources/method.php?method=post",
				"sServerMethod": "POST"
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);
	
	oTest.fnWaitTest(
		"Can make a PUT request",
		function () {
			oSession.fnRestore();
			$('#example').dataTable( {
				"sAjaxSource": "../data_sources/method.php?method=put",
				"sServerMethod": "PUT"
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);
	
	oTest.fnComplete();
} );
