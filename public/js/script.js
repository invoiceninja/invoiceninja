function generatePDF(invoice) {
	
	var clientName = invoice.client.name;
	var invoiceNumber = invoice.invoice_number;
	var issuedOn = invoice.invoice_date;
	var amount = '$0.00';

	var marginLeft = 90;
	var headerTop = 140;
	var headerLeft = 360;
	var headerRight = 540;
	var rowHeight = 15;
	var footerLeft = 450;

	var tableTop = 240;
	var tableLeft = 60;
	var descriptionLeft = 140;
	var unitCostRight = 400;
	var qtyRight = 470;
	var lineTotalRight = 540;
	
	var doc = new jsPDF('p', 'pt');
	doc.setFont('Helvetica','');
	doc.setFontSize(10);
	
	if (invoice.image)
	{
		doc.addImage(invoice.image, 'JPEG', 30, 30, invoice.imageWidth, invoice.imageHeight);
	}	
	
	/* table header */
	doc.setDrawColor(200,200,200);
	doc.setFillColor(230,230,230);
	doc.rect(headerLeft - 6, headerTop + rowHeight + 4, headerRight - headerLeft + 12, rowHeight + 2, 'FD'); 

	var invoiceNumberX = headerRight - (doc.getStringUnitWidth(invoiceNumber) * doc.internal.getFontSize());
	var issuedOnX = headerRight - (doc.getStringUnitWidth(issuedOn) * doc.internal.getFontSize());	

	doc.setFontType("normal");
	doc.text(marginLeft, headerTop, clientName);
	doc.text(headerLeft, headerTop, 'Invoice #');
	doc.text(invoiceNumberX, headerTop, invoiceNumber);
	doc.text(headerLeft, headerTop + rowHeight, 'Invoice Date');
	doc.text(issuedOnX, headerTop + rowHeight, issuedOn);
	
	doc.setFontType("bold");
	doc.text(headerLeft, headerTop + (2 * rowHeight), 'Amount Due');

	doc.setDrawColor(200,200,200);
	doc.setFillColor(230,230,230);
	doc.rect(tableLeft - 6, tableTop - 12, headerRight - tableLeft + 12, rowHeight + 2, 'FD');

	var costX = unitCostRight - (doc.getStringUnitWidth('Unit Cost') * doc.internal.getFontSize());
	var qtyX = qtyRight - (doc.getStringUnitWidth('Quantity') * doc.internal.getFontSize());
	var totalX = lineTotalRight - (doc.getStringUnitWidth('Line Total') * doc.internal.getFontSize());

	doc.text(tableLeft, tableTop, 'Item');
	doc.text(descriptionLeft, tableTop, 'Description');
	doc.text(costX, tableTop, 'Unit Cost');
	doc.text(qtyX, tableTop, 'Quantity');
	doc.text(totalX, tableTop, 'Line Total');

	/* line items */
	doc.setFontType("normal");
	var line = 1;
	var total = 0;
	var shownItem = false;

	for(var i=0; i<invoice.invoice_items.length; i++) {
		var item = invoice.invoice_items[i];
		var cost = formatNumber(item.cost);
		var qty = item.qty ? parseInt(item.qty, 10) + '' : '';
		var notes = item.notes;
		var productKey = item.product_key;

		// show at most one blank line
		if (shownItem && !cost && !qty && !notes && !productKey) {
			continue;
		}
		shownItem = true;

		var lineTotal = item.cost * item.qty;
		if (lineTotal) total += lineTotal;
		lineTotal = formatNumber(lineTotal);
		
		var costX = unitCostRight - (doc.getStringUnitWidth(cost) * doc.internal.getFontSize());
		var qtyX = qtyRight - (doc.getStringUnitWidth(qty) * doc.internal.getFontSize());
		var totalX = lineTotalRight - (doc.getStringUnitWidth(lineTotal) * doc.internal.getFontSize());
		var x = tableTop + (line * rowHeight) + 6;

		doc.text(tableLeft, x, productKey);
		doc.text(descriptionLeft, x, notes);
		doc.text(costX, x, cost);
		doc.text(qtyX, x, qty);
		doc.text(totalX, x, lineTotal);

		line += doc.splitTextToSize(item.notes, 200).length;
	}
	
	/* table footer */
	var x = tableTop + (line * rowHeight);
	doc.lines([[0,0],[headerRight-tableLeft+5,0]],tableLeft - 8, x);

	x += 16;
	doc.setFontType("bold");
	doc.text(footerLeft, x, 'Total');
	
	var total = formatMoney(total);
	var totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
	doc.text(totalX, x, total);		

	totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
	doc.text(totalX, headerTop + (2 * rowHeight), total);

	/* payment stub */	
	var y = 680;
	doc.lines([[0,0],[headerRight-tableLeft+5,0]],tableLeft - 8, y - 30);
	doc.setFontSize(20);
	doc.text(tableLeft, y, 'Payment Stub');

	doc.setFontSize(10);
	doc.setFontType("normal");
	y += 40;
	doc.text(tableLeft, y, invoice.account.name);	
	y += 16;
	doc.text(tableLeft, y, invoice.account.address1);	
	if (invoice.account.address2) {
		y += 16;
		doc.text(tableLeft, y, invoice.account.address2);	
	}
	y += 16;
	doc.text(tableLeft, y, invoice.account.city + ', ' + invoice.account.state + ' ' + invoice.account.postal_code);	
	y += 16;
	doc.text(tableLeft, y, invoice.account.country ? invoice.account.country.name : '');	


	var clientX = headerRight - (doc.getStringUnitWidth(invoice.client.name) * doc.internal.getFontSize());
	var numberX = headerRight - (doc.getStringUnitWidth(invoice.invoice_number) * doc.internal.getFontSize());
	var dateX = headerRight - (doc.getStringUnitWidth(issuedOn) * doc.internal.getFontSize());
	var totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());

	y = 720;
	doc.setFontType("bold");
	doc.text(headerLeft, y, 'Client');		
	doc.setFontType("normal");
	doc.text(clientX, y, invoice.client.name);		

	y += 16;
	doc.setFontType("bold");
	doc.text(headerLeft, y, 'Invoice #');		
	doc.setFontType("normal");
	doc.text(numberX, y, invoice.invoice_number);		

	y += 16;
	doc.setFontType("bold");
	doc.text(headerLeft, y, 'Invoice Date');		
	doc.setFontType("normal");
	doc.text(dateX, y, issuedOn);		

	y += 16;
	doc.setFontType("bold");
	doc.text(headerLeft, y, 'Amount Due');		
	doc.setFontType("normal");
	doc.text(totalX, y, total);		

	y += 16;
	doc.setFontType("bold");
	doc.text(headerLeft, y, 'Amount Enclosed');		

	return doc;		
}

