// http://stackoverflow.com/questions/9847580/how-to-detect-safari-chrome-ie-firefox-and-opera-browser
var isOpera = !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0;
var isFirefox = typeof InstallTrigger !== 'undefined';   // Firefox 1.0+
var isSafari = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0;
var isChrome = !!window.chrome && !isOpera;              // Chrome 1+
var isChromium = isChrome && navigator.userAgent.indexOf('Chromium') >= 0;
var isIE = /*@cc_on!@*/false || !!document.documentMode; // At least IE6





function generatePDF(invoice, checkMath) {

    report_id=invoice.invoice_design_id;
    doc= GetPdf(invoice,checkMath,report_id);
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
  }
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
    //console.log(invoice);
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


    if (report_id==1) return GetReportTemplate1(invoice,checkMath);
    if (report_id==2) return GetReportTemplate2(invoice,checkMath);
    if (report_id==3) return GetReportTemplate3(invoice,checkMath);


    alert('report template not implemented yet');
    return false;

}




function GetReportTemplate1 (invoice,checkMath)
{
    var doc=false;

    var GlobalY=0;//Y position of line at current page


    var client = invoice.client;
    var account = invoice.account;







    var po_number=   invoice.po_number ? invoice.po_number : '';
    var public_notes=invoice.public_notes ? invoice.public_notes : '';
    var terms=invoice.terms ? invoice.terms : '';




    var currencyId = client.currency_id;
    var invoiceNumber = invoice.invoice_number;
    var invoiceDate = invoice.invoice_date ? invoice.invoice_date : '';
    var dueDate = invoice.due_date ? invoice.due_date : '';

    var paid_to_date = invoice.amount - invoice.balance;

    var headerRight = 150;
    var accountTop = 30;
    var marginLeft = 180;
    var rowHeight = 10;
    var headerTop = 125; //height of HEADER //should be dynamic !


    var descriptionLeft = 162;
    var unitCostRight = 410;
    var qtyRight = 480;
    var taxRight = 480;
    var lineTotalRight = 550;
    var tableLeft = 50;

    var tablePadding = 14;



//------------------------------ move to functions !
    var total = 0;
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




    /*
     @param orientation One of "portrait" or "landscape" (or shortcuts "p" (Default), "l")
     @param unit Measurement unit to be used when coordinates are specified. One of "pt" (points), "mm" (Default), "cm", "in"
     @param format One of 'a3', 'a4' (Default),'a5' ,'letter' ,'legal'
     @returns {jsPDF}
     */
    var doc = new jsPDF('portrait', 'pt','a4');



    //Set PDF properities
    doc.setProperties({
        title: '',
        subject: 'Report',
        author: 'Ninja',
        keywords: 'pdf, javascript,geenerated',
        creator: 'Ninja'
    });





    //set default style for report
    doc.setFont('Helvetica','');
    doc.setFontSize(7);


//----------------------------------------------------------------------------------------------------
    //Print header on document
    //for now we will put static header
    //but later this could be changed to more flexible solution

    if (invoice.image)
    {
        var left = headerRight - invoice.imageWidth;
        doc.addImage(invoice.image, 'JPEG', left, 30, invoice.imageWidth, invoice.imageHeight);
    }



    if (invoice.imageLogo1)
    {
        pageHeight=820;
        var left = headerRight ;
        y=pageHeight-invoice.imageLogoHeight1;


        var left = headerRight - invoice.imageLogoWidth1;
        doc.addImage(invoice.imageLogo1, 'JPEG', left, y, invoice.imageLogoWidth1, invoice.imageLogoHeight1);


    }


    var invoiceNumberX = headerRight - (doc.getStringUnitWidth(invoiceNumber, false) * doc.internal.getFontSize());
    var invoiceDateX = headerRight - (doc.getStringUnitWidth(invoiceDate) * doc.internal.getFontSize());
    var dueDateX = headerRight - (doc.getStringUnitWidth(dueDate) * doc.internal.getFontSize());
    var poNumberX = headerRight - (doc.getStringUnitWidth(invoice.po_number) * doc.internal.getFontSize());



    var y = accountTop;
    var left = marginLeft;


    doc.setFontSize(7);

    SetPdfColor('LightBlue',doc);
    if (account.name) {
        y += rowHeight;
        doc.text(left, y, account.name);
    }

    SetPdfColor('GrayText',doc);
    doc.setFontSize(7);


    if (account.work_email) {
        y += rowHeight;
        doc.text(left, y, account.work_email);
    }


    if (account.work_phone) {
        y += rowHeight;
        doc.text(left, y, account.work_phone);
    }




    var HeaderMarginThirdColumn=90;//should be dynamic and dependent on 1st image and 2nd column width

    var y = accountTop;
    var left = marginLeft+HeaderMarginThirdColumn;

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
        doc.text(left, y, account.country ? account.country.name : '');
    }

