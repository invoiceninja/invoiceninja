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

        // handle custom functions
        if (typeof val === 'string') {
            if (val.indexOf('$firstAndLast') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return (i === 0 || i === node.table.body.length) ? parseFloat(parts[1]) : 0;
                };
            } else if (val.indexOf('$none') === 0) {
                return function (i, node) {
                    return 0;
                };
            } else if (val.indexOf('$notFirstAndLastColumn') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return (i === 0 || i === node.table.widths.length) ? 0 : parseFloat(parts[1]);
                };
            } else if (val.indexOf('$notFirst') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return i === 0 ? 0 : parseFloat(parts[1]);
                };
            } else if (val.indexOf('$amount') === 0) {
                var parts = val.split(':');
                return function (i, node) {
                    return parseFloat(parts[1]);
                };
            } else if (val.indexOf('$primaryColor') === 0) {
                var parts = val.split(':');
                return NINJA.primaryColor || parts[1];
            } else if (val.indexOf('$secondaryColor') === 0) {
                var parts = val.split(':');
                return NINJA.secondaryColor || parts[1];
            }
        }

        // determine whether or not to show the header/footer
        if (invoice.features.customize_invoice_design) {
            if (key === 'header') {
                return function(page, pages) {
                    if (page === 1 || invoice.account.all_pages_header == '1') {
                        if (invoice.features.remove_created_by) {
                            return NINJA.updatePageCount(JSON.parse(JSON.stringify(val)), page, pages);
                        } else {
                            return val;
                        }
                    } else {
                        return '';
                    }
                }
            } else if (key === 'footer') {
                return function(page, pages) {
                    if (page === pages || invoice.account.all_pages_footer == '1') {
                        if (invoice.features.remove_created_by) {
                            return NINJA.updatePageCount(JSON.parse(JSON.stringify(val)), page, pages);
                        } else {
                            return val;
                        }
                    } else {
                        return '';
                    }
                }
            }
        }

        // check for markdown
        if (key === 'text') {
            val = NINJA.parseMarkdownText(val, true);
        }

        /*
        if (key === 'stack') {
            val = NINJA.parseMarkdownStack(val);
            val = NINJA.parseMarkdownText(val, false);
        }
        */

        return val;
    }

    // Add ninja logo to the footer
    var dd = JSON.parse(javascript, jsonCallBack);
    var designId = invoice.invoice_design_id;
    if (!invoice.features.remove_created_by && ! isEdge) {
        var footer = (typeof dd.footer === 'function') ? dd.footer() : dd.footer;
        if (footer) {
            if (footer.hasOwnProperty('columns')) {
                footer.columns.push({image: logoImages.imageLogo1, alignment: 'right', width: 130, margin: [0, 0, 0, 0]})
            } else {
                var foundColumns;
                for (var i=0; i<footer.length; i++) {
                    var item = footer[i];
                    if (item.hasOwnProperty('columns')) {
                        foundColumns = true;
                        var columns = item.columns;
                        if (columns[0].hasOwnProperty('stack')) {
                            columns[0].stack.push({image: logoImages.imageLogo3, alignment: 'left', width: 130, margin: [40, 6, 0, 0]});
                        } else {
                            columns.push({image: logoImages.imageLogo1, alignment: 'right', width: 130, margin: [0, -40, 20, 0]})
                        }
                    }
                }
                if (!foundColumns) {
                    footer.push({image: logoImages.imageLogo1, alignment: 'right', width: 130, margin: [0, 0, 10, 10]})
                }
            }
        }
    }

    // set page size
    dd.pageSize = invoice.account.page_size;

    //dd.watermark = {text: 'PAID', color: 'blue', opacity: 0.3};

    pdfMake.fonts = {}
    fonts = window.invoiceFonts || invoice.invoice_fonts;

    // Add only the loaded fonts
    $.each(fonts, function(i,font){
        addFont(font);
    });


    function addFont(font){
        if(window.ninjaFontVfs[font.folder]){
            folder = 'fonts/'+font.folder;
            pdfMake.fonts[font.name] = {
                normal: folder+'/'+font.normal,
                italics: folder+'/'+font.italics,
                bold: folder+'/'+font.bold,
                bolditalics: folder+'/'+font.bolditalics
            }
        }
    }

    if(!dd.defaultStyle)dd.defaultStyle = {font:NINJA.bodyFont};
    else if(!dd.defaultStyle.font)dd.defaultStyle.font = NINJA.bodyFont;

    doc = pdfMake.createPdf(dd);
    doc.save = function(fileName) {
        this.download(fileName);
    };

    return doc;
}

