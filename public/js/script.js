// http://stackoverflow.com/questions/9847580/how-to-detect-safari-chrome-ie-firefox-and-opera-browser
var isOpera = !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0;
var isFirefox = typeof InstallTrigger !== 'undefined';   // Firefox 1.0+
var isSafari = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0;
var isChrome = !!window.chrome && !isOpera;              // Chrome 1+
var isChromium = isChrome && navigator.userAgent.indexOf('Chromium') >= 0;
var isIE = /*@cc_on!@*/false || !!document.documentMode; // At least IE6


var invoiceOld;
var refreshTimer;
function generatePDF(invoice, javascript, force, cb) {
  if (!invoice || !javascript) {
    return;
  }
  //console.log('== generatePDF - force: %s', force);
  if (force || !invoiceOld) {
    refreshTimer = null;
  } else {
      if (refreshTimer) {
        clearTimeout(refreshTimer);    
      }
      refreshTimer = setTimeout(function() {
        generatePDF(invoice, javascript, true, cb);
      }, 500);
      return;
  }

  invoice = calculateAmounts(invoice);
  var a = copyObject(invoice);
  var b = copyObject(invoiceOld);
  if (!force && _.isEqual(a, b)) {
    return;
  }
  invoiceOld = invoice;
  pdfmakeMarker = "{";
  if(javascript.slice(0, pdfmakeMarker.length) === pdfmakeMarker) {
    doc = GetPdfMake(invoice, javascript, cb);
  } else {
    doc = GetPdf(invoice, javascript);
    doc.getDataUrl = function(cb) {
      cb( this.output("datauristring"));  
    };    
  }

  if (cb) {
     doc.getDataUrl(cb);
  }

  return doc;
}

function copyObject(orig) {
  if (!orig) return false;
  return JSON.parse(JSON.stringify(orig));
}


function GetPdf(invoice, javascript){
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
    descriptionLeft: 150,
    unitCostRight: 410,
    qtyRight: 480,
    taxRight: 480,
    lineTotalRight: 550
  };

  /*
  if (invoice.has_taxes)
  {
    layout.descriptionLeft -= 20;
    layout.unitCostRight -= 40;
    layout.qtyRight -= 40;
  }
  */

  /*
   @param orientation One of "portrait" or "landscape" (or shortcuts "p" (Default), "l")
   @param unit Measurement unit to be used when coordinates are specified. One of "pt" (points), "mm" (Default), "cm", "in"
   @param format One of 'a3', 'a4' (Default),'a5' ,'letter' ,'legal'
   @returns {jsPDF}
   */
  var doc = new jsPDF('portrait', 'pt', 'a4');

  //doc.getStringUnitWidth = function(param) { console.log('getStringUnitWidth: %s', param); return 0};

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

  // For partial payments show "Amount Due" rather than "Balance Due"
  if (!invoiceLabels.balance_due_orig) {
    invoiceLabels.balance_due_orig = invoiceLabels.balance_due;
  }
  invoiceLabels.balance_due = NINJA.parseFloat(invoice.partial) ? invoiceLabels.amount_due : invoiceLabels.balance_due_orig;

  eval(javascript);

  // add footer
  if (invoice.invoice_footer) {
    doc.setFontType('normal');
    doc.setFontSize('8');
    SetPdfColor(invoice.invoice_design_id == 2 || invoice.invoice_design_id == 3 ? 'White' : 'Black',doc);
    var top = doc.internal.pageSize.height - layout.marginLeft;
    if (!invoice.is_pro) top -= 25;
    var footer = doc.splitTextToSize(processVariables(invoice.invoice_footer), 500);
    var numLines = footer.length - 1;
    doc.text(layout.marginLeft, top - (numLines * 8), footer);    
  }

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

  if (color=='orange') {
      return doc.setTextColor(234,121,45);//select color Custom Report GRAY Colour
  }

  if (color=='Green') {
      return doc.setTextColor(55,109,69);
  }
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
if ($.fn.dataTableExt) {
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
}

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
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
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
  return base64DecToArr(base64);
}

if (window.ko) {
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

  ko.bindingHandlers.placeholder = {
    init: function (element, valueAccessor, allBindingsAccessor) {
      var underlyingObservable = valueAccessor();
      ko.applyBindingsToNode(element, { attr: { placeholder: underlyingObservable } } );
    }
  };
}

function getContactDisplayName(contact)
{
    var str = '';
    if (contact.first_name || contact.last_name) {
        str += contact.first_name + ' ' + contact.last_name;
    }
    if (str && contact.email) {
        str += ' - ';
    }
    return str + contact.email;
}

