// DATA_TEMPLATE: empty_table
oTest.fnStart( "fnServerData for SSP sourced data" );

$(document).ready( function () {
	var mPass;
	
	oTest.fnTest(
		"Argument length",
		function () {
			$('#example').dataTable( {
				"bServerSide": true,
				"sAjaxSource": "../data_sources/param.php",
				"fnServerData": function () {
					mPass = arguments.length;
				}
			} );
		},
		function () { return mPass == 4; }
	);
	
	oTest.fnTest(
		"Url",
		function () {
			$('#example').dataTable( {
				"bDestroy": true,
				"bServerSide": true,
				"sAjaxSource": "../data_sources/param.php",
				"fnServerData": function (sUrl, aoData, fnCallback, oSettings) {
					mPass = sUrl == "../data_sources/param.php";
				}
			} );
		},
		function () { return mPass; }
	);
	
	oTest.fnTest(
		"Data array",
		function () {
			$('#example').dataTable( {
				"bDestroy": true,
				"bServerSide": true,
				"sAjaxSource": "../data_sources/param.php",
				"fnServerData": function (sUrl, aoData, fnCallback, oSettings) {
					mPass = aoData.length==35;
				}
			} );
		},
		function () { return mPass; }
	);
	
	oTest.fnTest(
		"Callback function",
		function () {
			$('#example').dataTable( {
				"bDestroy": true,
				"bServerSide": true,
				"sAjaxSource": "../data_sources/param.php",
				"fnServerData": function (sUrl, aoData, fnCallback, oSettings) {
					mPass = typeof fnCallback == 'function';
				}
			} );
		},
		function () { return mPass; }
	);
	
	
	oTest.fnComplete();
} );