NINJA.updatePageCount = function(obj, pageNumber, pageCount)
{
    var pageNumberRegExp = new RegExp('\\$pageNumber', 'g');
    var pageCountRegExp = new RegExp('\\$pageCount', 'g');

    for (key in obj) {
        if (!obj.hasOwnProperty(key)) {
            continue;
        }
        var val = obj[key];
        if (typeof val === 'string') {
            val = val.replace(pageNumberRegExp, pageNumber);
            val = val.replace(pageCountRegExp, pageCount);
            obj[key] = val;
        } else if (typeof val === 'object') {
            obj[key] = NINJA.updatePageCount(val, pageNumber, pageCount);
        }
    }

    return obj;
}

NINJA.decodeJavascript = function(invoice, javascript)
{
    var account = invoice.account;
    var blankImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVQYV2NgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII=';

    // search/replace variables
    var json = {
        'accountName': account.name || ' ',
        'accountLogo': ( ! isEdge && window.accountLogo) ? window.accountLogo : blankImage,
        'accountDetails': NINJA.accountDetails(invoice),
        'accountAddress': NINJA.accountAddress(invoice),
        'invoiceDetails': NINJA.invoiceDetails(invoice),
        'invoiceDetailsHeight': (NINJA.invoiceDetails(invoice).length * 16) + 16,
        'invoiceLineItems': invoice.is_statement ? NINJA.statementLines(invoice) : NINJA.invoiceLines(invoice),
        'invoiceLineItemColumns': invoice.is_statement ? NINJA.statementColumns(invoice) : NINJA.invoiceColumns(invoice),
        'invoiceDocuments' : isEdge ? [] : NINJA.invoiceDocuments(invoice),
        'quantityWidth': NINJA.quantityWidth(invoice),
        'taxWidth': NINJA.taxWidth(invoice),
        'clientDetails': NINJA.clientDetails(invoice),
        'notesAndTerms': NINJA.notesAndTerms(invoice),
        'subtotals': invoice.is_statement ? NINJA.statementSubtotals(invoice) : NINJA.subtotals(invoice),
        'subtotalsHeight': (NINJA.subtotals(invoice).length * 16) + 16,
        'subtotalsWithoutBalance': invoice.is_statement ? [[]] : NINJA.subtotals(invoice, true),
        'subtotalsBalance': NINJA.subtotalsBalance(invoice),
        'balanceDue': formatMoneyInvoice(invoice.balance_amount, invoice),
        'invoiceFooter': NINJA.invoiceFooter(invoice),
        'invoiceNumber': invoice.invoice_number || ' ',
        'entityType': invoice.is_statement ? invoiceLabels.statement : invoice.is_quote ? invoiceLabels.quote : invoice.balance_amount < 0 ? invoiceLabels.credit_note : invoiceLabels.invoice,
        'entityTypeUC': (invoice.is_statement ? invoiceLabels.statement : invoice.is_quote ? invoiceLabels.quote : invoice.balance_amount < 0 ? invoiceLabels.credit_note : invoiceLabels.invoice).toUpperCase(),
        'entityTaxType': invoice.is_statement ? invoiceLabels.statement : invoice.is_quote ? invoiceLabels.tax_quote : invoiceLabels.tax_invoice,
        'fontSize': NINJA.fontSize,
        'fontSizeLarger': NINJA.fontSize + 1,
        'fontSizeLargest': NINJA.fontSize + 2,
        'fontSizeSmaller': NINJA.fontSize - 1,
        'bodyFont': NINJA.bodyFont,
        'headerFont': NINJA.headerFont,
    }

    for (var key in json) {
        // remove trailing commas for these fields
        if (['quantityWidth', 'taxWidth'].indexOf(key) >= 0) {
            var regExp = new RegExp('"\\$'+key+'",', 'g');
            val = json[key];
        } else {
            var regExp = new RegExp('"\\$'+key+'"', 'g');
            var val = JSON.stringify(json[key]);
            val = doubleDollarSign(val);
        }
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
            if (match.indexOf('?') < 0 || value) {
                if (invoice.partial > 0 && field == 'balance_due') {
                    field = 'partial_due';
                } else if (invoice.is_quote) {
                    if (field == 'due_date') {
                        field = 'valid_until';
                    } else {
                        field = field.replace('invoice', 'quote');
                    }
                }
                if (invoice.is_statement) {
                    if (field == 'your_invoice') {
                        field = 'your_statement';
                    } else if (field == 'invoice_issued_to') {
                        field = 'statement_issued_to';
                    } else if (field == 'invoice_to') {
                        field = 'statement_to';
                    }
                } else if (invoice.balance_amount < 0) {
                    if (field == 'your_invoice') {
                        field = 'your_credit';
                    } else if (field == 'invoice_issued_to') {
                        field = 'credit_issued_to';
                    } else if (field == 'invoice_to') {
                        field = 'credit_to';
                    }
                }

                var label = invoiceLabels[field];
                if (match.indexOf('UC') >= 0) {
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
    var regExp = new RegExp('"\\$[a-z][\\\w\\\.]*?[Value]?"', 'g');
    var matches = javascript.match(regExp);

    if (matches) {
        for (var i=0; i<matches.length; i++) {
            var match = matches[i];

            // reserved words
            if ([
                '"$none"',
                '"$firstAndLast"',
                '"$notFirstAndLastColumn"',
                '"$notFirst"',
                '"$amount"',
                '"$primaryColor"',
                '"$secondaryColor"',
            ].indexOf(match) >= 0) {
                continue;
            }

            // legacy style had 'Value' at the end
            if (endsWith(match, 'Value"')) {
                field = match.substring(2, match.indexOf('Value'));
            } else {
                field = match.substring(2, match.length - 1);
            }
            field = toSnakeCase(field);

            var value = getDescendantProp(invoice, field) || ' ';
            value = doubleDollarSign(value);
            value = value.replace(/\n/g, "\\n").replace(/\r/g, "\\r");
            javascript = javascript.replace(match, '"'+value+'"');
        }
    }

    return javascript;
}


NINJA.notesAndTerms = function(invoice)
{
    var data = [];

    if (invoice.public_notes) {
        data.push({stack:[{text: invoice.is_recurring ? processVariables(invoice.public_notes) : invoice.public_notes, style: ['notes']}]});
        data.push({text:' '});
    }

    if (invoice.terms) {
        data.push({text:invoiceLabels.terms, style: ['termsLabel']});
        data.push({stack:[{text: invoice.is_recurring ? processVariables(invoice.terms) : invoice.terms, style: ['terms']}]});
    }

    return NINJA.prepareDataList(data, 'notesAndTerms');
}

NINJA.statementColumns = function(invoice)
{
    return ["22%", "22%", "22%", "17%", "17%"];
}

NINJA.statementLines = function(invoice)
{
    var grid = [[]];
    grid[0].push({text: invoiceLabels.invoice_number, style: ['tableHeader', 'itemTableHeader']});
    grid[0].push({text: invoiceLabels.invoice_date, style: ['tableHeader', 'invoiceDateTableHeader']});
    grid[0].push({text: invoiceLabels.due_date, style: ['tableHeader', 'dueDateTableHeader']});
    grid[0].push({text: invoiceLabels.total, style: ['tableHeader', 'totalTableHeader']});
    grid[0].push({text: invoiceLabels.balance, style: ['tableHeader', 'balanceTableHeader']});

    for (var i = 0; i < invoice.invoice_items.length; i++) {
        var item = invoice.invoice_items[i];
        var row = [];
        var rowStyle = (i % 2 == 0) ? 'odd' : 'even';
        grid.push([
            {text: item.invoice_number, style:['invoiceNumber', 'productKey', rowStyle]},
            {text: item.invoice_date && item.invoice_date != '0000-00-00' ? moment(item.invoice_date).format(invoice.date_format) : ' ', style:['invoiceDate', rowStyle]},
            {text: item.due_date && item.due_date != '0000-00-00' ? moment(item.due_date).format(invoice.date_format) : ' ', style:['dueDate', rowStyle]},
            {text: formatMoneyInvoice(item.amount, invoice), style:['subtotals', rowStyle]},
            {text: formatMoneyInvoice(item.balance, invoice), style:['lineTotal', rowStyle]},
        ]);
    }

    return NINJA.prepareDataTable(grid, 'invoiceItems');
}

NINJA.invoiceColumns = function(invoice)
{
    var account = invoice.account;
    var columns = [];

    if (invoice.has_product_key) {
        columns.push("15%");
    }

    columns.push("*")

    if (invoice.features.invoice_settings && account.custom_invoice_item_label1) {
        columns.push("10%");
    }
    if (invoice.features.invoice_settings && account.custom_invoice_item_label2) {
        columns.push("10%");
    }

    var count = 3;
    if (account.hide_quantity == '1') {
        count -= 2;
    }
    if (account.show_item_taxes == '1') {
        count++;
    }
    for (var i=0; i<count; i++) {
        columns.push("14%");
    }

    return columns;
}

NINJA.invoiceFooter = function(invoice)
{
    var footer = invoice.invoice_footer;

    if (invoice.is_recurring) {
        footer = processVariables(footer);
    }

    if (!invoice.features.invoice_settings && invoice.invoice_design_id == 3) {
        return footer ? footer.substring(0, 200) : ' ';
    } else {
        return footer || ' ';
    }
}

NINJA.quantityWidth = function(invoice)
{
    return invoice.account.hide_quantity == '1' ? '' : '"14%", ';
}

NINJA.taxWidth = function(invoice)
{
    return invoice.account.show_item_taxes == '1' ? '"14%", ' : '';
}

NINJA.invoiceLines = function(invoice) {
    var account = invoice.account;
    var total = 0;
    var shownItem = false;
    var hideQuantity = invoice.account.hide_quantity == '1';
    var showItemTaxes = invoice.account.show_item_taxes == '1';

    var grid = [[]];

    if (invoice.has_product_key) {
        grid[0].push({text: invoiceLabels.item, style: ['tableHeader', 'itemTableHeader']});
    }

    grid[0].push({text: invoiceLabels.description, style: ['tableHeader', 'descriptionTableHeader']});

    if (invoice.features.invoice_settings && account.custom_invoice_item_label1) {
        grid[0].push({text: account.custom_invoice_item_label1, style: ['tableHeader', 'custom1TableHeader']});
    }
    if (invoice.features.invoice_settings && account.custom_invoice_item_label2) {
        grid[0].push({text: account.custom_invoice_item_label2, style: ['tableHeader', 'custom2TableHeader']});
    }

    if (!hideQuantity) {
        grid[0].push({text: invoiceLabels.unit_cost, style: ['tableHeader', 'costTableHeader']});
        grid[0].push({text: invoiceLabels.quantity, style: ['tableHeader', 'qtyTableHeader']});
    }
    if (showItemTaxes) {
        grid[0].push({text: invoiceLabels.tax, style: ['tableHeader', 'taxTableHeader']});
    }

    grid[0].push({text: invoiceLabels.line_total, style: ['tableHeader', 'lineTotalTableHeader']});
    for (var i = 0; i < invoice.invoice_items.length; i++) {

        var row = [];
        var item = invoice.invoice_items[i];
        var cost = formatMoneyInvoice(item.cost, invoice, 'none');
        var qty = NINJA.parseFloat(item.qty) ? roundToTwo(NINJA.parseFloat(item.qty)) + '' : '';
        var notes = item.notes;
        var productKey = item.product_key;
        var tax1 = '';
        var tax2 = '';
        var custom_value1 = item.custom_value1;
        var custom_value2 = item.custom_value2;

        if (showItemTaxes) {
            if (item.tax_name1) {
                tax1 = parseFloat(item.tax_rate1);
            }
            if (item.tax_name2) {
                tax2 = parseFloat(item.tax_rate2);
            }
        }

        // show at most one blank line
        if (shownItem && !notes && !productKey && (!cost || cost == '0' || cost == '0.00' || cost == '0,00')) {
            continue;
        }

        shownItem = true;

        // process date variables
        if (invoice.is_recurring) {
            notes = processVariables(notes);
            productKey = processVariables(productKey);
            custom_value1 = processVariables(item.custom_value1);
            custom_value2 = processVariables(item.custom_value2);
        }

        var lineTotal = roundToTwo(NINJA.parseFloat(item.cost)) * roundToTwo(NINJA.parseFloat(item.qty));
        if (account.include_item_taxes_inline == '1') {
            var taxAmount1 = 0;
            var taxAmount2 = 0;
            if (tax1) {
                taxAmount1 = roundToTwo(lineTotal * tax1 / 100);
            }
            if (tax2) {
                taxAmount2 = roundToTwo(lineTotal * tax2 / 100);
            }
            lineTotal += taxAmount1 + taxAmount2;
        }
        lineTotal = formatMoneyInvoice(lineTotal, invoice);

        rowStyle = (i % 2 == 0) ? 'odd' : 'even';

        if (invoice.has_product_key) {
            row.push({style:["productKey", rowStyle], text:productKey || ' '}); // product key can be blank when selecting from a datalist
        }
        row.push({style:["notes", rowStyle], stack:[{text:notes || ' '}]});
        if (invoice.features.invoice_settings && account.custom_invoice_item_label1) {
            row.push({style:["customValue1", rowStyle], text:custom_value1 || ' '});
        }
        if (invoice.features.invoice_settings && account.custom_invoice_item_label2) {
            row.push({style:["customValue2", rowStyle], text:custom_value2 || ' '});
        }
        if (!hideQuantity) {
            row.push({style:["cost", rowStyle], text:cost});
            row.push({style:["quantity", rowStyle], text:formatAmount(qty, invoice.client.currency_id) || ' '});
        }
        if (showItemTaxes) {
            var str = ' ';
            if (item.tax_name1) {
                str += tax1.toString() + '%';
            }
            if (item.tax_name2) {
                if (item.tax_name1) {
                    str += '  ';
                }
                str += tax2.toString() + '%';
            }
            row.push({style:["tax", rowStyle], text:str});
        }
        row.push({style:["lineTotal", rowStyle], text:lineTotal || ' '});

        grid.push(row);
    }

    return NINJA.prepareDataTable(grid, 'invoiceItems');
}

NINJA.invoiceDocuments = function(invoice) {
    if (invoice.account.invoice_embed_documents != '1') {
        return [];
    }

    var j = 0;
    var stack = [];
    var stackItem = null;

    if (invoice.documents) {
        for (var i = 0; i < invoice.documents.length; i++) {
            addDoc(invoice.documents[i]);
        }
    }

    if (invoice.expenses) {
        for (var i = 0; i < invoice.expenses.length; i++) {
            var expense = invoice.expenses[i];
            for (var j = 0; j < expense.documents.length; j++) {
                addDoc(expense.documents[j]);
            }
        }
    }

    function addDoc(document){
        var path = document.base64;

        if(!path)path = 'docs/'+document.public_id+'/'+document.name;
        if(path && (window.pdfMake.vfs[path] || document.base64)){
            // Only embed if we actually have an image for it
            if(j%3==0){
                stackItem = {columns:[]};
                stack.push(stackItem);
            }
            stackItem.columns.push({stack:[{image:path,style:'invoiceDocument',fit:[150,150]}], width:175})
            j++;
        }
    }

    return stack.length?{stack:stack}:[];
}

NINJA.statementSubtotals = function(invoice)
{
    var data = [[
        { text: invoiceLabels.balance_due, style: ['subtotalsLabel', 'subtotalsBalanceDueLabel'] },
        { text: formatMoneyInvoice(invoice.balance_amount, invoice), style: ['subtotals', 'subtotalsBalanceDue'] }
    ]];

    return NINJA.prepareDataPairs(data, 'subtotals');
}

NINJA.subtotals = function(invoice, hideBalance)
{
    if (!invoice) {
        return;
    }

    var account = invoice.account;
    var data = [];
    data.push([{text: invoiceLabels.subtotal, style: ['subtotalsLabel', 'subtotalLabel']}, {text: formatMoneyInvoice(invoice.subtotal_amount, invoice), style: ['subtotals', 'subtotal']}]);

    if (invoice.discount_amount != 0) {
        data.push([{text: invoiceLabels.discount , style: ['subtotalsLabel', 'discountLabel']}, {text: formatMoneyInvoice(invoice.discount_amount, invoice), style: ['subtotals', 'discount']}]);
    }

    var customValue1 = NINJA.parseFloat(invoice.custom_value1);
    var customValue1Label = customValue1 >= 0 ? (account.custom_invoice_label1 || invoiceLabels.surcharge) : invoiceLabels.discount;

    var customValue2 = NINJA.parseFloat(invoice.custom_value2);
    var customValue2Label = customValue2 >= 0 ? (account.custom_invoice_label2 || invoiceLabels.surcharge) : invoiceLabels.discount;

    if (customValue1 && invoice.custom_taxes1 == '1') {
        data.push([{text: customValue1Label, style: ['subtotalsLabel', 'customTax1Label']}, {text: formatMoneyInvoice(invoice.custom_value1, invoice), style: ['subtotals', 'customTax1']}]);
    }
    if (customValue2 && invoice.custom_taxes2 == '1') {
        data.push([{text: customValue2Label, style: ['subtotalsLabel', 'customTax2Label']}, {text: formatMoneyInvoice(invoice.custom_value2, invoice), style: ['subtotals', 'customTax2']}]);
    }

    for (var key in invoice.item_taxes) {
        if (invoice.item_taxes.hasOwnProperty(key)) {
            var taxRate = invoice.item_taxes[key];
            var taxStr = taxRate.name + ' ' + (taxRate.rate*1).toString() + '%';
            data.push([{text: taxStr, style: ['subtotalsLabel', 'taxLabel']}, {text: formatMoneyInvoice(taxRate.amount, invoice), style: ['subtotals', 'tax']}]);
        }
    }

    if (invoice.tax_name1) {
        var taxStr = invoice.tax_name1 + ' ' + (invoice.tax_rate1*1).toString() + '%';
        data.push([{text: taxStr, style: ['subtotalsLabel', 'tax1Label']}, {text: formatMoneyInvoice(invoice.tax_amount1, invoice), style: ['subtotals', 'tax1']}]);
    }
    if (invoice.tax_name2) {
        var taxStr = invoice.tax_name2 + ' ' + (invoice.tax_rate2*1).toString() + '%';
        data.push([{text: taxStr, style: ['subtotalsLabel', 'tax2Label']}, {text: formatMoneyInvoice(invoice.tax_amount2, invoice), style: ['subtotals', 'tax2']}]);
    }

    if (customValue1 && invoice.custom_taxes1 != '1') {
        data.push([{text: customValue1Label, style: ['subtotalsLabel', 'custom1Label']}, {text: formatMoneyInvoice(invoice.custom_value1, invoice), style: ['subtotals', 'custom1']}]);
    }
    if (customValue2 && invoice.custom_taxes2 != '1') {
        data.push([{text: customValue2Label, style: ['subtotalsLabel', 'custom2Label']}, {text: formatMoneyInvoice(invoice.custom_value2, invoice), style: ['subtotals', 'custom2']}]);
    }

    var paid = invoice.amount - invoice.balance;
    if (!invoice.is_quote && invoice.balance_amount >= 0 && (invoice.account.hide_paid_to_date != '1' || paid)) {
        data.push([{text:invoiceLabels.paid_to_date, style: ['subtotalsLabel', 'paidToDateLabel']}, {text:formatMoneyInvoice(paid, invoice), style: ['subtotals', 'paidToDate']}]);
    }

    var isPartial = NINJA.parseFloat(invoice.partial);

    if (!hideBalance || isPartial) {
        data.push([
            { text: invoice.is_quote || invoice.balance_amount < 0 ? invoiceLabels.total : invoiceLabels.balance_due, style: ['subtotalsLabel', isPartial ? '' : 'subtotalsBalanceDueLabel'] },
            { text: formatMoneyInvoice(invoice.total_amount, invoice), style: ['subtotals', isPartial ? '' : 'subtotalsBalanceDue'] }
        ]);
    }

    if (!hideBalance) {
        if (isPartial) {
            data.push([
                { text: invoiceLabels.partial_due, style: ['subtotalsLabel', 'subtotalsBalanceDueLabel'] },
                { text: formatMoneyInvoice(invoice.balance_amount, invoice), style: ['subtotals', 'subtotalsBalanceDue'] }
            ]);
        }
    }

    return NINJA.prepareDataPairs(data, 'subtotals');
}

NINJA.subtotalsBalance = function(invoice) {
    var isPartial = NINJA.parseFloat(invoice.partial);
    return [[
        {text: isPartial ? invoiceLabels.partial_due : (invoice.is_quote || invoice.balance_amount < 0 ? invoiceLabels.total : invoiceLabels.balance_due), style:['subtotalsLabel', 'subtotalsBalanceDueLabel']},
        {text: formatMoneyInvoice(invoice.balance_amount, invoice), style:['subtotals', 'subtotalsBalanceDue']}
    ]];
}

NINJA.accountDetails = function(invoice) {
    var account = invoice.account;
    if (invoice.features.invoice_settings && account.invoice_fields) {
        var fields = JSON.parse(account.invoice_fields).account_fields1;
    } else {
        var fields = [
            'account.company_name',
            'account.id_number',
            'account.vat_number',
            'account.website',
            'account.email',
            'account.phone',
        ];
    }

    var data = [];

    for (var i=0; i < fields.length; i++) {
        var field = fields[i];
        var value = NINJA.renderField(invoice, field);
        if (value) {
            data.push(value);
        }
    }

    return NINJA.prepareDataList(data, 'accountDetails');
}

NINJA.accountAddress = function(invoice) {
    var account = invoice.account;
    if (invoice.features.invoice_settings && account.invoice_fields) {
        var fields = JSON.parse(account.invoice_fields).account_fields2;
    } else {
        var fields = [
            'account.address1',
            'account.address2',
            'account.city_state_postal',
            'account.country',
            'account.custom_value1',
            'account.custom_value2',
        ]
    }

    var data = [];

    for (var i=0; i < fields.length; i++) {
        var field = fields[i];
        var value = NINJA.renderField(invoice, field);
        if (value) {
            data.push(value);
        }
    }

    return NINJA.prepareDataList(data, 'accountAddress');
}

NINJA.invoiceDetails = function(invoice) {

    var account = invoice.account;
    if (invoice.features.invoice_settings && account.invoice_fields) {
        var fields = JSON.parse(account.invoice_fields).invoice_fields;
    } else {
        var fields = [
            'invoice.invoice_number',
            'invoice.po_number',
            'invoice.invoice_date',
            'invoice.due_date',
            'invoice.balance_due',
            'invoice.partial_due',
            'invoice.custom_text_value1',
            'invoice.custom_text_value2',
        ];
    }
    var data = [];

    for (var i=0; i < fields.length; i++) {
        var field = fields[i];
        var value = NINJA.renderField(invoice, field, true);
        if (value) {
            data.push(value);
        }
    }

    return NINJA.prepareDataPairs(data, 'invoiceDetails');
}


NINJA.renderField = function(invoice, field, twoColumn) {
    var client = invoice.client;
    if (!client) {
        return false;
    }
    var account = invoice.account;
    var contact = client.contacts[0];
    var clientName = client.name || (contact.first_name || contact.last_name ? (contact.first_name + ' ' + contact.last_name) : contact.email);

    var label = false;
    var value = false;

    if (field == 'client.client_name') {
        value = clientName || ' ';
    } else if (field == 'client.contact_name') {
        value = (contact.first_name || contact.last_name) ? contact.first_name + ' ' + contact.last_name : false;
    } else if (field == 'client.id_number') {
        value = client.id_number;
    } else if (field == 'client.vat_number') {
        value = client.vat_number;
    } else if (field == 'client.address1') {
        value = client.address1;
    } else if (field == 'client.address2') {
        value = client.address2;
    } else if (field == 'client.city_state_postal') {
        var cityStatePostal = '';
        if (client.city || client.state || client.postal_code) {
            var swap = client.country && client.country.swap_postal_code;
            cityStatePostal = formatAddress(client.city, client.state, client.postal_code, swap);
        }
        value = cityStatePostal;
    } else if (field == 'client.postal_city_state') {
        var postalCityState = '';
        if (client.city || client.state || client.postal_code) {
            postalCityState = formatAddress(client.city, client.state, client.postal_code, true);
        }
        value = postalCityState;
    } else if (field == 'client.country') {
        value = client.country ? client.country.name : '';
    } else if (field == 'client.email') {
        value = contact.email == clientName ? '' : contact.email;
    } else if (field == 'client.phone') {
        value = contact.phone;
    } else if (field == 'client.custom_value1') {
        if (account.custom_client_label1 && client.custom_value1) {
            label = account.custom_client_label1;
            value = client.custom_value1;
        }
    } else if (field == 'client.custom_value2') {
        if (account.custom_client_label2 && client.custom_value2) {
            label = account.custom_client_label2;
            value = client.custom_value2;
        }
    } else if (field == 'contact.custom_value1') {
        if (account.custom_contact_label1 && contact.custom_value1) {
            label = account.custom_contact_label1;
            value = contact.custom_value1;
        }
    } else if (field == 'contact.custom_value2') {
        if (account.custom_contact_label2 && contact.custom_value2) {
            label = account.custom_contact_label2;
            value = contact.custom_value2;
        }
    } else if (field == 'account.company_name') {
        value = account.name;
    } else if (field == 'account.id_number') {
        value = account.id_number;
    } else if (field == 'account.vat_number') {
        value = account.vat_number;
    } else if (field == 'account.website') {
        value = account.website;
    } else if (field == 'account.email') {
        value = account.work_email;
    } else if (field == 'account.phone') {
        value = account.work_phone;
    } else if (field == 'account.address1') {
        value = account.address1;
    } else if (field == 'account.address2') {
        value = account.address2;
    } else if (field == 'account.city_state_postal') {
        var cityStatePostal = '';
        if (account.city || account.state || account.postal_code) {
            var swap = account.country && account.country.swap_postal_code;
            cityStatePostal = formatAddress(account.city, account.state, account.postal_code, swap);
        }
        value = cityStatePostal;
    } else if (field == 'account.postal_city_state') {
        var postalCityState = '';
        if (account.city || account.state || account.postal_code) {
            postalCityState = formatAddress(account.city, account.state, account.postal_code, true);
        }
        value = postalCityState;
    } else if (field == 'account.country') {
        value = account.country ? account.country.name : false;
    } else if (field == 'account.custom_value1') {
        if (invoice.account.custom_label1 && invoice.account.custom_value1) {
            label = invoice.account.custom_label1;
            value = invoice.account.custom_value1;
        }
    } else if (field == 'account.custom_value2') {
        if (invoice.account.custom_label2 && invoice.account.custom_value2) {
            label = invoice.account.custom_label2;
            value = invoice.account.custom_value2;
        }
    } else if (field == 'invoice.invoice_number') {
        if (! invoice.is_statement) {
            label = invoice.is_quote ? invoiceLabels.quote_number : invoice.balance_amount < 0 ? invoiceLabels.credit_number : invoiceLabels.invoice_number;
            value = invoice.invoice_number;
        }
    } else if (field == 'invoice.po_number') {
        value = invoice.po_number;
    } else if (field == 'invoice.invoice_date') {
        label = invoice.is_statement ? invoiceLabels.statement_date : invoice.is_quote ? invoiceLabels.quote_date : invoice.balance_amount < 0 ? invoiceLabels.credit_date : invoiceLabels.invoice_date;
        value = invoice.invoice_date;
    } else if (field == 'invoice.due_date') {
        label = invoice.is_quote ? invoiceLabels.valid_until : invoiceLabels.due_date;
        value = invoice.is_recurring ? false : invoice.due_date;
    } else if (field == 'invoice.custom_text_value1') {
        if (invoice.custom_text_value1 && account.custom_invoice_text_label1) {
            label = invoice.account.custom_invoice_text_label1;
            value = invoice.is_recurring ? processVariables(invoice.custom_text_value1) : invoice.custom_text_value1;
        }
    } else if (field == 'invoice.custom_text_value2') {
        if (invoice.custom_text_value2 && account.custom_invoice_text_label2) {
            label = invoice.account.custom_invoice_text_label2;
            value = invoice.is_recurring ? processVariables(invoice.custom_text_value2) : invoice.custom_text_value2;
        }
    } else if (field == 'invoice.balance_due') {
        label = invoice.is_quote || invoice.balance_amount < 0 ? invoiceLabels.total : invoiceLabels.balance_due;
        value = formatMoneyInvoice(invoice.total_amount, invoice);
    } else if (field == invoice.partial_due) {
        if (NINJA.parseFloat(invoice.partial)) {
            label = invoiceLabels.partial_due;
            value = formatMoneyInvoice(invoice.balance_amount, invoice);
        }
    } else if (field == 'invoice.invoice_total') {
        if (invoice.is_statement || invoice.is_quote || invoice.balance_amount < 0) {
            // hide field
        } else {
            value = formatMoneyInvoice(invoice.amount, invoice);
        }
    } else if (field == 'invoice.outstanding') {
        if (invoice.is_statement || invoice.is_quote) {
            // hide field
        } else {
            value = formatMoneyInvoice(client.balance, invoice);
        }
    } else if (field == '.blank') {
        value = ' ';
    }

    if (value) {
        var shortField = false;
        var parts = field.split('.');
        if (parts.length >= 2) {
            var shortField = parts[1];
        }
        var style = snakeToCamel(shortField == 'company_name' ? 'account_name' : shortField); // backwards compatibility
        if (twoColumn) {
            // try to automatically determine the label
            if (! label && label != 'Blank') {
                if (invoiceLabels[shortField]) {
                    label = invoiceLabels[shortField];
                }
            }
            return [{text: label, style: [style + 'Label']}, {text: value, style: [style]}];
        } else {
            // if the label is set prepend it to the value
            if (label) {
                value = label + ': ' + value;
            }
            return {text:value, style: [style]};
        }
    } else {
        return false;
    }
}

NINJA.clientDetails = function(invoice) {
    var account = invoice.account;
    if (invoice.features.invoice_settings && account.invoice_fields) {
        var fields = JSON.parse(account.invoice_fields).client_fields;
    } else {
        var fields = [
            'client.client_name',
            'client.id_number',
            'client.vat_number',
            'client.address1',
            'client.address2',
            'client.city_state_postal',
            'client.country',
            'client.email',
            'client.custom_value1',
            'client.custom_value2',
            'contact.custom_value1',
            'contact.custom_value2',
        ];
    }
    var data = [];

    for (var i=0; i < fields.length; i++) {
        var field = fields[i];
        var value = NINJA.renderField(invoice, field);
        if (value) {
            data.push(value);
        }
    }

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
    if (! oldData.length) {
        oldData.push({text:' '});
    }
    for (var i=0; i<oldData.length; i++) {
        var item = NINJA.processItem(oldData[i], section);
        if (item.text || item.stack) {
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
            if (item.text || item.stack) {
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
    if (! oldData.length) {
        oldData.push([{text:' '}, {text:' '}]);
    }
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


NINJA.parseMarkdownText = function(val, groupText)
{
    var rules = [
        ['\\\*\\\*(\\\w.+?)\\\*\\\*', {'bold': true}], // **value**
        ['\\\*(\\\w.+?)\\\*', {'italics': true}], // *value*
        ['^###(.*)', {'style': 'help'}], // ### Small/gray help
        ['^##(.*)', {'style': 'subheader'}], // ## Header
        ['^#(.*)', {'style': 'header'}] // # Subheader
    ];

    var parts = typeof val === 'string' ? [val] : val;
    for (var i=0; i<rules.length; i++) {
        var rule = rules[i];
        var formatter = function(data) {
            return $.extend(data, rule[1]);
        }
        parts = NINJA.parseRegExp(parts, rule[0], formatter, true);
    }

    return parts.length > 1 ? parts : val;
}

/*
NINJA.parseMarkdownStack = function(val)
{
    if (val.length == 1) {
        var item = val[0];
        var line = item.hasOwnProperty('text') ? item.text : item;

        if (typeof line === 'string') {
            line = [line];
        }

        var regExp = '^\\\* (.*[\r\n|\n|\r]?)';
        var formatter = function(data) {
            return {"ul": [data.text]};
        }

        val = NINJA.parseRegExp(line, regExp, formatter, false);
    }

    return val;
}
*/

NINJA.parseRegExp = function(val, regExpStr, formatter, groupText)
{
    var regExp = new RegExp(regExpStr, 'gm');
    var parts = [];

    for (var i=0; i<val.length; i++) {
        var line = val[i];
        parts = parts.concat(NINJA.parseRegExpLine(line, regExp, formatter, groupText));
    }

    return parts.length > 1 ? parts : val;
}

NINJA.parseRegExpLine = function(line, regExp, formatter, groupText)
{
    var parts = [];
    var lastIndex = 0;

    while (match = regExp.exec(line)) {
        if (match.index > lastIndex) {
            parts.push(line.substring(lastIndex, match.index));
        }
        var data = {};
        data.text = match[1];
        data = formatter(data);
        parts.push(data);
        lastIndex = match.index + match[0].length;
    }

    if (parts.length) {
        if (lastIndex < line.length) {
            parts.push(line.substring(lastIndex));
        }
        return parts;
    }

    return line;
}