function getClientDisplayName(client)
{
  var contact = client.contacts ? client.contacts[0] : false;
  if (client.name) {
    return client.name;
  } else if (contact) {
    return getContactDisplayName(contact);
  }
  return '';
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
                getClientDisplayName(client) + ' - ' + formatMoney(invoice.amount, client.currency_id) + ' | ' +
                formatMoney(invoice.balance, client.currency_id),  invoice.public_id));
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
        $('#amount').val(parseFloat(invoice.balance).toFixed(2));
      }
    }
  });

  $invoiceSelect.combobox();

  if (invoiceId) {
    var invoice = invoiceMap[invoiceId];
    var client = clientMap[invoice.client.public_id];
    setComboboxValue($('.invoice-select'), invoice.public_id, (invoice.invoice_number + ' - ' +
            invoice.invoice_status.name + ' - ' + getClientDisplayName(client) + ' - ' +
            formatMoney(invoice.amount, client.currency_id) + ' | ' + formatMoney(invoice.balance, client.currency_id)));
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





function displayAccount(doc, invoice, x, y, layout) {
  var account = invoice.account;

  if (!account) {
    return;
  }

  var data1 = [
    account.name,
    account.id_number,
    account.vat_number,
    account.work_email,
    account.work_phone
  ];

  var data2 = [
    concatStrings(account.address1, account.address2),
    concatStrings(account.city, account.state, account.postal_code),
    account.country ? account.country.name : false,
    invoice.account.custom_value1 ? invoice.account['custom_label1'] + ' ' + invoice.account.custom_value1 : false,
    invoice.account.custom_value2 ? invoice.account['custom_label2'] + ' ' + invoice.account.custom_value2 : false,
  ];

  if (layout.singleColumn) {

    displayGrid(doc, invoice, data1.concat(data2), x, y, layout, {hasHeader:true});

  } else {

    displayGrid(doc, invoice, data1, x, y, layout, {hasHeader:true});

    var nameWidth = account.name ? (doc.getStringUnitWidth(account.name) * doc.internal.getFontSize() * 1.1) : 0;
    var emailWidth = account.work_email ? (doc.getStringUnitWidth(account.work_email) * doc.internal.getFontSize() * 1.1) : 0;
    width = Math.max(emailWidth, nameWidth, 120);
    x += width;

    displayGrid(doc, invoice, data2, x, y, layout);
  }
}


function displayClient(doc, invoice, x, y, layout) {
  var client = invoice.client;
  if (!client) {
    return;
  }
  var data = [
    getClientDisplayName(client),
    client.id_number,
    client.vat_number,
    concatStrings(client.address1, client.address2),
    concatStrings(client.city, client.state, client.postal_code),
    client.country ? client.country.name : false,
    invoice.contact && getClientDisplayName(client) != invoice.contact.email ? invoice.contact.email : false,
    invoice.client.custom_value1 ? invoice.account['custom_client_label1'] + ' ' + invoice.client.custom_value1 : false,
    invoice.client.custom_value2 ? invoice.account['custom_client_label2'] + ' ' + invoice.client.custom_value2 : false,
  ];
  return displayGrid(doc, invoice, data, x, y, layout, {hasheader:true});
}

function displayInvoice(doc, invoice, x, y, layout, rightAlignX) {
  if (!invoice) {
    return;
  }

  var data = getInvoiceDetails(invoice);
  var options = {
    hasheader: true,
    rightAlignX: rightAlignX,
  };

  return displayGrid(doc, invoice, data, x, y, layout, options);
}

function getInvoiceDetails(invoice) {
  var fields = [
    {'invoice_number': invoice.invoice_number},
    {'po_number': invoice.po_number},
    {'invoice_date': invoice.invoice_date},
    {'due_date': invoice.due_date},
  ];

  if (NINJA.parseFloat(invoice.balance) < NINJA.parseFloat(invoice.amount)) {
    fields.push({'total': formatMoney(invoice.amount, invoice.client.currency_id)});
  }

  if (NINJA.parseFloat(invoice.partial)) {
    fields.push({'balance': formatMoney(invoice.total_amount, invoice.client.currency_id)});
  }

  fields.push({'balance_due': formatMoney(invoice.balance_amount, invoice.client.currency_id)})

  return fields;
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

  var data = [
    {'subtotal': formatMoney(invoice.subtotal_amount, invoice.client.currency_id)},
    {'discount': invoice.discount_amount != 0 ? formatMoney(invoice.discount_amount, invoice.client.currency_id) : false}
  ];

  if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 == '1') {
    data.push({'custom_invoice_label1': formatMoney(invoice.custom_value1, invoice.client.currency_id) })
  }
  if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 == '1') {
    data.push({'custom_invoice_label2': formatMoney(invoice.custom_value2, invoice.client.currency_id) })
  }

  data.push({'tax': (invoice.tax && invoice.tax.name) || invoice.tax_name ? formatMoney(invoice.tax_amount, invoice.client.currency_id) : false});

  if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 != '1') {
    data.push({'custom_invoice_label1': formatMoney(invoice.custom_value1, invoice.client.currency_id) })
  }
  if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 != '1') {
    data.push({'custom_invoice_label2': formatMoney(invoice.custom_value2, invoice.client.currency_id) })
  }

  var paid = invoice.amount - invoice.balance;
  if (paid) {
    data.push({'total': formatMoney(invoice.amount, invoice.client.currency_id)});
  }

  if (invoice.account.hide_paid_to_date != '1' || paid) {
    data.push({'paid_to_date': formatMoney(paid, invoice.client.currency_id)});
  }

  if (NINJA.parseFloat(invoice.partial) && invoice.total_amount != invoice.subtotal_amount) {
    data.push({'balance': formatMoney(invoice.total_amount, invoice.client.currency_id)});
  }

  var options = {
    hasheader: true,
    rightAlignX: 550,
    rightAlignTitleX: rightAlignTitleX
  };

  return displayGrid(doc, invoice, data, 300, y, layout, options) + 10;
}

