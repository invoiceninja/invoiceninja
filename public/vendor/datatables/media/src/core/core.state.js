

/**
 * Save the state of a table in a cookie such that the page can be reloaded
 *  @param {object} oSettings dataTables settings object
 *  @memberof DataTable#oApi
 */
function _fnSaveState ( oSettings )
{
	if ( !oSettings.oFeatures.bStateSave || oSettings.bDestroying )
	{
		return;
	}

	/* Store the interesting variables */
	var i, iLen, bInfinite=oSettings.oScroll.bInfinite;
	var oState = {
		"iCreate":      new Date().getTime(),
		"iStart":       (bInfinite ? 0 : oSettings._iDisplayStart),
		"iEnd":         (bInfinite ? oSettings._iDisplayLength : oSettings._iDisplayEnd),
		"iLength":      oSettings._iDisplayLength,
		"aaSorting":    $.extend( true, [], oSettings.aaSorting ),
		"oSearch":      $.extend( true, {}, oSettings.oPreviousSearch ),
		"aoSearchCols": $.extend( true, [], oSettings.aoPreSearchCols ),
		"abVisCols":    []
	};

	for ( i=0, iLen=oSettings.aoColumns.length ; i<iLen ; i++ )
	{
		oState.abVisCols.push( oSettings.aoColumns[i].bVisible );
	}

	_fnCallbackFire( oSettings, "aoStateSaveParams", 'stateSaveParams', [oSettings, oState] );
	
	oSettings.fnStateSave.call( oSettings.oInstance, oSettings, oState );
}


/**
 * Attempt to load a saved table state from a cookie
 *  @param {object} oSettings dataTables settings object
 *  @param {object} oInit DataTables init object so we can override settings
 *  @memberof DataTable#oApi
 */
function _fnLoadState ( oSettings, oInit )
{
	if ( !oSettings.oFeatures.bStateSave )
	{
		return;
	}

	var oData = oSettings.fnStateLoad.call( oSettings.oInstance, oSettings );
	if ( !oData )
	{
		return;
	}
	
	/* Allow custom and plug-in manipulation functions to alter the saved data set and
	 * cancelling of loading by returning false
	 */
	var abStateLoad = _fnCallbackFire( oSettings, 'aoStateLoadParams', 'stateLoadParams', [oSettings, oData] );
	if ( $.inArray( false, abStateLoad ) !== -1 )
	{
		return;
	}
	
	/* Store the saved state so it might be accessed at any time */
	oSettings.oLoadedState = $.extend( true, {}, oData );
	
	/* Restore key features */
	oSettings._iDisplayStart    = oData.iStart;
	oSettings.iInitDisplayStart = oData.iStart;
	oSettings._iDisplayEnd      = oData.iEnd;
	oSettings._iDisplayLength   = oData.iLength;
	oSettings.aaSorting         = oData.aaSorting.slice();
	oSettings.saved_aaSorting   = oData.aaSorting.slice();
	
	/* Search filtering  */
	$.extend( oSettings.oPreviousSearch, oData.oSearch );
	$.extend( true, oSettings.aoPreSearchCols, oData.aoSearchCols );
	
	/* Column visibility state
	 * Pass back visibility settings to the init handler, but to do not here override
	 * the init object that the user might have passed in
	 */
	oInit.saved_aoColumns = [];
	for ( var i=0 ; i<oData.abVisCols.length ; i++ )
	{
		oInit.saved_aoColumns[i] = {};
		oInit.saved_aoColumns[i].bVisible = oData.abVisCols[i];
	}

	_fnCallbackFire( oSettings, 'aoStateLoaded', 'stateLoaded', [oSettings, oData] );
}


/**
 * Create a new cookie with a value to store the state of a table
 *  @param {string} sName name of the cookie to create
 *  @param {string} sValue the value the cookie should take
 *  @param {int} iSecs duration of the cookie
 *  @param {string} sBaseName sName is made up of the base + file name - this is the base
 *  @param {function} fnCallback User definable function to modify the cookie
 *  @memberof DataTable#oApi
 */
