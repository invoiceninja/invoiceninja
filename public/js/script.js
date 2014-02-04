// http://stackoverflow.com/questions/9847580/how-to-detect-safari-chrome-ie-firefox-and-opera-browser
var isOpera = !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0;
var isFirefox = typeof InstallTrigger !== 'undefined';   // Firefox 1.0+
var isSafari = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0;
var isChrome = !!window.chrome && !isOpera;              // Chrome 1+
var isChromium = isChrome && navigator.userAgent.indexOf('Chromium') >= 0;
var isIE = /*@cc_on!@*/false || !!document.documentMode; // At least IE6

function generatePDF(invoice, checkMath) {
	var client = invoice.client;
	var account = invoice.account;
	var currencyId = client.currency_id;
	var invoiceNumber = invoice.invoice_number;
	var invoiceDate = invoice.invoice_date ? invoice.invoice_date : '';
	var dueDate = invoice.due_date ? invoice.due_date : '';

	var marginLeft = 50;
	var accountTop = 30;
	var headerTop = 140;
	var headerLeft = 360;
	var headerRight = 550;
	var rowHeight = 15;
	var tableRowHeight = 20;
	var footerLeft = 420;
	var tablePadding = 6;

	var tableTop = 240;
	var tableLeft = 50;
	var descriptionLeft = 162;
	var unitCostRight = 410;
	var qtyRight = 480;
	var taxRight = 480;
	var lineTotalRight = 550;
	

	var hasTaxes = false;
	for (var i=0; i<invoice.invoice_items.length; i++) 
	{
		var item = invoice.invoice_items[i];		
		if ((item.tax && item.tax.rate > 0) || (item.tax_rate && parseFloat(item.tax_rate) > 0)) {
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
		var left = headerRight - invoice.imageWidth;
		doc.addImage(invoice.image, 'JPEG', left, 30, invoice.imageWidth, invoice.imageHeight);
	}	
	
	/* table header */
	doc.setDrawColor(200,200,200);
	doc.setFillColor(230,230,230);
	var x1 = headerLeft - tablePadding;
	var y1 = headerTop + rowHeight + 4;
	var x2 = headerRight - headerLeft + 11;
	var y2 = rowHeight + 1;
	if (invoice.po_number) {
		y1 += rowHeight;
	}
	if (dueDate) {
		y1 += rowHeight;
	}
	doc.rect(x1, y1, x2, y2, 'FD'); 

	var invoiceNumberX = headerRight - (doc.getStringUnitWidth(invoiceNumber, false) * doc.internal.getFontSize());
	var invoiceDateX = headerRight - (doc.getStringUnitWidth(invoiceDate) * doc.internal.getFontSize());	
	var dueDateX = headerRight - (doc.getStringUnitWidth(dueDate) * doc.internal.getFontSize());	
	var poNumberX = headerRight - (doc.getStringUnitWidth(invoice.po_number) * doc.internal.getFontSize());	

	doc.setFontType("normal");

	var y = accountTop;
	var left = marginLeft;
	
	if (account.name) {
		y += rowHeight;
		doc.text(left, y, account.name);
	}
	if (account.address1) {
		y += rowHeight;
		doc.text(left, y, account.address1);
	}
	if (account.address2) {
		y += rowHeight;
		doc.text(left, y, account.address2);
	}
	if (account.city || account.state || account.postal_code) {
		y += rowHeight;
		doc.text(left, y, account.city + ', ' + account.state + ' ' + account.postal_code);
	}
	if (account.country) {
		y += rowHeight;
		doc.text(left, y, account.country.name);
	}

	if (client) {
		var y = headerTop;
		doc.text(marginLeft, y, getClientDisplayName(client));
		if (client.address1) {
			y += rowHeight;
			doc.text(marginLeft, y, client.address1);
		}
		if (client.address2) {
			y += rowHeight;
			doc.text(marginLeft, y, client.address2);
		}
		if (client.city || client.state || client.postal_code) {
			y += rowHeight;
			doc.text(marginLeft, y, client.city + ', ' + client.state + ' ' + client.postal_code);
		}
		if (client.country) {
			y += rowHeight;
			doc.text(marginLeft, y, client.country.name);
		}
	}

	var headerY = headerTop;
	doc.text(headerLeft, headerY, 'Invoice Number');
	doc.text(invoiceNumberX, headerY, invoiceNumber);

	if (invoice.po_number) {
		headerY += rowHeight;
		doc.text(headerLeft, headerY, 'PO Number');
		doc.text(poNumberX, headerY, invoice.po_number);		
	}

	headerY += rowHeight;
	doc.text(headerLeft, headerY, 'Invoice Date');
	doc.text(invoiceDateX, headerY, invoiceDate);
	
	if (dueDate) {
		headerY += rowHeight;
		doc.text(headerLeft, headerY, 'Due Date');
		doc.text(dueDateX, headerY, dueDate);				
	}	
	
	headerY += rowHeight;
	doc.setFontType("bold");
	doc.text(headerLeft, headerY, 'Balance Due');

	var total = 0;

	for (var i=0; i<invoice.invoice_items.length; i++) {
		var item = invoice.invoice_items[i];
		var tax = 0;
		if (item.tax && parseFloat(item.tax.rate)) {
			tax = parseFloat(item.tax.rate);
		} else if (item.tax_rate && parseFloat(item.tax_rate)) {
			tax = parseFloat(item.tax_rate);
		}		

		var lineTotal = item.cost * item.qty;
		if (tax) {
			lineTotal += lineTotal * tax / 100;
		}
		if (lineTotal) {
			total += lineTotal;
		}
	}

	if (invoice.discount > 0) {

		var discount = total * (invoice.discount/100);
		total -= discount;
	}

	var tax = 0;
	if (invoice.tax && parseFloat(invoice.tax.rate)) {
		tax = parseFloat(invoice.tax.rate);
	} else if (invoice.tax_rate && parseFloat(invoice.tax_rate)) {
		tax = parseFloat(invoice.tax_rate);
	}		

	if (tax) {
		var tax = total * (tax/100);
		total = parseFloat(total) + parseFloat(tax);
	}

	total = formatMoney(total - (invoice.amount - invoice.balance), currencyId);

	var balance = formatMoney(total, currencyId);
	balanceX = headerRight - (doc.getStringUnitWidth(balance) * doc.internal.getFontSize());
	doc.text(balanceX, headerY, balance);


	doc.setDrawColor(200,200,200);
	doc.setFillColor(230,230,230);
	doc.rect(tableLeft - tablePadding, tableTop - 12, headerRight - tableLeft + 12, rowHeight + 2, 'FD');

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
		var cost = formatMoney(item.cost, currencyId, true);
		var qty = NINJA.parseFloat(item.qty) ? NINJA.parseFloat(item.qty) + '' : '';
		var notes = item.notes;
		var productKey = item.product_key;
		var tax = 0;
		if (item.tax && parseFloat(item.tax.rate)) {
			tax = parseFloat(item.tax.rate);
		} else if (item.tax_rate && parseFloat(item.tax_rate)) {
			tax = parseFloat(item.tax_rate);
		}		

		// show at most one blank line
		if (shownItem && (!cost || cost == '0.00') && !qty && !notes && !productKey) {
			continue;
		}		
		shownItem = true;

		// process date variables
		notes = processVariables(notes);
		productKey = processVariables(productKey);

		var lineTotal = NINJA.parseFloat(item.cost) * NINJA.parseFloat(item.qty);
		if (tax) {
			lineTotal += lineTotal * tax / 100;
		}
		if (lineTotal) {
			total += lineTotal;
		}
		lineTotal = formatMoney(lineTotal, currencyId, true);
		
		var costX = unitCostRight - (doc.getStringUnitWidth(cost) * doc.internal.getFontSize());
		var qtyX = qtyRight - (doc.getStringUnitWidth(qty) * doc.internal.getFontSize());
		var taxX = taxRight - (doc.getStringUnitWidth(tax+'%') * doc.internal.getFontSize());
		var totalX = lineTotalRight - (doc.getStringUnitWidth(lineTotal) * doc.internal.getFontSize());
		var x = tableTop + (line * tableRowHeight) + 6;
		if (i==0) x -= 4;

		doc.text(tableLeft, x, productKey);
		doc.text(descriptionLeft, x, notes);
		doc.text(costX, x, cost);
		doc.text(qtyX, x, qty);
		doc.text(totalX, x, lineTotal);

		if (tax) {
			doc.text(taxX, x, tax+'%');
		}

		line += (doc.splitTextToSize(item.notes, 200).length * .6) + .4;

		if (i < invoice.invoice_items.length - 2) {
			doc.setLineWidth(0.5);
			doc.setDrawColor(220,220,220);
			doc.line(tableLeft - tablePadding, tableTop + (line * tableRowHeight) - 8, 
				lineTotalRight+tablePadding, tableTop + (line * tableRowHeight) - 8);
		}

		if (line > 20) {
			line = 0;
			tableTop = 60;
			doc.addPage();
		}
	}
	
	/* table footer */
	doc.setDrawColor(200,200,200);
	var x = tableTop + (line * tableRowHeight) - 6;

	doc.setLineWidth(1);
	doc.line(tableLeft - tablePadding, x, lineTotalRight+tablePadding, x);
	//console.log('%s %s %s', lineTotalRight, tableLeft, (lineTotalRight-tableLeft));

	doc.text(tableLeft, x+16, invoice.public_notes);
	if (invoice.terms) {
		var termsX = x+16 + (doc.splitTextToSize(invoice.public_notes, 340).length * rowHeight) + (rowHeight/2);
		doc.setFontType("bold");
		doc.text(tableLeft, termsX, "Terms");
		doc.setFontType("normal");
		doc.text(tableLeft, termsX + rowHeight, invoice.terms);
	}

	x += 16;
	doc.text(footerLeft, x, 'Subtotal');
	var prettyTotal = formatMoney(total, currencyId, true);
	var totalX = headerRight - (doc.getStringUnitWidth(prettyTotal) * doc.internal.getFontSize());
	doc.text(totalX, x, prettyTotal);		

	if (invoice.discount > 0) {

		x += 16;
		doc.text(footerLeft, x, 'Discount');
		var discount = total * (invoice.discount/100);
		total -= discount;
		discount = formatMoney(discount, currencyId, true);
		var discountX = headerRight - (doc.getStringUnitWidth(discount) * doc.internal.getFontSize());
		doc.text(discountX, x, discount);		
	}

	var tax = 0;
	if (invoice.tax && parseFloat(invoice.tax.rate)) {
		tax = parseFloat(invoice.tax.rate);
	} else if (invoice.tax_rate && parseFloat(invoice.tax_rate)) {
		tax = parseFloat(invoice.tax_rate);
	}		

	if (tax) {
		x += 16;
		doc.text(footerLeft, x, 'Tax ' + tax + '%');
		var tax = total * (tax/100);
		total = parseFloat(total) + parseFloat(tax);
		tax = formatMoney(tax, currencyId, true);
		var taxX = headerRight - (doc.getStringUnitWidth(tax) * doc.internal.getFontSize());
		doc.text(taxX, x, tax);		
	}

	x += 16;
	doc.text(footerLeft, x, 'Paid to Date');
	var paid = formatMoney(invoice.amount - invoice.balance, currencyId, true);
	var paidX = headerRight - (doc.getStringUnitWidth(paid) * doc.internal.getFontSize());
	doc.text(paidX, x, paid);		

	x += 16;

	if (checkMath && NINJA.parseFloat(total).toFixed(4) != NINJA.parseFloat(invoice.amount).toFixed(4)) 
	{
		var doc = new jsPDF('p', 'pt');
		doc.setFont('Helvetica','');
		doc.setFontSize(10);
		doc.text(100, 100, "An error occurred, please try again later.");
		onerror('Failed to generate PDF ' + total + ', ' + invoice.amount );
		return doc;		
	}	


	doc.setDrawColor(200,200,200);
	doc.setFillColor(230,230,230);
	var x1 = footerLeft - tablePadding;
	var y1 = x - 11;
	var x2 = headerRight - footerLeft + 11;
	var y2 = 16;
	doc.rect(x1, y1, x2, y2, 'FD'); 

	doc.setFontType("bold");
	doc.text(footerLeft, x, 'Balance Due');
	
	total = formatMoney(total - (invoice.amount - invoice.balance), currencyId);
	var totalX = headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
	doc.text(totalX, x, total);		

	
	doc.setFontType("normal");
	doc.text(marginLeft, 790, "Created by InvoiceNinja.com");

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
    if (month < 0) {
    	month += 12;
    }
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
       //console.log("combo-init: %s", id);
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
       	//console.log("combo-update: %s", id);
    	if (id) { 
    		$(element).val(id);       
    		$(element).combobox('refresh');
    	} else {
    		$(element).combobox('clearTarget');    		
    		$(element).combobox('clearElement');    		
    	}       
    }    
};


