// DATA_TEMPLATE: empty_table
oTest.fnStart( "ajax" );


$(document).ready( function () {
	var json;
	var result;

	//
	// As a string
	//
	oTest.fnWaitTest(
		"Basic request as a string - getting arrays",
		function () {
			$('#example').dataTable( {
				"serverSide": true,
				"ajax": "../data_sources/arrays.php"
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);

	oTest.fnWaitTest(
		"Basic request as a string - getting objects",
		function () {
			oSession.fnRestore();
			$('#example').dataTable( {
				"serverSide": true,
				"ajax": "../data_sources/objects.php",
				"columns": [
					{ data: 'engine' },
					{ data: 'browser' },
					{ data: 'platform' },
					{ data: 'version' },
					{ data: 'grade' }
				]
			} );
		},
		function () {
			return $('tbody td').eq(1).html() === '20';
		}
	);

	oTest.fnWaitTest(
		"Default request is GET - string based",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": "../data_sources/method.php?method=get"
			} );
		},
		function () {
			return  $('tbody td').eq(0).html() === '1';
		}
	);

	oTest.fnWaitTest(
		"jQuery anti-cache parameter is sent by default - string based",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": "../data_sources/param.php"
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get._;
		}
	);
	
	oTest.fnWaitTest(
		"Only the SSP parameters were also sent",
		null,
		function () {
			return json.get_length === 36;
		}
	);
	


	//
	// As an object
	//
	oTest.fnWaitTest(
		"Get Ajax using url parameter only",
		function () {
			oSession.fnRestore();
			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/arrays.php"
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);


	// props
	oTest.fnWaitTest(
		"Set an error callback",
		function () {
			oSession.fnRestore();
			result = false;

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/rubbish",
					"error": function () {
						result = true;
					}
				}
			} );
		},
		function () {
			return result;
		}
	);

	// type
	oTest.fnWaitTest(
		"type - Default request is GET",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/method.php?method=get"
				}
			} );
		},
		function () {
			return $('tbody td').eq(1).html() === '2';
		}
	);

	oTest.fnWaitTest(
		"type - Can use `type` to make a POST request",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/method.php?method=post",
					"type": "POST"
				}
			} );
		},
		function () {
			return $('tbody td').eq(2).html() === '3';
		}
	);

	oTest.fnWaitTest(
		"type - Can use `type` to make a PUT request",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/method.php?method=put",
					"type": "PUT"
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);


	// data
	oTest.fnWaitTest(
		"data - Function based data - has standard SSP parameters only",
		function () {
			oSession.fnRestore();
			result = false;

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/param.php",
					"data": function ( d ) {
						result = d.length === 35;
					}
				}
			} );
		},
		function () {
			return result;
		}
	);

	oTest.fnWaitTest(
		"data - Function based data - can return an object",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": function ( d ) {
							return { 'tapestry': 'king' };
						}
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.tapestry === 'king';
		}
	);

	oTest.fnWaitTest(
		"data - Function based data - multiple properties",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": function ( d ) {
							return { 'tapestry': 'king', 'move': 'earth' };
						}
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.tapestry === 'king' && json.get.move === 'earth';
		}
	);
	
	oTest.fnWaitTest(
		"data - Confirm only SSP parameters were also sent",
		null,
		function () {
			return json.get_length === 38;
		}
	);


	oTest.fnWaitTest(
		"data - Function based data - can return an array of key/value object pairs",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": function ( d ) {
							return [
								{ 'name': 'tapestry', 'value': 'carole' }
							];
						}
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.tapestry === 'carole';
		}
	);

	oTest.fnWaitTest(
		"data - Function based data - multiple properties",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": function ( d ) {
							return [
								{ 'name': 'tapestry', 'value': 'carole' },
								{ 'name': 'feel', 'value': 'earth move' }
							];
						}
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.tapestry === 'carole' && json.get.feel === 'earth move';
		}
	);

	oTest.fnWaitTest(
		"data - Function based data - add parameters to passed in array",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": function ( d ) {
							d.push( { 'name': 'tapestry', 'value': 'carole' } );
							d.push( { 'name': 'rich', 'value': 'hue' } );
						}
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.tapestry === 'carole' && json.get.rich === 'hue';
		}
	);

	oTest.fnWaitTest(
		"data - Function based data - send parameters by POST",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": function ( d ) {
							d.push( { 'name': 'tapestry', 'value': 'king' } );
							d.push( { 'name': 'rich', 'value': 'hue' } );
						},
						"type": "POST"
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.post && json.post.tapestry === 'king' && json.post.rich === 'hue';
		}
	);

	oTest.fnWaitTest(
		"data - Object based data - sends parameters defined",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": {
							"too": "late",
							"got": "friend"
						}
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.too === 'late' && json.get.got === 'friend';
		}
	);

	oTest.fnWaitTest(
		"data - Array based data - sends parameters defined",
		function () {
			oSession.fnRestore();
			json = {};

			$('#example').dataTable( {
					"serverSide": true,
					"ajax": {
						"url": "../data_sources/param.php",
						"data": [
							{ 'name': 'tapestry', 'value': 'king' },
							{ 'name': 'far', 'value': 'away' }
						]
					}
				} ).on('xhr', function (e, settings, o) {
					json = o;
				} );
		},
		function () {
			return json.get && json.get.tapestry === 'king' && json.get.far === 'away';
		}
	);


	// dataSrc
	oTest.fnWaitTest(
		"dataSrc - Default data source is aaData",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/arrays.php"
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);

	oTest.fnWaitTest(
		"dataSrc - as a string - read from `data`",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/arrays.php?dataSrc=data",
					"dataSrc": "data"
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);

	oTest.fnWaitTest(
		"dataSrc - as a string - read from nested property `data.inner`",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/arrays.php?dataSrc=nested",
					"dataSrc": "data.inner"
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);

	oTest.fnWaitTest(
		"dataSrc - as a function, return JSON property",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/arrays.php?dataSrc=nested",
					"dataSrc": function ( json ) {
						return json.data.inner;
					}
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);

	oTest.fnWaitTest(
		"dataSrc - as a function, can manipulate the data",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": {
					"url": "../data_sources/arrays.php?dataSrc=data",
					"dataSrc": function ( json ) {
						json.data[0][0] = "Tapestry";
						return json.data;
					}
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === 'Tapestry' &&
			       $('tbody td').eq(1).html() === '2';
		}
	);



	//
	// As a function
	//
	oTest.fnTest(
		"ajax as a function - first parameter is array of data",
		function () {
			oSession.fnRestore();
			result = null;

			$('#example').dataTable( {
				"serverSide": true,
				"ajax": function ( data, callback, settings ) {
					result = arguments;
					callback( {
						sEcho: 1,
						iTotalRecords: 1,
						iTotalDisplayRecords: 1,
						aaData: []
					} );
				}
			} );
		},
		function () {
			console.log( result );
			return $.isArray( result[0] ) && result[0].length === 35;
		}
	);

	oTest.fnTest(
		"ajax as a function - second parameter is callback function",
		null,
		function () {
			return $.isFunction( result[1] );
		}
	);

	oTest.fnTest(
		"ajax as a function - third parameter is settings object",
		null,
		function () {
			return result[2] === $('#example').dataTable().fnSettings();
		}
	);

	oTest.fnTest(
		"ajax as a function - only three parameters",
		null,
		function () {
			return result.length === 3;
		}
	);

	oTest.fnTest(
		"ajax as a function - callback will insert data into the table",
		function () {
			oSession.fnRestore();

			$('#example').dataTable( {
				"ajax": function ( data, callback, settings ) {
					callback( {
						sEcho: 1,
						iTotalRecords: 1,
						iTotalDisplayRecords: 1,
						aaData: [[1,2,3,4,5]]
					} );
				}
			} );
		},
		function () {
			return $('tbody td').eq(0).html() === '1';
		}
	);


	
	oTest.fnComplete();
} );
