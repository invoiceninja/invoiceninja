var NINJA = NINJA || {};

NINJA.TEMPLATES = {
    CLEAN: "1",
    BOLD:"2",
    MODERN: "3",
    NORMAL:"4",
    BUSINESS:"5",
    CREATIVE:"6",
    ELEGANT:"7",
    HIPSTER:"8",
    PLAYFUL:"9",
    PHOTO:"10"
};

function GetPdfMake(invoice, javascript, callback) {    

    javascript = NINJA.decodeJavascript(invoice, javascript);

    function jsonCallBack(key, val) {        
        if ((val+'').indexOf('$firstAndLast') === 0) {
            var parts = val.split(':');
            return function (i, node) {
                return (i === 0 || i === node.table.body.length) ? parseFloat(parts[1]) : 0;
            };
        } else if ((val+'').indexOf('$none') === 0) {
            return function (i, node) {
                return 0;
            };
        } else if ((val+'').indexOf('$notFirst') === 0) {
            var parts = val.split(':');
            return function (i, node) {
                return i === 0 ? 0 : parseFloat(parts[1]);
            };
        } else if ((val+'').indexOf('$amount') === 0) {            
            var parts = val.split(':');
            return function (i, node) {
                return parseFloat(parts[1]);
            };
        } else if ((val+'').indexOf('$primaryColor') === 0) {
            var parts = val.split(':');
            return NINJA.primaryColor || parts[1];
        } else if ((val+'').indexOf('$secondaryColor') === 0) {
            var parts = val.split(':');
            return NINJA.secondaryColor || parts[1];
        }

        return val;
    }


    //console.log(javascript);
    var dd = JSON.parse(javascript, jsonCallBack);

    if (!invoice.is_pro && dd.hasOwnProperty('footer') && dd.footer.hasOwnProperty('columns')) {
        dd.footer.columns.push({image: logoImages.imageLogo1, alignment: 'right', width: 130})
    }

    //console.log(JSON.stringify(dd));

    /*
    var fonts = {
        Roboto: {
            normal: 'Roboto-Regular.ttf',
            bold: 'Roboto-Medium.ttf',
            italics: 'Roboto-Italic.ttf',
            bolditalics: 'Roboto-Italic.ttf'
        },
    };
    */
    
    doc = pdfMake.createPdf(dd);
    doc.save = function(fileName) {
        this.download(fileName);
    };
    return doc;
}

NINJA.decodeJavascript = function(invoice, javascript)
{
    var account = invoice.account;
    var blankImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVQYV2NgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII=';

    // search/replace variables
    var json = {
        'accountName': account.name || ' ',
        'accountLogo': window.accountLogo || blankImage,
        'accountDetails': NINJA.accountDetails(invoice), 
        'accountAddress': NINJA.accountAddress(invoice),
        'invoiceDetails': NINJA.invoiceDetails(invoice),
        'invoiceDetailsHeight': NINJA.invoiceDetails(invoice).length * 22,
        'invoiceLineItems': NINJA.invoiceLines(invoice),
        'invoiceLineItemColumns': NINJA.invoiceColumns(invoice),
        'clientDetails': NINJA.clientDetails(invoice),
        'notesAndTerms': NINJA.notesAndTerms(invoice),
        'subtotals': NINJA.subtotals(invoice),
        'subtotalsHeight': NINJA.subtotals(invoice).length * 22,
        'subtotalsWithoutBalance': NINJA.subtotals(invoice, true),        
        'balanceDue': formatMoney(invoice.balance_amount, invoice.client.currency_id),
        'invoiceFooter': account.invoice_footer || ' ',
        'invoiceNumber': invoice.invoice_number || ' ',
        'entityType': invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice,
        'entityTypeUC': (invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice).toUpperCase(),
        'fontSize': NINJA.fontSize,
        'fontSizeLarger': NINJA.fontSize + 1,
        'fontSizeLargest': NINJA.fontSize + 2,
    }

    for (var key in json) {
        var regExp = new RegExp('"\\$'+key+'"', 'g');
        var val = JSON.stringify(json[key]);
        javascript = javascript.replace(regExp, val);
    }

    // search/replace labels 
    var regExp = new RegExp('"\\$\\\w*?Label(UC)?(:)?(\\\?)?"', 'g');
    var matches = javascript.match(regExp);    
    
    if (matches) {
        for (var i=0; i<matches.length; i++) {
            var match = matches[i];
            field = match.substring(2, match.indexOf('Label'));
            field = toSnakeCase(field);
            var value = getDescendantProp(invoice, field);
            if (match.indexOf('?') < 0) {
                var label = invoiceLabels[field];            
                if (match.indexOf('UC') >= 0) {
                    if (!label) console.log('match: ' + field);
                    label = label.toUpperCase();
                }
                if (match.indexOf(':') >= 0) {
                    label = label + ':';
                }
            } else {
                label = ' ';
            }
            javascript = javascript.replace(match, '"'+label+'"');
        }        
    }

    // search/replace values 
    var regExp = new RegExp('"\\$\\\w*?Value"', 'g');
    var matches = javascript.match(regExp);    
    
    if (matches) {
        for (var i=0; i<matches.length; i++) {
            var match = matches[i];
            field = match.substring(2, match.indexOf('Value'));
            field = toSnakeCase(field);
            var value = getDescendantProp(invoice, field) || ' ';            
            if (field.toLowerCase().indexOf('date') >= 0 && value != ' ') {
                value = moment(value, 'YYYY-MM-DD').format('MMM D YYYY');
            }
            javascript = javascript.replace(match, '"'+value+'"');
        }
    }

    return javascript;
}