function formatAddress(city, state, zip, swap) {
    var str = '';
    if (swap) {
        str += zip ? zip + ' ' : '';
        str += city ? city : '';
        str += (city && state) ? ', ' : (city ? ' ' : '');
        str += state;        
    } else {
        str += city ? city : '';
        str += (city && state) ? ', ' : (state ? ' ' : '');
        str += state + ' ' + zip;
    }
    return str;
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
  return data.length ? concatStr : "";
}

function displayGrid(doc, invoice, data, x, y, layout, options)  {
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

    if (options && (options.hasheader && i === 0 && !options.rightAlignTitleX)) {
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
      if (options.rightAlignX) {
        marginLeft = options.rightAlignX - (doc.getStringUnitWidth(value) * doc.internal.getFontSize());
      } else {
        marginLeft = x + 80;
      }
      doc.text(marginLeft, y, value);

      doc.setFontType('normal');
      if (invoice.is_quote) {
        if (key == 'invoice_number') {
          key = 'quote_number';
        } else if (key == 'invoice_date') {
          key = 'quote_date';
        } else if (key == 'balance_due') {
          key = 'total';
        }
      }

      if (key.substring(0, 6) === 'custom') {
        key = invoice.account[key];
      } else if (key === 'tax' && invoice.tax_name) {
        key = invoice.tax_name + ' ' + (invoice.tax_rate*1).toString() + '%';
      } else if (key === 'discount' && NINJA.parseFloat(invoice.discount) && !parseInt(invoice.is_amount_discount)) {
        key = invoiceLabels[key] + ' ' + parseFloat(invoice.discount) + '%';
      } else {
        key = invoiceLabels[key];
      }

      if (options.rightAlignTitleX) {
        marginLeft = options.rightAlignTitleX - (doc.getStringUnitWidth(key) * doc.internal.getFontSize());
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
    var notes = doc.splitTextToSize(processVariables(invoice.public_notes), 260);
    doc.text(layout.marginLeft, y, notes);
    y += 16 + (notes.length * doc.internal.getFontSize());
  }

  if (invoice.terms) {
    var terms = doc.splitTextToSize(processVariables(invoice.terms), 260);
    doc.setFontType("bold");    
    doc.text(layout.marginLeft, y, invoiceLabels.terms);
    y += 16;
    doc.setFontType("normal");
    doc.text(layout.marginLeft, y, terms);
    y += 16 + (terms.length * doc.internal.getFontSize());
  }

  return y - origY;
}

function calculateAmounts(invoice) {
  var total = 0;
  var hasTaxes = false;
  var taxes = {};

  // sum line item
  for (var i=0; i<invoice.invoice_items.length; i++) {
    var item = invoice.invoice_items[i];
    var lineTotal = roundToTwo(NINJA.parseFloat(item.cost)) * roundToTwo(NINJA.parseFloat(item.qty));
    if (lineTotal) {
      total += lineTotal;
    }
  }

  for (var i=0; i<invoice.invoice_items.length; i++) {
    var item = invoice.invoice_items[i];
    var taxRate = 0;
    var taxName = '';

    // the object structure differs if it's read from the db or created by knockoutJS
    if (item.tax && parseFloat(item.tax.rate)) {
      taxRate = parseFloat(item.tax.rate);
      taxName = item.tax.name;
    } else if (item.tax_rate && parseFloat(item.tax_rate)) {
      taxRate = parseFloat(item.tax_rate);
      taxName = item.tax_name;
    }

    // calculate line item tax
    var lineTotal = roundToTwo(NINJA.parseFloat(item.cost)) * roundToTwo(NINJA.parseFloat(item.qty));
    if (invoice.discount != 0) {
        if (parseInt(invoice.is_amount_discount)) {
            lineTotal -= roundToTwo((lineTotal/total) * invoice.discount);
        } else {
            lineTotal -= roundToTwo(lineTotal * (invoice.discount/100));
        }
    }
    var taxAmount = roundToTwo(lineTotal * taxRate / 100);

    if (taxRate) {
      var key = taxName + taxRate;
      if (taxes.hasOwnProperty(key)) {
        taxes[key].amount += taxAmount;
      } else {
        taxes[key] = {name: taxName, rate:taxRate, amount:taxAmount};
      }
    }

    if ((item.tax && item.tax.name) || item.tax_name) {
      hasTaxes = true;
    }
  }

  invoice.subtotal_amount = total;

  var discount = 0;
  if (invoice.discount != 0) {
    if (parseInt(invoice.is_amount_discount)) {
      discount = roundToTwo(invoice.discount);
    } else {
      discount = roundToTwo(total * (invoice.discount/100));
    }
    total -= discount;
  }

  // custom fields with taxes
  if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 == '1') {
    total += roundToTwo(invoice.custom_value1);
  }
  if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 == '1') {
    total += roundToTwo(invoice.custom_value2);
  }

  var tax = 0;
  if (invoice.tax && parseFloat(invoice.tax.rate)) {
    tax = parseFloat(invoice.tax.rate);
  } else if (invoice.tax_rate && parseFloat(invoice.tax_rate)) {
    tax = parseFloat(invoice.tax_rate);
  }

  if (tax) {
    var tax = roundToTwo(total * (tax/100));
    total = parseFloat(total) + parseFloat(tax);
  }

  for (var key in taxes) {
    if (taxes.hasOwnProperty(key)) {
        total += taxes[key].amount;
    }
  }

  // custom fields w/o with taxes
  if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 != '1') {
    total += roundToTwo(invoice.custom_value1);
  }
  if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 != '1') {
    total += roundToTwo(invoice.custom_value2);
  }

  invoice.total_amount = roundToTwo(total) - (roundToTwo(invoice.amount) - roundToTwo(invoice.balance));
  invoice.discount_amount = discount;
  invoice.tax_amount = tax;
  invoice.item_taxes = taxes;
  
  if (NINJA.parseFloat(invoice.partial)) {
    invoice.balance_amount = roundToTwo(invoice.partial);
  } else {
    invoice.balance_amount = invoice.total_amount;
  }

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

  if (invoice.invoice_design_id == 6 || invoice.invoice_design_id == 8 || invoice.invoice_design_id == 10) {
    invoiceLabels.item = invoiceLabels.item.toUpperCase();
    invoiceLabels.description = invoiceLabels.description.toUpperCase();
    invoiceLabels.unit_cost = invoiceLabels.unit_cost.toUpperCase();
    invoiceLabels.quantity = invoiceLabels.quantity.toUpperCase();
    invoiceLabels.line_total = invoiceLabels.total.toUpperCase();
    invoiceLabels.tax = invoiceLabels.tax.toUpperCase();
  }

  var costX = layout.unitCostRight - (doc.getStringUnitWidth(invoiceLabels.unit_cost) * doc.internal.getFontSize());
  var qtyX = layout.qtyRight - (doc.getStringUnitWidth(invoiceLabels.quantity) * doc.internal.getFontSize());
  var taxX = layout.taxRight - (doc.getStringUnitWidth(invoiceLabels.tax) * doc.internal.getFontSize());
  var totalX = layout.lineTotalRight - (doc.getStringUnitWidth(invoiceLabels.line_total) * doc.internal.getFontSize());

  doc.text(layout.marginLeft, layout.tableTop, invoiceLabels.item);
  doc.text(layout.descriptionLeft, layout.tableTop, invoiceLabels.description);
  doc.text(costX, layout.tableTop, invoiceLabels.unit_cost);
  if (invoice.account.hide_quantity != '1') {
    doc.text(qtyX, layout.tableTop, invoiceLabels.quantity);
  }
  doc.text(totalX, layout.tableTop, invoiceLabels.line_total);

  /*
  if (invoice.has_taxes)
  {
    doc.text(taxX, layout.tableTop, invoiceLabels.tax);
  }
  */
}

