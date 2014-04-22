

/**
 * Initialisation options that can be given to DataTables at initialisation 
 * time.
 *  @namespace
 */
DataTable.defaults = {
	/**
	 * An array of data to use for the table, passed in at initialisation which 
	 * will be used in preference to any data which is already in the DOM. This is
	 * particularly useful for constructing tables purely in Javascript, for
	 * example with a custom Ajax call.
	 *  @type array
	 *  @default null
	 *  @dtopt Option
	 * 
	 *  @example
	 *    // Using a 2D array data source
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "aaData": [
	 *          ['Trident', 'Internet Explorer 4.0', 'Win 95+', 4, 'X'],
	 *          ['Trident', 'Internet Explorer 5.0', 'Win 95+', 5, 'C'],
	 *        ],
	 *        "aoColumns": [
	 *          { "sTitle": "Engine" },
	 *          { "sTitle": "Browser" },
	 *          { "sTitle": "Platform" },
	 *          { "sTitle": "Version" },
	 *          { "sTitle": "Grade" }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using an array of objects as a data source (mData)
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "aaData": [
	 *          {
	 *            "engine":   "Trident",
	 *            "browser":  "Internet Explorer 4.0",
	 *            "platform": "Win 95+",
	 *            "version":  4,
	 *            "grade":    "X"
	 *          },
	 *          {
	 *            "engine":   "Trident",
	 *            "browser":  "Internet Explorer 5.0",
	 *            "platform": "Win 95+",
	 *            "version":  5,
	 *            "grade":    "C"
	 *          }
	 *        ],
	 *        "aoColumns": [
	 *          { "sTitle": "Engine",   "mData": "engine" },
	 *          { "sTitle": "Browser",  "mData": "browser" },
	 *          { "sTitle": "Platform", "mData": "platform" },
	 *          { "sTitle": "Version",  "mData": "version" },
	 *          { "sTitle": "Grade",    "mData": "grade" }
	 *        ]
	 *      } );
	 *    } );
	 */
	"aaData": null,


	/**
	 * If sorting is enabled, then DataTables will perform a first pass sort on 
	 * initialisation. You can define which column(s) the sort is performed upon, 
	 * and the sorting direction, with this variable. The aaSorting array should 
	 * contain an array for each column to be sorted initially containing the 
	 * column's index and a direction string ('asc' or 'desc').
	 *  @type array
	 *  @default [[0,'asc']]
	 *  @dtopt Option
	 * 
	 *  @example
	 *    // Sort by 3rd column first, and then 4th column
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aaSorting": [[2,'asc'], [3,'desc']]
	 *      } );
	 *    } );
	 *    
	 *    // No initial sorting
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aaSorting": []
	 *      } );
	 *    } );
	 */
	"aaSorting": [[0,'asc']],


	/**
	 * This parameter is basically identical to the aaSorting parameter, but 
	 * cannot be overridden by user interaction with the table. What this means 
	 * is that you could have a column (visible or hidden) which the sorting will 
	 * always be forced on first - any sorting after that (from the user) will 
	 * then be performed as required. This can be useful for grouping rows 
	 * together.
	 *  @type array
	 *  @default null
	 *  @dtopt Option
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aaSortingFixed": [[0,'asc']]
	 *      } );
	 *    } )
	 */
	"aaSortingFixed": null,


	/**
	 * This parameter allows you to readily specify the entries in the length drop
	 * down menu that DataTables shows when pagination is enabled. It can be 
	 * either a 1D array of options which will be used for both the displayed 
	 * option and the value, or a 2D array which will use the array in the first 
	 * position as the value, and the array in the second position as the 
	 * displayed options (useful for language strings such as 'All').
	 *  @type array
	 *  @default [ 10, 25, 50, 100 ]
	 *  @dtopt Option
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aLengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]]
	 *      } );
	 *    } );
	 *  
	 *  @example
	 *    // Setting the default display length as well as length menu
	 *    // This is likely to be wanted if you remove the '10' option which
	 *    // is the iDisplayLength default.
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "iDisplayLength": 25,
	 *        "aLengthMenu": [[25, 50, 100, -1], [25, 50, 100, "All"]]
	 *      } );
	 *    } );
	 */
	"aLengthMenu": [ 10, 25, 50, 100 ],


	/**
	 * The aoColumns option in the initialisation parameter allows you to define
	 * details about the way individual columns behave. For a full list of
	 * column options that can be set, please see 
	 * {@link DataTable.defaults.columns}. Note that if you use aoColumns to
	 * define your columns, you must have an entry in the array for every single
	 * column that you have in your table (these can be null if you don't which
	 * to specify any options).
	 *  @member
	 */
	"aoColumns": null,

	/**
	 * Very similar to aoColumns, aoColumnDefs allows you to target a specific 
	 * column, multiple columns, or all columns, using the aTargets property of 
	 * each object in the array. This allows great flexibility when creating 
	 * tables, as the aoColumnDefs arrays can be of any length, targeting the 
	 * columns you specifically want. aoColumnDefs may use any of the column 
	 * options available: {@link DataTable.defaults.columns}, but it _must_
	 * have aTargets defined in each object in the array. Values in the aTargets
	 * array may be:
	 *   <ul>
	 *     <li>a string - class name will be matched on the TH for the column</li>
	 *     <li>0 or a positive integer - column index counting from the left</li>
	 *     <li>a negative integer - column index counting from the right</li>
	 *     <li>the string "_all" - all columns (i.e. assign a default)</li>
	 *   </ul>
	 *  @member
	 */
	"aoColumnDefs": null,


	/**
	 * Basically the same as oSearch, this parameter defines the individual column
	 * filtering state at initialisation time. The array must be of the same size 
	 * as the number of columns, and each element be an object with the parameters
	 * "sSearch" and "bEscapeRegex" (the latter is optional). 'null' is also
	 * accepted and the default will be used.
	 *  @type array
	 *  @default []
	 *  @dtopt Option
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoSearchCols": [
	 *          null,
	 *          { "sSearch": "My filter" },
	 *          null,
	 *          { "sSearch": "^[0-9]", "bEscapeRegex": false }
	 *        ]
	 *      } );
	 *    } )
	 */
	"aoSearchCols": [],


	/**
	 * An array of CSS classes that should be applied to displayed rows. This 
	 * array may be of any length, and DataTables will apply each class 
	 * sequentially, looping when required.
	 *  @type array
	 *  @default null <i>Will take the values determined by the oClasses.sStripe*
	 *    options</i>
	 *  @dtopt Option
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "asStripeClasses": [ 'strip1', 'strip2', 'strip3' ]
	 *      } );
	 *    } )
	 */
	"asStripeClasses": null,


	/**
	 * Enable or disable automatic column width calculation. This can be disabled
	 * as an optimisation (it takes some time to calculate the widths) if the
	 * tables widths are passed in using aoColumns.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bAutoWidth": false
	 *      } );
	 *    } );
	 */
	"bAutoWidth": true,


	/**
	 * Deferred rendering can provide DataTables with a huge speed boost when you
	 * are using an Ajax or JS data source for the table. This option, when set to
	 * true, will cause DataTables to defer the creation of the table elements for
	 * each row until they are needed for a draw - saving a significant amount of
	 * time.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "sAjaxSource": "sources/arrays.txt",
	 *        "bDeferRender": true
	 *      } );
	 *    } );
	 */
	"bDeferRender": false,


	/**
	 * Replace a DataTable which matches the given selector and replace it with 
	 * one which has the properties of the new initialisation object passed. If no
	 * table matches the selector, then the new DataTable will be constructed as
	 * per normal.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sScrollY": "200px",
	 *        "bPaginate": false
	 *      } );
	 *      
	 *      // Some time later....
	 *      $('#example').dataTable( {
	 *        "bFilter": false,
	 *        "bDestroy": true
	 *      } );
	 *    } );
	 */
	"bDestroy": false,


	/**
	 * Enable or disable filtering of data. Filtering in DataTables is "smart" in
	 * that it allows the end user to input multiple words (space separated) and
	 * will match a row containing those words, even if not in the order that was
	 * specified (this allow matching across multiple columns). Note that if you
	 * wish to use filtering in DataTables this must remain 'true' - to remove the
	 * default filtering input box and retain filtering abilities, please use
	 * {@link DataTable.defaults.sDom}.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bFilter": false
	 *      } );
	 *    } );
	 */
	"bFilter": true,


	/**
	 * Enable or disable the table information display. This shows information 
	 * about the data that is currently visible on the page, including information
	 * about filtered data if that action is being performed.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bInfo": false
	 *      } );
	 *    } );
	 */
	"bInfo": true,


	/**
	 * Enable jQuery UI ThemeRoller support (required as ThemeRoller requires some
	 * slightly different and additional mark-up from what DataTables has
	 * traditionally used).
	 *  @type boolean
	 *  @default false
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bJQueryUI": true
	 *      } );
	 *    } );
	 */
	"bJQueryUI": false,


	/**
	 * Allows the end user to select the size of a formatted page from a select
	 * menu (sizes are 10, 25, 50 and 100). Requires pagination (bPaginate).
	 *  @type boolean
	 *  @default true
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bLengthChange": false
	 *      } );
	 *    } );
	 */
	"bLengthChange": true,


	/**
	 * Enable or disable pagination.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bPaginate": false
	 *      } );
	 *    } );
	 */
	"bPaginate": true,


	/**
	 * Enable or disable the display of a 'processing' indicator when the table is
	 * being processed (e.g. a sort). This is particularly useful for tables with
	 * large amounts of data where it can take a noticeable amount of time to sort
	 * the entries.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bProcessing": true
	 *      } );
	 *    } );
	 */
	"bProcessing": false,


	/**
	 * Retrieve the DataTables object for the given selector. Note that if the
	 * table has already been initialised, this parameter will cause DataTables
	 * to simply return the object that has already been set up - it will not take
	 * account of any changes you might have made to the initialisation object
	 * passed to DataTables (setting this parameter to true is an acknowledgement
	 * that you understand this). bDestroy can be used to reinitialise a table if
	 * you need.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      initTable();
	 *      tableActions();
	 *    } );
	 *    
	 *    function initTable ()
	 *    {
	 *      return $('#example').dataTable( {
	 *        "sScrollY": "200px",
	 *        "bPaginate": false,
	 *        "bRetrieve": true
	 *      } );
	 *    }
	 *    
	 *    function tableActions ()
	 *    {
	 *      var oTable = initTable();
	 *      // perform API operations with oTable 
	 *    }
	 */
	"bRetrieve": false,


	/**
	 * Indicate if DataTables should be allowed to set the padding / margin
	 * etc for the scrolling header elements or not. Typically you will want
	 * this.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bScrollAutoCss": false,
	 *        "sScrollY": "200px"
	 *      } );
	 *    } );
	 */
	"bScrollAutoCss": true,


	/**
	 * When vertical (y) scrolling is enabled, DataTables will force the height of
	 * the table's viewport to the given height at all times (useful for layout).
	 * However, this can look odd when filtering data down to a small data set,
	 * and the footer is left "floating" further down. This parameter (when
	 * enabled) will cause DataTables to collapse the table's viewport down when
	 * the result set will fit within the given Y height.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sScrollY": "200",
	 *        "bScrollCollapse": true
	 *      } );
	 *    } );
	 */
	"bScrollCollapse": false,


	/**
	 * Enable infinite scrolling for DataTables (to be used in combination with
	 * sScrollY). Infinite scrolling means that DataTables will continually load
	 * data as a user scrolls through a table, which is very useful for large
	 * dataset. This cannot be used with pagination, which is automatically
	 * disabled. Note - the Scroller extra for DataTables is recommended in
	 * in preference to this option.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bScrollInfinite": true,
	 *        "bScrollCollapse": true,
	 *        "sScrollY": "200px"
	 *      } );
	 *    } );
	 */
	"bScrollInfinite": false,


	/**
	 * Configure DataTables to use server-side processing. Note that the
	 * sAjaxSource parameter must also be given in order to give DataTables a
	 * source to obtain the required data for each draw.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Features
	 *  @dtopt Server-side
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bServerSide": true,
	 *        "sAjaxSource": "xhr.php"
	 *      } );
	 *    } );
	 */
	"bServerSide": false,


	/**
	 * Enable or disable sorting of columns. Sorting of individual columns can be
	 * disabled by the "bSortable" option for each column.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bSort": false
	 *      } );
	 *    } );
	 */
	"bSort": true,


	/**
	 * Allows control over whether DataTables should use the top (true) unique
	 * cell that is found for a single column, or the bottom (false - default).
	 * This is useful when using complex headers.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bSortCellsTop": true
	 *      } );
	 *    } );
	 */
	"bSortCellsTop": false,


	/**
	 * Enable or disable the addition of the classes 'sorting_1', 'sorting_2' and
	 * 'sorting_3' to the columns which are currently being sorted on. This is
	 * presented as a feature switch as it can increase processing time (while
	 * classes are removed and added) so for large data sets you might want to
	 * turn this off.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bSortClasses": false
	 *      } );
	 *    } );
	 */
	"bSortClasses": true,


	/**
	 * Enable or disable state saving. When enabled a cookie will be used to save
	 * table display information such as pagination information, display length,
	 * filtering and sorting. As such when the end user reloads the page the
	 * display display will match what thy had previously set up.
	 *  @type boolean
	 *  @default false
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "bStateSave": true
	 *      } );
	 *    } );
	 */
	"bStateSave": false,


	/**
	 * Customise the cookie and / or the parameters being stored when using
	 * DataTables with state saving enabled. This function is called whenever
	 * the cookie is modified, and it expects a fully formed cookie string to be
	 * returned. Note that the data object passed in is a Javascript object which
	 * must be converted to a string (JSON.stringify for example).
	 *  @type function
	 *  @param {string} sName Name of the cookie defined by DataTables
	 *  @param {object} oData Data to be stored in the cookie
	 *  @param {string} sExpires Cookie expires string
	 *  @param {string} sPath Path of the cookie to set
	 *  @returns {string} Cookie formatted string (which should be encoded by
	 *    using encodeURIComponent())
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function () {
	 *      $('#example').dataTable( {
	 *        "fnCookieCallback": function (sName, oData, sExpires, sPath) {
	 *          // Customise oData or sName or whatever else here
	 *          return sName + "="+JSON.stringify(oData)+"; expires=" + sExpires +"; path=" + sPath;
	 *        }
	 *      } );
	 *    } );
	 */
	"fnCookieCallback": null,


	/**
	 * This function is called when a TR element is created (and all TD child
	 * elements have been inserted), or registered if using a DOM source, allowing
	 * manipulation of the TR element (adding classes etc).
	 *  @type function
	 *  @param {node} nRow "TR" element for the current row
	 *  @param {array} aData Raw data array for this row
	 *  @param {int} iDataIndex The index of this row in aoData
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnCreatedRow": function( nRow, aData, iDataIndex ) {
	 *          // Bold the grade for all 'A' grade browsers
	 *          if ( aData[4] == "A" )
	 *          {
	 *            $('td:eq(4)', nRow).html( '<b>A</b>' );
	 *          }
	 *        }
	 *      } );
	 *    } );
	 */
	"fnCreatedRow": null,


	/**
	 * This function is called on every 'draw' event, and allows you to
	 * dynamically modify any aspect you want about the created DOM.
	 *  @type function
	 *  @param {object} oSettings DataTables settings object
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnDrawCallback": function( oSettings ) {
	 *          alert( 'DataTables has redrawn the table' );
	 *        }
	 *      } );
	 *    } );
	 */
	"fnDrawCallback": null,


	/**
	 * Identical to fnHeaderCallback() but for the table footer this function
	 * allows you to modify the table footer on every 'draw' even.
	 *  @type function
	 *  @param {node} nFoot "TR" element for the footer
	 *  @param {array} aData Full table data (as derived from the original HTML)
	 *  @param {int} iStart Index for the current display starting point in the 
	 *    display array
	 *  @param {int} iEnd Index for the current display ending point in the 
	 *    display array
	 *  @param {array int} aiDisplay Index array to translate the visual position
	 *    to the full data array
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnFooterCallback": function( nFoot, aData, iStart, iEnd, aiDisplay ) {
	 *          nFoot.getElementsByTagName('th')[0].innerHTML = "Starting index is "+iStart;
	 *        }
	 *      } );
	 *    } )
	 */
	"fnFooterCallback": null,


	/**
	 * When rendering large numbers in the information element for the table
	 * (i.e. "Showing 1 to 10 of 57 entries") DataTables will render large numbers
	 * to have a comma separator for the 'thousands' units (e.g. 1 million is
	 * rendered as "1,000,000") to help readability for the end user. This
	 * function will override the default method DataTables uses.
	 *  @type function
	 *  @member
	 *  @param {int} iIn number to be formatted
	 *  @returns {string} formatted string for DataTables to show the number
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnFormatNumber": function ( iIn ) {
	 *          if ( iIn &lt; 1000 ) {
	 *            return iIn;
	 *          } else {
	 *            var 
	 *              s=(iIn+""), 
	 *              a=s.split(""), out="", 
	 *              iLen=s.length;
	 *            
	 *            for ( var i=0 ; i&lt;iLen ; i++ ) {
	 *              if ( i%3 === 0 &amp;&amp; i !== 0 ) {
	 *                out = "'"+out;
	 *              }
	 *              out = a[iLen-i-1]+out;
	 *            }
	 *          }
	 *          return out;
	 *        };
	 *      } );
	 *    } );
	 */
	"fnFormatNumber": function ( iIn ) {
		if ( iIn < 1000 )
		{
			// A small optimisation for what is likely to be the majority of use cases
			return iIn;
		}

		var s=(iIn+""), a=s.split(""), out="", iLen=s.length;
		
		for ( var i=0 ; i<iLen ; i++ )
		{
			if ( i%3 === 0 && i !== 0 )
			{
				out = this.oLanguage.sInfoThousands+out;
			}
			out = a[iLen-i-1]+out;
		}
		return out;
	},


	/**
	 * This function is called on every 'draw' event, and allows you to
	 * dynamically modify the header row. This can be used to calculate and
	 * display useful information about the table.
	 *  @type function
	 *  @param {node} nHead "TR" element for the header
	 *  @param {array} aData Full table data (as derived from the original HTML)
	 *  @param {int} iStart Index for the current display starting point in the
	 *    display array
	 *  @param {int} iEnd Index for the current display ending point in the
	 *    display array
	 *  @param {array int} aiDisplay Index array to translate the visual position
	 *    to the full data array
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnHeaderCallback": function( nHead, aData, iStart, iEnd, aiDisplay ) {
	 *          nHead.getElementsByTagName('th')[0].innerHTML = "Displaying "+(iEnd-iStart)+" records";
	 *        }
	 *      } );
	 *    } )
	 */
	"fnHeaderCallback": null,


	/**
	 * The information element can be used to convey information about the current
	 * state of the table. Although the internationalisation options presented by
	 * DataTables are quite capable of dealing with most customisations, there may
	 * be times where you wish to customise the string further. This callback
	 * allows you to do exactly that.
	 *  @type function
	 *  @param {object} oSettings DataTables settings object
	 *  @param {int} iStart Starting position in data for the draw
	 *  @param {int} iEnd End position in data for the draw
	 *  @param {int} iMax Total number of rows in the table (regardless of
	 *    filtering)
	 *  @param {int} iTotal Total number of rows in the data set, after filtering
	 *  @param {string} sPre The string that DataTables has formatted using it's
	 *    own rules
	 *  @returns {string} The string to be displayed in the information element.
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $('#example').dataTable( {
	 *      "fnInfoCallback": function( oSettings, iStart, iEnd, iMax, iTotal, sPre ) {
	 *        return iStart +" to "+ iEnd;
	 *      }
	 *    } );
	 */
	"fnInfoCallback": null,


	/**
	 * Called when the table has been initialised. Normally DataTables will
	 * initialise sequentially and there will be no need for this function,
	 * however, this does not hold true when using external language information
	 * since that is obtained using an async XHR call.
	 *  @type function
	 *  @param {object} oSettings DataTables settings object
	 *  @param {object} json The JSON object request from the server - only
	 *    present if client-side Ajax sourced data is used
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnInitComplete": function(oSettings, json) {
	 *          alert( 'DataTables has finished its initialisation.' );
	 *        }
	 *      } );
	 *    } )
	 */
	"fnInitComplete": null,


	/**
	 * Called at the very start of each table draw and can be used to cancel the
	 * draw by returning false, any other return (including undefined) results in
	 * the full draw occurring).
	 *  @type function
	 *  @param {object} oSettings DataTables settings object
	 *  @returns {boolean} False will cancel the draw, anything else (including no
	 *    return) will allow it to complete.
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnPreDrawCallback": function( oSettings ) {
	 *          if ( $('#test').val() == 1 ) {
	 *            return false;
	 *          }
	 *        }
	 *      } );
	 *    } );
	 */
	"fnPreDrawCallback": null,


	/**
	 * This function allows you to 'post process' each row after it have been
	 * generated for each table draw, but before it is rendered on screen. This
	 * function might be used for setting the row class name etc.
	 *  @type function
	 *  @param {node} nRow "TR" element for the current row
	 *  @param {array} aData Raw data array for this row
	 *  @param {int} iDisplayIndex The display index for the current table draw
	 *  @param {int} iDisplayIndexFull The index of the data in the full list of
	 *    rows (after filtering)
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "fnRowCallback": function( nRow, aData, iDisplayIndex, iDisplayIndexFull ) {
	 *          // Bold the grade for all 'A' grade browsers
	 *          if ( aData[4] == "A" )
	 *          {
	 *            $('td:eq(4)', nRow).html( '<b>A</b>' );
	 *          }
	 *        }
	 *      } );
	 *    } );
	 */
	"fnRowCallback": null,


	/**
	 * This parameter allows you to override the default function which obtains
	 * the data from the server ($.getJSON) so something more suitable for your
	 * application. For example you could use POST data, or pull information from
	 * a Gears or AIR database.
	 *  @type function
	 *  @member
	 *  @param {string} sSource HTTP source to obtain the data from (sAjaxSource)
	 *  @param {array} aoData A key/value pair object containing the data to send
	 *    to the server
	 *  @param {function} fnCallback to be called on completion of the data get
	 *    process that will draw the data on the page.
	 *  @param {object} oSettings DataTables settings object
	 *  @dtopt Callbacks
	 *  @dtopt Server-side
	 * 
	 *  @example
	 *    // POST data to server
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bProcessing": true,
	 *        "bServerSide": true,
	 *        "sAjaxSource": "xhr.php",
	 *        "fnServerData": function ( sSource, aoData, fnCallback, oSettings ) {
	 *          oSettings.jqXHR = $.ajax( {
	 *            "dataType": 'json', 
	 *            "type": "POST", 
	 *            "url": sSource, 
	 *            "data": aoData, 
	 *            "success": fnCallback
	 *          } );
	 *        }
	 *      } );
	 *    } );
	 */
	"fnServerData": function ( sUrl, aoData, fnCallback, oSettings ) {
		oSettings.jqXHR = $.ajax( {
			"url":  sUrl,
			"data": aoData,
			"success": function (json) {
				if ( json.sError ) {
					oSettings.oApi._fnLog( oSettings, 0, json.sError );
				}
				
				$(oSettings.oInstance).trigger('xhr', [oSettings, json]);
				fnCallback( json );
			},
			"dataType": "json",
			"cache": false,
			"type": oSettings.sServerMethod,
			"error": function (xhr, error, thrown) {
				if ( error == "parsererror" ) {
					oSettings.oApi._fnLog( oSettings, 0, "DataTables warning: JSON data from "+
						"server could not be parsed. This is caused by a JSON formatting error." );
				}
			}
		} );
	},


	/**
	 * It is often useful to send extra data to the server when making an Ajax
	 * request - for example custom filtering information, and this callback
	 * function makes it trivial to send extra information to the server. The
	 * passed in parameter is the data set that has been constructed by
	 * DataTables, and you can add to this or modify it as you require.
	 *  @type function
	 *  @param {array} aoData Data array (array of objects which are name/value
	 *    pairs) that has been constructed by DataTables and will be sent to the
	 *    server. In the case of Ajax sourced data with server-side processing
	 *    this will be an empty array, for server-side processing there will be a
	 *    significant number of parameters!
	 *  @returns {undefined} Ensure that you modify the aoData array passed in,
	 *    as this is passed by reference.
	 *  @dtopt Callbacks
	 *  @dtopt Server-side
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bProcessing": true,
	 *        "bServerSide": true,
	 *        "sAjaxSource": "scripts/server_processing.php",
	 *        "fnServerParams": function ( aoData ) {
	 *          aoData.push( { "name": "more_data", "value": "my_value" } );
	 *        }
	 *      } );
	 *    } );
	 */
	"fnServerParams": null,


	/**
	 * Load the table state. With this function you can define from where, and how, the
	 * state of a table is loaded. By default DataTables will load from its state saving
	 * cookie, but you might wish to use local storage (HTML5) or a server-side database.
	 *  @type function
	 *  @member
	 *  @param {object} oSettings DataTables settings object
	 *  @return {object} The DataTables state object to be loaded
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bStateSave": true,
	 *        "fnStateLoad": function (oSettings) {
	 *          var o;
	 *          
	 *          // Send an Ajax request to the server to get the data. Note that
	 *          // this is a synchronous request.
	 *          $.ajax( {
	 *            "url": "/state_load",
	 *            "async": false,
	 *            "dataType": "json",
	 *            "success": function (json) {
	 *              o = json;
	 *            }
	 *          } );
	 *          
	 *          return o;
	 *        }
	 *      } );
	 *    } );
	 */
	"fnStateLoad": function ( oSettings ) {
		var sData = this.oApi._fnReadCookie( oSettings.sCookiePrefix+oSettings.sInstance );
		var oData;

		try {
			oData = (typeof $.parseJSON === 'function') ? 
				$.parseJSON(sData) : eval( '('+sData+')' );
		} catch (e) {
			oData = null;
		}

		return oData;
	},


	/**
	 * Callback which allows modification of the saved state prior to loading that state.
	 * This callback is called when the table is loading state from the stored data, but
	 * prior to the settings object being modified by the saved state. Note that for 
	 * plug-in authors, you should use the 'stateLoadParams' event to load parameters for 
	 * a plug-in.
	 *  @type function
	 *  @param {object} oSettings DataTables settings object
	 *  @param {object} oData The state object that is to be loaded
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    // Remove a saved filter, so filtering is never loaded
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bStateSave": true,
	 *        "fnStateLoadParams": function (oSettings, oData) {
	 *          oData.oSearch.sSearch = "";
	 *        }
	 *      } );
	 *    } );
	 * 
	 *  @example
	 *    // Disallow state loading by returning false
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bStateSave": true,
	 *        "fnStateLoadParams": function (oSettings, oData) {
	 *          return false;
	 *        }
	 *      } );
	 *    } );
	 */
	"fnStateLoadParams": null,


	/**
	 * Callback that is called when the state has been loaded from the state saving method
	 * and the DataTables settings object has been modified as a result of the loaded state.
	 *  @type function
	 *  @param {object} oSettings DataTables settings object
	 *  @param {object} oData The state object that was loaded
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    // Show an alert with the filtering value that was saved
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bStateSave": true,
	 *        "fnStateLoaded": function (oSettings, oData) {
	 *          alert( 'Saved filter was: '+oData.oSearch.sSearch );
	 *        }
	 *      } );
	 *    } );
	 */
	"fnStateLoaded": null,


	/**
	 * Save the table state. This function allows you to define where and how the state
	 * information for the table is stored - by default it will use a cookie, but you
	 * might want to use local storage (HTML5) or a server-side database.
	 *  @type function
	 *  @member
	 *  @param {object} oSettings DataTables settings object
	 *  @param {object} oData The state object to be saved
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bStateSave": true,
	 *        "fnStateSave": function (oSettings, oData) {
	 *          // Send an Ajax request to the server with the state object
	 *          $.ajax( {
	 *            "url": "/state_save",
	 *            "data": oData,
	 *            "dataType": "json",
	 *            "method": "POST"
	 *            "success": function () {}
	 *          } );
	 *        }
	 *      } );
	 *    } );
	 */
	"fnStateSave": function ( oSettings, oData ) {
		this.oApi._fnCreateCookie( 
			oSettings.sCookiePrefix+oSettings.sInstance, 
			this.oApi._fnJsonString(oData), 
			oSettings.iCookieDuration, 
			oSettings.sCookiePrefix, 
			oSettings.fnCookieCallback
		);
	},


	/**
	 * Callback which allows modification of the state to be saved. Called when the table 
	 * has changed state a new state save is required. This method allows modification of
	 * the state saving object prior to actually doing the save, including addition or 
	 * other state properties or modification. Note that for plug-in authors, you should 
	 * use the 'stateSaveParams' event to save parameters for a plug-in.
	 *  @type function
	 *  @param {object} oSettings DataTables settings object
	 *  @param {object} oData The state object to be saved
	 *  @dtopt Callbacks
	 * 
	 *  @example
	 *    // Remove a saved filter, so filtering is never saved
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bStateSave": true,
	 *        "fnStateSaveParams": function (oSettings, oData) {
	 *          oData.oSearch.sSearch = "";
	 *        }
	 *      } );
	 *    } );
	 */
	"fnStateSaveParams": null,


	/**
	 * Duration of the cookie which is used for storing session information. This
	 * value is given in seconds.
	 *  @type int
	 *  @default 7200 <i>(2 hours)</i>
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "iCookieDuration": 60*60*24; // 1 day
	 *      } );
	 *    } )
	 */
	"iCookieDuration": 7200,


	/**
	 * When enabled DataTables will not make a request to the server for the first
	 * page draw - rather it will use the data already on the page (no sorting etc
	 * will be applied to it), thus saving on an XHR at load time. iDeferLoading
	 * is used to indicate that deferred loading is required, but it is also used
	 * to tell DataTables how many records there are in the full table (allowing
	 * the information element and pagination to be displayed correctly). In the case
	 * where a filtering is applied to the table on initial load, this can be
	 * indicated by giving the parameter as an array, where the first element is
	 * the number of records available after filtering and the second element is the
	 * number of records without filtering (allowing the table information element
	 * to be shown correctly).
	 *  @type int | array
	 *  @default null
	 *  @dtopt Options
	 * 
	 *  @example
	 *    // 57 records available in the table, no filtering applied
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bServerSide": true,
	 *        "sAjaxSource": "scripts/server_processing.php",
	 *        "iDeferLoading": 57
	 *      } );
	 *    } );
	 * 
	 *  @example
	 *    // 57 records after filtering, 100 without filtering (an initial filter applied)
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bServerSide": true,
	 *        "sAjaxSource": "scripts/server_processing.php",
	 *        "iDeferLoading": [ 57, 100 ],
	 *        "oSearch": {
	 *          "sSearch": "my_filter"
	 *        }
	 *      } );
	 *    } );
	 */
	"iDeferLoading": null,


	/**
	 * Number of rows to display on a single page when using pagination. If
	 * feature enabled (bLengthChange) then the end user will be able to override
	 * this to a custom setting using a pop-up menu.
	 *  @type int
	 *  @default 10
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "iDisplayLength": 50
	 *      } );
	 *    } )
	 */
	"iDisplayLength": 10,


	/**
	 * Define the starting point for data display when using DataTables with
	 * pagination. Note that this parameter is the number of records, rather than
	 * the page number, so if you have 10 records per page and want to start on
	 * the third page, it should be "20".
	 *  @type int
	 *  @default 0
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "iDisplayStart": 20
	 *      } );
	 *    } )
	 */
	"iDisplayStart": 0,


	/**
	 * The scroll gap is the amount of scrolling that is left to go before
	 * DataTables will load the next 'page' of data automatically. You typically
	 * want a gap which is big enough that the scrolling will be smooth for the
	 * user, while not so large that it will load more data than need.
	 *  @type int
	 *  @default 100
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bScrollInfinite": true,
	 *        "bScrollCollapse": true,
	 *        "sScrollY": "200px",
	 *        "iScrollLoadGap": 50
	 *      } );
	 *    } );
	 */
	"iScrollLoadGap": 100,


	/**
	 * By default DataTables allows keyboard navigation of the table (sorting, paging,
	 * and filtering) by adding a tabindex attribute to the required elements. This
	 * allows you to tab through the controls and press the enter key to activate them.
	 * The tabindex is default 0, meaning that the tab follows the flow of the document.
	 * You can overrule this using this parameter if you wish. Use a value of -1 to
	 * disable built-in keyboard navigation.
	 *  @type int
	 *  @default 0
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "iTabIndex": 1
	 *      } );
	 *    } );
	 */
	"iTabIndex": 0,


	/**
	 * All strings that DataTables uses in the user interface that it creates
	 * are defined in this object, allowing you to modified them individually or
	 * completely replace them all as required.
	 *  @namespace
	 */
	"oLanguage": {
		/**
		 * Strings that are used for WAI-ARIA labels and controls only (these are not
		 * actually visible on the page, but will be read by screenreaders, and thus
		 * must be internationalised as well).
		 *  @namespace
		 */
		"oAria": {
			/**
			 * ARIA label that is added to the table headers when the column may be
			 * sorted ascending by activing the column (click or return when focused).
			 * Note that the column header is prefixed to this string.
			 *  @type string
			 *  @default : activate to sort column ascending
			 *  @dtopt Language
			 * 
			 *  @example
			 *    $(document).ready( function() {
			 *      $('#example').dataTable( {
			 *        "oLanguage": {
			 *          "oAria": {
			 *            "sSortAscending": " - click/return to sort ascending"
			 *          }
			 *        }
			 *      } );
			 *    } );
			 */
			"sSortAscending": ": activate to sort column ascending",

			/**
			 * ARIA label that is added to the table headers when the column may be
			 * sorted descending by activing the column (click or return when focused).
			 * Note that the column header is prefixed to this string.
			 *  @type string
			 *  @default : activate to sort column ascending
			 *  @dtopt Language
			 * 
			 *  @example
			 *    $(document).ready( function() {
			 *      $('#example').dataTable( {
			 *        "oLanguage": {
			 *          "oAria": {
			 *            "sSortDescending": " - click/return to sort descending"
			 *          }
			 *        }
			 *      } );
			 *    } );
			 */
			"sSortDescending": ": activate to sort column descending"
		},

		/**
		 * Pagination string used by DataTables for the two built-in pagination
		 * control types ("two_button" and "full_numbers")
		 *  @namespace
		 */
		"oPaginate": {
			/**
			 * Text to use when using the 'full_numbers' type of pagination for the
			 * button to take the user to the first page.
			 *  @type string
			 *  @default First
			 *  @dtopt Language
			 * 
			 *  @example
			 *    $(document).ready( function() {
			 *      $('#example').dataTable( {
			 *        "oLanguage": {
			 *          "oPaginate": {
			 *            "sFirst": "First page"
			 *          }
			 *        }
			 *      } );
			 *    } );
			 */
			"sFirst": "First",
		
		
			/**
			 * Text to use when using the 'full_numbers' type of pagination for the
			 * button to take the user to the last page.
			 *  @type string
			 *  @default Last
			 *  @dtopt Language
			 * 
			 *  @example
			 *    $(document).ready( function() {
			 *      $('#example').dataTable( {
			 *        "oLanguage": {
			 *          "oPaginate": {
			 *            "sLast": "Last page"
			 *          }
			 *        }
			 *      } );
			 *    } );
			 */
			"sLast": "Last",
		
		
			/**
			 * Text to use for the 'next' pagination button (to take the user to the 
			 * next page).
			 *  @type string
			 *  @default Next
			 *  @dtopt Language
			 * 
			 *  @example
			 *    $(document).ready( function() {
			 *      $('#example').dataTable( {
			 *        "oLanguage": {
			 *          "oPaginate": {
			 *            "sNext": "Next page"
			 *          }
			 *        }
			 *      } );
			 *    } );
			 */
			"sNext": "Next",
		
		
			/**
			 * Text to use for the 'previous' pagination button (to take the user to  
			 * the previous page).
			 *  @type string
			 *  @default Previous
			 *  @dtopt Language
			 * 
			 *  @example
			 *    $(document).ready( function() {
			 *      $('#example').dataTable( {
			 *        "oLanguage": {
			 *          "oPaginate": {
			 *            "sPrevious": "Previous page"
			 *          }
			 *        }
			 *      } );
			 *    } );
			 */
			"sPrevious": "Previous"
		},
	
		/**
		 * This string is shown in preference to sZeroRecords when the table is
		 * empty of data (regardless of filtering). Note that this is an optional
		 * parameter - if it is not given, the value of sZeroRecords will be used
		 * instead (either the default or given value).
		 *  @type string
		 *  @default No data available in table
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sEmptyTable": "No data available in table"
		 *        }
		 *      } );
		 *    } );
		 */
		"sEmptyTable": "No data available in table",
	
	
		/**
		 * This string gives information to the end user about the information that 
		 * is current on display on the page. The _START_, _END_ and _TOTAL_ 
		 * variables are all dynamically replaced as the table display updates, and 
		 * can be freely moved or removed as the language requirements change.
		 *  @type string
		 *  @default Showing _START_ to _END_ of _TOTAL_ entries
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sInfo": "Got a total of _TOTAL_ entries to show (_START_ to _END_)"
		 *        }
		 *      } );
		 *    } );
		 */
		"sInfo": "Showing _START_ to _END_ of _TOTAL_ entries",
	
	
		/**
		 * Display information string for when the table is empty. Typically the 
		 * format of this string should match sInfo.
		 *  @type string
		 *  @default Showing 0 to 0 of 0 entries
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sInfoEmpty": "No entries to show"
		 *        }
		 *      } );
		 *    } );
		 */
		"sInfoEmpty": "Showing 0 to 0 of 0 entries",
	
	
		/**
		 * When a user filters the information in a table, this string is appended 
		 * to the information (sInfo) to give an idea of how strong the filtering 
		 * is. The variable _MAX_ is dynamically updated.
		 *  @type string
		 *  @default (filtered from _MAX_ total entries)
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sInfoFiltered": " - filtering from _MAX_ records"
		 *        }
		 *      } );
		 *    } );
		 */
		"sInfoFiltered": "(filtered from _MAX_ total entries)",
	
	
		/**
		 * If can be useful to append extra information to the info string at times,
		 * and this variable does exactly that. This information will be appended to
		 * the sInfo (sInfoEmpty and sInfoFiltered in whatever combination they are
		 * being used) at all times.
		 *  @type string
		 *  @default <i>Empty string</i>
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sInfoPostFix": "All records shown are derived from real information."
		 *        }
		 *      } );
		 *    } );
		 */
		"sInfoPostFix": "",
	
	
		/**
		 * DataTables has a build in number formatter (fnFormatNumber) which is used
		 * to format large numbers that are used in the table information. By
		 * default a comma is used, but this can be trivially changed to any
		 * character you wish with this parameter.
		 *  @type string
		 *  @default ,
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sInfoThousands": "'"
		 *        }
		 *      } );
		 *    } );
		 */
		"sInfoThousands": ",",
	
	
		/**
		 * Detail the action that will be taken when the drop down menu for the
		 * pagination length option is changed. The '_MENU_' variable is replaced
		 * with a default select list of 10, 25, 50 and 100, and can be replaced
		 * with a custom select box if required.
		 *  @type string
		 *  @default Show _MENU_ entries
		 *  @dtopt Language
		 * 
		 *  @example
		 *    // Language change only
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sLengthMenu": "Display _MENU_ records"
		 *        }
		 *      } );
		 *    } );
		 *    
		 *  @example
		 *    // Language and options change
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sLengthMenu": 'Display <select>'+
		 *            '<option value="10">10</option>'+
		 *            '<option value="20">20</option>'+
		 *            '<option value="30">30</option>'+
		 *            '<option value="40">40</option>'+
		 *            '<option value="50">50</option>'+
		 *            '<option value="-1">All</option>'+
		 *            '</select> records'
		 *        }
		 *      } );
		 *    } );
		 */
		"sLengthMenu": "Show _MENU_ entries",
	
	
		/**
		 * When using Ajax sourced data and during the first draw when DataTables is
		 * gathering the data, this message is shown in an empty row in the table to
		 * indicate to the end user the the data is being loaded. Note that this
		 * parameter is not used when loading data by server-side processing, just
		 * Ajax sourced data with client-side processing.
		 *  @type string
		 *  @default Loading...
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sLoadingRecords": "Please wait - loading..."
		 *        }
		 *      } );
		 *    } );
		 */
		"sLoadingRecords": "Loading...",
	
	
		/**
		 * Text which is displayed when the table is processing a user action
		 * (usually a sort command or similar).
		 *  @type string
		 *  @default Processing...
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sProcessing": "DataTables is currently busy"
		 *        }
		 *      } );
		 *    } );
		 */
		"sProcessing": "Processing...",
	
	
		/**
		 * Details the actions that will be taken when the user types into the
		 * filtering input text box. The variable "_INPUT_", if used in the string,
		 * is replaced with the HTML text box for the filtering input allowing
		 * control over where it appears in the string. If "_INPUT_" is not given
		 * then the input box is appended to the string automatically.
		 *  @type string
		 *  @default Search:
		 *  @dtopt Language
		 * 
		 *  @example
		 *    // Input text box will be appended at the end automatically
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sSearch": "Filter records:"
		 *        }
		 *      } );
		 *    } );
		 *    
		 *  @example
		 *    // Specify where the filter should appear
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sSearch": "Apply filter _INPUT_ to table"
		 *        }
		 *      } );
		 *    } );
		 */
		"sSearch": "Search:",
	
	
		/**
		 * All of the language information can be stored in a file on the
		 * server-side, which DataTables will look up if this parameter is passed.
		 * It must store the URL of the language file, which is in a JSON format,
		 * and the object has the same properties as the oLanguage object in the
		 * initialiser object (i.e. the above parameters). Please refer to one of
		 * the example language files to see how this works in action.
		 *  @type string
		 *  @default <i>Empty string - i.e. disabled</i>
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sUrl": "http://www.sprymedia.co.uk/dataTables/lang.txt"
		 *        }
		 *      } );
		 *    } );
		 */
		"sUrl": "",
	
	
		/**
		 * Text shown inside the table records when the is no information to be
		 * displayed after filtering. sEmptyTable is shown when there is simply no
		 * information in the table at all (regardless of filtering).
		 *  @type string
		 *  @default No matching records found
		 *  @dtopt Language
		 * 
		 *  @example
		 *    $(document).ready( function() {
		 *      $('#example').dataTable( {
		 *        "oLanguage": {
		 *          "sZeroRecords": "No records to display"
		 *        }
		 *      } );
		 *    } );
		 */
		"sZeroRecords": "No matching records found"
	},


	/**
	 * This parameter allows you to have define the global filtering state at
	 * initialisation time. As an object the "sSearch" parameter must be
	 * defined, but all other parameters are optional. When "bRegex" is true,
	 * the search string will be treated as a regular expression, when false
	 * (default) it will be treated as a straight string. When "bSmart"
	 * DataTables will use it's smart filtering methods (to word match at
	 * any point in the data), when false this will not be done.
	 *  @namespace
	 *  @extends DataTable.models.oSearch
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "oSearch": {"sSearch": "Initial search"}
	 *      } );
	 *    } )
	 */
	"oSearch": $.extend( {}, DataTable.models.oSearch ),


	/**
	 * By default DataTables will look for the property 'aaData' when obtaining
	 * data from an Ajax source or for server-side processing - this parameter
	 * allows that property to be changed. You can use Javascript dotted object
	 * notation to get a data source for multiple levels of nesting.
	 *  @type string
	 *  @default aaData
	 *  @dtopt Options
	 *  @dtopt Server-side
	 * 
	 *  @example
	 *    // Get data from { "data": [...] }
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "sAjaxSource": "sources/data.txt",
	 *        "sAjaxDataProp": "data"
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Get data from { "data": { "inner": [...] } }
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "sAjaxSource": "sources/data.txt",
	 *        "sAjaxDataProp": "data.inner"
	 *      } );
	 *    } );
	 */
	"sAjaxDataProp": "aaData",


	/**
	 * You can instruct DataTables to load data from an external source using this
	 * parameter (use aData if you want to pass data in you already have). Simply
	 * provide a url a JSON object can be obtained from. This object must include
	 * the parameter 'aaData' which is the data source for the table.
	 *  @type string
	 *  @default null
	 *  @dtopt Options
	 *  @dtopt Server-side
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sAjaxSource": "http://www.sprymedia.co.uk/dataTables/json.php"
	 *      } );
	 *    } )
	 */
	"sAjaxSource": null,


	/**
	 * This parameter can be used to override the default prefix that DataTables
	 * assigns to a cookie when state saving is enabled.
	 *  @type string
	 *  @default SpryMedia_DataTables_
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sCookiePrefix": "my_datatable_",
	 *      } );
	 *    } );
	 */
	"sCookiePrefix": "SpryMedia_DataTables_",


	/**
	 * This initialisation variable allows you to specify exactly where in the
	 * DOM you want DataTables to inject the various controls it adds to the page
	 * (for example you might want the pagination controls at the top of the
	 * table). DIV elements (with or without a custom class) can also be added to
	 * aid styling. The follow syntax is used:
	 *   <ul>
	 *     <li>The following options are allowed:	
	 *       <ul>
	 *         <li>'l' - Length changing</li
	 *         <li>'f' - Filtering input</li>
	 *         <li>'t' - The table!</li>
	 *         <li>'i' - Information</li>
	 *         <li>'p' - Pagination</li>
	 *         <li>'r' - pRocessing</li>
	 *       </ul>
	 *     </li>
	 *     <li>The following constants are allowed:
	 *       <ul>
	 *         <li>'H' - jQueryUI theme "header" classes ('fg-toolbar ui-widget-header ui-corner-tl ui-corner-tr ui-helper-clearfix')</li>
	 *         <li>'F' - jQueryUI theme "footer" classes ('fg-toolbar ui-widget-header ui-corner-bl ui-corner-br ui-helper-clearfix')</li>
	 *       </ul>
	 *     </li>
	 *     <li>The following syntax is expected:
	 *       <ul>
	 *         <li>'&lt;' and '&gt;' - div elements</li>
	 *         <li>'&lt;"class" and '&gt;' - div with a class</li>
	 *         <li>'&lt;"#id" and '&gt;' - div with an ID</li>
	 *       </ul>
	 *     </li>
	 *     <li>Examples:
	 *       <ul>
	 *         <li>'&lt;"wrapper"flipt&gt;'</li>
	 *         <li>'&lt;lf&lt;t&gt;ip&gt;'</li>
	 *       </ul>
	 *     </li>
	 *   </ul>
	 *  @type string
	 *  @default lfrtip <i>(when bJQueryUI is false)</i> <b>or</b> 
	 *    <"H"lfr>t<"F"ip> <i>(when bJQueryUI is true)</i>
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sDom": '&lt;"top"i&gt;rt&lt;"bottom"flp&gt;&lt;"clear"&gt;'
	 *      } );
	 *    } );
	 */
	"sDom": "lfrtip",


	/**
	 * DataTables features two different built-in pagination interaction methods
	 * ('two_button' or 'full_numbers') which present different page controls to
	 * the end user. Further methods can be added using the API (see below).
	 *  @type string
	 *  @default two_button
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sPaginationType": "full_numbers"
	 *      } );
	 *    } )
	 */
	"sPaginationType": "two_button",


	/**
	 * Enable horizontal scrolling. When a table is too wide to fit into a certain
	 * layout, or you have a large number of columns in the table, you can enable
	 * x-scrolling to show the table in a viewport, which can be scrolled. This
	 * property can be any CSS unit, or a number (in which case it will be treated
	 * as a pixel measurement).
	 *  @type string
	 *  @default <i>blank string - i.e. disabled</i>
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sScrollX": "100%",
	 *        "bScrollCollapse": true
	 *      } );
	 *    } );
	 */
	"sScrollX": "",


	/**
	 * This property can be used to force a DataTable to use more width than it
	 * might otherwise do when x-scrolling is enabled. For example if you have a
	 * table which requires to be well spaced, this parameter is useful for
	 * "over-sizing" the table, and thus forcing scrolling. This property can by
	 * any CSS unit, or a number (in which case it will be treated as a pixel
	 * measurement).
	 *  @type string
	 *  @default <i>blank string - i.e. disabled</i>
	 *  @dtopt Options
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sScrollX": "100%",
	 *        "sScrollXInner": "110%"
	 *      } );
	 *    } );
	 */
	"sScrollXInner": "",


	/**
	 * Enable vertical scrolling. Vertical scrolling will constrain the DataTable
	 * to the given height, and enable scrolling for any data which overflows the
	 * current viewport. This can be used as an alternative to paging to display
	 * a lot of data in a small area (although paging and scrolling can both be
	 * enabled at the same time). This property can be any CSS unit, or a number
	 * (in which case it will be treated as a pixel measurement).
	 *  @type string
	 *  @default <i>blank string - i.e. disabled</i>
	 *  @dtopt Features
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "sScrollY": "200px",
	 *        "bPaginate": false
	 *      } );
	 *    } );
	 */
	"sScrollY": "",


	/**
	 * Set the HTTP method that is used to make the Ajax call for server-side
	 * processing or Ajax sourced data.
	 *  @type string
	 *  @default GET
	 *  @dtopt Options
	 *  @dtopt Server-side
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "bServerSide": true,
	 *        "sAjaxSource": "scripts/post.php",
	 *        "sServerMethod": "POST"
	 *      } );
	 *    } );
	 */
	"sServerMethod": "GET"
};

