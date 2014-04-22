<?php
  /* MySQL connection */
	include( $_SERVER['DOCUMENT_ROOT']."/datatables/mysql.php" ); /* ;-) */
	
	/* 
	 * Local functions
	 */
	function fatal_error ( $sErrorMessage = '' )
	{
		header( $_SERVER['SERVER_PROTOCOL'] .' 500 Internal Server Error' );
		die( $sErrorMessage );
	}

	
	/* 
	 * MySQL connection
	 */
	if ( ! $gaSql['link'] = mysql_pconnect( $gaSql['server'], $gaSql['user'], $gaSql['password']  ) )
	{
		fatal_error( 'Could not open connection to server' );
	}

	if ( ! mysql_select_db( $gaSql['db'], $gaSql['link'] ) )
	{
		fatal_error( 'Could not select database ' );
	}


	
	/* Paging */
	$sLimit = "";
	if ( isset( $_GET['iDisplayStart'] ) && $_GET['iDisplayLength'] != '-1' )
	{
		$sLimit = "LIMIT ".mysql_real_escape_string( $_GET['iDisplayStart'] ).", ".
			mysql_real_escape_string( $_GET['iDisplayLength'] );
	}
	
	/* Ordering */
	if ( isset( $_GET['iSortCol_0'] ) )
	{
		$sOrder = "ORDER BY  ";
		for ( $i=0 ; $i<mysql_real_escape_string( $_GET['iSortingCols'] ) ; $i++ )
		{
			$sOrder .= fnColumnToField(mysql_real_escape_string( $_GET['iSortCol_'.$i] ))."
			 	".mysql_real_escape_string( $_GET['sSortDir_'.$i] ) .", ";
		}
		$sOrder = substr_replace( $sOrder, "", -2 );
	}
	
	/* Filtering - NOTE this does not match the built-in DataTables filtering which does it
	 * word by word on any field. It's possible to do here, but concerned about efficiency
	 * on very large tables, and MySQL's regex functionality is very limited
	 */
	$sWhere = "";
	if ( $_GET['sSearch'] != "" )
	{
		$sWhere = "WHERE ( engine LIKE '%".mysql_real_escape_string( $_GET['sSearch'] )."%' OR ".
		                "browser LIKE '%".mysql_real_escape_string( $_GET['sSearch'] )."%' OR ".
		                "platform LIKE '%".mysql_real_escape_string( $_GET['sSearch'] )."%' OR ".
		                "version LIKE '%".mysql_real_escape_string( $_GET['sSearch'] )."%' OR ".
		                "grade LIKE '%".mysql_real_escape_string( $_GET['sSearch'] )."%' )";
	}
	
	for ( $i=0 ; $i<$_GET['iColumns'] ; $i++ )
	{
		if ( $_GET['sSearch_'.$i] != '' )
		{
			if ( $sWhere != "" )
			{
				$sWhere .= " AND ";
			}
			else
			{
				$sWhere .= "WHERE ";
			}
			$sWhere .= fnColumnToField($i) ." LIKE '%".mysql_real_escape_string( $_GET['sSearch_'.$i] )."%'";
		}
	}
	
	$sQuery = "
		SELECT SQL_CALC_FOUND_ROWS id, engine, browser, platform, version, grade
		FROM   ajax
		$sWhere
		$sOrder
		$sLimit
	";
	$rResult = mysql_query( $sQuery, $gaSql['link'] ) or fatal_error( 'MySQL Error: ' . mysql_errno() );
	
	$sQuery = "
		SELECT FOUND_ROWS()
	";
	$rResultFilterTotal = mysql_query( $sQuery, $gaSql['link'] ) or fatal_error( 'MySQL Error: ' . mysql_errno() );
	$aResultFilterTotal = mysql_fetch_array($rResultFilterTotal);
	$iFilteredTotal = $aResultFilterTotal[0];
	
	$sQuery = "
		SELECT COUNT(id)
		FROM   ajax
	";
	$rResultTotal = mysql_query( $sQuery, $gaSql['link'] ) or fatal_error( 'MySQL Error: ' . mysql_errno() );
	$aResultTotal = mysql_fetch_array($rResultTotal);
	$iTotal = $aResultTotal[0];
	
	$sOutput = '{';
	$sOutput .= '"sEcho": '.intval($_GET['sEcho']).', ';
	$sOutput .= '"iTotalRecords": '.$iTotal.', ';
	$sOutput .= '"iTotalDisplayRecords": '.$iFilteredTotal.', ';
	$sOutput .= '"aaData": [ ';
	while ( $aRow = mysql_fetch_array( $rResult ) )
	{
		$sOutput .= "[";
		$sOutput .= '"'.str_replace('"', '\"', $aRow['engine']).'",';
		$sOutput .= '"'.str_replace('"', '\"', $aRow['browser']).'",';
		$sOutput .= '"'.str_replace('"', '\"', $aRow['platform']).'",';
		if ( $aRow['version'] == "0" )
			$sOutput .= '"-",';
		else
			$sOutput .= '"'.str_replace('"', '\"', $aRow['version']).'",';
		$sOutput .= '"'.str_replace('"', '\"', $aRow['grade']).'"';
		$sOutput .= "],";
	}
	$sOutput = substr_replace( $sOutput, "", -1 );
	$sOutput .= '] }';
	
	echo $sOutput;
	
	
	function fnColumnToField( $i )
	{
		if ( $i == 0 )
			return "engine";
		else if ( $i == 1 )
			return "browser";
		else if ( $i == 2 )
			return "platform";
		else if ( $i == 3 )
			return "version";
		else if ( $i == 4 )
			return "grade";
	}
?>