function displayInvoiceItems(doc, invoice, layout) {
  doc.setFontType("normal");

  var line = 1;
  var total = 0;
  var shownItem = false;
  var currencyId = invoice && invoice.client ? invoice.client.currency_id : 1;
  var tableTop = layout.tableTop;
  var hideQuantity = invoice.account.hide_quantity == '1';

  doc.setFontSize(8);
  for (var i=0; i<invoice.invoice_items.length; i++) {
    var item = invoice.invoice_items[i];
    var cost = formatMoney(item.cost, currencyId, true);
    var qty = NINJA.parseFloat(item.qty) ? roundToTwo(NINJA.parseFloat(item.qty)) + '' : '';
    var notes = item.notes;
    var productKey = item.product_key;
    var tax = 0;
    if (item.tax && parseFloat(item.tax.rate)) {
      tax = parseFloat(item.tax.rate);
    } else if (item.tax_rate && parseFloat(item.tax_rate)) {
      tax = parseFloat(item.tax_rate);
    }

    // show at most one blank line
    if (shownItem && (!cost || cost == '0.00') && !notes && !productKey) {
      continue;
    }
    shownItem = true;

    var numLines = Math.max(doc.splitTextToSize(item.notes, 200).length, doc.splitTextToSize(item.product_key, 60).length) + 2;
    //console.log('num lines %s', numLines);

    var y = tableTop + (line * layout.tableRowHeight) + (2 * layout.tablePadding);
    var top = y - layout.tablePadding;
    var newTop = top + (numLines * layout.tableRowHeight);

    if (newTop > 770) {
      line = 0;
      tableTop = layout.accountTop + layout.tablePadding;
      y = tableTop + (2 * layout.tablePadding);
      top = y - layout.tablePadding;
      newTop = top + (numLines * layout.tableRowHeight);
      doc.addPage();
    }

    var left = layout.marginLeft - layout.tablePadding;
    var width = layout.marginRight + layout.tablePadding;

    // process date variables
    if (invoice.is_recurring) {
      notes = processVariables(notes);
      productKey = processVariables(productKey);
    }

    var lineTotal = roundToTwo(NINJA.parseFloat(item.cost)) * roundToTwo(NINJA.parseFloat(item.qty));
    if (tax) {
      lineTotal += lineTotal * tax / 100;
    }
    if (lineTotal) {
      total += lineTotal;
    }
    lineTotal = formatMoney(lineTotal, currencyId);


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
    } else if (invoice.invoice_design_id == 5) {
      if (i%2 == 0) {
        doc.setDrawColor(255,255,255);
        doc.setFillColor(247,247,247);
        doc.rect(left, top, width-left+17, newTop-top, 'FD');
      } else {
        doc.setDrawColor(255,255,255);
        doc.setFillColor(232,232,232);
        doc.rect(left, top, width-left+17, newTop-top, 'FD');
      }
    } else if (invoice.invoice_design_id == 6) {
      if (i%2 == 0) {
        doc.setDrawColor(232,232,232);
        doc.setFillColor(232,232,232);
        doc.rect(left, top, width-left, newTop-top, 'FD');
      } else {
        doc.setDrawColor(255,255,255);
        doc.setFillColor(255,255,255);
        doc.rect(left, top, width-left, newTop-top, 'FD');
      }
    } else if (invoice.invoice_design_id == 7) {
      doc.setLineWidth(1);
      doc.setDrawColor(45,35,32);
      for(var k = 1; k<=width-20; k++) {
        doc.line(left+4+k,newTop,left+4+1+k,newTop);
        k = k+3;
      }
    } else if (invoice.invoice_design_id == 8) {

    } else if (invoice.invoice_design_id == 9) {
      doc.setLineWidth(1);
      doc.setDrawColor(0,157,145);
      for(var j = 1; j<=width-40; j++) {
        doc.line(left+j,newTop,left+2+j,newTop);
        j = j+5;
      }
    } else if (invoice.invoice_design_id == 10) {
      doc.setLineWidth(0.3);
      doc.setDrawColor(63,60,60);
      doc.line(left, newTop, width, newTop);
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
    } else if (invoice.invoice_design_id == 4) {
      SetPdfColor('Black', doc);
    } else if (invoice.invoice_design_id == 5) {
      SetPdfColor('Black', doc);
    } else if (invoice.invoice_design_id == 6) {
      SetPdfColor('Black', doc);
    }


    var splitTitle = doc.splitTextToSize(productKey, 60);
    if(invoice.invoice_design_id == 6) {
      doc.setFontType('bold');
    }
    if(invoice.invoice_design_id == 9) {
      doc.setTextColor(187,51,40);
    }
    if(invoice.invoice_design_id == 10) {
      doc.setTextColor(205,81,56);
    }
    doc.text(layout.marginLeft, y+2, splitTitle);

    if (invoice.invoice_design_id == 5) {

      doc.setDrawColor(255, 255, 255);
      doc.setLineWidth(1);
      doc.line(layout.descriptionLeft-8, y-16,layout.descriptionLeft-8, y+55);

      doc.line(costX-30, y-16,costX-30, y+55);

      doc.line(qtyX-45, y-16,qtyX-45, y+55);

      /*
      if (invoice.has_taxes) {
        doc.line(taxX-15, y-16,taxX-15, y+55);
      }
      */
      doc.line(totalX-27, y-16,totalX-27, y+55);

    }
    /*
    if (invoice.invoice_design_id == 8) {

      doc.setDrawColor(30, 30, 30);
      doc.setLineWidth(0.5);
      doc.line(layout.marginLeft-10, y-60,layout.marginLeft-10, y+20);

      doc.line(layout.descriptionLeft-8, y-60,layout.descriptionLeft-8, y+20);

      doc.line(costX-30, y-60,costX-30, y+20);
      console.log('CostX: %s', costX);
      doc.line(qtyX-45, y-60,qtyX-45, y+20);

      if (invoice.has_taxes) {
        doc.line(taxX-10, y-60,taxX-10, y+20);
      }

      doc.line(totalX-27, y-60,totalX-27, y+120);

      doc.line(totalX+35, y-60,totalX+35, y+120);

    }
    */

    SetPdfColor('Black', doc);
    doc.setFontType('normal');

    var splitDescription = doc.splitTextToSize(notes, 190);
    doc.text(layout.descriptionLeft, y+2, splitDescription);
    doc.text(costX, y+2, cost);
    if (!hideQuantity) {
      doc.text(qtyX, y+2, qty);
    }
    if(invoice.invoice_design_id == 9) {
      doc.setTextColor(187,51,40);
      doc.setFontType('bold');
    }
    if(invoice.invoice_design_id == 10) {
      doc.setTextColor(205,81,56);
    }
    doc.text(totalX, y+2, lineTotal);
    doc.setFontType('normal');
    SetPdfColor('Black', doc);
    if (tax) {
      doc.text(taxX, y+2, tax+'%');
    }
  }

  y = tableTop + (line * layout.tableRowHeight) + (3 * layout.tablePadding);

  if (invoice.invoice_design_id == 8) {
    doc.setDrawColor(30, 30, 30);
    doc.setLineWidth(0.5);

    var topX = tableTop - 14;
    doc.line(layout.marginLeft-10, topX,layout.marginLeft-10, y);
    doc.line(layout.descriptionLeft-8, topX,layout.descriptionLeft-8, y);
    doc.line(layout.unitCostRight-55, topX,layout.unitCostRight-55, y);
    doc.line(layout.qtyRight-50, topX,layout.qtyRight-50, y);
    /*
    if (invoice.has_taxes) {
      doc.line(layout.taxRight-28, topX,layout.taxRight-28, y);
    }
    */
    doc.line(totalX-25, topX,totalX-25, y+90);
    doc.line(totalX+45, topX,totalX+45, y+90);
  }

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