NINJA.notesAndTerms = function(invoice)
{
    var data = [];

    if (invoice.public_notes) {
        data.push({text:invoice.public_notes, style: ['notes']});
        data.push({text:' '});
    }

    if (invoice.terms) {
        data.push({text:invoiceLabels.terms, style: ['termsLabel']});
        data.push({text:invoice.terms, style: ['terms']});
    }

    return NINJA.prepareDataList(data, 'notesAndTerms');
}

NINJA.invoiceColumns = function(invoice)
{
    if (invoice.has_taxes) {
        return ["15%", "*", "auto", "auto", "auto", "15%"];
    } else {
        return ["15%", "*", "auto", "auto", "15%"]
    }
}

NINJA.invoiceLines = function(invoice) {
    var grid = [
        [
            {text: invoiceLabels.item, style: ['tableHeader', 'itemTableHeader']}, 
            {text: invoiceLabels.description, style: ['tableHeader', 'descriptionTableHeader']}, 
            {text: invoiceLabels.unit_cost, style: ['tableHeader', 'costTableHeader']}, 
            {text: invoiceLabels.quantity, style: ['tableHeader', 'qtyTableHeader']}, 
            {text: invoice.has_taxes ? invoiceLabels.tax : '', style: ['tableHeader', 'taxTableHeader']}, 
            {text: invoiceLabels.line_total, style: ['tableHeader', 'lineTotalTableHeader']}
        ]
    ];

    var total = 0;
    var shownItem = false;
    var currencyId = invoice && invoice.client ? invoice.client.currency_id : 1;
    var hideQuantity = invoice.account.hide_quantity == '1';

    for (var i = 0; i < invoice.invoice_items.length; i++) {
        
        var row = [];
        var item = invoice.invoice_items[i];
        var cost = formatMoney(item.cost, currencyId, true);
        var qty = NINJA.parseFloat(item.qty) ? roundToTwo(NINJA.parseFloat(item.qty)) + '' : '';
        var notes = item.notes;
        var productKey = item.product_key;
        var tax = '';        
        
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

        rowStyle = (i % 2 == 0) ? 'odd' : 'even';
        
        row.push({style:["productKey", rowStyle], text:productKey || ' '}); // product key can be blank when selecting from a datalist
        row.push({style:["notes", rowStyle], text:notes || ' '}); 
        row.push({style:["cost", rowStyle], text:cost});
        row.push({style:["quantity", rowStyle], text:qty || ' '});

        if (invoice.has_taxes) {
            row.push({style:["tax", rowStyle], text: tax+'' || ''});
        }

        row.push({style:["lineTotal", rowStyle], text:lineTotal || ' '});

        grid.push(row);
    }   

    return NINJA.prepareDataTable(grid, 'invoiceItems');
}

NINJA.subtotals = function(invoice, removeBalance)
{
    if (!invoice) {
        return;
    }

    var account = invoice.account;
    var data = [];
    data.push([{text: invoiceLabels.subtotal}, {text: formatMoney(invoice.subtotal_amount, invoice.client.currency_id)}]);

    if (invoice.discount_amount != 0) {
        data.push([{text: invoiceLabels.discount}, {text: formatMoney(invoice.discount_amount, invoice.client.currency_id)}]);        
    }
    
    if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 == '1') {
        data.push([{text: account.custom_invoice_label1}, {text: formatMoney(invoice.custom_value1, invoice.client.currency_id)}]);        
    }
    if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 == '1') {
        data.push([{text: account.custom_invoice_label2}, {text: formatMoney(invoice.custom_value2, invoice.client.currency_id)}]);        
    }

    if (invoice.tax && invoice.tax.name || invoice.tax_name) {
        data.push([{text: invoiceLabels.tax}, {text: formatMoney(invoice.tax_amount, invoice.client.currency_id)}]);        
    }
    
    if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 != '1') {        
        data.push([{text: account.custom_invoice_label1}, {text: formatMoney(invoice.custom_value1, invoice.client.currency_id)}]);
    }
    if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 != '1') {
        data.push([{text: account.custom_invoice_label2}, {text: formatMoney(invoice.custom_value2, invoice.client.currency_id)}]);        
    }    

	var paid = invoice.amount - invoice.balance;
    if (invoice.account.hide_paid_to_date != '1' || paid) {
        data.push([{text:invoiceLabels.paid_to_date}, {text:formatMoney(paid, invoice.client.currency_id)}]);        
    }

    if (!removeBalance) {
        data.push([
            {text:invoice.is_quote ? invoiceLabels.balance_due : invoiceLabels.balance_due, style:['balanceDueLabel']},
            {text:formatMoney(invoice.balance_amount, invoice.client.currency_id), style:['balanceDue']}
        ]);
    }        

    return NINJA.prepareDataPairs(data, 'subtotals');
}