//-----------------------------Publish Client Details block--------------------------------------------

    var y = accountTop;
    var left = marginLeft;

    var headerY = headerTop;

    SetPdfColor('LightBlue',doc);
    doc.setFontSize('11');
    doc.text(50, headerTop, 'INVOICE');


    y=130;


    doc.setDrawColor(220,220,220);
    doc.line(30, y, 560, y); // horizontal line



    var line1=headerTop+16;
    var line2=headerTop+16*2;
    var line21=headerTop+16*1.6;
    var line22=headerTop+16*2.2;

    var line3=headerTop+16*3;
    var line31=headerTop+16*3.6;

    var marginLeft1=50;
    var marginLeft2=120;
    var marginLeft3=180;






    GlobalY=line1;

    SetPdfColor('Black',doc); //set black color
    doc.setFontSize(7);



    if(invoiceNumber){
    //inv number
    doc.text(marginLeft1, GlobalY, 'Invoice Number');
    doc.setFontType("bold");
    doc.text(marginLeft2, GlobalY, invoiceNumber);
    doc.setFontType("normal");


        GlobalY=GlobalY+16;
    }

    doc.setFontType("normal");
    //PO#
    if(po_number){
        //inv number
        doc.text(marginLeft1, GlobalY, 'PO Number');
      //  doc.setFontType("bold");
        doc.text(marginLeft2, GlobalY,po_number);
        doc.setFontType("normal");
        GlobalY=GlobalY+16;
    }

    //PO#
    if(invoiceDate){
        //inv number
        doc.text(marginLeft1, GlobalY, 'Invoice Date');
      //  doc.setFontType("bold");
        doc.text(marginLeft2, GlobalY,invoiceDate);
        doc.setFontType("normal");
        GlobalY=GlobalY+16;
    }

    //PO#
    if(dueDate){
        //inv number
        doc.text(marginLeft1, GlobalY, 'Due Date');
      //  doc.setFontType("bold");
        doc.text(marginLeft2, GlobalY,dueDate);
        doc.setFontType("normal");
        GlobalY=GlobalY+16;
    }

    //PO#
    if(balance){
        //inv number
        doc.text(marginLeft1, GlobalY, 'Balance ');
      //  doc.setFontType("bold");
        doc.text(marginLeft2, GlobalY,balance);
        doc.setFontType("normal");
        GlobalY=GlobalY+16;
    }







    y=GlobalY;

    y1=GlobalY;


    GlobalY=line1;



    ClientCompanyName=client.name;
    ClientCompanyEmail='';
    ClientCompanyPhone=client.work_phone;

    ClientCompanyAddress1=client.address1;
    ClientCompanyAddress2=client.address2+' '+client.postal_code;


    SetPdfColor('Black',doc); //set black color

    doc.setFontType("bold");
    doc.text(marginLeft3, GlobalY, ClientCompanyName);
    doc.setFontType("normal");
    GlobalY=GlobalY+16;


    if(client)
    {
        ClientCompanyName=getClientDisplayName(client);
        ClientCompanyPhone=client.work_phone;
        ClientCompanyEmail=client.contacts[0].email;

    }


    doc.text(marginLeft3, GlobalY, ClientCompanyAddress1);
    GlobalY=GlobalY+8;

    doc.text(marginLeft3, GlobalY, ClientCompanyAddress2);
    GlobalY=GlobalY+16;

    doc.text(marginLeft3, GlobalY, ClientCompanyEmail);
    GlobalY=GlobalY+8;
    doc.text(marginLeft3, GlobalY, ClientCompanyPhone);

    GlobalY=GlobalY+16;




y2=GlobalY;



    if (y2>y1) {GlobalY=y2;y=y2;}



    doc.setDrawColor(220,220,220);


    doc.line(30, y-8, 560, y-8); // horizontal line









//--------------------------------Publishing Table--------------------------------------------------
    GlobalY=y+30;


    SetPdfColor('Black',doc);
    doc.setFontSize(7);

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

    var costX = unitCostRight - (doc.getStringUnitWidth('Unit Cost') * doc.internal.getFontSize());
    var qtyX = qtyRight - (doc.getStringUnitWidth('Quantity') * doc.internal.getFontSize());
    var taxX = taxRight - (doc.getStringUnitWidth('Tax') * doc.internal.getFontSize());
    var totalX = lineTotalRight - (doc.getStringUnitWidth('Line Total') * doc.internal.getFontSize());

    tableTop=GlobalY;//redefine this to dynamic value

    doc.setFontSize(10);
    doc.setFontType("bold");
    doc.text(tableLeft, tableTop, 'Item');
    doc.text(descriptionLeft, tableTop, 'Description');
    doc.text(costX, tableTop, 'Unit Cost');
    doc.text(qtyX, tableTop, 'Quantity');
    doc.text(totalX, tableTop, 'Line Total');

    if (hasTaxes)
    {
        doc.text(taxX, tableTop, 'Tax');
    }
    doc.setFontType("normal");

    doc.setFontSize(7);

    /* line items */

    var line = 1;
    var total = 0;
    var shownItem = false;


    GlobalY=GlobalY+14+10; //padding from top

    var FontSize=7;
    doc.setFontSize(FontSize);


    var MaxLinesPerPage=40;



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


        length=doc.splitTextToSize(item.notes, 200).length;
        var h=length*FontSize;


        MaxGlobalY=760;

        if (h+GlobalY > MaxGlobalY) {

            GlobalY=Report1AddNewPage(invoice,account,doc);



        }

        if ((i%2)===0){
            doc.setLineWidth(0.5);
            doc.setDrawColor(251,251,251);
            doc.setFillColor(251,251,251);

            var x1 = tableLeft-tablePadding ;

            var y1 = GlobalY-FontSize-5;

            var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
            var h2 = doc.internal.getFontSize()*length+length*1.1+10;//+h;//+tablePadding;



            doc.rect(x1, y1, w2, h2, 'FD');

            doc.setDrawColor(220,220,220);
            doc.line(x1, y1, x1+w2, y1); // horizontal line

            doc.line(x1, y1+h2, x1+w2, y1+h2); // horizontal line




        }
        x=GlobalY;

        GlobalY=GlobalY+h+tablePadding*2;






        SetPdfColor('LightBlue',doc);
        doc.text(tableLeft, x, productKey);

        SetPdfColor('Black',doc);
        doc.text(descriptionLeft, x, notes);

        doc.text(costX, x, cost);
        doc.text(qtyX, x, qty);
        doc.text(totalX, x, lineTotal);

        if (tax) {
            doc.text(taxX, x, tax+'%');
        }


        line=line+length;



    }








