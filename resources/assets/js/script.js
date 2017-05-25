// http://stackoverflow.com/questions/9847580/how-to-detect-safari-chrome-ie-firefox-and-opera-browser
var isOpera = !!window.opera || navigator.userAgent.indexOf(' OPR/') >= 0;
var isFirefox = typeof InstallTrigger !== 'undefined';   // Firefox 1.0+
var isSafari = Object.prototype.toString.call(window.HTMLElement).indexOf('Constructor') > 0;
var isEdge = navigator.userAgent.indexOf('Edge/') >= 0;
var isChrome = !!window.chrome && !isOpera && !isEdge; // Chrome 1+
var isChromium = isChrome && navigator.userAgent.indexOf('Chromium') >= 0;
// https://code.google.com/p/chromium/issues/detail?id=574648
var isChrome48 = isChrome && navigator.userAgent.indexOf('Chrome/48') >= 0;
var isIE = /*@cc_on!@*/false || !!document.documentMode; // At least IE6
var isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

var refreshTimer;
function generatePDF(invoice, javascript, force, cb) {
  if (!invoice || !javascript) {
    return;
  }
  //console.log('== generatePDF - force: %s', force);
  if (force) {
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
  var pdfDoc = GetPdfMake(invoice, javascript, cb);

  if (cb) {
     pdfDoc.getDataUrl(cb);
  }

  return pdfDoc;
}

function copyObject(orig) {
  if (!orig) return false;
  return JSON.parse(JSON.stringify(orig));
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

  ko.bindingHandlers.combobox = {
      init: function (element, valueAccessor, allBindingsAccessor) {
         var options = allBindingsAccessor().dropdownOptions|| {};
         var value = ko.utils.unwrapObservable(valueAccessor());
         var id = (value && value.public_id) ? value.public_id() : (value && value.id) ? value.id() : value ? value : false;
         if (id) $(element).val(id);
         $(element).combobox(options);

          ko.utils.registerEventHandler(element, "change", function () {
            var value = valueAccessor();
            value($(element).val());
          });
      },
      update: function (element, valueAccessor) {
        var value = ko.utils.unwrapObservable(valueAccessor());
        var id = (value && value.public_id) ? value.public_id() : (value && value.id) ? value.id() : value ? value : false;
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

  ko.bindingHandlers.tooltip = {
    init: function(element, valueAccessor) {
        var local = ko.utils.unwrapObservable(valueAccessor()),
        options = {};

        ko.utils.extend(options, ko.bindingHandlers.tooltip.options);
        ko.utils.extend(options, local);

        $(element).tooltip(options);

        ko.utils.domNodeDisposal.addDisposeCallback(element, function() {
            $(element).tooltip("destroy");
        });
    },
    options: {
        placement: "bottom",
        trigger: "hover"
    }
  };

  ko.bindingHandlers.typeahead = {
      init: function (element, valueAccessor, allBindingsAccessor, viewModel, bindingContext) {
          var $element = $(element);
          var allBindings = allBindingsAccessor();

          $element.typeahead({
              highlight: true,
              minLength: 0,
          },
          {
              name: 'data',
              display: allBindings.key,
              limit: 50,
              source: searchData(allBindings.items, allBindings.key)
          }).on('typeahead:change', function(element, datum, name) {
              var value = valueAccessor();
              value(datum);
          });
      },

      update: function (element, valueAccessor) {
          var value = ko.utils.unwrapObservable(valueAccessor());
          if (value) {
              $(element).typeahead('val', value);
          }
      }
  };
}

function comboboxHighlighter(item) {
    var query = this.query.replace(/[\-\[\]{}()*+?.,\\\^$|#\s]/g, '\\$&');
    var result = item.replace(new RegExp('<br/>', 'g'), "\n");
    result = stripHtmlTags(result);
    result = result.replace(new RegExp('(' + query + ')', 'ig'), function ($1, match) {
        return match ? '<strong>' + match + '</strong>' : query;
    });
    return result.replace(new RegExp("\n", 'g'), '<br/>');
}

function comboboxMatcher(item) {
    return ~stripHtmlTags(item).toLowerCase().indexOf(this.query.toLowerCase());
}

function stripHtmlTags(text) {
    // http://stackoverflow.com/a/5002618/497368
    var div = document.createElement("div");
    div.innerHTML = text;
    return div.textContent || div.innerText || '';
}

function getContactDisplayName(contact)
{
    if (contact.first_name || contact.last_name) {
        return $.trim((contact.first_name || '') + ' ' + (contact.last_name || ''));
    } else {
        return contact.email;
    }
}

function getContactDisplayNameWithEmail(contact)
{
    var str = '';

    if (contact.first_name || contact.last_name) {
        str += $.trim((contact.first_name || '') + ' ' + (contact.last_name || ''));
    }

    if (contact.email) {
        if (str) {
            str += ' - ';
        }

        str += contact.email;
    }

    return $.trim(str);
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
    var clientName = getClientDisplayName(client);
    if (!clientName) {
        continue;
    }
    $clientSelect.append(new Option(clientName, client.public_id));
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
      if (!client || !getClientDisplayName(client)) continue; // client is deleted/archived
      $invoiceCombobox.append(new Option(invoice.invoice_number + ' - ' + invoice.invoice_status.name + ' - ' +
                getClientDisplayName(client) + ' - ' + formatMoneyInvoice(invoice.amount, invoice) + ' | ' +
                formatMoneyInvoice(invoice.balance, invoice),  invoice.public_id));
    }
    $('select#invoice').combobox('refresh');
  });

  var $invoiceSelect = $('select#invoice').on('change', function(e) {
    $clientCombobox = $('select#client');
    var invoiceId = $('input[name=invoice]').val();
    if (invoiceId) {
      var invoice = invoiceMap[invoiceId];
      var client = clientMap[invoice.client.public_id];
      invoice.client = client;
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
    invoice.client = client;
    setComboboxValue($('.invoice-select'), invoice.public_id, (invoice.invoice_number + ' - ' +
            invoice.invoice_status.name + ' - ' + getClientDisplayName(client) + ' - ' +
            formatMoneyInvoice(invoice.amount, invoice) + ' | ' + formatMoneyInvoice(invoice.balance, invoice)));
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
CONSTS.INVOICE_STATUS_APPROVED = 4;
CONSTS.INVOICE_STATUS_PARTIAL = 5;
CONSTS.INVOICE_STATUS_PAID = 6;

$.fn.datepicker.defaults.autoclose = true;
$.fn.datepicker.defaults.todayHighlight = true;

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

function calculateAmounts(invoice) {
  var total = 0;
  var hasTaxes = false;
  var taxes = {};
  invoice.has_product_key = false;

  // Bold designs currently breaks w/o the product column
  if (invoice.invoice_design_id == 2) {
      invoice.has_product_key = true;
  }

  // sum line item
  for (var i=0; i<invoice.invoice_items.length; i++) {
    var item = invoice.invoice_items[i];
    var lineTotal = invoice.is_statement ? roundToTwo(NINJA.parseFloat(item.balance)) : roundToTwo(NINJA.parseFloat(item.cost)) * roundToTwo(NINJA.parseFloat(item.qty));
    lineTotal = roundToTwo(lineTotal);
    if (lineTotal) {
      total += lineTotal;
    }
  }

  for (var i=0; i<invoice.invoice_items.length; i++) {
    var item = invoice.invoice_items[i];
    var taxRate1 = 0;
    var taxName1 = '';
    var taxRate2 = 0;
    var taxName2 = '';

    if (item.product_key) {
        invoice.has_product_key = true;
    } else if (invoice.invoice_items.length == 1 && !item.qty) {
        invoice.has_product_key = true;
    }

    if (item.tax_name1) {
      taxRate1 = parseFloat(item.tax_rate1);
      taxName1 = item.tax_name1;
    }

    if (item.tax_name2) {
      taxRate2 = parseFloat(item.tax_rate2);
      taxName2 = item.tax_name2;
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

    var taxAmount1 = roundToTwo(lineTotal * taxRate1 / 100);
    if (taxName1) {
      var key = taxName1 + taxRate1;
      if (taxes.hasOwnProperty(key)) {
        taxes[key].amount += taxAmount1;
      } else {
        taxes[key] = {name: taxName1, rate:taxRate1, amount:taxAmount1};
      }
    }

    var taxAmount2 = roundToTwo(lineTotal * taxRate2 / 100);
    if (taxName2) {
      var key = taxName2 + taxRate2;
      if (taxes.hasOwnProperty(key)) {
        taxes[key].amount += taxAmount2;
      } else {
        taxes[key] = {name: taxName2, rate:taxRate2, amount:taxAmount2};
      }
    }

    if (item.tax_name1 || item.tax_name2) {
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

  taxRate1 = 0;
  taxRate2 = 0;
  if (invoice.tax_rate1 && parseFloat(invoice.tax_rate1)) {
    taxRate1 = parseFloat(invoice.tax_rate1);
  }
  if (invoice.tax_rate2 && parseFloat(invoice.tax_rate2)) {
    taxRate2 = parseFloat(invoice.tax_rate2);
  }
  taxAmount1 = roundToTwo(total * taxRate1 / 100);
  taxAmount2 = roundToTwo(total * taxRate2 / 100);
  total = total + taxAmount1 + taxAmount2;

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

  invoice.total_amount = roundToTwo(roundToTwo(total) - (roundToTwo(invoice.amount) - roundToTwo(invoice.balance)));
  invoice.discount_amount = discount;
  invoice.tax_amount1 = taxAmount1;
  invoice.tax_amount2 = taxAmount2;
  invoice.item_taxes = taxes;

  if (NINJA.parseFloat(invoice.partial)) {
    invoice.balance_amount = roundToTwo(invoice.partial);
  } else {
    invoice.balance_amount = invoice.total_amount;
  }

  return invoice;
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

function roundToFour(num, toString) {
  var val = +(Math.round(num + "e+4")  + "e-4");
  return toString ? val.toFixed(4) : (val || 0);
}

function truncate(str, length) {
  return (str && str.length > length) ? (str.substr(0, length-1) + '...') : str;
}

// http://stackoverflow.com/questions/280634/endswith-in-javascript
function endsWith(str, suffix) {
    return str.indexOf(suffix, str.length - suffix.length) !== -1;
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

// https://coderwall.com/p/iprsng/convert-snake-case-to-camelcase
function snakeToCamel(s){
    return s.replace(/_([a-z])/g, function (g) { return g[1].toUpperCase(); });
}

function getDescendantProp(obj, desc) {
    var arr = desc.split(".");
    while(arr.length && (obj = obj[arr.shift()]));
    return obj;
}

function doubleDollarSign(str) {
    if (!str) return '';
    if (!str.replace) return str;
    return str.replace(/\$/g, '\$\$\$');
}

function truncate(string, length){
   if (string.length > length) {
      return string.substring(0, length) + '...';
   } else {
      return string;
   }
};

// Show/hide the 'Select' option in the datalists
function actionListHandler() {
    $('tbody tr .tr-action').closest('tr').mouseover(function() {
        $(this).closest('tr').find('.tr-action').show();
        $(this).closest('tr').find('.tr-status').hide();
    }).mouseout(function() {
        $dropdown = $(this).closest('tr').find('.tr-action');
        if (!$dropdown.hasClass('open')) {
          $dropdown.hide();
          $(this).closest('tr').find('.tr-status').show();
        }
    });
}

function loadImages(selector) {
    $(selector + ' img').each(function(index, item) {
        var src = $(item).attr('data-src');
        $(item).attr('src', src);
        $(item).attr('data-src', src);
    });
}

// http://stackoverflow.com/questions/4810841/how-can-i-pretty-print-json-using-javascript
function prettyJson(json) {
    if (typeof json != 'string') {
         json = JSON.stringify(json, undefined, 2);
    }
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        var cls = 'number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'key';
            } else {
                cls = 'string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'boolean';
        } else if (/null/.test(match)) {
            cls = 'null';
        }
        match = snakeToCamel(match);
        return '<span class="' + cls + '">' + match + '</span>';
    });
}

function searchData(data, key, fuzzy) {
    return function findMatches(q, cb) {
    var matches, substringRegex;
    if (fuzzy) {
        var options = {
          keys: [key],
        }
        var fuse = new Fuse(data, options);
        matches = fuse.search(q);
    } else {
        matches = [];
        substrRegex = new RegExp(escapeRegExp(q), 'i');
        $.each(data, function(i, obj) {
          if (substrRegex.test(obj[key])) {
            matches.push(obj);
          }
        });
    }
    cb(matches);
    }
};

function escapeRegExp(str) {
  return str.replace(/[\-\[\]\/\{\}\(\)\*\+\?\.\\\^\$\|]/g, "\\$&");
}

function firstJSONError(json) {
    for (var key in json) {
        if ( ! json.hasOwnProperty(key)) {
            continue;
        }
        var item = json[key];
        for (var subKey in item) {
            if ( ! item.hasOwnProperty(subKey)) {
                continue;
            }
            return item[subKey];
        }
    }
    return false;
}

// http://stackoverflow.com/questions/10073699/pad-a-number-with-leading-zeros-in-javascript
function pad(n, width, z) {
    z = z || '0';
    n = n + '';
    return n.length >= width ? n : new Array(width - n.length + 1).join(z) + n;
}
