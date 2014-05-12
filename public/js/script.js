// http://stackoverflow.com/questions/9847580/how-to-detect-safari-chrome-ie-firefox-and-opera-browser
var isOpera = !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0;
var isFirefox = typeof InstallTrigger !== 'undefined';   // Firefox 1.0+
var isSafari = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0;
var isChrome = !!window.chrome && !isOpera;              // Chrome 1+
var isChromium = isChrome && navigator.userAgent.indexOf('Chromium') >= 0;
var isIE = /*@cc_on!@*/false || !!document.documentMode; // At least IE6

function GetReportTemplate4(doc, invoice, layout, checkMath) {

  var client = invoice.client;
  var account = invoice.account;
  var currencyId = client.currency_id;  
  
  if (invoice.image)
  {
    var left = layout.headerRight - invoice.imageWidth;
    doc.addImage(invoice.image, 'JPEG', left, 30);
  } 
  
  /* table header */
  doc.setDrawColor(200,200,200);
  doc.setFillColor(230,230,230);
  
  var detailsHeight = getInvoiceDetailsHeight(invoice, layout)
  var left = layout.headerLeft - layout.tablePadding;
  var top = layout.headerTop + detailsHeight - layout.rowHeight - layout.tablePadding;
  var width = layout.headerRight - layout.headerLeft + (2 * layout.tablePadding);
  var height = layout.rowHeight + 1;
  doc.rect(left, top, width, height, 'FD'); 

  doc.setFontSize(10);
  doc.setFontType("normal");

  displayAccount(doc, invoice, layout.marginLeft, layout.accountTop, layout);
  displayClient(doc, invoice, layout.marginLeft, layout.headerTop, layout);

  displayInvoice(doc, invoice, layout.headerLeft, layout.headerTop, layout, layout.headerRight);
  layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (2 * layout.tablePadding));

  var headerY = layout.headerTop;
  var total = 0;

  doc.setDrawColor(200,200,200);
  doc.setFillColor(230,230,230);
  var left = layout.marginLeft - layout.tablePadding;
  var top = layout.tableTop - layout.tablePadding;
  var width = layout.headerRight - layout.marginLeft + (2 * layout.tablePadding);
  var height = layout.rowHeight + 2;
  doc.rect(left, top, width, height, 'FD');   

  displayInvoiceHeader(doc, invoice, layout);
  var y = displayInvoiceItems(doc, invoice, layout);

  doc.setFontSize(10);

  /* table footer */
  /*
  doc.setDrawColor(200,200,200);  
  doc.setLineWidth(1);
  doc.line(layout.marginLeft - layout.tablePadding, x, layout.lineTotalRight+layout.tablePadding, x);
  */

  displayNotesAndTerms(doc, layout, invoice, y+20);

  y += displaySubtotals(doc, layout, invoice, y+20, 480) + 20;

  /*
  if (checkMath && NINJA.parseFloat(total).toFixed(4) != NINJA.parseFloat(invoice.amount).toFixed(4)) 
  {
    var doc = new jsPDF('p', 'pt');
    doc.setFont('Helvetica','');
    doc.setFontSize(10);
    doc.text(100, 100, "An error occurred, please try again later.");
    onerror('Failed to generate PDF ' + total + ', ' + invoice.amount );
    return doc;   
  } 
  */

  doc.setDrawColor(200,200,200);
  doc.setFillColor(230,230,230);
  
  var left = layout.footerLeft - layout.tablePadding;
  var top = y - layout.tablePadding;
  var width = layout.headerRight - layout.footerLeft + (2 * layout.tablePadding);
  var height = layout.rowHeight + 2;
  doc.rect(left, top, width, height, 'FD'); 
  
  doc.setFontType("bold");
  doc.text(layout.footerLeft, y, invoiceLabels.balance_due);

  total = formatMoney(invoice.balance_amount, currencyId);
  var totalX = layout.headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
  doc.text(totalX, y, total);   

  if (!invoice.is_pro) {
    doc.setFontType("normal");
    doc.text(layout.marginLeft, 790, "Created by InvoiceNinja.com");
  }

  return doc;     
}


var invoiceOld;
function generatePDF(invoice, force) {
  invoice = calculateAmounts(invoice);  
  var a = copyInvoice(invoice);
  var b = copyInvoice(invoiceOld);
  if (!force && _.isEqual(a, b)) {
    return;
  }
  invoiceOld = invoice;
  report_id = invoice.invoice_design_id;
  doc = GetPdf(invoice, false, report_id);
  return doc;
}

