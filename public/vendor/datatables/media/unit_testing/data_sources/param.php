<?php


if ( isset($_REQUEST['sEcho']) ) {
	echo json_encode( array(
		'sEcho' => intval( $_REQUEST['sEcho'] ),
		'iTotalRecords' => 1,
		'iTotalDisplayRecords' => 1,
		'aaData' => array(
			array(1, 2, 3, 4, 5)
		),
		'post' => xss( $_POST ),
		'get' => xss( $_GET ),
		'post_length' => count( array_keys( $_POST ) ),
		'get_length' => count( array_keys( $_GET ) )
	) );
}
else {
	echo json_encode( array(
		'aaData' => array( 
			array(1, 2, 3, 4, 5)
		),
		'post' => xss( $_POST ),
		'get' => xss( $_GET ),
		'post_length' => count( array_keys( $_POST ) ),
		'get_length' => count( array_keys( $_GET ) )
	) );
}



// This script shouldn't be hosted on a public server, but to prevent attacks:
function xss ( $a )
{
	$out = array();

	foreach ($a as $key => $value) {
		$out[ $key ] = htmlentities( $value );
	}

	return $out;
}