// http://stackoverflow.com/questions/11941876/correctly-suppressing-warnings-in-datatables
window.alert = (function() {
    var nativeAlert = window.alert;
    return function(message) {
        window.alert = nativeAlert;
        message && message.indexOf("DataTables warning") === 0 ?
            console.error(message) :
            nativeAlert(message);
    }
})();


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



/*\
|*|
|*|  Base64 / binary data / UTF-8 strings utilities
|*|
|*|  https://developer.mozilla.org/en-US/docs/Web/JavaScript/Base64_encoding_and_decoding
|*|
\*/

/* Array of bytes to base64 string decoding */

function b64ToUint6 (nChr) {

  return nChr > 64 && nChr < 91 ?
      nChr - 65
    : nChr > 96 && nChr < 123 ?
      nChr - 71
    : nChr > 47 && nChr < 58 ?
      nChr + 4
    : nChr === 43 ?
      62
    : nChr === 47 ?
      63
    :
      0;

}

function base64DecToArr (sBase64, nBlocksSize) {

  var
    sB64Enc = sBase64.replace(/[^A-Za-z0-9\+\/]/g, ""), nInLen = sB64Enc.length,
    nOutLen = nBlocksSize ? Math.ceil((nInLen * 3 + 1 >> 2) / nBlocksSize) * nBlocksSize : nInLen * 3 + 1 >> 2, taBytes = new Uint8Array(nOutLen);

  for (var nMod3, nMod4, nUint24 = 0, nOutIdx = 0, nInIdx = 0; nInIdx < nInLen; nInIdx++) {
    nMod4 = nInIdx & 3;
    nUint24 |= b64ToUint6(sB64Enc.charCodeAt(nInIdx)) << 18 - 6 * nMod4;
    if (nMod4 === 3 || nInLen - nInIdx === 1) {
      for (nMod3 = 0; nMod3 < 3 && nOutIdx < nOutLen; nMod3++, nOutIdx++) {
        taBytes[nOutIdx] = nUint24 >>> (16 >>> nMod3 & 24) & 255;
      }
      nUint24 = 0;

    }
  }

  return taBytes;
}