function copyInvoice(orig) {
  if (!orig) return false;
  var copy = JSON.stringify(orig);
  var copy = JSON.parse(copy);
  return copy;
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
          //var  
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
  var doc = new jsPDF('p', 'pt');
  doc.setFont('Helvetica','');
  doc.setFontSize(10);

  var lines = value.split("\n");
  for (var i = 0; i < lines.length; i++) {
    var numLines = doc.splitTextToSize(lines[i], width).length;
    if (numLines <= 1) continue;
    var j = 0; space = lines[i].length;
    while (j++ < lines[i].length) {
      if (lines[i].charAt(j) === ' ') space = j;
    }
    if (space == lines[i].length) space = width/6;    
    lines[i + 1] = lines[i].substring(space + 1) + ' ' + (lines[i + 1] || '');
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
      if (!client) continue; // client is deleted/archived
      $invoiceCombobox.append(new Option(invoice.invoice_number + ' - ' + invoice.invoice_status.name + ' - ' +
                getClientDisplayName(client) + ' - ' + formatMoney(invoice.amount, invoice.currency_id) + ' | ' +
                formatMoney(invoice.balance, invoice.currency_id),  invoice.public_id));
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
    setComboboxValue($('.invoice-select'), invoice.public_id, (invoice.invoice_number + ' - ' +
            invoice.invoice_status.name + ' - ' + getClientDisplayName(client) + ' - ' +
            formatMoney(invoice.amount, invoice.currency_id) + ' | ' + formatMoney(invoice.balance, invoice.currency_id)));
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



//====================================================================================================================

function GetPdf(invoice,checkMath,report_id){
  var layout = {
    accountTop: 40,
    marginLeft: 50,
    marginRight: 550,
    headerTop: 150,
    headerLeft: 360,
    headerRight: 550,
    rowHeight: 15,
    tableRowHeight: 10,
    footerLeft: 420,
    tablePadding: 12,
    tableTop: 250,
    descriptionLeft: 162,
    unitCostRight: 410,
    qtyRight: 480,
    taxRight: 480,
    lineTotalRight: 550
  };

  if (invoice.has_taxes)
  {
    layout.descriptionLeft -= 20;
    layout.unitCostRight -= 40;
    layout.qtyRight -= 40;
  } 

  /*
   @param orientation One of "portrait" or "landscape" (or shortcuts "p" (Default), "l")
   @param unit Measurement unit to be used when coordinates are specified. One of "pt" (points), "mm" (Default), "cm", "in"
   @param format One of 'a3', 'a4' (Default),'a5' ,'letter' ,'legal'
   @returns {jsPDF}
   */
  var doc = new jsPDF('portrait', 'pt', 'a4');


  //Set PDF properities
  doc.setProperties({
      title: 'Invoice ' + invoice.invoice_number,
      subject: '',
      author: 'InvoiceNinja.com',
      keywords: 'pdf, invoice',
      creator: 'InvoiceNinja.com'
  });

  //set default style for report
  doc.setFont('Helvetica','');

  if (report_id==1) {
    return GetReportTemplate1(doc, invoice, layout, checkMath);
  } else if (report_id==2) {
    return GetReportTemplate2(doc, invoice, layout, checkMath);
  } else if (report_id==3) {
    return GetReportTemplate3(doc, invoice, layout, checkMath);
  } else {
    return GetReportTemplate4(doc, invoice, layout, checkMath);
  }
}

function GetReportTemplate1(doc, invoice, layout, checkMath)
{
    var GlobalY=0;//Y position of line at current page

    var client = invoice.client;
    var account = invoice.account;
    var currencyId = client.currency_id;

    layout.headerRight = 550;
    layout.rowHeight = 15;

    doc.setFontSize(9);

    if (invoice.image)
    {
      var left = layout.headerRight - invoice.imageWidth;
      doc.addImage(invoice.image, 'JPEG', layout.marginLeft, 30);
    }

    if (!invoice.is_pro && logoImages.imageLogo1)
    {
      pageHeight=820;
      y=pageHeight-logoImages.imageLogoHeight1;
      doc.addImage(logoImages.imageLogo1, 'JPEG', layout.marginLeft, y, logoImages.imageLogoWidth1, logoImages.imageLogoHeight1);
    }


    doc.setFontSize(9);
    SetPdfColor('LightBlue', doc, 'primary');
    displayAccount(doc, invoice, 220, layout.accountTop, layout);

    SetPdfColor('LightBlue', doc, 'primary');
    doc.setFontSize('11');
    doc.text(50, layout.headerTop, invoiceLabels.invoice.toUpperCase());

    //doc.setDrawColor(220,220,220);
    //doc.line(30, y, 560, y); // horizontal line


    SetPdfColor('Black',doc); //set black color
    doc.setFontSize(9);

    var invoiceHeight = displayInvoice(doc, invoice, 50, 170, layout);
    var clientHeight = displayClient(doc, invoice, 220, 170, layout);
    var detailsHeight = Math.max(invoiceHeight, clientHeight);
    layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (3 * layout.rowHeight));

    

    doc.setLineWidth(0.3);        
    doc.setDrawColor(200,200,200);
    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + 6, layout.marginRight + layout.tablePadding, layout.headerTop + 6);
    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + detailsHeight + 14, layout.marginRight + layout.tablePadding, layout.headerTop + detailsHeight + 14);
 

    //doc.setDrawColor(220,220,220);
    //doc.line(30, y-8, 560, y-8); // horizontal line


    doc.setFontSize(10);
    doc.setFontType("bold");
    displayInvoiceHeader(doc, invoice, layout);
    var y = displayInvoiceItems(doc, invoice, layout);

    //doc.setFontType("normal");
    doc.setFontSize(9);

    doc.setFontType("bold");

    GlobalY=GlobalY+25;


    doc.setLineWidth(0.3);
    doc.setDrawColor(241,241,241);
    doc.setFillColor(241,241,241);
    var x1 = layout.marginLeft - 12;
    var y1 = GlobalY-layout.tablePadding;

    var w2 = 510 + 24;
    var h2 = doc.internal.getFontSize()*3+layout.tablePadding*2;

    if (invoice.discount) {
        h2 += doc.internal.getFontSize()*2;
    }
    if (invoice.tax_amount) {
        h2 += doc.internal.getFontSize()*2;
    }

    //doc.rect(x1, y1, w2, h2, 'FD');

    doc.setFontSize(9);
    displayNotesAndTerms(doc, layout, invoice, y);
    y += displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);


    doc.setFontSize(10);
    Msg = invoiceLabels.balance_due;
    var TmpMsgX = layout.unitCostRight-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
    
    doc.text(TmpMsgX, y, Msg);

    SetPdfColor('LightBlue', doc, 'primary');
    AmountText = formatMoney(invoice.balance_amount, currencyId);
    headerLeft=layout.headerRight+400;
    var AmountX = layout.lineTotalRight - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, y, AmountText);

    return doc;
}