//-------------------------------Publishing Document balance------------------------------------------

//check do we need to go to next page
    MinHeight=700;

    if (GlobalY > MinHeight) {


        GlobalY=Report1AddNewPage(invoice,account,doc);
    }




    // var dueDateX = headerRight - (doc.getStringUnitWidth(dueDate) * doc.internal.getFontSize());
    x += 16+50;

    doc.setFontType("bold");
    MsgRightAlign=400;



  //  GlobalY=x;

    GlobalY=GlobalY+25;

    doc.setLineWidth(0.3);
    doc.setDrawColor(251,251,251);
    doc.setFillColor(251,251,251);
    var x1 = tableLeft-tablePadding*2+14 ;
    var y1 = GlobalY-FontSize-tablePadding;

    var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
    var h2 = doc.internal.getFontSize()*3+tablePadding*2;

    if (invoice.discount)
    {
        var h2 =   doc.internal.getFontSize()*4+tablePadding*2;
    }

    doc.rect(x1, y1, w2, h2, 'FD');


    x=y1+FontSize+tablePadding;


    Msg='Subtotal';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
    doc.text(TmpMsgX, x, Msg);



    doc.setFontType("normal");
    AmountText = formatMoney(total , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);

    x += doc.internal.getFontSize()*2;



    if (invoice.discount>0 )
    {
        Msg='Discount';
        var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
        doc.text(TmpMsgX, x, Msg);


        discount=invoice.discount/100;

        d=total*discount;


        doc.setFontType("normal");
        AmountText = formatMoney(d , currencyId);
        headerLeft=headerRight+400;
        var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
        doc.text(AmountX, x, AmountText);
        x += doc.internal.getFontSize()*2;
    }




    Msg='Paid to Date';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
    doc.text(TmpMsgX, x, Msg);



    AmountText = formatMoney(paid_to_date , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);






    doc.setFontSize(10);
    x += doc.internal.getFontSize()*4;
    //doc.text(footerLeft, x, '');
    Msg='Balance Due';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());



    doc.text(TmpMsgX, x, Msg);


    SetPdfColor('LightBlue',doc);
    AmountText = formatMoney(balance , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);






    return doc;
}