/* Base64 string to array encoding */

function uint6ToB64 (nUint6) {

  return nUint6 < 26 ?
      nUint6 + 65
    : nUint6 < 52 ?
      nUint6 + 71
    : nUint6 < 62 ?
      nUint6 - 4
    : nUint6 === 62 ?
      43
    : nUint6 === 63 ?
      47
    :
      65;

}

function base64EncArr (aBytes) {

  var nMod3 = 2, sB64Enc = "";

  for (var nLen = aBytes.length, nUint24 = 0, nIdx = 0; nIdx < nLen; nIdx++) {
    nMod3 = nIdx % 3;
    if (nIdx > 0 && (nIdx * 4 / 3) % 76 === 0) { sB64Enc += "\r\n"; }
    nUint24 |= aBytes[nIdx] << (16 >>> nMod3 & 24);
    if (nMod3 === 2 || aBytes.length - nIdx === 1) {
      sB64Enc += String.fromCharCode(uint6ToB64(nUint24 >>> 18 & 63), uint6ToB64(nUint24 >>> 12 & 63), uint6ToB64(nUint24 >>> 6 & 63), uint6ToB64(nUint24 & 63));
      nUint24 = 0;
    }
  }

  return sB64Enc.substr(0, sB64Enc.length - 2 + nMod3) + (nMod3 === 2 ? '' : nMod3 === 1 ? '=' : '==');

}