NINJA.accountDetails = function(invoice) {
    var account = invoice.account;
    var data = [
        {text:account.name, style: ['accountName']},
        {text:account.id_number},
        {text:account.vat_number},
        {text:account.work_email},
        {text:account.work_phone}
    ];	
    return NINJA.prepareDataList(data, 'accountDetails');
}

NINJA.accountAddress = function(invoice) {
    var account = invoice.account;    
    var cityStatePostal = '';
    if (account.city || account.state || account.postal_code) {
        cityStatePostal = ((account.city ? account.city + ', ' : '') + account.state + ' ' + account.postal_code).trim();
    }   

	var data = [
        {text: account.address1},
        {text: account.address2},
        {text: cityStatePostal},
        {text: account.country ? account.country.name : ''}
    ];
    
    return NINJA.prepareDataList(data, 'accountAddress');
}

NINJA.invoiceDetails = function(invoice) {

    var data = [
        [
            {text: (invoice.is_quote ? invoiceLabels.quote_number : invoiceLabels.invoice_number), style: ['invoiceNumberLabel']},
            {text: invoice.invoice_number, style: ['invoiceNumber']}
        ],
        [
            {text: invoiceLabels.po_number},            
            {text: invoice.po_number}
        ],
        [
            {text: invoiceLabels.invoice_date}, 
            {text: moment(invoice.invoice_date, 'YYYY-MM-DD').format('MMM D YYYY')}
        ],
        [
            {text: invoiceLabels.due_date}, 
            {text: invoice.due_date ? moment(invoice.due_date, 'YYYY-MM-DD').format('MMM D YYYY') : false}
        ]
    ];

    if (NINJA.parseFloat(invoice.balance) < NINJA.parseFloat(invoice.amount)) {
        data.push([
            {text: invoiceLabels.total},
            {text: formatMoney(invoice.amount, invoice.client.currency_id)}
        ]);
    }

    if (NINJA.parseFloat(invoice.partial)) {
        data.push([
            {text: invoiceLabels.balance},
            {text: formatMoney(invoice.total_amount, invoice.client.currency_id)}
        ]);
    }

    data.push([
        {text: invoiceLabels.balance_due, style: ['invoiceDetailBalanceDueLabel']},
        {text: formatMoney(invoice.balance_amount, invoice.client.currency_id), style: ['invoiceDetailBalanceDue']}
    ])

    return NINJA.prepareDataPairs(data, 'invoiceDetails');
}

NINJA.clientDetails = function(invoice) {
    var client = invoice.client;
    var data;
    if (!client) {
        return;
    }
    var contact = client.contacts[0];
    var clientName = client.name || (contact.first_name || contact.last_name ? (contact.first_name + ' ' + contact.last_name) : contact.email);
    var clientEmail = client.contacts[0].email == clientName ? '' : client.contacts[0].email; 

    data = [
        {text:clientName || ' ', style: ['clientName']},
        {text:client.address1},
        {text:concatStrings(client.city, client.state, client.postal_code)},
        {text:client.country ? client.country.name : ''},
        {text:clientEmail}
    ];

    return NINJA.prepareDataList(data, 'clientDetails');
}

NINJA.getPrimaryColor = function(defaultColor) {
    return NINJA.primaryColor ? NINJA.primaryColor : defaultColor;
}

NINJA.getSecondaryColor = function(defaultColor) {
    return NINJA.primaryColor ? NINJA.secondaryColor : defaultColor;
}

// remove blanks and add section style to all elements
NINJA.prepareDataList = function(oldData, section) {
    var newData = [];
    for (var i=0; i<oldData.length; i++) {
        var item = NINJA.processItem(oldData[i], section);
        if (item.text) {
            newData.push(item);
        }
    }
    return newData;    
}

NINJA.prepareDataTable = function(oldData, section) {
    var newData = [];
    for (var i=0; i<oldData.length; i++) {
        var row = oldData[i];        
        var newRow = [];
        for (var j=0; j<row.length; j++) {
            var item = NINJA.processItem(row[j], section);
            if (item.text) {
                newRow.push(item);
            }
        }            
        if (newRow.length) {
            newData.push(newRow);
        }
    }
    return newData;    
}

NINJA.prepareDataPairs = function(oldData, section) {
    var newData = [];
    for (var i=0; i<oldData.length; i++) {
        var row = oldData[i];
        var isBlank = false;
        for (var j=0; j<row.length; j++) {
            var item = NINJA.processItem(row[j], section);
            if (!item.text) {
                isBlank = true;                    
            }
            if (j == 1) {
                NINJA.processItem(row[j], section + "Value");
            }
        }            
        if (!isBlank) {
            newData.push(oldData[i]);
        }
    }
    return newData;    
}

NINJA.processItem = function(item, section) {
    if (item.style && item.style instanceof Array) {
        item.style.push(section);
    } else {
        item.style = [section];
    }
    return item;
}