function GetReportTemplate2 (invoice,checkMath)
{
    var doc=false;

    var GlobalY=0;//Y position of line at current page



    var client = invoice.client;
    var account = invoice.account;


    var currencyId = client.currency_id;
    var invoiceNumber = invoice.invoice_number;
    var invoiceDate = invoice.invoice_date ? invoice.invoice_date : '';
    var dueDate = invoice.due_date ? invoice.due_date : '';

    var paid_to_date = invoice.amount - invoice.balance;

    var headerRight = 150;
    var accountTop = 30;
    var marginLeft = 180;
    var rowHeight = 10;
    var headerTop = 125; //height of HEADER //should be dynamic !


    var descriptionLeft = 162;
    var unitCostRight = 410;
    var qtyRight = 480;
    var taxRight = 480;
    var lineTotalRight = 550;
    var tableLeft = 50;


    //var tableTop = 240+100;

    //var tableRowHeight = 18;
    var tablePadding = 14;



//------------------------------ move to functions !
    var total = 0;
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

    /*
     @param orientation One of "portrait" or "landscape" (or shortcuts "p" (Default), "l")
     @param unit Measurement unit to be used when coordinates are specified. One of "pt" (points), "mm" (Default), "cm", "in"
     @param format One of 'a3', 'a4' (Default),'a5' ,'letter' ,'legal'
     @returns {jsPDF}
     */
    var doc = new jsPDF('portrait', 'pt','a4');



    //Set PDF properities
    doc.setProperties({
        title: '',
        subject: 'Report',
        author: 'Ninja',
        keywords: 'pdf, javascript,geenerated',
        creator: 'Ninja'
    });


    //set default style for report
    doc.setFont('Helvetica','');
    doc.setFontSize(7);

    doc.setLineWidth(0.5);

    doc.setFillColor( 46,43,43);
    doc.setFillColor( 46,43,43);

    var x1 =0;

    var y1 = 0;

    var w2 = 595;
    var h2 = 100;// doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;
    doc.rect(x1, y1, w2, h2, 'FD');






//----------------------------------------------------------------------------------------------------
    if (invoice.image)
    {
        var left = headerRight - invoice.imageWidth;
        doc.addImage(invoice.image, 'JPEG', left, 30, invoice.imageWidth, invoice.imageHeight);
    }

    Report2AddFooter (invoice,doc);

    var invoiceNumberX = headerRight - (doc.getStringUnitWidth(invoiceNumber, false) * doc.internal.getFontSize());
    var invoiceDateX = headerRight - (doc.getStringUnitWidth(invoiceDate) * doc.internal.getFontSize());
    var dueDateX = headerRight - (doc.getStringUnitWidth(dueDate) * doc.internal.getFontSize());
    var poNumberX = headerRight - (doc.getStringUnitWidth(invoice.po_number) * doc.internal.getFontSize());



    var y = accountTop;
    var left = marginLeft;


    doc.setFontSize(7);
    SetPdfColor('White',doc);



//TODO:NOT AVAILEABLE FROM DATAMODEL
    //account.email='email N/A';
    if (account.email) {
        y += rowHeight;
        doc.text(left, y, account.email);
    }
    else
    {
        //console.log('account.email NOT DEFINED !');
    }

//TODO:NOT AVAILEABLE FROM DATAMODEL
    if (account.phone) {
        y += rowHeight;
        doc.text(left, y, account.phone);
    }
    else
    {
        //console.log('account.phone NOT DEFINED !');
    }


    var HeaderMarginThirdColumn=70;

    //second column
    doc.setFontType("bold");
    var MaxWidth=594;

    var LineOne= account.name;

    var AlignLine = MaxWidth-30- (doc.getStringUnitWidth(LineOne) * doc.internal.getFontSize());

    if (account.name) {

        y += rowHeight;
        doc.text(AlignLine, y, LineOne);
    }

    y += rowHeight;

    doc.setFontType("normal");


    var LineTwo= account.address1+' '+account.address2+account.city+' '+(account.country ? account.country.name : '');

    var AlignLine = MaxWidth-30- (doc.getStringUnitWidth(LineTwo) * doc.internal.getFontSize());
    y += rowHeight;
    doc.text(AlignLine, y, LineTwo);


//-----------------------------Publish Client Details block--------------------------------------------

    var y = accountTop;
    var left = marginLeft;

    var headerY = headerTop;



    SetPdfColor('GrayLogo',doc); //set black color


    var line1=headerTop+16;
    var line2=headerTop+16*2;
    var line21=headerTop+16*1.6;
    var line22=headerTop+16*2.2;

    var line3=headerTop+16*3;
    var line31=headerTop+16*3.6;

    var marginLeft1=50;


    SetPdfColor('Black',doc); //set black color

    doc.setFontSize(7);


    ClientCompanyName=client.name;
    ClientCompanyEmail='';//client.email;//'22222222';
    ClientCompanyPhone=client.work_phone;

    ClientCompanyAddress1=client.address1;
    ClientCompanyAddress2=client.address2+' '+client.postal_code;

    if(client)
    {
        ClientCompanyName=getClientDisplayName(client);
        ClientCompanyPhone=client.work_phone;
        ClientCompanyEmail=client.contacts[0].email;

    }

    //show left column
    SetPdfColor('Black',doc); //set black color
    doc.setFontType("normal");


//publish filled box
    doc.setDrawColor(200,200,200);
    doc.setFillColor(54,164,152);

    GlobalY=190;
    doc.setLineWidth(0.5);

    var BlockLenght=220;
    var x1 =595-BlockLenght;
    var y1 = GlobalY-12;
    var w2 = BlockLenght;
    var h2 = 90;
    doc.rect(x1, y1, w2, h2, 'FD');



    SetPdfColor('SomeGreen',doc);
    doc.setFontSize('14');
    doc.setFontType("bold");
    doc.text(50, GlobalY, 'YOUR INVOICE');


    var z=GlobalY;
    z=z+30;


    SetPdfColor('Black',doc);

    if (ClientCompanyName)
    {
        doc.setFontSize('8');
        doc.setFontType("bold");
        doc.text(marginLeft1, z, ClientCompanyName);
        z=z+15;//doc.internal.getFontSize();
    }

    if (ClientCompanyAddress1)
    {

        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyAddress1);
        z=z+15;////z=z+doc.internal.getFontSize();
    }
    if (ClientCompanyAddress2)
    {

        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyAddress2);
        z=z+15;////z=z+doc.internal.getFontSize();
    }

    if (ClientCompanyEmail)
    {
        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyEmail);
        z=z+15;///z=z+doc.internal.getFontSize();
    }
    if (ClientCompanyPhone)
    {

        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyPhone);
        z=z+15;////z=z+doc.internal.getFontSize();
    }


    marginLeft2=395;

    //publish left side information

    SetPdfColor('White',doc);
    z=GlobalY+15;

    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft2, z,'Invoice Number');
    z=z+15;//doc.internal.getFontSize();


    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft2, z,'Invoice date');
    z=z+15;//doc.internal.getFontSize();

    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft2, z,'Due Date');
    z=z+15;//doc.internal.getFontSize();


    marginLeft3=marginLeft2+65;

    z=GlobalY+15;


    var invoiceNumber = invoice.invoice_number;
    var invoiceDate = invoice.invoice_date ? invoice.invoice_date : '';
    var dueDate = invoice.due_date ? invoice.due_date : '';


    doc.setFontSize('7');
    doc.setFontType("bold");
    doc.text(marginLeft3, z,invoiceNumber);
    z=z+15;//doc.internal.getFontSize();


    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft3, z,invoiceDate);
    z=z+15;//doc.internal.getFontSize();

    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft3, z,dueDate);
    z=z+15;//doc.internal.getFontSize();

    y=z+60;