function GetReportTemplate2(doc, invoice, layout, checkMath)
{
  var GlobalY=0;//Y position of line at current page

  var client = invoice.client;
  var account = invoice.account;
  var currencyId = client.currency_id;

  layout.headerRight = 150;
  layout.rowHeight = 15;
  layout.headerTop = 125;
  layout.tableTop = 300;

  doc.setLineWidth(0.5);

  if (NINJA.primaryColor) {
    setDocHexFill(doc, NINJA.primaryColor);
    setDocHexDraw(doc, NINJA.primaryColor);
  } else {
    doc.setFillColor(46,43,43);
  }  

  var x1 =0;
  var y1 = 0;
  var w2 = 595;
  var h2 = 100;
  doc.rect(x1, y1, w2, h2, 'FD');


  if (invoice.image)
  {
      var left = layout.headerRight - invoice.imageWidth;
      doc.addImage(invoice.image, 'JPEG', layout.marginLeft, 30);
  }

  Report2AddFooter (invoice,doc);


  doc.setFontSize(7);
  doc.setFontType("bold");
  SetPdfColor('White',doc);

  displayAccount(doc, invoice, 300, layout.accountTop, layout);

  /*
  var spacer = '     ';
  var line1 = account.name + spacer + account.work_email + spacer + account.work_phone;
  var lineWidth = doc.getStringUnitWidth(line1) * doc.internal.getFontSize();
  var nameWidth = doc.getStringUnitWidth(account.name + spacer) * doc.internal.getFontSize();
  var nameX = lineTotalRight - lineWidth;
  var detailsX = lineTotalRight - (lineWidth - nameWidth);  
  
  doc.text(nameX, accountTop, account.name);  
  doc.setFontType("normal");
  doc.text(detailsX, accountTop, account.work_email + spacer + account.work_phone);  

  var line2 = concatStrings(account.address1, account.address2) + spacer + concatStrings(account.city, account.state, account.postal_code);
  var lineWidth = doc.getStringUnitWidth(line2) * doc.internal.getFontSize();
  var line2X = lineTotalRight - lineWidth;

  doc.text(line2X, accountTop + 16, line2);  
  */

//-----------------------------Publish Client Details block--------------------------------------------

  var y = layout.accountTop;
  var left = layout.marginLeft;

  var headerY = layout.headerTop;



  SetPdfColor('GrayLogo',doc); //set black color


  doc.setFontSize(7);

  //show left column
  SetPdfColor('Black',doc); //set black color
  doc.setFontType("normal");


  //publish filled box
  doc.setDrawColor(200,200,200);

  if (NINJA.secondaryColor) {
    setDocHexFill(doc, NINJA.secondaryColor);
  } else {
    doc.setFillColor(54,164,152);  
  }  

  GlobalY=190;
  doc.setLineWidth(0.5);

  var BlockLenght=220;
  var x1 =595-BlockLenght;
  var y1 = GlobalY-12;
  var w2 = BlockLenght;
  var h2 = getInvoiceDetailsHeight(invoice, layout) + layout.tablePadding + 2;

  doc.rect(x1, y1, w2, h2, 'FD');


  SetPdfColor('SomeGreen', doc, 'secondary');
  doc.setFontSize('14');
  doc.setFontType("bold");
  doc.text(50, GlobalY, invoiceLabels.your_invoice.toUpperCase());


  var z=GlobalY;
  z=z+30;


  doc.setFontSize('8');        
  SetPdfColor('Black',doc);
  displayClient(doc, invoice, layout.marginLeft, z, layout);


  marginLeft2=395;

  //publish left side information

  SetPdfColor('White',doc);
  doc.setFontSize('8');
  var detailsHeight = displayInvoice(doc, invoice, marginLeft2, z-25, layout) + 75;
  layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (2 * layout.tablePadding));

  y=z+60;


  x = GlobalY + 100;
  
  doc.setFontType("bold");



  doc.setFontSize(12);
  doc.setFontType("bold");
  SetPdfColor('Black',doc);
  displayInvoiceHeader(doc, invoice, layout);
  var y = displayInvoiceItems(doc, invoice, layout);

  //GlobalY=600;

  doc.setLineWidth(0.3);

  /*
  doc.setDrawColor(251,251,251);
  doc.setFillColor(251,251,251);
  var x1 = layout.marginLeft-layout.tablePadding*2 +14;
  var y1 = GlobalY-layout.tablePadding;
  var w2 = 510+layout.tablePadding*2;//lineTotalRight-tablePadding*5;
  var h2 = doc.internal.getFontSize()*3+layout.tablePadding*2;
  doc.rect(x1, y1, w2, h2, 'FD');
  */

  displayNotesAndTerms(doc, layout, invoice, y);
  y += displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);

  doc.setFontType("bold");

  doc.setFontSize(12);
  x += doc.internal.getFontSize()*4;
  Msg = invoiceLabels.balance_due;
  var TmpMsgX = layout.unitCostRight-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());



  doc.text(TmpMsgX, y, Msg);


  //SetPdfColor('LightBlue',doc);
  AmountText = formatMoney(invoice.balance_amount , currencyId);
  headerLeft=layout.headerRight+400;
  var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
  SetPdfColor('SomeGreen', doc, 'secondary');
  doc.text(AmountX, y, AmountText);

  return doc;
}







