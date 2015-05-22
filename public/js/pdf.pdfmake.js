var NINJA = NINJA || {};

function GetPdfMake(invoice, javascript, callback) {
    var account = invoice.account;
    var baseDD = {
        pageMargins: [40, 40, 40, 40],
        styles: {
            bold: {
                bold: true
            },            
            cost: {
                alignment: 'right'
            },
            quantity: {
                alignment: 'right'
            },
            tax: {
                alignment: 'right'
            },
            lineTotal: {
                alignment: 'right'
            },
            right: {
                alignment: 'right'
            },
            subtotals: {
                alignment: 'right'
            },            
            termsLabel: {
                bold: true,
                margin: [0, 10, 0, 4]
            }            
        },
        footer: function(){
            f = [{ text:invoice.invoice_footer?invoice.invoice_footer:"", margin: [40, 0]}]
            if (!invoice.is_pro && logoImages.imageLogo1) {
                f.push({
                    image: logoImages.imageLogo1,
                    width: 150,
                    margin: [40,0]
                });
                }
            return f;
        },

    };

    eval(javascript);    
    dd = $.extend(true, baseDD, dd);    

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

NINJA.notesAndTerms = function(invoice)
{
    var text = [];
    if (invoice.public_notes) {
        text.push({text:invoice.public_notes, style:'notes'});
    }

    if (invoice.terms) {
        text.push({text:invoiceLabels.terms, style:'termsLabel'});
        text.push({text:invoice.terms, style:'terms'});
    }

    return text;
}

NINJA.invoiceLines = function(invoice) {
    var grid = [
        [
            {text: invoiceLabels.item, style: 'tableHeader'}, 
            {text: invoiceLabels.description, style: 'tableHeader'}, 
            {text: invoiceLabels.unit_cost, style: 'tableHeader'}, 
            {text: invoiceLabels.quantity, style: 'tableHeader'}, 
            {text: invoice.has_taxes?invoiceLabels.tax:'', style: 'tableHeader'}, 
            {text: invoiceLabels.line_total, style: 'tableHeader'}
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
        var tax = "";
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

        rowStyle = i%2===0?'odd':'even';

        row[0] = {style:["productKey", rowStyle], text:productKey};
        row[1] = {style:["notes", rowStyle], text:notes};
        row[2] = {style:["cost", rowStyle], text:cost};
        row[3] = {style:["quantity", rowStyle], text:qty};
        row[4] = {style:["tax", rowStyle], text:""+tax};
        row[5] = {style:["lineTotal", rowStyle], text:lineTotal};

        grid.push(row);
    }   

    return grid;
}

NINJA.subtotals = function(invoice)
{
    if (!invoice) {
        return;
    }

    var data = [
        [invoiceLabels.subtotal, formatMoney(invoice.subtotal_amount, invoice.client.currency_id)],
    ];

    if(invoice.discount_amount != 0) {
        data.push([invoiceLabels.discount, formatMoney(invoice.discount_amount, invoice.client.currency_id)]);
    }

    if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 == '1') {
        data.push([invoiceLabels.custom_invoice_label1, formatMoney(invoice.custom_value1, invoice.client.currency_id)]);
    }
    if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 == '1') {
        data.push([invoiceLabels.custom_invoice_label2, formatMoney(invoice.custom_value2, invoice.client.currency_id)]);
    }

    if(invoice.tax && invoice.tax.name || invoice.tax_name) {
        data.push([invoiceLabels.tax, formatMoney(invoice.tax_amount, invoice.client.currency_id)]);
    }

    if (NINJA.parseFloat(invoice.custom_value1) && invoice.custom_taxes1 != '1') {
        data.push([invoiceLabels.custom_invoice_label1, formatMoney(invoice.custom_value1, invoice.client.currency_id)]);
    }
    if (NINJA.parseFloat(invoice.custom_value2) && invoice.custom_taxes2 != '1') {
        data.push([invoiceLabels.custom_invoice_label2, formatMoney(invoice.custom_value2, invoice.client.currency_id)]);
    }

    var paid = invoice.amount - invoice.balance;
    if (invoice.account.hide_paid_to_date != '1' || paid) {
        data.push([invoiceLabels.paid_to_date, formatMoney(paid, invoice.client.currency_id)]);
    }

    data.push([{text:invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due, style:'balanceDueLabel'},
        {text:formatMoney(invoice.balance_amount, invoice.client.currency_id), style:'balanceDueValue'}]);
    return data;
}

NINJA.accountDetails = function(account) {
    var data = [];
    if(account.name) data.push({text:account.name, style:'accountName'});
    if(account.id_number) data.push({text:account.id_number, style:'accountDetails'});
    if(account.vat_number) data.push({text:account.vat_number, style:'accountDetails'});
    if(account.work_email) data.push({text:account.work_email, style:'accountDetails'});
    if(account.work_phone) data.push({text:account.work_phone, style:'accountDetails'});
    return data;
}

NINJA.accountAddress = function(account) {
    var address = '';
    if (account.city || account.state || account.postal_code) {
        address = ((account.city ? account.city + ', ' : '') + account.state + ' ' + account.postal_code).trim();
    }    
    var data = [];
    if(account.address1) data.push({text:account.address1, style:'accountDetails'});
    if(account.address2) data.push({text:account.address2, style:'accountDetails'});
    if(address) data.push({text:address, style:'accountDetails'});
    if(account.country) data.push({text:account.country.name, style: 'accountDetails'});
    return data;
}

NINJA.invoiceDetails = function(invoice) {
    var data = [
        [
            invoice.is_quote ? invoiceLabels.quote_number : invoiceLabels.invoice_number,
            {style: 'bold', text: invoice.invoice_number},
        ],
        [
            invoice.is_quote ? invoiceLabels.quote_date : invoiceLabels.invoice_date, 
            invoice.invoice_date, 
        ],
        [
            invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due, 
            formatMoney(invoice.balance_amount, invoice.client.currency_id), 
        ],
    ];

    return data;
}

NINJA.clientDetails = function(invoice) {    
    var client = invoice.client;
    if (!client) {
        return;
    }

    var fields = [
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

    var data = [];
    for (var i=0; i<fields.length; i++) {
        var field = fields[i];
        if (!field) {
            continue;
        }
        data.push([field]);        
    }
    if (!data.length) {
        data.push(['']);
    }
    return data;
}


NINJA.getPrimaryColor = function(defaultColor) {
    return NINJA.primaryColor ? NINJA.primaryColor : defaultColor;
}

NINJA.getSecondaryColor = function(defaultColor) {
    return NINJA.primaryColor ? NINJA.secondaryColor : defaultColor;
}

NINJA.getEntityLabel = function(invoice) {
    return invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice;
}