//--------------------------------Publishing Table--------------------------------------------------
    GlobalY=y+30;
    GlobalY=GlobalY+70;//y+30;
    SetPdfColor('Black',doc);
    doc.setFontSize(7);
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

    var costX = unitCostRight - (doc.getStringUnitWidth('Unit Cost') * doc.internal.getFontSize());
    var qtyX = qtyRight - (doc.getStringUnitWidth('Quantity') * doc.internal.getFontSize());
    var taxX = taxRight - (doc.getStringUnitWidth('Tax') * doc.internal.getFontSize());
    var totalX = lineTotalRight - (doc.getStringUnitWidth('Line Total') * doc.internal.getFontSize());

    tableTop=GlobalY;//redefine this to dynamic value

    doc.setFontSize(12);
    doc.setFontType("bold");

    doc.text(tableLeft, tableTop, 'Item');
    doc.text(descriptionLeft, tableTop, 'Description');
    doc.text(costX, tableTop, 'Unit Cost');
    doc.text(qtyX, tableTop, 'Quantity');
    doc.text(totalX, tableTop, 'Line Total');

    if (hasTaxes)
    {
        doc.text(taxX, tableTop, 'Tax');
    }


    doc.setFontSize(7);

    /* line items */

    var line = 1;
    var total = 0;
    var shownItem = false;


    GlobalY=GlobalY+24; //padding from top

    var FontSize=7;
    doc.setFontSize(FontSize);
    doc.setFontType("normal");

    var MaxLinesPerPage=40;


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


        length=doc.splitTextToSize(item.notes, 200).length;
        var h=length*FontSize;
        MaxGlobalY=740;
        if (h+GlobalY > MaxGlobalY) {

            tableTop = 40;
            GlobalY=tableTop;

            doc.addPage();
            Report2AddFooter(invoice,doc);
        }

        if ((i%2)===0){
            doc.setLineWidth(0.5);
            doc.setDrawColor(240,240,240);
            doc.setFillColor(240,240,240);



            var x1 = tableLeft-tablePadding ;
            var y1 = GlobalY-FontSize-5;
            var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
            var h2 = doc.internal.getFontSize()*length+length*1.1+10;//+h;//+tablePadding;
            doc.rect(x1, y1, w2, h2, 'FD');
        }
        else
        {
            doc.setLineWidth(0.5);
            doc.setDrawColor(251,251,251);
            doc.setFillColor(251,251,251);


            var x1 = tableLeft-tablePadding ;
            var y1 = GlobalY-FontSize-5;
            var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
            var h2 = doc.internal.getFontSize()*length+length*1.1+10;//+h;//+tablePadding;
            doc.rect(x1, y1, w2, h2, 'FD');
        }

        x=GlobalY;

        GlobalY=GlobalY+h+tablePadding*2;


        SetPdfColor('SomeGreen',doc);
        doc.text(tableLeft, x, productKey);

        SetPdfColor('Black',doc);
        doc.text(descriptionLeft, x, notes);

        doc.text(costX, x, cost);
        doc.text(qtyX, x, qty);
        doc.text(totalX, x, lineTotal);

        if (tax) {
            doc.text(taxX, x, tax+'%');
        }


        line=line+length;
    }
//-------------------------------Publishing Document balance------------------------------------------


    x += 16+50;

    doc.setFontType("bold");
    MsgRightAlign=400;



    GlobalY=x;

    doc.setLineWidth(0.3);

    doc.setDrawColor(251,251,251);
    doc.setFillColor(251,251,251);
    var x1 = tableLeft-tablePadding*2 +14;
    var y1 = GlobalY-FontSize-tablePadding;
    var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
    var h2 = doc.internal.getFontSize()*3+tablePadding*2;
    doc.rect(x1, y1, w2, h2, 'FD');


    Msg='Total';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
    doc.text(TmpMsgX, x, Msg);

    doc.setFontType("normal");
    AmountText = formatMoney(total , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);




    x += doc.internal.getFontSize()*2;

    Msg='Amount Payed';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
    doc.text(TmpMsgX, x, Msg);



    AmountText = formatMoney(paid_to_date , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);




    doc.setFontType("bold");

    doc.setFontSize(12);
    x += doc.internal.getFontSize()*4;
    Msg='Amount Due';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());



    doc.text(TmpMsgX, x, Msg);


    //SetPdfColor('LightBlue',doc);
    AmountText = formatMoney(balance , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);






    return doc;
}







