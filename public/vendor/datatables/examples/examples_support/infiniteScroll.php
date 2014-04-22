<?php
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 * Easy set variables
	 */
	
	/* Array of database columns which should be read and sent back to DataTables. Use a space where
	 * you want to insert a non-database field (for example a counter or static image)
	 */
	$aColumns = array( 'name', 'phone', 'email', 'city', 'zip' );
	
	/* Indexed column (used for fast and accurate table cardinality) */
	$sIndexColumn = "id";
	
	/* DB table to use */
	$sTable = "testData";
	
	/* Database connection information */
	$gaSql['user']       = "";
	$gaSql['password']   = "";
	$gaSql['db']         = "";
	$gaSql['server']     = "localhost";
	
	/* REMOVE THIS LINE (it just includes my SQL connection user/pass) */
	include( $_SERVER['DOCUMENT_ROOT']."/datatables/mysql.php" );
	
	
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
	 * If you just want to use the basic configuration for DataTables with PHP server-side, there is
	 * no need to edit below this line
	 */
	
	/* 
	 * MySQL connection
	 */
	$gaSql['link'] =  mysql_pconnect( $gaSql['server'], $gaSql['user'], $gaSql['password']  ) or
		die( 'Could not open connection to server' );
	
	mysql_select_db( $gaSql['db'], $gaSql['link'] ) or 
		die( 'Could not select database '. $gaSql['db'] );
	
	
	/* 
	 * Paging
	 */
	$sLimit = "";
	if ( isset( $_GET['iStart'] ) && isset( $_GET['iLength'] ) )
	{
		$sLimit = "LIMIT ".mysql_real_escape_string( $_GET['iStart'] ).", ".
			mysql_real_escape_string( $_GET['iLength'] );
	}
	else
	{
		echo '{ "aaData": [] }';
		exit();
	}
	
	/*
	 * SQL queries
	 * Get data to display
	 */
	$sQuery = "
		SELECT ".str_replace(" , ", " ", implode(", ", $aColumns))."
		FROM   $sTable
		ORDER BY name ASC
		$sLimit
	";
	$rResult = mysql_query( $sQuery, $gaSql['link'] ) or die(mysql_error());
	
	/*
	 * Output
	 */
	$sOutput = '{';
	$sOutput .= '"aaData": [ ';
	while ( $aRow = mysql_fetch_array( $rResult ) )
	{
		$sOutput .= "[";
		for ( $i=0 ; $i<count($aColumns) ; $i++ )
		{
			/* General output */
			$sOutput .= '"'.str_replace('"', '\"', $aRow[ $aColumns[$i] ]).'",';
		}
		
		/*
		 * Optional Configuration:
		 * If you need to add any extra columns (add/edit/delete etc) to the table, that aren't in the
		 * database - you can do it here
		 */
		
		
		$sOutput = substr_replace( $sOutput, "", -1 );
		$sOutput .= "],";
	}
	$sOutput = substr_replace( $sOutput, "", -1 );
	$sOutput .= '] }';
	
	echo $sOutput;
?>