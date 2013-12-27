function generatePDF(invoice) {
	var invoiceNumber = invoice.invoice_number;
	var issuedOn = invoice.invoice_date ? invoice.invoice_date : '';
	var amount = '$0.00';

	var marginLeft = 90;
	var headerTop = 140;
	var headerLeft = 360;
	var headerRight = 540;
	var rowHeight = 15;
	var footerLeft = 420;

	var tableTop = 240;
	var tableLeft = 60;
	var descriptionLeft = 140;
	var unitCostRight = 400;
	var qtyRight = 470;
	var taxRight = 470;
	var lineTotalRight = 540;
	

	var hasTaxes = true;
	for (var i=0; i<invoice.invoice_items.length; i++) 
	{
		var item = invoice.invoice_items[i];
		if (item.tax_rate > 0) {
			hasTaxes = true;
			break;
		}
	}

	if (hasTaxes)
	{
		descriptionLeft -= 20;
		unitCostRight -= 40;
		qtyRight -= 40;
	}	


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
	var x1 = headerLeft - 6;
	var y1 = headerTop + rowHeight + 4;
	var x2 = headerRight - headerLeft + 12;
	var y2 = rowHeight + 2;
	if (invoice.po_number) {
		y1 += rowHeight;
	}
	doc.rect(x1, y1, x2, y2, 'FD'); 

	var invoiceNumberX = headerRight - (doc.getStringUnitWidth(invoiceNumber) * doc.internal.getFontSize());
	var issuedOnX = headerRight - (doc.getStringUnitWidth(issuedOn) * doc.internal.getFontSize());	
	var poNumberX = headerRight - (doc.getStringUnitWidth(invoice.po_number) * doc.internal.getFontSize());	

	doc.setFontType("normal");
	if (invoice.client) {
		var y = headerTop;
		doc.text(marginLeft, y, invoice.client.name);
		y += rowHeight;
		doc.text(marginLeft, y, invoice.client.address1);
		if (invoice.client.address2) {
			y += rowHeight;
			doc.text(marginLeft, y, invoice.client.address2);
		}
		if (invoice.client.city || invoice.client.state || invoice.client.postal_code) {
			y += rowHeight;
			doc.text(marginLeft, y, invoice.client.city + ', ' + invoice.client.state + ' ' + invoice.client.postal_code);
		}
		if (invoice.client.country) {
			y += rowHeight;
			doc.text(marginLeft, y, invoice.client.country.name);
		}
	}

	var headerY = headerTop;
	doc.text(headerLeft, headerY, 'Invoice #');
	doc.text(invoiceNumberX, headerY, invoiceNumber);

	if (invoice.po_number) {
		headerY += rowHeight;
		doc.text(headerLeft, headerY, 'PO Number');
		doc.text(poNumberX, headerY, invoice.po_number);		
	}

	headerY += rowHeight;
	doc.text(headerLeft, headerY, 'Invoice Date');
	doc.text(issuedOnX, headerY, issuedOn);
	
	headerY += rowHeight;
	doc.setFontType("bold");
	doc.text(headerLeft, headerY, 'Amount Due');

	doc.setDrawColor(200,200,200);
	doc.setFillColor(230,230,230);
	doc.rect(tableLeft - 6, tableTop - 12, headerRight - tableLeft + 12, rowHeight + 2, 'FD');

	var costX = unitCostRight - (doc.getStringUnitWidth('Unit Cost') * doc.internal.getFontSize());
	var qtyX = qtyRight - (doc.getStringUnitWidth('Quantity') * doc.internal.getFontSize());
	var taxX = taxRight - (doc.getStringUnitWidth('Tax') * doc.internal.getFontSize());
	var totalX = lineTotalRight - (doc.getStringUnitWidth('Line Total') * doc.internal.getFontSize());

	doc.text(tableLeft, tableTop, 'Item');
	doc.text(descriptionLeft, tableTop, 'Description');
	doc.text(costX, tableTop, 'Unit Cost');
	doc.text(qtyX, tableTop, 'Quantity');
	doc.text(totalX, tableTop, 'Line Total');

	if (hasTaxes)
	{
		doc.text(taxX, tableTop, 'Tax');
	}

	/* line items */
	doc.setFontType("normal");
	var line = 1;
	var total = 0;
	var shownItem = false;

	for (var i=0; i<invoice.invoice_items.length; i++) {
		var item = invoice.invoice_items[i];
		var cost = formatNumber(item.cost);
		var qty = item.qty ? parseFloat(item.qty) + '' : '';
		var notes = item.notes;
		var productKey = item.product_key;

		// show at most one blank line
		if (shownItem && (!cost || cost == '0.00') && !qty && !notes && !productKey) {
			continue;
		}		
		shownItem = true;

		// process date variables
		notes = processVariables(notes);
		productKey = processVariables(productKey);

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


	doc.text(tableLeft, x+16, invoice.terms);

	x += 16;
	doc.text(footerLeft, x, 'Subtotal');
	var total = formatNumber(total);
	var totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
	doc.text(totalX, x, total);		

	if (invoice.discount > 0) {

		x += 16;
		doc.text(footerLeft, x, 'Discount');
		var discount = formatNumber(total * (invoice.discount/100));
		total -= discount;
		var discountX = headerRight - (doc.getStringUnitWidth(discount) * doc.internal.getFontSize());
		doc.text(discountX, x, discount);		
	}

	x += 16;
	doc.text(footerLeft, x, 'Paid to Date');
	var paid = formatNumber(0);
	var paidX = headerRight - (doc.getStringUnitWidth(paid) * doc.internal.getFontSize());
	doc.text(paidX, x, paid);		


	x += 16;
	doc.setFontType("bold");
	doc.text(footerLeft, x, 'Total');
	
	var total = formatMoney(total);
	var totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
	doc.text(totalX, x, total);		

	totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
	doc.text(totalX, headerY, total);

	/* payment stub */	
	/*
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


	if (invoice.client) {
		var clientX = headerRight - (doc.getStringUnitWidth(invoice.client.name) * doc.internal.getFontSize());
	}
	var numberX = headerRight - (doc.getStringUnitWidth(invoice.invoice_number) * doc.internal.getFontSize());
	var dateX = headerRight - (doc.getStringUnitWidth(issuedOn) * doc.internal.getFontSize());
	var totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());

	y = 720;
	if (invoice.client) {
		doc.setFontType("bold");
		doc.text(headerLeft, y, 'Client');		
		doc.setFontType("normal");
		doc.text(clientX, y, invoice.client.name);		
	}

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
	*/
	
	return doc;		
}


/* Handle converting variables in the invoices (ie, MONTH+1) */
function processVariables(str) {
	if (!str) return '';
	var variables = ['MONTH','QUARTER','YEAR'];
	for (var i=0; i<variables.length; i++) {
		var variable = variables[i];        
        var regexp = new RegExp(':' + variable + '[+-]?[\\d]*', 'g');
        var matches = str.match(regexp);        
        if (!matches) {
             continue;  
        }
        for (var j=0; j<matches.length; j++) {
            var match = matches[j];
            var offset = 0;                
            if (match.split('+').length > 1) {
                offset = match.split('+')[1];
            } else if (match.split('-').length > 1) {
                offset = parseInt(match.split('-')[1]) * -1;
            }
            str = str.replace(match, getDatePart(variable, offset));            
        }
	}		
	
	return str;
}

function getDatePart(part, offset) {
    offset = parseInt(offset);
    if (!offset) {
        offset = 0;
    }
	if (part == 'MONTH') {
		return getMonth(offset);
	} else if (part == 'QUARTER') {
		return getQuarter(offset);
	} else if (part == 'YEAR') {
		return getYear(offset);
	}
}

function getMonth(offset) {
	var today = new Date();
	var months = [ "January", "February", "March", "April", "May", "June",
    				"July", "August", "September", "October", "November", "December" ];
	var month = today.getMonth();
    month = parseInt(month) + offset;    
    month = month % 12;
    return months[month];
}

function getYear(offset) {
	var today = new Date();
	var year = today.getFullYear();
	return parseInt(year) + offset;
}

function getQuarter(offset) {
	var today = new Date();
	var quarter = Math.floor((today.getMonth() + 3) / 3);
	quarter += offset;
    quarter = quarter % 4;
    if (quarter == 0) {
         quarter = 4;   
    }
    return 'Q' + quarter;
}




function formatMoney(num) {
	num = parseFloat(num);
    if (!num) return '$0.00';
	return '$' + formatNumber(num);
}


function formatNumber(num) {
	num = parseFloat(num);
    if (!num) num = 0;
	var p = num.toFixed(2).split(".");
    return p[0].split("").reverse().reduce(function(acc, num, i, orig) {
        return  num + (i && !(i % 3) ? "," : "") + acc;
    }, "") + "." + p[1];
}


/* Set the defaults for DataTables initialisation */
$.extend( true, $.fn.dataTable.defaults, {
	"sDom": "t<'row-fluid'<'span6'i><'span6'p>>",
	//"sDom": "<'row'<'span6'l><'span6'f>r>t<'row'<'span6'i><'span6'p>>",		
	"sPaginationType": "bootstrap",
	//"bProcessing": true,            
	//"iDisplayLength": 50,
	"bInfo": true,
	"oLanguage": {
		//"sLengthMenu": "_MENU_ records per page"
		"sLengthMenu": "_MENU_",
		"sSearch": ""
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
	$combobox.find('input').val(id);
	$combobox.find('input.form-control').val(name);
	if (id && name) {
		$combobox.find('select').combobox('setSelected');
		$combobox.find('.combobox-container').addClass('combobox-selected');
	} else {
		$combobox.find('.combobox-container').removeClass('combobox-selected');
	}
}


var BASE64_MARKER = ';base64,';
function convertDataURIToBinary(dataURI) {
  var base64Index = dataURI.indexOf(BASE64_MARKER) + BASE64_MARKER.length;
  var base64 = dataURI.substring(base64Index);
  var raw = window.atob(base64);
  var rawLength = raw.length;
  var array = new Uint8Array(new ArrayBuffer(rawLength));

  for(i = 0; i < rawLength; i++) {
    array[i] = raw.charCodeAt(i);
  }
  return array;
}


ko.bindingHandlers.dropdown = {
    init: function (element, valueAccessor, allBindingsAccessor) {
       var options = allBindingsAccessor().dropdownOptions|| {};
       var value = ko.utils.unwrapObservable(valueAccessor());
       var id = (value && value.public_id) ? value.public_id() : (value && value.id) ? value.id() : value ? value : false;
       if (id) $(element).val(id);
       console.log("combo-init: %s", id);
       $(element).combobox(options);       

       /*
        ko.utils.registerEventHandler(element, "change", function () {
        	console.log("change: %s", $(element).val());
	       	var  
	       	valueAccessor($(element).val());
            //$(element).combobox('refresh');
        });
			*/
    },
    update: function (element, valueAccessor) {    	
    	var value = ko.utils.unwrapObservable(valueAccessor());
    	var id = (value && value.public_id) ? value.public_id() : (value && value.id) ? value.id() : value ? value : false;
       	console.log("combo-update: %s", id);
    	if (id) $(element).val(id);       
        $(element).combobox('refresh');
    }    
};


/*
ko.bindingHandlers.datePicker = {
    init: function (element, valueAccessor, allBindingsAccessor) {
       var value = ko.utils.unwrapObservable(valueAccessor());       
       if (value) $(element).datepicker('update', value);
       console.log("datePicker-init: %s", value);
    },
    update: function (element, valueAccessor) {    	
       var value = ko.utils.unwrapObservable(valueAccessor());
       if (value) $(element).datepicker('update', value);
       console.log("datePicker-init: %s", value);
    }    
};
*/


function wordWrapText(value, width)
{
	if (!width) width = 200;
	var doc = new jsPDF('p', 'pt');
	doc.setFont('Helvetica','');
	doc.setFontSize(10);

	var lines = value.split("\n");
    for (var i = 0; i < lines.length; i++) {
    	var numLines = doc.splitTextToSize(lines[i], width).length;
        if (numLines <= 1) continue;
        var j = 0; space = lines[i].length;
        while (j++ < lines[i].length) {
            if (lines[i].charAt(j) === " ") space = j;
        }
        lines[i + 1] = lines[i].substring(space + 1) + (lines[i + 1] || "");
        lines[i] = lines[i].substring(0, space);
    }
    
    return lines.slice(0, 6).join("\n");
}

var CONSTS = {};
CONSTS.INVOICE_STATUS_DRAFT = 1;
CONSTS.INVOICE_STATUS_SENT = 2;
CONSTS.INVOICE_STATUS_VIEWED = 3;
CONSTS.INVOICE_STATUS_PARTIAL = 4;
CONSTS.INVOICE_STATUS_PAID = 5;