ko.bindingHandlers.datePicker = {
    init: function (element, valueAccessor, allBindingsAccessor) {
       var value = ko.utils.unwrapObservable(valueAccessor());       
       if (value) $(element).datepicker('update', value);
       $(element).change(function() { 
       		var value = valueAccessor();
            value($(element).val());
       })
    },
    update: function (element, valueAccessor) {    	
       var value = ko.utils.unwrapObservable(valueAccessor());
       if (value) $(element).datepicker('update', value);
    }    
};


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
        lines[i + 1] = lines[i].substring(space + 1) + ' ' + (lines[i + 1] || "");
        lines[i] = lines[i].substring(0, space);
    }
    
    var newValue = (lines.join("\n")).trim();

    if (value == newValue) {
    	return newValue;
    } else {
    	return wordWrapText(newValue, width);
    }
}



function getClientDisplayName(client)
{
	var contact = client.contacts[0];
	if (client.name) {
		return client.name;
	} else if (contact.first_name || contact.last_name) {
		return contact.first_name + ' ' + contact.last_name;
	} else {
		return contact.email;
	}
}


function populateInvoiceComboboxes(clientId, invoiceId) {
	var clientMap = {};
	var invoiceMap = {};
	var invoicesForClientMap = {};
	var $clientSelect = $('select#client');		
	
	for (var i=0; i<invoices.length; i++) {
		var invoice = invoices[i];
		var client = invoice.client;			

		if (!invoicesForClientMap.hasOwnProperty(client.public_id)) {
			invoicesForClientMap[client.public_id] = [];				
		}

		invoicesForClientMap[client.public_id].push(invoice);
		invoiceMap[invoice.public_id] = invoice;
	}

	for (var i=0; i<clients.length; i++) {
		var client = clients[i];
		clientMap[client.public_id] = client;
	}

	$clientSelect.append(new Option('', ''));	
	for (var i=0; i<clients.length; i++) {
		var client = clients[i];
		$clientSelect.append(new Option(getClientDisplayName(client), client.public_id));
	}	

	if (clientId) {
		$clientSelect.val(clientId);
	}

	$clientSelect.combobox();
	$clientSelect.on('change', function(e) {						
		var clientId = $('input[name=client]').val();
		var invoiceId = $('input[name=invoice]').val();						
		var invoice = invoiceMap[invoiceId];
		if (invoice && invoice.client.public_id == clientId) {
			e.preventDefault();
			return;
		}
		setComboboxValue($('.invoice-select'), '', '');				
		$invoiceCombobox = $('select#invoice');
		$invoiceCombobox.find('option').remove().end().combobox('refresh');			
		$invoiceCombobox.append(new Option('', ''));
		var list = clientId ? (invoicesForClientMap.hasOwnProperty(clientId) ? invoicesForClientMap[clientId] : []) : invoices;
		for (var i=0; i<list.length; i++) {
			var invoice = list[i];
			var client = clientMap[invoice.client.public_id];
			$invoiceCombobox.append(new Option(invoice.invoice_number + ' - ' + invoice.invoice_status.name + ' - ' + getClientDisplayName(client) + ' - ' + formatMoney(invoice.amount, invoice.currency_id) + ' | ' + formatMoney(invoice.balance, invoice.currency_id),  invoice.public_id));		
		}
		$('select#invoice').combobox('refresh');
	});

	var $invoiceSelect = $('select#invoice').on('change', function(e) {			
		$clientCombobox = $('select#client');
		var invoiceId = $('input[name=invoice]').val();						
		if (invoiceId) {
			var invoice = invoiceMap[invoiceId];				
			var client = clientMap[invoice.client.public_id];
			setComboboxValue($('.client-select'), client.public_id, getClientDisplayName(client));
			if (!parseFloat($('#amount').val())) {
				$('#amount').val(formatMoney(invoice.balance, invoice.currency_id, true));
			}
		}
	});

	$invoiceSelect.combobox();	

	if (invoiceId) {
		var invoice = invoiceMap[invoiceId];
		var client = clientMap[invoice.client.public_id];
		//console.log(invoice);
		setComboboxValue($('.invoice-select'), invoice.public_id, (invoice.invoice_number + ' - ' + invoice.invoice_status.name + ' - ' + getClientDisplayName(client) + ' - ' + formatMoney(invoice.amount, invoice.currency_id) + ' | ' + formatMoney(invoice.balance, invoice.currency_id)));
		$invoiceSelect.trigger('change');
	} else if (clientId) {
		var client = clientMap[clientId];
		setComboboxValue($('.client-select'), client.public_id, getClientDisplayName(client));
		$clientSelect.trigger('change');
	} else {
		$clientSelect.trigger('change');
	}	
}


var CONSTS = {};
CONSTS.INVOICE_STATUS_DRAFT = 1;
CONSTS.INVOICE_STATUS_SENT = 2;
CONSTS.INVOICE_STATUS_VIEWED = 3;
CONSTS.INVOICE_STATUS_PARTIAL = 4;
CONSTS.INVOICE_STATUS_PAID = 5;

$.fn.datepicker.defaults.autoclose = true;
$.fn.datepicker.defaults.todayHighlight = true;