function _fnCreateCookie ( sName, sValue, iSecs, sBaseName, fnCallback )
{
	var date = new Date();
	date.setTime( date.getTime()+(iSecs*1000) );
	
	/* 
	 * Shocking but true - it would appear IE has major issues with having the path not having
	 * a trailing slash on it. We need the cookie to be available based on the path, so we
	 * have to append the file name to the cookie name. Appalling. Thanks to vex for adding the
	 * patch to use at least some of the path
	 */
	var aParts = window.location.pathname.split('/');
	var sNameFile = sName + '_' + aParts.pop().replace(/[\/:]/g,"").toLowerCase();
	var sFullCookie, oData;
	
	if ( fnCallback !== null )
	{
		oData = (typeof $.parseJSON === 'function') ? 
			$.parseJSON( sValue ) : eval( '('+sValue+')' );
		sFullCookie = fnCallback( sNameFile, oData, date.toGMTString(),
			aParts.join('/')+"/" );
	}
	else
	{
		sFullCookie = sNameFile + "=" + encodeURIComponent(sValue) +
			"; expires=" + date.toGMTString() +"; path=" + aParts.join('/')+"/";
	}
	
	/* Are we going to go over the cookie limit of 4KiB? If so, try to delete a cookies
	 * belonging to DataTables.
	 */
	var
		aCookies =document.cookie.split(';'),
		iNewCookieLen = sFullCookie.split(';')[0].length,
		aOldCookies = [];
	
	if ( iNewCookieLen+document.cookie.length+10 > 4096 ) /* Magic 10 for padding */
	{
		for ( var i=0, iLen=aCookies.length ; i<iLen ; i++ )
		{
			if ( aCookies[i].indexOf( sBaseName ) != -1 )
			{
				/* It's a DataTables cookie, so eval it and check the time stamp */
				var aSplitCookie = aCookies[i].split('=');
				try {
					oData = eval( '('+decodeURIComponent(aSplitCookie[1])+')' );

					if ( oData && oData.iCreate )
					{
						aOldCookies.push( {
							"name": aSplitCookie[0],
							"time": oData.iCreate
						} );
					}
				}
				catch( e ) {}
			}
		}

		// Make sure we delete the oldest ones first
		aOldCookies.sort( function (a, b) {
			return b.time - a.time;
		} );

		// Eliminate as many old DataTables cookies as we need to
		while ( iNewCookieLen + document.cookie.length + 10 > 4096 ) {
			if ( aOldCookies.length === 0 ) {
				// Deleted all DT cookies and still not enough space. Can't state save
				return;
			}
			
			var old = aOldCookies.pop();
			document.cookie = old.name+"=; expires=Thu, 01-Jan-1970 00:00:01 GMT; path="+
				aParts.join('/') + "/";
		}
	}
	
	document.cookie = sFullCookie;
}


/**
 * Read an old cookie to get a cookie with an old table state
 *  @param {string} sName name of the cookie to read
 *  @returns {string} contents of the cookie - or null if no cookie with that name found
 *  @memberof DataTable#oApi
 */
function _fnReadCookie ( sName )
{
	var
		aParts = window.location.pathname.split('/'),
		sNameEQ = sName + '_' + aParts[aParts.length-1].replace(/[\/:]/g,"").toLowerCase() + '=',
	 	sCookieContents = document.cookie.split(';');
	
	for( var i=0 ; i<sCookieContents.length ; i++ )
	{
		var c = sCookieContents[i];
		
		while (c.charAt(0)==' ')
		{
			c = c.substring(1,c.length);
		}
		
		if (c.indexOf(sNameEQ) === 0)
		{
			return decodeURIComponent( c.substring(sNameEQ.length,c.length) );
		}
	}
	return null;
}