function formatNumber(num) {
	num = parseFloat(num);
    if (!num) return '';
	var p = num.toFixed(2).split(".");
    return p[0].split("").reverse().reduce(function(acc, num, i, orig) {
        return  num + (i && !(i % 3) ? "," : "") + acc;
    }, "") + "." + p[1];
}

function formatMoney(num) {
	num = parseFloat(num);
    if (!num) return '$0.00';
	return '$' + formatNumber(num);
}








/* Set the defaults for DataTables initialisation */
$.extend( true, $.fn.dataTable.defaults, {
	"sDom": "t<'row-fluid'<'span6'i><'span6'p>>",
	//"sDom": "<'row'<'span6'l><'span6'f>r>t<'row'<'span6'i><'span6'p>>",		
	"sPaginationType": "bootstrap",
	"bProcessing": false,            
	//"iDisplayLength": 50,
	"bInfo": true,
	"oLanguage": {
		//"sLengthMenu": "_MENU_ records per page"
		"sLengthMenu": "_MENU_"
	},
	//"sScrollY": "500px",	
} );


/* Default class modification */
$.extend( $.fn.dataTableExt.oStdClasses, {
	"sWrapper": "dataTables_wrapper form-inline"
} );


/* API method to get paging information */
$.fn.dataTableExt.oApi.fnPagingInfo = function ( oSettings )
{
	return {
		"iStart":         oSettings._iDisplayStart,
		"iEnd":           oSettings.fnDisplayEnd(),
		"iLength":        oSettings._iDisplayLength,
		"iTotal":         oSettings.fnRecordsTotal(),
		"iFilteredTotal": oSettings.fnRecordsDisplay(),
		"iPage":          oSettings._iDisplayLength === -1 ?
			0 : Math.ceil( oSettings._iDisplayStart / oSettings._iDisplayLength ),
		"iTotalPages":    oSettings._iDisplayLength === -1 ?
			0 : Math.ceil( oSettings.fnRecordsDisplay() / oSettings._iDisplayLength )
	};
};


