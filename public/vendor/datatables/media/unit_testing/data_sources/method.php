<?php

if ( strcasecmp( $_GET['method'], $_SERVER['REQUEST_METHOD'] ) === 0 ) {
	pass();
}
else {
	fail();
}


function pass()
{
	if ( isset($_REQUEST['sEcho']) ) {
		// Server-side processing
		echo json_encode( array(
			'sEcho' => intval( $_REQUEST['sEcho'] ),
			'iTotalRecords' => 1,
			'iTotalDisplayRecords' => 1,
			'aaData' => array(
				array(1, 2, 3, 4, 5)
			)
		) );
	}
	else {
		// Client-side processing
		echo json_encode( array(
			'aaData' => array(
				array(1, 2, 3, 4, 5)
			)
		) );
	}
}


function fail()
{
	if ( isset($_REQUEST['sEcho']) ) {
		// Server-side processing
		echo json_encode( array(
			'sEcho' => intval( $_REQUEST['sEcho'] ),
			'iTotalRecords' => 0,
			'iTotalDisplayRecords' => 0,
			'aaData' => array()
		) );
	}
	else {
		// Client-side processing
		echo json_encode( array(
			'aaData' => array()
		) );
	}
}