function SetPdfColor(color,doc)
{

    if (color=='LightBlue') {
        return doc.setTextColor(41,156, 194);
    }

    if (color=='Black') {
        return doc.setTextColor(46,43,43);//select color black
    }
    if (color=='GrayLogo') {
        //return doc.setTextColor(207,209, 210);//select color Custom Report GRAY
        return doc.setTextColor(207,241, 241);//select color Custom Report GRAY
    }

    if (color=='GrayBackground') {
        //return doc.setTextColor(207,209, 210);//select color Custom Report GRAY
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




    alert('color is not defined');
    return false;

}


function Report2AddFooter (invoice,doc)
{

    doc.setLineWidth(0.5);
    doc.setDrawColor(41,37,37);
    doc.setFillColor(41,37,37);


    // return doc.setTextColor(240,240,240);//select color Custom Report GRAY Colour




    var x1 = 0;//tableLeft-tablePadding ;

    var y1 = 750;

    var w2 = 596;
    var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;



    doc.rect(x1, y1, w2, h2, 'FD');


    if (invoice.imageLogo2)
    {
        pageHeight=820;
        var left = 250;//headerRight ;
        y=pageHeight-invoice.imageLogoHeight2;
        var headerRight=370;

        var left = headerRight - invoice.imageLogoWidth2;
        doc.addImage(invoice.imageLogo2, 'JPEG', left, y, invoice.imageLogoWidth2, invoice.imageLogoHeight2);


    }



}

function Report3AddFooter (invoice,account,doc)
{

    doc.setLineWidth(0.5);
    doc.setDrawColor(242,101,34);
    doc.setFillColor(242,101,34);

    // return doc.setTextColor(240,240,240);//select color Custom Report GRAY Colour




    var x1 = 0;//tableLeft-tablePadding ;

    var y1 = 750;

    var w2 = 596;
    var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;



    doc.rect(x1, y1, w2, h2, 'FD');


    if (invoice.imageLogo3)
    {
        pageHeight=820;
      // var left = 25;//250;//headerRight ;
        y=pageHeight-invoice.imageLogoHeight3;
        //var headerRight=370;

        //var left = headerRight - invoice.imageLogoWidth3;
        doc.addImage(invoice.imageLogo3, 'JPEG', 40, y, invoice.imageLogoWidth3, invoice.imageLogoHeight3);


    }







    var LineOne= account.name;

    MaxWidth=440;
    var AlignLine = MaxWidth-30- (doc.getStringUnitWidth(LineOne) * doc.internal.getFontSize());

    if (account.name) {

        y =780;

        doc.setFontSize(12);
        //doc.setFontType("normal");
        doc.setFontType("bold");
        SetPdfColor('White',doc);


        doc.text(AlignLine, y, LineOne);

        doc.setFontSize(7);
    }


var    y =780;

    var AlignLine2=AlignLine+110;
    if (account.address1) {

        doc.setFontSize(12);
        doc.setFontType("normal");
        SetPdfColor('White',doc);
        doc.text(AlignLine2, y,account.address1);
        doc.setFontSize(7);

        y=y+18;
    }



    if (account.address2) {

        doc.setFontSize(12);
        doc.setFontType("normal");
        SetPdfColor('White',doc);
        doc.text(AlignLine2, y,account.address2);
        doc.setFontSize(7);

        y=y+18;
    }



        doc.setFontSize(12);
        doc.setFontType("normal");
        SetPdfColor('White',doc);


        var LineTwo= account.city+' '+(account.country ? account.country.name : '');

        doc.text(AlignLine2, y,LineTwo);
        doc.setFontSize(7);




}






function GetReportTemplate3 (invoice,checkMath)
{
    var doc=false;

    var GlobalY=0;//Y position of line at current page



    var client = invoice.client;
    var account = invoice.account;


    var currencyId = client.currency_id;
    var invoiceNumber = invoice.invoice_number;
    var invoiceDate = invoice.invoice_date ? invoice.invoice_date : '';
    var dueDate = invoice.due_date ? invoice.due_date : '';

    var paid_to_date = invoice.amount - invoice.balance;

    var headerRight = 150;
    var accountTop = 30;
    var marginLeft = 180;
    var rowHeight = 10;
    var headerTop = 125; //height of HEADER //should be dynamic !


    var descriptionLeft = 162;
    var unitCostRight = 410;
    var qtyRight = 480;
    var taxRight = 480;
    var lineTotalRight = 550;
    var tableLeft = 50;


    //var tableTop = 240+100;

    //var tableRowHeight = 18;
    var tablePadding = 14;



//------------------------------ move to functions !
    var total = 0;
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

    /*
     @param orientation One of "portrait" or "landscape" (or shortcuts "p" (Default), "l")
     @param unit Measurement unit to be used when coordinates are specified. One of "pt" (points), "mm" (Default), "cm", "in"
     @param format One of 'a3', 'a4' (Default),'a5' ,'letter' ,'legal'
     @returns {jsPDF}
     */
    var doc = new jsPDF('portrait', 'pt','a4');



    //Set PDF properities
    doc.setProperties({
        title: '',
        subject: 'Report',
        author: 'Ninja',
        keywords: 'pdf, javascript,geenerated',
        creator: 'Ninja'
    });


    //set default style for report
    doc.setFont('Helvetica','');
    doc.setFontSize(7);




    Report3AddHeader (invoice,account,doc);



//----------------------------------------------------------------------------------------------------
    if (invoice.image)
    {

        y=170;
        var left = headerRight - invoice.imageWidth;
        doc.addImage(invoice.image, 'JPEG', left, y, invoice.imageWidth, invoice.imageHeight);
    }

    Report3AddFooter (invoice,account,doc);

    var invoiceNumberX = headerRight - (doc.getStringUnitWidth(invoiceNumber, false) * doc.internal.getFontSize());
    var invoiceDateX = headerRight - (doc.getStringUnitWidth(invoiceDate) * doc.internal.getFontSize());
    var dueDateX = headerRight - (doc.getStringUnitWidth(dueDate) * doc.internal.getFontSize());
    var poNumberX = headerRight - (doc.getStringUnitWidth(invoice.po_number) * doc.internal.getFontSize());



    var y = accountTop;
    var left = marginLeft;


    doc.setFontSize(7);
    SetPdfColor('White',doc);



//TODO:NOT AVAILEABLE FROM DATAMODEL
    //account.email='email N/A';
    if (account.email) {
        y += rowHeight;
        doc.text(left, y, account.email);
    }
    else
    {
        //console.log('account.email NOT DEFINED !');
    }

//TODO:NOT AVAILEABLE FROM DATAMODEL
    if (account.phone) {
        y += rowHeight;
        doc.text(left, y, account.phone);
    }
    else
    {
        //console.log('account.phone NOT DEFINED !');
    }


    var HeaderMarginThirdColumn=70;




//-----------------------------Publish Client Details block--------------------------------------------

    var y = accountTop;
    var left = marginLeft;

    var headerY = headerTop;



    SetPdfColor('GrayLogo',doc); //set black color


    var line1=headerTop+16;
    var line2=headerTop+16*2;
    var line21=headerTop+16*1.6;
    var line22=headerTop+16*2.2;

    var line3=headerTop+16*3;
    var line31=headerTop+16*3.6;

    var marginLeft1=394;


    SetPdfColor('Black',doc); //set black color

    doc.setFontSize(7);


    ClientCompanyName=client.name;
    ClientCompanyEmail='';//client.email;//'22222222';
    ClientCompanyPhone=client.work_phone;

    ClientCompanyAddress1=client.address1;
    ClientCompanyAddress2=client.address2+' '+client.postal_code;

    if(client)
    {
        ClientCompanyName=getClientDisplayName(client);
        ClientCompanyPhone=client.work_phone;
        ClientCompanyEmail=client.contacts[0].email;

    }

    //show left column
    SetPdfColor('Black',doc); //set black color
    doc.setFontType("normal");

    GlobalY=170;

    var z=GlobalY;



    SetPdfColor('Black',doc);

    if (ClientCompanyName)
    {
        doc.setFontSize('8');
        doc.setFontType("bold");
        doc.text(marginLeft1, z, ClientCompanyName);
        z=z+15;//doc.internal.getFontSize();
    }

    if (ClientCompanyAddress1)
    {

        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyAddress1);
        z=z+15;////z=z+doc.internal.getFontSize();
    }
    if (ClientCompanyAddress2)
    {

        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyAddress2);
        z=z+15;////z=z+doc.internal.getFontSize();
    }

    if (ClientCompanyEmail)
    {
        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyEmail);
        z=z+15;///z=z+doc.internal.getFontSize();
    }
    if (ClientCompanyPhone)
    {

        doc.setFontSize('8');
        doc.setFontType("normal");
        doc.text(marginLeft1, z, ClientCompanyPhone);
        z=z+15;////z=z+doc.internal.getFontSize();
    }








    marginLeft2=395;

    //publish left side information

    SetPdfColor('White',doc);
    z=30;//GlobalY+15;

    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft2, z,'Invoice Number');
    z=z+15;//doc.internal.getFontSize();


    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft2, z,'Invoice date');
    z=z+15;//doc.internal.getFontSize();

    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft2, z,'Payment Date');
    z=z+15;//doc.internal.getFontSize();


    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft2, z,'Amount Due');
    z=z+15;//doc.internal.getFontSize();


    marginLeft3=marginLeft2+65;

    z=30;//GlobalY+15;


    var invoiceNumber = invoice.invoice_number;
    var invoiceDate = invoice.invoice_date ? invoice.invoice_date : '';
    var dueDate = invoice.due_date ? invoice.due_date : '';


    doc.setFontSize('7');
    doc.setFontType("bold");
    doc.text(marginLeft3, z,invoiceNumber);
    z=z+15;//doc.internal.getFontSize();


    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft3, z,invoiceDate);
    z=z+15;//doc.internal.getFontSize();

    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft3, z,dueDate);
    z=z+15;//doc.internal.getFontSize();



    doc.setFontSize('8');
    doc.setFontType("normal");
    doc.text(marginLeft3, z,total);
    z=z+15;//doc.internal.getFontSize();





    y=z+60;