/* Bootstrap style pagination control */
$.extend( $.fn.dataTableExt.oPagination, {
	"bootstrap": {
		"fnInit": function( oSettings, nPaging, fnDraw ) {
			var oLang = oSettings.oLanguage.oPaginate;
			var fnClickHandler = function ( e ) {
				e.preventDefault();
				if ( oSettings.oApi._fnPageChange(oSettings, e.data.action) ) {
					fnDraw( oSettings );
				}
			};

			$(nPaging).addClass('pagination').append(
				'<ul class="pagination">'+
					'<li class="prev disabled"><a href="#">&laquo;</a></li>'+
					'<li class="next disabled"><a href="#">&raquo;</a></li>'+
				'</ul>'
			);
			var els = $('a', nPaging);
			$(els[0]).bind( 'click.DT', { action: "previous" }, fnClickHandler );
			$(els[1]).bind( 'click.DT', { action: "next" }, fnClickHandler );
		},

		"fnUpdate": function ( oSettings, fnDraw ) {
			var iListLength = 5;
			var oPaging = oSettings.oInstance.fnPagingInfo();
			var an = oSettings.aanFeatures.p;
			var i, ien, j, sClass, iStart, iEnd, iHalf=Math.floor(iListLength/2);

			if ( oPaging.iTotalPages < iListLength) {
				iStart = 1;
				iEnd = oPaging.iTotalPages;
			}
			else if ( oPaging.iPage <= iHalf ) {
				iStart = 1;
				iEnd = iListLength;
			} else if ( oPaging.iPage >= (oPaging.iTotalPages-iHalf) ) {
				iStart = oPaging.iTotalPages - iListLength + 1;
				iEnd = oPaging.iTotalPages;
			} else {
				iStart = oPaging.iPage - iHalf + 1;
				iEnd = iStart + iListLength - 1;
			}

			for ( i=0, ien=an.length ; i<ien ; i++ ) {
				// Remove the middle elements
				$('li:gt(0)', an[i]).filter(':not(:last)').remove();

				// Add the new list items and their event handlers
				for ( j=iStart ; j<=iEnd ; j++ ) {
					sClass = (j==oPaging.iPage+1) ? 'class="active"' : '';
					$('<li '+sClass+'><a href="#">'+j+'</a></li>')
						.insertBefore( $('li:last', an[i])[0] )
						.bind('click', function (e) {
							e.preventDefault();
							oSettings._iDisplayStart = (parseInt($('a', this).text(),10)-1) * oPaging.iLength;
							fnDraw( oSettings );
						} );
				}

				// Add / remove disabled classes from the static elements
				if ( oPaging.iPage === 0 ) {
					$('li:first', an[i]).addClass('disabled');
				} else {
					$('li:first', an[i]).removeClass('disabled');
				}

				if ( oPaging.iPage === oPaging.iTotalPages-1 || oPaging.iTotalPages === 0 ) {
					$('li:last', an[i]).addClass('disabled');
				} else {
					$('li:last', an[i]).removeClass('disabled');
				}
			}
		}
	}
} );


