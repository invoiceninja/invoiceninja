<?php


if ( isset($_REQUEST['sEcho']) ) {
	echo json_encode( array(
		'sEcho' => intval( $_REQUEST['sEcho'] ),
		'iTotalRecords' => 1,
		'iTotalDisplayRecords' => 1,
		'aaData' => array(
			array(
				'engine' => 10,
				'browser' => 20,
				'platform' => 30,
				'version' => 40,
				'grade' => 50
			)
		)
	) );
}
else {
	echo json_encode( array(
		'aaData' => array(
			array(
				'engine' => 10,
				'browser' => 20,
				'platform' => 30,
				'version' => 40,
				'grade' => 50
			)
		)
	) );
}

