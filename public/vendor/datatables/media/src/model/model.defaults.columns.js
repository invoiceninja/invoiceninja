

/**
 * Column options that can be given to DataTables at initialisation time.
 *  @namespace
 */
DataTable.defaults.columns = {
	/**
	 * Allows a column's sorting to take multiple columns into account when 
	 * doing a sort. For example first name / last name columns make sense to 
	 * do a multi-column sort over the two columns.
	 *  @type array
	 *  @default null <i>Takes the value of the column index automatically</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [
	 *          { "aDataSort": [ 0, 1 ], "aTargets": [ 0 ] },
	 *          { "aDataSort": [ 1, 0 ], "aTargets": [ 1 ] },
	 *          { "aDataSort": [ 2, 3, 4 ], "aTargets": [ 2 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [
	 *          { "aDataSort": [ 0, 1 ] },
	 *          { "aDataSort": [ 1, 0 ] },
	 *          { "aDataSort": [ 2, 3, 4 ] },
	 *          null,
	 *          null
	 *        ]
	 *      } );
	 *    } );
	 */
	"aDataSort": null,


	/**
	 * You can control the default sorting direction, and even alter the behaviour
	 * of the sort handler (i.e. only allow ascending sorting etc) using this
	 * parameter.
	 *  @type array
	 *  @default [ 'asc', 'desc' ]
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [
	 *          { "asSorting": [ "asc" ], "aTargets": [ 1 ] },
	 *          { "asSorting": [ "desc", "asc", "asc" ], "aTargets": [ 2 ] },
	 *          { "asSorting": [ "desc" ], "aTargets": [ 3 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [
	 *          null,
	 *          { "asSorting": [ "asc" ] },
	 *          { "asSorting": [ "desc", "asc", "asc" ] },
	 *          { "asSorting": [ "desc" ] },
	 *          null
	 *        ]
	 *      } );
	 *    } );
	 */
	"asSorting": [ 'asc', 'desc' ],


	/**
	 * Enable or disable filtering on the data in this column.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "bSearchable": false, "aTargets": [ 0 ] }
	 *        ] } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "bSearchable": false },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ] } );
	 *    } );
	 */
	"bSearchable": true,


	/**
	 * Enable or disable sorting on this column.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "bSortable": false, "aTargets": [ 0 ] }
	 *        ] } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "bSortable": false },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ] } );
	 *    } );
	 */
	"bSortable": true,


	/**
	 * <code>Deprecated</code> When using fnRender() for a column, you may wish 
	 * to use the original data (before rendering) for sorting and filtering 
	 * (the default is to used the rendered data that the user can see). This 
	 * may be useful for dates etc.
	 * 
	 * Please note that this option has now been deprecated and will be removed
	 * in the next version of DataTables. Please use mRender / mData rather than
	 * fnRender.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Columns
	 *  @deprecated
	 */
	"bUseRendered": true,


	/**
	 * Enable or disable the display of this column.
	 *  @type boolean
	 *  @default true
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "bVisible": false, "aTargets": [ 0 ] }
	 *        ] } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "bVisible": false },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ] } );
	 *    } );
	 */
	"bVisible": true,
	
	
	/**
	 * Developer definable function that is called whenever a cell is created (Ajax source,
	 * etc) or processed for input (DOM source). This can be used as a compliment to mRender
	 * allowing you to modify the DOM element (add background colour for example) when the
	 * element is available.
	 *  @type function
	 *  @param {element} nTd The TD node that has been created
	 *  @param {*} sData The Data for the cell
	 *  @param {array|object} oData The data for the whole row
	 *  @param {int} iRow The row index for the aoData data store
	 *  @param {int} iCol The column index for aoColumns
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ {
	 *          "aTargets": [3],
	 *          "fnCreatedCell": function (nTd, sData, oData, iRow, iCol) {
	 *            if ( sData == "1.7" ) {
	 *              $(nTd).css('color', 'blue')
	 *            }
	 *          }
	 *        } ]
	 *      });
	 *    } );
	 */
	"fnCreatedCell": null,


	/**
	 * <code>Deprecated</code> Custom display function that will be called for the 
	 * display of each cell in this column.
	 *
	 * Please note that this option has now been deprecated and will be removed
	 * in the next version of DataTables. Please use mRender / mData rather than
	 * fnRender.
	 *  @type function
	 *  @param {object} o Object with the following parameters:
	 *  @param {int}    o.iDataRow The row in aoData
	 *  @param {int}    o.iDataColumn The column in question
	 *  @param {array}  o.aData The data for the row in question
	 *  @param {object} o.oSettings The settings object for this DataTables instance
	 *  @param {object} o.mDataProp The data property used for this column
	 *  @param {*}      val The current cell value
	 *  @returns {string} The string you which to use in the display
	 *  @dtopt Columns
	 *  @deprecated
	 */
	"fnRender": null,


	/**
	 * The column index (starting from 0!) that you wish a sort to be performed
	 * upon when this column is selected for sorting. This can be used for sorting
	 * on hidden columns for example.
	 *  @type int
	 *  @default -1 <i>Use automatically calculated column index</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "iDataSort": 1, "aTargets": [ 0 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "iDataSort": 1 },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ]
	 *      } );
	 *    } );
	 */
	"iDataSort": -1,


	/**
	 * This parameter has been replaced by mData in DataTables to ensure naming
	 * consistency. mDataProp can still be used, as there is backwards compatibility
	 * in DataTables for this option, but it is strongly recommended that you use
	 * mData in preference to mDataProp.
	 *  @name DataTable.defaults.columns.mDataProp
	 */


	/**
	 * This property can be used to read data from any JSON data source property,
	 * including deeply nested objects / properties. mData can be given in a
	 * number of different ways which effect its behaviour:
	 *   <ul>
	 *     <li>integer - treated as an array index for the data source. This is the
	 *       default that DataTables uses (incrementally increased for each column).</li>
	 *     <li>string - read an object property from the data source. Note that you can
	 *       use Javascript dotted notation to read deep properties / arrays from the
	 *       data source.</li>
	 *     <li>null - the sDefaultContent option will be used for the cell (null
	 *       by default, so you will need to specify the default content you want -
	 *       typically an empty string). This can be useful on generated columns such 
	 *       as edit / delete action columns.</li>
	 *     <li>function - the function given will be executed whenever DataTables 
	 *       needs to set or get the data for a cell in the column. The function 
	 *       takes three parameters:
	 *       <ul>
	 *         <li>{array|object} The data source for the row</li>
	 *         <li>{string} The type call data requested - this will be 'set' when
	 *           setting data or 'filter', 'display', 'type', 'sort' or undefined when 
	 *           gathering data. Note that when <i>undefined</i> is given for the type
	 *           DataTables expects to get the raw data for the object back</li>
	 *         <li>{*} Data to set when the second parameter is 'set'.</li>
	 *       </ul>
	 *       The return value from the function is not required when 'set' is the type
	 *       of call, but otherwise the return is what will be used for the data
	 *       requested.</li>
	 *    </ul>
	 *
	 * Note that prior to DataTables 1.9.2 mData was called mDataProp. The name change
	 * reflects the flexibility of this property and is consistent with the naming of
	 * mRender. If 'mDataProp' is given, then it will still be used by DataTables, as
	 * it automatically maps the old name to the new if required.
	 *  @type string|int|function|null
	 *  @default null <i>Use automatically calculated column index</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Read table data from objects
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "sAjaxSource": "sources/deep.txt",
	 *        "aoColumns": [
	 *          { "mData": "engine" },
	 *          { "mData": "browser" },
	 *          { "mData": "platform.inner" },
	 *          { "mData": "platform.details.0" },
	 *          { "mData": "platform.details.1" }
	 *        ]
	 *      } );
	 *    } );
	 * 
	 *  @example
	 *    // Using mData as a function to provide different information for
	 *    // sorting, filtering and display. In this case, currency (price)
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "aoColumnDefs": [ {
	 *          "aTargets": [ 0 ],
	 *          "mData": function ( source, type, val ) {
	 *            if (type === 'set') {
	 *              source.price = val;
	 *              // Store the computed dislay and filter values for efficiency
	 *              source.price_display = val=="" ? "" : "$"+numberFormat(val);
	 *              source.price_filter  = val=="" ? "" : "$"+numberFormat(val)+" "+val;
	 *              return;
	 *            }
	 *            else if (type === 'display') {
	 *              return source.price_display;
	 *            }
	 *            else if (type === 'filter') {
	 *              return source.price_filter;
	 *            }
	 *            // 'sort', 'type' and undefined all just use the integer
	 *            return source.price;
	 *          }
	 *        } ]
	 *      } );
	 *    } );
	 */
	"mData": null,


	/**
	 * This property is the rendering partner to mData and it is suggested that
	 * when you want to manipulate data for display (including filtering, sorting etc)
	 * but not altering the underlying data for the table, use this property. mData
	 * can actually do everything this property can and more, but this parameter is
	 * easier to use since there is no 'set' option. Like mData is can be given
	 * in a number of different ways to effect its behaviour, with the addition of 
	 * supporting array syntax for easy outputting of arrays (including arrays of
	 * objects):
	 *   <ul>
	 *     <li>integer - treated as an array index for the data source. This is the
	 *       default that DataTables uses (incrementally increased for each column).</li>
	 *     <li>string - read an object property from the data source. Note that you can
	 *       use Javascript dotted notation to read deep properties / arrays from the
	 *       data source and also array brackets to indicate that the data reader should
	 *       loop over the data source array. When characters are given between the array
	 *       brackets, these characters are used to join the data source array together.
	 *       For example: "accounts[, ].name" would result in a comma separated list with
	 *       the 'name' value from the 'accounts' array of objects.</li>
	 *     <li>function - the function given will be executed whenever DataTables 
	 *       needs to set or get the data for a cell in the column. The function 
	 *       takes three parameters:
	 *       <ul>
	 *         <li>{array|object} The data source for the row (based on mData)</li>
	 *         <li>{string} The type call data requested - this will be 'filter', 'display', 
	 *           'type' or 'sort'.</li>
	 *         <li>{array|object} The full data source for the row (not based on mData)</li>
	 *       </ul>
	 *       The return value from the function is what will be used for the data
	 *       requested.</li>
	 *    </ul>
	 *  @type string|int|function|null
	 *  @default null <i>Use mData</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Create a comma separated list from an array of objects
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "sAjaxSource": "sources/deep.txt",
	 *        "aoColumns": [
	 *          { "mData": "engine" },
	 *          { "mData": "browser" },
	 *          {
	 *            "mData": "platform",
	 *            "mRender": "[, ].name"
	 *          }
	 *        ]
	 *      } );
	 *    } );
	 * 
	 *  @example
	 *    // Use as a function to create a link from the data source
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "aoColumnDefs": [
	 *        {
	 *          "aTargets": [ 0 ],
	 *          "mData": "download_link",
	 *          "mRender": function ( data, type, full ) {
	 *            return '<a href="'+data+'">Download</a>';
	 *          }
	 *        ]
	 *      } );
	 *    } );
	 */
	"mRender": null,


	/**
	 * Change the cell type created for the column - either TD cells or TH cells. This
	 * can be useful as TH cells have semantic meaning in the table body, allowing them
	 * to act as a header for a row (you may wish to add scope='row' to the TH elements).
	 *  @type string
	 *  @default td
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Make the first column use TH cells
	 *    $(document).ready( function() {
	 *      var oTable = $('#example').dataTable( {
	 *        "aoColumnDefs": [ {
	 *          "aTargets": [ 0 ],
	 *          "sCellType": "th"
	 *        } ]
	 *      } );
	 *    } );
	 */
	"sCellType": "td",


	/**
	 * Class to give to each cell in this column.
	 *  @type string
	 *  @default <i>Empty string</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "sClass": "my_class", "aTargets": [ 0 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "sClass": "my_class" },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ]
	 *      } );
	 *    } );
	 */
	"sClass": "",
	
	/**
	 * When DataTables calculates the column widths to assign to each column,
	 * it finds the longest string in each column and then constructs a
	 * temporary table and reads the widths from that. The problem with this
	 * is that "mmm" is much wider then "iiii", but the latter is a longer 
	 * string - thus the calculation can go wrong (doing it properly and putting
	 * it into an DOM object and measuring that is horribly(!) slow). Thus as
	 * a "work around" we provide this option. It will append its value to the
	 * text that is found to be the longest string for the column - i.e. padding.
	 * Generally you shouldn't need this, and it is not documented on the 
	 * general DataTables.net documentation
	 *  @type string
	 *  @default <i>Empty string<i>
	 *  @dtopt Columns
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          null,
	 *          null,
	 *          null,
	 *          {
	 *            "sContentPadding": "mmm"
	 *          }
	 *        ]
	 *      } );
	 *    } );
	 */
	"sContentPadding": "",


	/**
	 * Allows a default value to be given for a column's data, and will be used
	 * whenever a null data source is encountered (this can be because mData
	 * is set to null, or because the data source itself is null).
	 *  @type string
	 *  @default null
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          {
	 *            "mData": null,
	 *            "sDefaultContent": "Edit",
	 *            "aTargets": [ -1 ]
	 *          }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          null,
	 *          null,
	 *          null,
	 *          {
	 *            "mData": null,
	 *            "sDefaultContent": "Edit"
	 *          }
	 *        ]
	 *      } );
	 *    } );
	 */
	"sDefaultContent": null,


	/**
	 * This parameter is only used in DataTables' server-side processing. It can
	 * be exceptionally useful to know what columns are being displayed on the
	 * client side, and to map these to database fields. When defined, the names
	 * also allow DataTables to reorder information from the server if it comes
	 * back in an unexpected order (i.e. if you switch your columns around on the
	 * client-side, your server-side code does not also need updating).
	 *  @type string
	 *  @default <i>Empty string</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "sName": "engine", "aTargets": [ 0 ] },
	 *          { "sName": "browser", "aTargets": [ 1 ] },
	 *          { "sName": "platform", "aTargets": [ 2 ] },
	 *          { "sName": "version", "aTargets": [ 3 ] },
	 *          { "sName": "grade", "aTargets": [ 4 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "sName": "engine" },
	 *          { "sName": "browser" },
	 *          { "sName": "platform" },
	 *          { "sName": "version" },
	 *          { "sName": "grade" }
	 *        ]
	 *      } );
	 *    } );
	 */
	"sName": "",


	/**
	 * Defines a data source type for the sorting which can be used to read
	 * real-time information from the table (updating the internally cached
	 * version) prior to sorting. This allows sorting to occur on user editable
	 * elements such as form inputs.
	 *  @type string
	 *  @default std
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [
	 *          { "sSortDataType": "dom-text", "aTargets": [ 2, 3 ] },
	 *          { "sType": "numeric", "aTargets": [ 3 ] },
	 *          { "sSortDataType": "dom-select", "aTargets": [ 4 ] },
	 *          { "sSortDataType": "dom-checkbox", "aTargets": [ 5 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [
	 *          null,
	 *          null,
	 *          { "sSortDataType": "dom-text" },
	 *          { "sSortDataType": "dom-text", "sType": "numeric" },
	 *          { "sSortDataType": "dom-select" },
	 *          { "sSortDataType": "dom-checkbox" }
	 *        ]
	 *      } );
	 *    } );
	 */
	"sSortDataType": "std",


	/**
	 * The title of this column.
	 *  @type string
	 *  @default null <i>Derived from the 'TH' value for this column in the 
	 *    original HTML table.</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "sTitle": "My column title", "aTargets": [ 0 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "sTitle": "My column title" },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ]
	 *      } );
	 *    } );
	 */
	"sTitle": null,


	/**
	 * The type allows you to specify how the data for this column will be sorted.
	 * Four types (string, numeric, date and html (which will strip HTML tags
	 * before sorting)) are currently available. Note that only date formats
	 * understood by Javascript's Date() object will be accepted as type date. For
	 * example: "Mar 26, 2008 5:03 PM". May take the values: 'string', 'numeric',
	 * 'date' or 'html' (by default). Further types can be adding through
	 * plug-ins.
	 *  @type string
	 *  @default null <i>Auto-detected from raw data</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "sType": "html", "aTargets": [ 0 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "sType": "html" },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ]
	 *      } );
	 *    } );
	 */
	"sType": null,


	/**
	 * Defining the width of the column, this parameter may take any CSS value
	 * (3em, 20px etc). DataTables apples 'smart' widths to columns which have not
	 * been given a specific width through this interface ensuring that the table
	 * remains readable.
	 *  @type string
	 *  @default null <i>Automatic</i>
	 *  @dtopt Columns
	 * 
	 *  @example
	 *    // Using aoColumnDefs
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumnDefs": [ 
	 *          { "sWidth": "20%", "aTargets": [ 0 ] }
	 *        ]
	 *      } );
	 *    } );
	 *    
	 *  @example
	 *    // Using aoColumns
	 *    $(document).ready( function() {
	 *      $('#example').dataTable( {
	 *        "aoColumns": [ 
	 *          { "sWidth": "20%" },
	 *          null,
	 *          null,
	 *          null,
	 *          null
	 *        ]
	 *      } );
	 *    } );
	 */
	"sWidth": null
};