//--------------------------------Publishing Table--------------------------------------------------
    GlobalY=y+130;
    GlobalY=GlobalY+30;//y+30;
    SetPdfColor('Black',doc);
    doc.setFontSize(7);
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
















    var costX = unitCostRight - (doc.getStringUnitWidth('Unit Cost') * doc.internal.getFontSize());
    var qtyX = qtyRight - (doc.getStringUnitWidth('Quantity') * doc.internal.getFontSize());
    var taxX = taxRight - (doc.getStringUnitWidth('Tax') * doc.internal.getFontSize());
    var totalX = lineTotalRight - (doc.getStringUnitWidth('Line Total') * doc.internal.getFontSize()) -15;

    tableTop=GlobalY;//redefine this to dynamic value


    doc.setFontSize(12);
    doc.setFontType("bold");










    doc.setLineWidth(0.3);

    doc.setDrawColor(63,60,60);
    doc.setFillColor(63,60,60);
    var x1 = tableLeft-tablePadding*2+5 ;
    var y1 = GlobalY-doc.internal.getFontSize()-15;
    var w2 = 515+tablePadding*2-3;//lineTotalRight-tablePadding*5;
    var h2 = doc.internal.getFontSize()+15*2;
    doc.rect(x1, y1, w2, h2, 'FD');


    SetPdfColor('White',doc);



    doc.text(tableLeft, tableTop, 'Item');
    doc.text(descriptionLeft, tableTop, 'Description');
    doc.text(costX, tableTop, 'Unit Cost');
    doc.text(qtyX, tableTop, 'Quantity');
    doc.text(totalX, tableTop, 'Line Total');

    if (hasTaxes)
    {
        doc.text(taxX, tableTop, 'Tax');
    }

    SetPdfColor('Black',doc);
    doc.setFontSize(7);

    /* line items */

    var line = 1;
    var total = 0;
    var shownItem = false;


    GlobalY=GlobalY+24+10; //padding from top

    var FontSize=7;
    doc.setFontSize(FontSize);
    doc.setFontType("normal");

    var MaxLinesPerPage=40;



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


        length=doc.splitTextToSize(item.notes, 200).length;
        var h=length*FontSize;
        MaxGlobalY=730;



        if (h+GlobalY > MaxGlobalY) {

            tableTop = 180;
            GlobalY=tableTop;

            doc.addPage();
            Report3AddHeader(invoice,account,doc);
            Report3AddFooter(invoice,account,doc);
            NewPageFlag=1;
        }
        else  NewPageFlag=0;