function SetPdfColor(color, doc, role)
{
  if (role === 'primary' && NINJA.primaryColor) {
    return setDocHexColor(doc, NINJA.primaryColor);
  } else if (role === 'secondary' && NINJA.secondaryColor) {
    return setDocHexColor(doc, NINJA.secondaryColor);
  }

  if (color=='LightBlue') {
      return doc.setTextColor(41,156, 194);
  }

  if (color=='Black') {
      return doc.setTextColor(46,43,43);//select color black
  }
  if (color=='GrayLogo') {
      return doc.setTextColor(207,241, 241);//select color Custom Report GRAY
  }

  if (color=='GrayBackground') {
      return doc.setTextColor(251,251, 251);//select color Custom Report GRAY
  }

  if (color=='GrayText') {
      return doc.setTextColor(161,160,160);//select color Custom Report GRAY Colour
  }

  if (color=='White') {
      return doc.setTextColor(255,255,255);//select color Custom Report GRAY Colour
  }

  if (color=='SomeGreen') {
      return doc.setTextColor(54,164,152);//select color Custom Report GRAY Colour
  }

  if (color=='LightGrayReport2-gray') {
      return doc.setTextColor(240,240,240);//select color Custom Report GRAY Colour
  }

  if (color=='LightGrayReport2-white') {
      return doc.setTextColor(251,251,251);//select color Custom Report GRAY Colour
  }

}

function Report2AddFooter (invoice,doc)
{
  doc.setLineWidth(0.5);
  if (NINJA.primaryColor) {
    setDocHexFill(doc, NINJA.primaryColor);
    setDocHexDraw(doc, NINJA.primaryColor);
  } else {
    doc.setFillColor(46,43,43);
    doc.setDrawColor(46,43,43);
  }  

  // return doc.setTextColor(240,240,240);//select color Custom Report GRAY Colour
  var x1 = 0;//tableLeft-tablePadding ;
  var y1 = 750;
  var w2 = 596;
  var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;


  doc.rect(x1, y1, w2, h2, 'FD');

  if (!invoice.is_pro && logoImages.imageLogo2)
  {
      pageHeight=820;
      var left = 250;//headerRight ;
      y=pageHeight-logoImages.imageLogoHeight2;
      var headerRight=370;

      var left = headerRight - logoImages.imageLogoWidth2;
      doc.addImage(logoImages.imageLogo2, 'JPEG', left, y, logoImages.imageLogoWidth2, logoImages.imageLogoHeight2);
  }
}

function Report3AddFooter (invoice, account, doc, layout)
{

  doc.setLineWidth(0.5);

  if (NINJA.primaryColor) {
    setDocHexFill(doc, NINJA.primaryColor);
    setDocHexDraw(doc, NINJA.primaryColor);
  } else {
    doc.setDrawColor(242,101,34);
    doc.setFillColor(242,101,34);
  }  

  var x1 = 0;//tableLeft-tablePadding ;
  var y1 = 750;
  var w2 = 596;
  var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;

  doc.rect(x1, y1, w2, h2, 'FD');

  if (!invoice.is_pro && logoImages.imageLogo3)
  {
      pageHeight=820;
    // var left = 25;//250;//headerRight ;
      y=pageHeight-logoImages.imageLogoHeight3;
      //var headerRight=370;

      //var left = headerRight - invoice.imageLogoWidth3;
      doc.addImage(logoImages.imageLogo3, 'JPEG', 40, y, logoImages.imageLogoWidth3, logoImages.imageLogoHeight3);
  }

  doc.setFontSize(10);  
  var marginLeft = 340;
  displayAccount(doc, invoice, marginLeft, 780, layout);
}