/* UTF-8 array to DOMString and vice versa */

function UTF8ArrToStr (aBytes) {

  var sView = "";

  for (var nPart, nLen = aBytes.length, nIdx = 0; nIdx < nLen; nIdx++) {
    nPart = aBytes[nIdx];
    sView += String.fromCharCode(
      nPart > 251 && nPart < 254 && nIdx + 5 < nLen ? /* six bytes */
        /* (nPart - 252 << 32) is not possible in ECMAScript! So...: */
        (nPart - 252) * 1073741824 + (aBytes[++nIdx] - 128 << 24) + (aBytes[++nIdx] - 128 << 18) + (aBytes[++nIdx] - 128 << 12) + (aBytes[++nIdx] - 128 << 6) + aBytes[++nIdx] - 128
      : nPart > 247 && nPart < 252 && nIdx + 4 < nLen ? /* five bytes */
        (nPart - 248 << 24) + (aBytes[++nIdx] - 128 << 18) + (aBytes[++nIdx] - 128 << 12) + (aBytes[++nIdx] - 128 << 6) + aBytes[++nIdx] - 128
      : nPart > 239 && nPart < 248 && nIdx + 3 < nLen ? /* four bytes */
        (nPart - 240 << 18) + (aBytes[++nIdx] - 128 << 12) + (aBytes[++nIdx] - 128 << 6) + aBytes[++nIdx] - 128
      : nPart > 223 && nPart < 240 && nIdx + 2 < nLen ? /* three bytes */
        (nPart - 224 << 12) + (aBytes[++nIdx] - 128 << 6) + aBytes[++nIdx] - 128
      : nPart > 191 && nPart < 224 && nIdx + 1 < nLen ? /* two bytes */
        (nPart - 192 << 6) + aBytes[++nIdx] - 128
      : /* nPart < 127 ? */ /* one byte */
        nPart
    );
  }

  return sView;

}