if (i!=0&NewPageFlag==0)
{
        doc.setDrawColor(0,0,0); // draw red lines
        doc.setLineWidth(0.1);

        var x1 = tableLeft-tablePadding ;
        var y1 = GlobalY-FontSize;
        var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
        doc.line(x1, y1-10,x1+w2, y1-10); // horizontal line
}



        x=GlobalY;

        GlobalY=GlobalY+h+tablePadding*2;


//        SetPdfColor('SomeGreen',doc);

        doc.setFontType("bold");
        doc.text(tableLeft, x, productKey);

        doc.setFontType("normal");
        SetPdfColor('Black',doc);
        doc.text(descriptionLeft, x, notes);



        doc.text(costX, x, cost);
        doc.text(qtyX, x, qty);
        doc.text(totalX, x, lineTotal);

        if (tax) {
            doc.text(taxX, x, tax+'%');
        }



        line=line+length;
    }


    if (GlobalY>600)
    {
        GlobalY=150;
        x=150;
        doc.addPage();
        Report3AddHeader(invoice,account,doc);
        Report3AddFooter(invoice,account,doc);
    }


    else

    {

        //put line separator

        doc.setDrawColor(0,0,0); // draw red lines
        doc.setLineWidth(0.1);

        var x1 = tableLeft-tablePadding ;
        var y1 = GlobalY-FontSize;
        var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
        doc.line(x1, y1,x1+w2, y1); // horizontal line

    }



//-------------------------------Publishing Document balance------------------------------------------


    x += 16+50;

    doc.setFontType("bold");
    MsgRightAlign=400;



    GlobalY=x;


    SetPdfColor('Black',doc);
    Msg='Total';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
    doc.text(TmpMsgX, x, Msg);

    doc.setFontType("normal");
    AmountText = formatMoney(total , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);




    x += doc.internal.getFontSize()*2;

    Msg='Amount Payed';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
    doc.text(TmpMsgX, x, Msg);



    AmountText = formatMoney(paid_to_date , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);










        doc.setLineWidth(0.3);

    doc.setDrawColor(63,60,60);
    doc.setFillColor(63,60,60);
    var x1 = tableLeft-tablePadding*2 +14;
    var y1 = x+ doc.internal.getFontSize()*4;
    var w2 = 510+tablePadding*2;//lineTotalRight-tablePadding*5;
    var h2 = doc.internal.getFontSize()*3+tablePadding*2;
    doc.rect(x1, y1, w2, h2, 'FD');










    doc.setFontType("bold");
    SetPdfColor('White',doc);
    doc.setFontSize(12);
    x += doc.internal.getFontSize()*4;
    Msg='Amount Due';
    var TmpMsgX = MsgRightAlign-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());



    doc.text(TmpMsgX, x, Msg);


    doc.setFontType("normal");
    AmountText = formatMoney(balance , currencyId);
    headerLeft=headerRight+400;
    var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
    doc.text(AmountX, x, AmountText);






    return doc;
}




function Report3AddHeader (invoice,account,doc)
{
    doc.setLineWidth(0.5);
    //doc.setFillColor( 46,43,43);
    //doc.setFillColor( 46,43,43);
    doc.setDrawColor(242,101,34);
    doc.setFillColor(242,101,34);
    var x1 =0;
    var y1 = 0;
    var w2 = 595;
    var h2 = 140;
    doc.rect(x1, y1, w2, h2, 'FD');

    SetPdfColor('White',doc);
    //second column
    doc.setFontType("bold");
    var MaxWidth=594;
    var LineOne= account.name;
    var AlignLine = MaxWidth-30- (doc.getStringUnitWidth(LineOne) * doc.internal.getFontSize());
    if (account.name) {

        //SetPdfColor('SomeGreen',doc);
        doc.setFontSize('36');
        doc.setFontType("bold");
        doc.text(40,50, LineOne);

        doc.setFontType("normal");
        doc.setFontSize('7');
    }


}


function Report1AddNewPage(invoice,account,doc)
{
    doc.addPage();
    if (invoice.imageLogo1)
    {
        pageHeight=820;
        y=pageHeight-invoice.imageLogoHeight1;
        var left = 20;//headerRight - invoice.imageLogoWidth1;
        doc.addImage(invoice.imageLogo1, 'JPEG', left, y, invoice.imageLogoWidth1, invoice.imageLogoHeight1);

    }

    GlobalY = 40;
    return GlobalY;
}