function GetReportTemplate3(doc, invoice, layout, checkMath)
{
    var client = invoice.client;
    var account = invoice.account;
    var currencyId = client.currency_id;

    layout.headerRight = 400;
    layout.rowHeight = 15;


    doc.setFontSize(7);

    Report3AddHeader(invoice, layout, doc);

    if (invoice.image)
    {
        y=130;
        var left = layout.headerRight - invoice.imageWidth;
        doc.addImage(invoice.image, 'JPEG', layout.marginLeft, y);
    }

    Report3AddFooter (invoice, account, doc, layout);


    SetPdfColor('White',doc);    
    doc.setFontSize('8');
    var detailsHeight = displayInvoice(doc, invoice, layout.headerRight, layout.accountTop-10, layout);
    layout.headerTop = Math.max(layout.headerTop, detailsHeight + 50);
    layout.tableTop = Math.max(layout.tableTop, detailsHeight + 150);

    SetPdfColor('Black',doc); //set black color
    doc.setFontSize(7);
    doc.setFontType("normal");
    displayClient(doc, invoice, layout.headerRight, layout.headerTop, layout);


      
    SetPdfColor('White',doc);    
    doc.setFontType('bold');

    doc.setLineWidth(0.3);
    if (NINJA.secondaryColor) {
      setDocHexFill(doc, NINJA.secondaryColor);
      setDocHexDraw(doc, NINJA.secondaryColor);
    } else {
      doc.setDrawColor(63,60,60);
      doc.setFillColor(63,60,60);
    }  

    var left = layout.marginLeft - layout.tablePadding;
    var top = layout.tableTop - layout.tablePadding;
    var width = layout.marginRight - (2 * layout.tablePadding);
    var height = 20;
    doc.rect(left, top, width, height, 'FD');
    

    displayInvoiceHeader(doc, invoice, layout);
    SetPdfColor('Black',doc);
    var y = displayInvoiceItems(doc, invoice, layout);


    var height1 = displayNotesAndTerms(doc, layout, invoice, y);
    var height2 = displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);
    y += Math.max(height1, height2);


    var left = layout.marginLeft - layout.tablePadding;
    var top = y - layout.tablePadding;
    var width = layout.marginRight - (2 * layout.tablePadding);
    var height = 20;
    if (NINJA.secondaryColor) {
      setDocHexFill(doc, NINJA.secondaryColor);
      setDocHexDraw(doc, NINJA.secondaryColor);
    } else {
      doc.setDrawColor(63,60,60);
      doc.setFillColor(63,60,60);
    }  
    doc.rect(left, top, width, height, 'FD');
    
    doc.setFontType('bold');
    SetPdfColor('White', doc);
    doc.setFontSize(12);
    
    var label = invoiceLabels.balance_due;
    var labelX = layout.unitCostRight-(doc.getStringUnitWidth(label) * doc.internal.getFontSize());
    doc.text(labelX, y+2, label);


    doc.setFontType('normal');
    var amount = formatMoney(invoice.balance_amount , currencyId);
    headerLeft=layout.headerRight+400;
    var amountX = layout.lineTotalRight - (doc.getStringUnitWidth(amount) * doc.internal.getFontSize());
    doc.text(amountX, y+2, amount);

    return doc;
}




function Report3AddHeader (invoice, layout, doc)
{
    doc.setLineWidth(0.5);

    if (NINJA.primaryColor) {
      setDocHexFill(doc, NINJA.primaryColor);
      setDocHexDraw(doc, NINJA.primaryColor);
    } else {
      doc.setDrawColor(242,101,34);
      doc.setFillColor(242,101,34);
    }  

    var x1 =0;
    var y1 = 0;
    var w2 = 595;
    var h2 = Math.max(110, getInvoiceDetailsHeight(invoice, layout) + 30);
    doc.rect(x1, y1, w2, h2, 'FD');

    SetPdfColor('White',doc);

    //second column
    doc.setFontType('bold');
    var MaxWidth=594;
    var LineOne= invoice.account.name;
    var AlignLine = MaxWidth-30- (doc.getStringUnitWidth(LineOne) * doc.internal.getFontSize());
    if (LineOne) {
        doc.setFontSize('36');
        doc.setFontType('bold');
        doc.text(40, 50, LineOne);
    }
}


function Report1AddNewPage(invoice,account,doc)
{
    doc.addPage();
    if (logoImages.imageLogo1)
    {
        pageHeight=820;
        y=pageHeight-logoImages.imageLogoHeight1;
        var left = 20;//headerRight - invoice.imageLogoWidth1;
        doc.addImage(logoImages.imageLogo1, 'JPEG', left, y, logoImages.imageLogoWidth1, logoImages.imageLogoHeight1);

    }

    GlobalY = 40;
    return GlobalY;
}