/*
 * TableTools Bootstrap compatibility
 * Required TableTools 2.1+
 */
if ( $.fn.DataTable.TableTools ) {
	// Set the classes that TableTools uses to something suitable for Bootstrap
	$.extend( true, $.fn.DataTable.TableTools.classes, {
		"container": "DTTT btn-group",
		"buttons": {
			"normal": "btn",
			"disabled": "disabled"
		},
		"collection": {
			"container": "DTTT_dropdown dropdown-menu",
			"buttons": {
				"normal": "",
				"disabled": "disabled"
			}
		},
		"print": {
			"info": "DTTT_print_info modal"
		},
		"select": {
			"row": "active"
		}
	} );

	// Have the collection use a bootstrap compatible dropdown
	$.extend( true, $.fn.DataTable.TableTools.DEFAULTS.oTags, {
		"collection": {
			"container": "ul",
			"button": "li",
			"liner": "a"
		}
	} );
}

/*
$(document).ready(function() {
	$('#example').dataTable( {
		"sDom": "<'row'<'span6'l><'span6'f>r>t<'row'<'span6'i><'span6'p>>",
		"sPaginationType": "bootstrap",
		"oLanguage": {
			"sLengthMenu": "_MENU_ records per page"
		}
	} );
} );
*/

function isStorageSupported() {
  try {
      return 'localStorage' in window && window['localStorage'] !== null;
  } catch (e) {
      return false;
  }
}

function isValidEmailAddress(emailAddress) {
    var pattern = new RegExp(/^((([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+(\.([a-z]|\d|[!#\$%&'\*\+\-\/=\?\^_`{\|}~]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])+)*)|((\x22)((((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(([\x01-\x08\x0b\x0c\x0e-\x1f\x7f]|\x21|[\x23-\x5b]|[\x5d-\x7e]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(\\([\x01-\x09\x0b\x0c\x0d-\x7f]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF]))))*(((\x20|\x09)*(\x0d\x0a))?(\x20|\x09)+)?(\x22)))@((([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|\d|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.)+(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])|(([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])([a-z]|\d|-|\.|_|~|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])*([a-z]|[\u00A0-\uD7FF\uF900-\uFDCF\uFDF0-\uFFEF])))\.?$/i);
    return pattern.test(emailAddress);
};

$(function() {
    $.ajaxSetup({
        headers: {
            'X-CSRF-Token': $('meta[name="csrf-token"]').attr('content')
        }
    });
});


function enableHoverClick($combobox, $entityId, url) {
	/*
	$combobox.mouseleave(function() {
		$combobox.css('text-decoration','none');
	}).on('mouseenter', function(e) {
		setAsLink($combobox, $combobox.closest('.combobox-container').hasClass('combobox-selected'));
	}).on('focusout mouseleave', function(e) {
		setAsLink($combobox, false);
	}).on('click', function() {
		var clientId = $entityId.val();
		if ($(combobox).closest('.combobox-container').hasClass('combobox-selected')) {				
			if (parseInt(clientId) > 0) {
				window.open(url + '/' + clientId, '_blank');
			} else {
				$('#myModal').modal('show');
			}
		};
	});
*/
}

function setAsLink($input, enable) {
	if (enable) {
		$input.css('text-decoration','underline');
		$input.css('cursor','pointer');	
	} else {
		$input.css('text-decoration','none');
		$input.css('cursor','text');	
	}
}

function setComboboxValue($combobox, id, name) {
	console.log('id: ' + id);
	$combobox.find('input').val(id);
	$combobox.find('input.form-control').val(name);
	if (id && name) {
		//console.log('%s %s', $combobox.find('select')[0], id);
		//$combobox.find('select').val(id).prop('selected', 'selected');
		$combobox.find('.combobox-container').addClass('combobox-selected');
	} else {
		//$combobox.find('select').val('');
		$combobox.find('.combobox-container').removeClass('combobox-selected');
	}
	//console.log($combobox).combobox('');
}