function strToUTF8Arr (sDOMStr) {

  var aBytes, nChr, nStrLen = sDOMStr.length, nArrLen = 0;

  /* mapping... */

  for (var nMapIdx = 0; nMapIdx < nStrLen; nMapIdx++) {
    nChr = sDOMStr.charCodeAt(nMapIdx);
    nArrLen += nChr < 0x80 ? 1 : nChr < 0x800 ? 2 : nChr < 0x10000 ? 3 : nChr < 0x200000 ? 4 : nChr < 0x4000000 ? 5 : 6;
  }

  aBytes = new Uint8Array(nArrLen);

  /* transcription... */

  for (var nIdx = 0, nChrIdx = 0; nIdx < nArrLen; nChrIdx++) {
    nChr = sDOMStr.charCodeAt(nChrIdx);
    if (nChr < 128) {
      /* one byte */
      aBytes[nIdx++] = nChr;
    } else if (nChr < 0x800) {
      /* two bytes */
      aBytes[nIdx++] = 192 + (nChr >>> 6);
      aBytes[nIdx++] = 128 + (nChr & 63);
    } else if (nChr < 0x10000) {
      /* three bytes */
      aBytes[nIdx++] = 224 + (nChr >>> 12);
      aBytes[nIdx++] = 128 + (nChr >>> 6 & 63);
      aBytes[nIdx++] = 128 + (nChr & 63);
    } else if (nChr < 0x200000) {
      /* four bytes */
      aBytes[nIdx++] = 240 + (nChr >>> 18);
      aBytes[nIdx++] = 128 + (nChr >>> 12 & 63);
      aBytes[nIdx++] = 128 + (nChr >>> 6 & 63);
      aBytes[nIdx++] = 128 + (nChr & 63);
    } else if (nChr < 0x4000000) {
      /* five bytes */
      aBytes[nIdx++] = 248 + (nChr >>> 24);
      aBytes[nIdx++] = 128 + (nChr >>> 18 & 63);
      aBytes[nIdx++] = 128 + (nChr >>> 12 & 63);
      aBytes[nIdx++] = 128 + (nChr >>> 6 & 63);
      aBytes[nIdx++] = 128 + (nChr & 63);
    } else /* if (nChr <= 0x7fffffff) */ {
      /* six bytes */
      aBytes[nIdx++] = 252 + /* (nChr >>> 32) is not possible in ECMAScript! So...: */ (nChr / 1073741824);
      aBytes[nIdx++] = 128 + (nChr >>> 24 & 63);
      aBytes[nIdx++] = 128 + (nChr >>> 18 & 63);
      aBytes[nIdx++] = 128 + (nChr >>> 12 & 63);
      aBytes[nIdx++] = 128 + (nChr >>> 6 & 63);
      aBytes[nIdx++] = 128 + (nChr & 63);
    }
  }

  return aBytes;

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

function toggleDatePicker(field) {
  $('#'+field).datepicker('show');
}

function roundToTwo(num, toString) {
  var val = +(Math.round(num + "e+2")  + "e-2");
  return toString ? val.toFixed(2) : (val || 0);
}

function truncate(str, length) {
  return (str && str.length > length) ? (str.substr(0, length-1) + '...') : str;
}

// http://codeaid.net/javascript/convert-seconds-to-hours-minutes-and-seconds-%28javascript%29
function secondsToTime(secs)
{
    secs = Math.round(secs);
    var hours = Math.floor(secs / (60 * 60));

    var divisor_for_minutes = secs % (60 * 60);
    var minutes = Math.floor(divisor_for_minutes / 60);

    var divisor_for_seconds = divisor_for_minutes % 60;
    var seconds = Math.ceil(divisor_for_seconds);

    var obj = {
        "h": hours,
        "m": minutes,
        "s": seconds
    };
    return obj;
}

function twoDigits(value) {
   if (value < 10) {
       return '0' + value;
   }
   return value;
}

function toSnakeCase(str) {
    if (!str) return '';
    return str.replace(/([A-Z])/g, function($1){return "_"+$1.toLowerCase();});
}

function getDescendantProp(obj, desc) {
    var arr = desc.split(".");
    while(arr.length && (obj = obj[arr.shift()]));
    return obj;
}

function doubleDollarSign(str) {
    if (!str) return '';
    return str.replace(/\$/g, '\$\$\$');
}

function truncate(string, length){
   if (string.length > length) {
      return string.substring(0, length) + '...';
   } else {
      return string;
   }
};