function displayAccount(doc, invoice, x, y, layout) {
  var account = invoice.account;

  if (!account) {
    return;
  }

  var data = [
    account.name,
    account.work_email,
    account.work_phone
  ];

  displayGrid(doc, invoice, data, x, y, layout, true);

  data = [
    concatStrings(account.address1, account.address2),
    concatStrings(account.city, account.state, account.postal_code),    
    account.country ? account.country.name : false
  ];

  var nameWidth = doc.getStringUnitWidth(account.name) * doc.internal.getFontSize() * 1.1;
  var emailWidth = doc.getStringUnitWidth(account.work_email) * doc.internal.getFontSize() * 1.1;
  width = Math.max(emailWidth, nameWidth, 120);

  x += width;

  displayGrid(doc, invoice, data, x, y, layout);  
}


function displayClient(doc, invoice, x, y, layout) {
  var client = invoice.client;
  if (!client) {
    return;
  }  
  var data = [
    getClientDisplayName(client),
    concatStrings(client.address1, client.address2),
    concatStrings(client.city, client.state, client.postal_code),
    client.country ? client.country.name : false,
    client.contacts[0].email
  ];

  return displayGrid(doc, invoice, data, x, y, layout, true);
}

function displayInvoice(doc, invoice, x, y, layout, rightAlignX) {
  if (!invoice) {
    return;
  }

  var data = getInvoiceDetails(invoice);
  return displayGrid(doc, invoice, data, x, y, layout, true, rightAlignX);
}

function getInvoiceDetails(invoice) {
  return [
    {'invoice_number': invoice.invoice_number},
    {'po_number': invoice.po_number},
    {'invoice_date': invoice.invoice_date},
    {'due_date': invoice.due_date},
    {'custom_label1': invoice.account.custom_value1},
    {'custom_label2': invoice.account.custom_value2},
    {'custom_client_label1': invoice.client.custom_value1},
    {'custom_client_label2': invoice.client.custom_value2},
    {'balance_due': formatMoney(invoice.balance_amount, invoice.client.currency_id)},
  ]; 
}

function getInvoiceDetailsHeight(invoice, layout) {
  var data = getInvoiceDetails(invoice);
  var count = 0;
  for (var key in data) {
    if (!data.hasOwnProperty(key)) {
      continue;
    }
    var obj = data[key];
    for (var subKey in obj) {
      if (!obj.hasOwnProperty(subKey)) {
        continue;
      }
      if (obj[subKey]) {
        count++;
      }
    }
  }
  return count * layout.rowHeight;
}

function displaySubtotals(doc, layout, invoice, y, rightAlignTitleX)
{
  if (!invoice) {
    return;
  }

  //var taxTitle = 'Tax ' + getInvoiceTaxRate(invoice) + '%';
  var data = [
    {'subtotal': formatMoney(invoice.subtotal_amount, invoice.client.currency_id)},
    {'discount': invoice.discount_amount > 0 ? formatMoney(invoice.discount_amount, invoice.client.currency_id) : false},
    {'tax': invoice.tax_amount > 0 ? formatMoney(invoice.tax_amount, invoice.client.currency_id) : false},
    {'paid_to_date': formatMoney(invoice.amount - invoice.balance, invoice.client.currency_id)}
  ];

  return displayGrid(doc, invoice, data, 300, y, layout, true, 550, rightAlignTitleX) + 10;
}

function concatStrings() {
  var concatStr = '';
  var data = [];
  for (var i=0; i<arguments.length; i++) {
    var string = arguments[i];
    if (string) {
      data.push(string);
    }
  }
  for (var i=0; i<data.length; i++) {
    concatStr += data[i];
    if (i == 0 && data.length > 1) {
      concatStr += ', ';
    } else if (i < data.length -1) {
      concatStr += ' ';
    }
  }
  return data.length ? concatStr : false;
}

function displayGrid(doc, invoice, data, x, y, layout, hasheader, rightAlignX, rightAlignTitleX)  {
  var numLines = 0;
  var origY = y;
  for (var i=0; i<data.length; i++) {
    doc.setFontType('normal');
      
    if (invoice.invoice_design_id == 1 && i > 0 && origY === layout.accountTop) {
      SetPdfColor('GrayText',doc);
    }

    var row = data[i];
    if (!row) {
      continue;
    }

    if (hasheader && i === 0 && !rightAlignTitleX) {
      doc.setFontType('bold');
    }

    if (typeof row === 'object') {      
      for (var key in row) {
        if (row.hasOwnProperty(key)) {
          var value = row[key] ? row[key] + '' : false;
        }
      }
      if (!value) {
        continue;
      }  

      var marginLeft;
      if (rightAlignX) {
        marginLeft = rightAlignX - (doc.getStringUnitWidth(value) * doc.internal.getFontSize());          
      } else {
        marginLeft = x + 80;
      }
      doc.text(marginLeft, y, value);       
      
      doc.setFontType('normal');
      if (key.substring(0, 6) === 'custom') {
        key = invoice.account[key];
      } else {
        key = invoiceLabels[key];
      }

      if (rightAlignTitleX) {
        marginLeft = rightAlignTitleX - (doc.getStringUnitWidth(key) * doc.internal.getFontSize());
      } else {
        marginLeft = x;
      }

      doc.text(marginLeft, y, key);      
    } else {
      doc.text(x, y, row);
    }

    numLines++;
    y += layout.rowHeight;
  }

  return numLines * layout.rowHeight;
}

