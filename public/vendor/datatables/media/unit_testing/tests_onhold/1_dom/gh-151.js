// DATA_TEMPLATE: html_table
oTest.fnStart( "Check type is correctly applied to filtering columns" );


$(document).ready( function () {
	// The second column is HTML type, and should be detected as such, while the first
	// column is number type. We should get no results, as a test for the bug, when
	// searching for http
	$('#example').dataTable( {
		columnDefs: [
			{
				targets: [ 0 ],
				searchable: false
			}
		]
	} );
	
	oTest.fnTest(
		"Check html is stripped from second column",
		function () { $('#example').dataTable().fnFilter('http'); },
		function () { return $('div.dataTables_info').html() ==
			'Showing 0 to 0 of 0 entries (filtered from 4 total entries)';
		}
	);
	
	oTest.fnTest(
		"But can filter on text in links",
		function () { $('#example').dataTable().fnFilter('Integrity'); },
		function () { return $('div.dataTables_info').html() ==
			'Showing 1 to 3 of 3 entries (filtered from 4 total entries)';
		}
	);
	
	oTest.fnTest(
		"And on non-link text",
		function () { $('#example').dataTable().fnFilter('EInt'); },
		function () { return $('div.dataTables_info').html() ==
			'Showing 1 to 1 of 1 entries (filtered from 4 total entries)';
		}
	);
	
	oTest.fnTest(
		"No search results on non-serachable data (first column)",
		function () { $('#example').dataTable().fnFilter('2'); },
		function () { return $('div.dataTables_info').html() ==
			'Showing 0 to 0 of 0 entries (filtered from 4 total entries)';
		}
	);
	
	oTest.fnTest(
		"Release search",
		function () { $('#example').dataTable().fnFilter(''); },
		function () { return $('div.dataTables_info').html() ==
			'Showing 1 to 4 of 4 entries';
		}
	);
	
	
	oTest.fnComplete();
} );