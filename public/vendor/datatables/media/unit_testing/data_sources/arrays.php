<?php


if ( isset($_REQUEST['sEcho']) ) {
	$a = dataSrc();
	$a['sEcho'] = intval( $_REQUEST['sEcho'] );
	$a['iTotalRecords'] = 1;
	$a['iTotalDisplayRecords'] = 1;

	echo json_encode( $a );
}
else {
	echo json_encode( dataSrc() );
}



function dataSrc()
{
	if ( !isset( $_REQUEST['dataSrc'] ) ) {
		return array(
			'aaData' => array( 
				array(1, 2, 3, 4, 5)
			)
		);
	}
	else if ( $_REQUEST['dataSrc'] === 'data' ) {
		return array(
			'data' => array( 
				array(1, 2, 3, 4, 5)
			)
		);
	}
	else if ( $_REQUEST['dataSrc'] === 'nested' ) {
		return array(
			'data' => array(
				'inner' => array( 
					array(1, 2, 3, 4, 5)
				)
			)
		);
	}
	else if ( $_REQUEST['dataSrc'] === 'plain' ) {
		return array( 
			array(1, 2, 3, 4, 5)
		);
	}
}