function displayNotesAndTerms(doc, layout, invoice, y)
{
  doc.setFontType("normal");
  var origY = y;

  if (invoice.public_notes) {
    doc.text(layout.marginLeft, y, invoice.public_notes);
    y += 16 + (doc.splitTextToSize(invoice.public_notes, 300).length * doc.internal.getFontSize());    
  }
    
  if (invoice.terms) {
    doc.setFontType("bold");
    doc.text(layout.marginLeft, y, invoiceLabels.terms);
    y += 16;
    doc.setFontType("normal");
    doc.text(layout.marginLeft, y, invoice.terms);
    y += 16 + (doc.splitTextToSize(invoice.terms, 300).length * doc.internal.getFontSize());    
  }

  return y - origY;
}

function calculateAmounts(invoice) {
  var total = 0;
  var hasTaxes = false;

  for (var i=0; i<invoice.invoice_items.length; i++) {
    var item = invoice.invoice_items[i];
    var tax = 0;
    if (item.tax && parseFloat(item.tax.rate)) {
      tax = parseFloat(item.tax.rate);
    } else if (item.tax_rate && parseFloat(item.tax_rate)) {
      tax = parseFloat(item.tax_rate);
    }   

    var lineTotal = NINJA.parseFloat(item.cost) * NINJA.parseFloat(item.qty);
    if (tax) {
      lineTotal += lineTotal * tax / 100;
    }
    if (lineTotal) {
      total += lineTotal;
    }

    if ((item.tax && item.tax.rate > 0) || (item.tax_rate && parseFloat(item.tax_rate) > 0)) {
      hasTaxes = true;
    }
  }

  invoice.subtotal_amount = total;

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

  invoice.balance_amount = accounting.toFixed(total,2) - (accounting.toFixed(invoice.amount,2) - accounting.toFixed(invoice.balance,2));
  invoice.tax_amount = tax;
  invoice.discount_amount = discount;
  invoice.has_taxes = hasTaxes;

  return invoice;
}

function getInvoiceTaxRate(invoice) {
  var tax = 0;
  if (invoice.tax && parseFloat(invoice.tax.rate)) {
    tax = parseFloat(invoice.tax.rate);
  } else if (invoice.tax_rate && parseFloat(invoice.tax_rate)) {
    tax = parseFloat(invoice.tax_rate);
  }   
  return tax;
}

function displayInvoiceHeader(doc, invoice, layout) {

  var costX = layout.unitCostRight - (doc.getStringUnitWidth(invoiceLabels.unit_cost) * doc.internal.getFontSize());
  var qtyX = layout.qtyRight - (doc.getStringUnitWidth(invoiceLabels.quantity) * doc.internal.getFontSize());
  var taxX = layout.taxRight - (doc.getStringUnitWidth(invoiceLabels.tax) * doc.internal.getFontSize());
  var totalX = layout.lineTotalRight - (doc.getStringUnitWidth(invoiceLabels.line_total) * doc.internal.getFontSize());

  doc.text(layout.marginLeft, layout.tableTop, invoiceLabels.item);
  doc.text(layout.descriptionLeft, layout.tableTop, invoiceLabels.description);
  doc.text(costX, layout.tableTop, invoiceLabels.unit_cost);
  doc.text(qtyX, layout.tableTop, invoiceLabels.quantity);
  doc.text(totalX, layout.tableTop, invoiceLabels.line_total);

  if (invoice.has_taxes)
  {
    doc.text(taxX, layout.tableTop, invoiceLabels.tax);
  }

}

function displayInvoiceItems(doc, invoice, layout) {
  doc.setFontType("normal");

  var line = 1;
  var total = 0;
  var shownItem = false;
  var currencyId = invoice && invoice.client ? invoice.client.currency_id : 1;
  var tableTop = layout.tableTop;

  doc.setFontSize(8);
  for (var i=0; i<invoice.invoice_items.length; i++) {
    var item = invoice.invoice_items[i];
    var numLines = doc.splitTextToSize(item.notes, 200).length + 2;
    //console.log('num lines %s', numLines);

    var y = tableTop + (line * layout.tableRowHeight) + (2 * layout.tablePadding);
    var top = y - layout.tablePadding;
    var newTop = top + (numLines * layout.tableRowHeight);

    if (newTop > 770) {
      line = 0;
      tableTop = layout.accountTop + layout.tablePadding;
      y = tableTop;
      top = y - layout.tablePadding;
      newTop = top + (numLines * layout.tableRowHeight);
      doc.addPage();
    }

    var left = layout.marginLeft - layout.tablePadding;
    var width = layout.marginRight + layout.tablePadding;

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
    if (invoice.is_recurring) {
      notes = processVariables(notes);
      productKey = processVariables(productKey);
    }
    
    var lineTotal = NINJA.parseFloat(item.cost) * NINJA.parseFloat(item.qty);
    if (tax) {
      lineTotal += lineTotal * tax / 100;
    }
    if (lineTotal) {
      total += lineTotal;
    }
    lineTotal = formatMoney(lineTotal, currencyId, true);
    
    var costX = layout.unitCostRight - (doc.getStringUnitWidth(cost) * doc.internal.getFontSize());
    var qtyX = layout.qtyRight - (doc.getStringUnitWidth(qty) * doc.internal.getFontSize());
    var taxX = layout.taxRight - (doc.getStringUnitWidth(tax+'%') * doc.internal.getFontSize());
    var totalX = layout.lineTotalRight - (doc.getStringUnitWidth(lineTotal) * doc.internal.getFontSize());
    //if (i==0) y -= 4;

    line += numLines;

    
    if (invoice.invoice_design_id == 1) {
      if (i%2 == 0) {      
        doc.setDrawColor(255,255,255);
        doc.setFillColor(246,246,246);
        doc.rect(left, top, width-left, newTop-top, 'FD');

        doc.setLineWidth(0.3);        
        doc.setDrawColor(200,200,200);
        doc.line(left, top, width, top);
        doc.line(left, newTop, width, newTop);        
      }
    } else if (invoice.invoice_design_id == 2) {
      if (i%2 == 0) {      
        left = 0;
        width = 1000;

        doc.setDrawColor(255,255,255);
        doc.setFillColor(235,235,235);
        doc.rect(left, top, width-left, newTop-top, 'FD');

      }
    } else {
      doc.setLineWidth(0.3);
      doc.setDrawColor(150,150,150);
      doc.line(left, newTop, width, newTop);
    }

    y += 4;

    if (invoice.invoice_design_id == 1) {
      SetPdfColor('LightBlue', doc, 'primary');
    } else if (invoice.invoice_design_id == 2) {
      SetPdfColor('SomeGreen', doc, 'primary');
    } else if (invoice.invoice_design_id == 3) {
      doc.setFontType('bold');
    } else {
      SetPdfColor('Black', doc);
    }
    doc.text(layout.marginLeft, y+2, productKey);
    
    SetPdfColor('Black', doc);
    doc.setFontType('normal');

    doc.text(layout.descriptionLeft, y+2, notes);
    doc.text(costX, y+2, cost);
    doc.text(qtyX, y+2, qty);
    doc.text(totalX, y+2, lineTotal);

    if (tax) {
      doc.text(taxX, y+2, tax+'%');
    }
  }  

  y = tableTop + (line * layout.tableRowHeight) + (3 * layout.tablePadding);
  var cutoff = 700;
  if (invoice.terms) {
    cutoff -= 50;
  }
  if (invoice.public_notes) {
    cutoff -= 50;
  }

  if (y > cutoff) {
    doc.addPage();
    return layout.marginLeft;
  }

  return y;
}

// http://stackoverflow.com/questions/1068834/object-comparison-in-javascript
function objectEquals(x, y) {
    // if both are function
    if (x instanceof Function) {
        if (y instanceof Function) {
            return x.toString() === y.toString();
        }
        return false;
    }
    if (x === null || x === undefined || y === null || y === undefined) { return x === y; }
    if (x === y || x.valueOf() === y.valueOf()) { return true; }

    // if one of them is date, they must had equal valueOf
    if (x instanceof Date) { return false; }
    if (y instanceof Date) { return false; }

    // if they are not function or strictly equal, they both need to be Objects
    if (!(x instanceof Object)) { return false; }
    if (!(y instanceof Object)) { return false; }

    var p = Object.keys(x);
    return Object.keys(y).every(function (i) { return p.indexOf(i) !== -1; }) ?
            p.every(function (i) { return objectEquals(x[i], y[i]); }) : false;
}

function hexToR(h) {return parseInt((cutHex(h)).substring(0,2),16)}
function hexToG(h) {return parseInt((cutHex(h)).substring(2,4),16)}
function hexToB(h) {return parseInt((cutHex(h)).substring(4,6),16)}
function cutHex(h) {return (h.charAt(0)=="#") ? h.substring(1,7):h}
function setDocHexColor(doc, hex) {
  var r = hexToR(hex);
  var g = hexToG(hex);
  var b = hexToB(hex);
  return doc.setTextColor(r, g, b);
}
function setDocHexFill(doc, hex) {
  var r = hexToR(hex);
  var g = hexToG(hex);
  var b = hexToB(hex);
  return doc.setFillColor(r, g, b);
}
function setDocHexDraw(doc, hex) {
  var r = hexToR(hex);
  var g = hexToG(hex);
  var b = hexToB(hex);
  return doc.setDrawColor(r, g, b);
}

function openUrl(url, track) {
  trackUrl(track ? track : url);
  window.open(url, '_blank');
}