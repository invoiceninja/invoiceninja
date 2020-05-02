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
    var itemsTable = false;

    // check if we need to add a second table for tasks
    if (invoice.hasTasks) {
        if (invoice.hasSecondTable) {
            var json = JSON.parse(javascript);
            for (var i=0; i<json.content.length; i++) {
                var item = json.content[i];
                if (item.table && item.table.body == '$invoiceLineItems') {
                    itemsTable = JSON.stringify(item);
                    itemsTable = itemsTable.replace('$invoiceLineItems', '$taskLineItems');
                    itemsTable = itemsTable.replace('$invoiceLineItemColumns', '$taskLineItemColumns');
                    break;
                }
            }
            itemsTable = JSON.parse(itemsTable);
            json.content.splice(i+1, 0, itemsTable);
            javascript = JSON.stringify(json);
        // use the single product table for tasks
        } else {
            javascript = javascript.replace('$invoiceLineItems', '$taskLineItems');
            javascript = javascript.replace('$invoiceLineItemColumns', '$taskLineItemColumns');
        }
    } else if (invoice.is_statement) {
        var json = JSON.parse(javascript);
        for (var i=0; i<json.content.length; i++) {
            var item = json.content[i];
            if (item.table && item.table.body == '$invoiceLineItems') {
                json.content.splice(i, 2);
                json.content.splice(i, 0, "$statementDetails");
            }
        }
        javascript = JSON.stringify(json);
    }

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
    if (!invoice.features.remove_created_by) {
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

    // support setting noWrap as a style
    dd.styles.noWrap = {'noWrap': true};
    dd.styles.discount = {'alignment': 'right'};
    dd.styles.alignRight = {'alignment': 'right'};

    // set page size
    dd.pageSize = invoice.account.page_size;

    if (invoice.watermark) {
        dd.watermark = {
            text: invoice.watermark,
            color: 'black',
            opacity: 0.04,
        };
    }

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

    if (window.accountBackground) {
        var origBackground = dd.background;
        if (! origBackground) {
            origBackground = [{"image": window.accountBackground, "alignment": "center"}];
        }
        dd.background = function(currentPage) {
            var allPages = origBackground.length && origBackground[0].pages == 'all';
            return currentPage == 1 || allPages ? origBackground : false;
        }
    } else {
        // prevent unnecessarily showing blank image
        dd.background = false;
    }

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
        'accountLogo': window.accountLogo ? window.accountLogo : blankImage,
        'accountBackground': window.accountBackground ? window.accountBackground : blankImage,
        'accountDetails': NINJA.accountDetails(invoice),
        'accountAddress': NINJA.accountAddress(invoice),
        'invoiceDetails': NINJA.invoiceDetails(invoice),
        'invoiceDetailsHeight': (NINJA.invoiceDetails(invoice).length * 16) + 16,
        'invoiceLineItems': NINJA.invoiceLines(invoice),
        'invoiceLineItemColumns': NINJA.invoiceColumns(invoice, javascript),
        'taskLineItems': NINJA.invoiceLines(invoice, true),
        'taskLineItemColumns': NINJA.invoiceColumns(invoice, javascript, true),
        'invoiceDocuments' : NINJA.invoiceDocuments(invoice),
        'quantityWidth': NINJA.quantityWidth(invoice),
        'taxWidth': NINJA.taxWidth(invoice),
        'clientDetails': NINJA.clientDetails(invoice),
        'statementDetails': NINJA.statementDetails(invoice),
        'notesAndTerms': NINJA.notesAndTerms(invoice),
        'subtotals': NINJA.subtotals(invoice),
        'subtotalsHeight': (NINJA.subtotals(invoice).length * 16) + 16,
        'subtotalsWithoutBalance': NINJA.subtotals(invoice, true),
        'subtotalsBalance': NINJA.subtotalsBalance(invoice),
        'balanceDue': formatMoneyInvoice(invoice.balance_amount, invoice),
        'invoiceFooter': NINJA.invoiceFooter(invoice),
        'invoiceNumber': invoice.is_statement ? '' : (invoice.invoice_number || ' '),
        'entityType': NINJA.entityType(invoice),
        'entityTypeUC': NINJA.entityType(invoice).toUpperCase(),
        'entityTaxType': invoice.is_statement ? invoiceLabels.statement : invoice.is_quote ? invoiceLabels.tax_quote : invoiceLabels.tax_invoice,
        'fontSize': NINJA.fontSize,
        'fontSizeLarger': NINJA.fontSize + 1,
        'fontSizeLargest': NINJA.fontSize + 2,
        'fontSizeSmaller': NINJA.fontSize - 1,
        'bodyFont': NINJA.bodyFont,
        'headerFont': NINJA.headerFont,
        'signature': NINJA.signature(invoice),
        'signatureBase64': NINJA.signatureImage(invoice),
        'signatureDate': NINJA.signatureDate(invoice),
        'invoiceTotal': formatMoneyInvoice(invoice.amount, invoice),
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
                } else if (invoice.is_delivery_note) {
                    field = 'delivery_note';
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
    var regExp = new RegExp('\\$[a-zA-Z][a-zA-Z0-9_\\.]*[Value]?', 'g');
    var matches = javascript.match(regExp);

    if (matches) {
        for (var i=0; i<matches.length; i++) {
            var match = matches[i];

            // reserved words
            if ([
                '$none',
                '$firstAndLast',
                '$notFirstAndLastColumn',
                '$notFirst',
                '$amount',
                '$primaryColor',
                '$secondaryColor',
            ].indexOf(match) >= 0) {
                continue;
            }

            field = match.replace('$invoice.', '$');

            // legacy style had 'Value' at the end
            if (endsWith(field, 'Value')) {
                field = field.substring(1, field.indexOf('Value'));
            } else {
                field = field.substring(1, field.length);
            }

            if (! field) {
                continue;
            }

            field = toSnakeCase(field);

            if (field == 'footer') {
                field = 'invoice_footer';
            } else if (match == '$account.phone') {
                field = 'account.work_phone';
            } else if (match == '$client.phone') {
                field = 'client.phone';
            }

            var value = getDescendantProp(invoice, field) || ' ';
            value = doubleDollarSign(value) + '';
            value = value.replace(/\n/g, "\\n").replace(/\r/g, "\\r");

            if (['amount', 'partial', 'client.balance', 'client.paid_to_date'].indexOf(field) >= 0) {
                value = formatMoneyInvoice(value, invoice);
            }

            if (['$pageNumber', '$pageCount'].indexOf(match) == -1) {
                javascript = javascript.replace(match, value);
            }
        }
    }

    return javascript;
}

NINJA.statementDetails = function(invoice) {
    if (! invoice.is_statement) {
        return false;
    }

    var data = {
        "stack": []
    };

    var table = {
        "style": "invoiceLineItemsTable",
        "margin": [0, 20, 0, 16],
        "table": {
            "headerRows": 1,
            "widths": false,
            "body": false,
        },
        "layout": {
            "hLineWidth": "$notFirst:.5",
            "vLineWidth": "$none",
            "hLineColor": "#D8D8D8",
            "paddingLeft": "$amount:8",
            "paddingRight": "$amount:8",
            "paddingTop": "$amount:14",
            "paddingBottom": "$amount:14"
        }
    };

    var subtotals =   {
        "columns": [
            {
                "text": " ",
                "width": "60%",
            },
            {
                "table": {
                    "widths": [
                        "*",
                        "40%"
                    ],
                    "body": false,
                },
                "margin": [0, 0, 0, 16],
                "layout": {
                    "hLineWidth": "$none",
                    "vLineWidth": "$none",
                    "paddingLeft": "$amount:34",
                    "paddingRight": "$amount:8",
                    "paddingTop": "$amount:4",
                    "paddingBottom": "$amount:4"
                }
            }
        ]
    };


    var hasPayments = false;
    var hasAging = false;
    var paymentTotal = 0;
    for (var i = 0; i < invoice.invoice_items.length; i++) {
        var item = invoice.invoice_items[i];
        if (item.invoice_item_type_id == 3) {
            paymentTotal += item.cost;
            hasPayments = true;
        } else if (item.invoice_item_type_id == 4) {
            hasAging = true;
        }
    }

    var clone = JSON.parse(JSON.stringify(table));
    clone.table.body = NINJA.prepareDataTable(NINJA.statementInvoices(invoice), 'invoiceItems');
    clone.table.widths = ["22%", "22%", "22%", "17%", "17%"];
    data.stack.push(clone);

    var clone = JSON.parse(JSON.stringify(subtotals));
    clone.columns[1].table.body = [[
        { text: invoiceLabels.balance_due, style: ['subtotalsLabel', 'subtotalsBalanceDueLabel'] },
        { text: formatMoneyInvoice(invoice.balance_amount, invoice), style: ['subtotals', 'subtotalsBalanceDue', 'noWrap'] }
    ]];
    data.stack.push(clone);

    if (hasPayments) {
        var clone = JSON.parse(JSON.stringify(table));
        clone.table.body = NINJA.prepareDataTable(NINJA.statementPayments(invoice), 'invoiceItems');
        clone.table.widths = ["22%", "22%", "39%", "17%"];
        data.stack.push(clone);

        var clone = JSON.parse(JSON.stringify(subtotals));
        clone.columns[1].table.body = [[
            { text: invoiceLabels.amount_paid, style: ['subtotalsLabel', 'subtotalsBalanceDueLabel'] },
            { text: formatMoneyInvoice(paymentTotal, invoice), style: ['subtotals', 'subtotalsBalanceDue', 'noWrap'] }
        ]];
        data.stack.push(clone);
    }

    if (hasAging) {
        var clone = JSON.parse(JSON.stringify(table));
        clone.table.body = NINJA.prepareDataTable(NINJA.statementAging(invoice), 'invoiceItems');
        clone.table.widths = ["20%", "20%", "20%", "20%", "20%"];
        data.stack.push(clone);
    }

    return data;
}

NINJA.statementInvoices = function(invoice) {
    var grid = [[]];
    grid[0].push({text: invoiceLabels.invoice_number, style: ['tableHeader', 'itemTableHeader', 'firstColumn']});
    grid[0].push({text: invoiceLabels.invoice_date, style: ['tableHeader', 'invoiceDateTableHeader']});
    grid[0].push({text: invoiceLabels.due_date, style: ['tableHeader', 'dueDateTableHeader']});
    grid[0].push({text: invoiceLabels.total, style: ['tableHeader', 'totalTableHeader']});
    grid[0].push({text: invoiceLabels.balance, style: ['tableHeader', 'balanceTableHeader', 'lastColumn']});

    var counter = 0;
    for (var i = 0; i < invoice.invoice_items.length; i++) {
        var item = invoice.invoice_items[i];
        if (item.invoice_item_type_id != 1) {
            continue;
        }
        var rowStyle = (counter++ % 2 == 0) ? 'odd' : 'even';
        grid.push([
            {text: item.product_key, style:['invoiceNumber', 'productKey', rowStyle, 'firstColumn']},
            {text: item.custom_value1 && item.custom_value1 != '0000-00-00' ? moment(item.custom_value1).format(invoice.account.date_format ? invoice.account.date_format.format_moment : 'MMM D, YYYY') : ' ', style:['invoiceDate', rowStyle]},
            {text: item.custom_value2 && item.custom_value2 != '0000-00-00' ? moment(item.custom_value2).format(invoice.account.date_format ? invoice.account.date_format.format_moment : 'MMM D, YYYY') : ' ', style:['dueDate', rowStyle]},
            {text: formatMoneyInvoice(item.notes, invoice), style:['subtotals', rowStyle]},
            {text: formatMoneyInvoice(item.cost, invoice), style:['lineTotal', rowStyle, 'lastColumn']},
        ]);
    }

    return grid;
}

NINJA.statementPayments = function(invoice) {
    var grid = [[]];

    grid[0].push({text: invoiceLabels.invoice_number, style: ['tableHeader', 'itemTableHeader', 'firstColumn']});
    grid[0].push({text: invoiceLabels.payment_date, style: ['tableHeader', 'invoiceDateTableHeader']});
    grid[0].push({text: invoiceLabels.method, style: ['tableHeader', 'dueDateTableHeader']});
    //grid[0].push({text: invoiceLabels.reference, style: ['tableHeader', 'totalTableHeader']});
    grid[0].push({text: invoiceLabels.amount, style: ['tableHeader', 'balanceTableHeader', 'lastColumn']});

    var counter = 0;
    for (var i = 0; i < invoice.invoice_items.length; i++) {
        var item = invoice.invoice_items[i];
        if (item.invoice_item_type_id != 3) {
            continue;
        }
        var rowStyle = (counter++ % 2 == 0) ? 'odd' : 'even';
        grid.push([
            {text: item.product_key, style:['invoiceNumber', 'productKey', rowStyle, 'firstColumn']},
            {text: item.custom_value1 && item.custom_value1 != '0000-00-00' ? moment(item.custom_value1).format(invoice.account.date_format ? invoice.account.date_format.format_moment : 'MMM D, YYYY') : ' ', style:['invoiceDate', rowStyle]},
            {text: item.custom_value2 ? item.custom_value2 : ' ', style:['dueDate', rowStyle]},
            //{text: item.transaction_reference, style:['subtotals', rowStyle]},
            {text: formatMoneyInvoice(item.cost, invoice), style:['lineTotal', rowStyle, 'lastColumn']},
        ]);
    }

    return grid;
}
NINJA.statementAging = function(invoice) {
    var grid = [[]];

    grid[0].push({text: '0 - 30', style: ['tableHeader', 'alignRight', 'firstColumn']});
    grid[0].push({text: '30 - 60', style: ['tableHeader', 'alignRight']});
    grid[0].push({text: '60 - 90', style: ['tableHeader', 'alignRight']});
    grid[0].push({text: '90 - 120', style: ['tableHeader', 'alignRight']});
    grid[0].push({text: '120+', style: ['tableHeader', 'alignRight', 'lastColumn']});

    for (var i = 0; i < invoice.invoice_items.length; i++) {
        var item = invoice.invoice_items[i];
        if (item.invoice_item_type_id != 4) {
            continue;
        }
        grid.push([
            {text: formatMoneyInvoice(item.product_key, invoice), style:['subtotals', 'odd', 'firstColumn']},
            {text: formatMoneyInvoice(item.notes, invoice), style:['subtotals', 'odd']},
            {text: formatMoneyInvoice(item.custom_value1, invoice), style:['subtotals', 'odd']},
            {text: formatMoneyInvoice(item.custom_value2, invoice), style:['subtotals', 'odd']},
            {text: formatMoneyInvoice(item.cost, invoice), style:['subtotals', 'odd', 'lastColumn']},
        ]);
    }

    return grid;
}

NINJA.signature = function(invoice) {
    var invitation = NINJA.getSignatureInvitation(invoice);
    if (invitation) {
        return {
            "stack": [
                {
                    "image": "$signatureBase64",
                    "margin": [200, 10, 0, 0]
                },
                {
                    "canvas": [{
                        "type": "line",
                        "x1": 200,
                        "y1": -25,
                        "x2": 504,
                        "y2": -25,
                        "lineWidth": 1,
                        "lineColor": "#888888"
                    }]
                },
                {
                    "text": [invoiceLabels.date, ": ", "$signatureDate"],
                    "margin": [200, -20, 0, 0]
                }
            ]
        };
    } else {
        return '';
    }
}

NINJA.signatureImage = function(invoice) {
    var blankImage = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVQYV2NgYAAAAAMAAWgmWQ0AAAAASUVORK5CYII=';
    var invitation = NINJA.getSignatureInvitation(invoice);
    return invitation ? invitation.signature_base64 : blankImage;
}

NINJA.signatureDate = function(invoice) {
    var invitation = NINJA.getSignatureInvitation(invoice);
    return invitation ? NINJA.formatDateTime(invitation.signature_date, invoice.account) : '';
}

NINJA.getSignatureInvitation = function(invoice) {
    if (! invoice.invitations || ! invoice.invitations.length) {
        return false;
    }

    if (! parseInt(invoice.account.signature_on_pdf)) {
        return false;
    }

    for (var i=0; i<invoice.invitations.length; i++) {
        var invitation = invoice.invitations[i];
        if (invitation.signature_base64) {
            return invitation;
        }
    }

    return false;
}

NINJA.formatDateTime = function(date, account) {
    var format = account.datetime_format ? account.datetime_format.format_moment : 'LLL';
    var timezone = account.timezone ? account.timezone.name : 'US/Eastern';

    return date ? moment.utc(date).tz(timezone).format(format) : '';
}

NINJA.entityType = function(invoice)
{
    if (invoice.is_delivery_note) {
        return invoiceLabels.delivery_note;
    } else if (invoice.is_statement) {
        return invoiceLabels.statement;
    } else if (invoice.is_quote) {
        return invoiceLabels.quote;
    } else if (invoice.balance_amount < 0) {
        return invoiceLabels.credit_note;
    } else {
        return invoiceLabels.invoice;
    }
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

NINJA.invoiceColumns = function(invoice, design, isTasks)
{
    var account = invoice.account;
    var columns = [];
    var fields = NINJA.productFields(invoice, isTasks);
    var hasDescription = fields.indexOf('product.description') >= 0;
    var hasPadding = design.indexOf('"pageMargins":[0') == -1 && design.indexOf('"pageMargins": [0') == -1;

    for (var i=0; i<fields.length; i++) {
        var field = fields[i];
        var width = 0;

        if (invoice.is_delivery_note) {
            var skipFields = [
                'product.unit_cost',
                'product.rate',
                'product.tax',
                'product.line_total',
            ];
            if (skipFields.indexOf(field) >= 0) {
                continue;
            }
        }

        if (field == 'product.custom_value1') {
            if (invoice.has_custom_item_value1) {
                width = 10;
            } else {
                continue;
            }
        } else if (field == 'product.custom_value2') {
            if (invoice.has_custom_item_value2) {
                width = 10;
            } else {
                continue;
            }
        } else if (field == 'product.tax') {
            if (invoice.has_item_taxes) {
                width = 15;
            } else {
                continue;
            }
        } else if (field == 'product.discount') {
            if (invoice.has_item_discounts) {
                width = 15;
            } else {
                continue;
            }
        } else if (field == 'product.description') {
            width = 0;
        } else {
            width = 14;
        }

        if (width) {
            // make the first and last columns of the Bold design a bit wider
            if (! hasPadding) {
                if (i == 0 || i == fields.length - 1) {
                    width += 8;
                }
            }
            if (! hasDescription) {
                width = '*';
            } else {
                width += '%';
            }
        } else {
            width = '*';
        }

        columns.push(width)
    }

    //console.log(columns);
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
    var fields = NINJA.productFields(invoice);
    return fields.indexOf('product.quantity') >= 0 ? '"14%", ' : '';
}

NINJA.taxWidth = function(invoice)
{
    var fields = NINJA.productFields(invoice);
    return invoice.has_item_taxes && fields.indexOf('product.tax') >= 0 ? '"14%", ' : '';
}

NINJA.productFields = function(invoice, isTasks) {
    var account = invoice.account;
    var allFields = JSON.parse(account.invoice_fields);

    if (allFields) {
        if (isTasks && allFields.task_fields && allFields.task_fields.length) {
            return allFields.task_fields;
        } else if (! isTasks && allFields.product_fields && allFields.product_fields.length) {
            return allFields.product_fields;
        }
    }

    var fields = [
        isTasks ? 'product.service' : 'product.item',
        'product.description',
        'product.custom_value1',
        'product.custom_value2',
        isTasks ? 'product.rate' : 'product.unit_cost',
        isTasks ? 'product.hours' : 'product.quantity',
        'product.tax',
        'product.line_total',
    ];

    // add backwards compatibility for 'hide qty' setting
    if (invoice.account.hide_quantity == '1' && ! isTasks) {
        fields.splice(5, 1);
    }

    return fields;
}

NINJA.invoiceLines = function(invoice, isSecondTable) {
    var account = invoice.account;
    var total = 0;
    var shownItem = false;
    var isTasks = isSecondTable || (invoice.hasTasks && !invoice.hasStandard);
    var grid = [[]];
    var styles = ['tableHeader'];
    var skipFields = [
        'product.unit_cost',
        'product.rate',
        'product.tax',
        'product.line_total',
        'product.discount',
    ];

    if (isSecondTable && invoice.hasStandard) {
        styles.push('secondTableHeader');
    }

    var fields = NINJA.productFields(invoice, isTasks);
    var hasDescription = fields.indexOf('product.description') >= 0;

    for (var i=0; i<fields.length; i++) {
        var field = fields[i].split('.')[1]; // split to remove 'product.'

        if (invoice.is_delivery_note && skipFields.indexOf(fields[i]) >= 0) {
            continue;
        }

        var headerStyles = styles.concat([snakeToCamel(field), snakeToCamel(field) + 'TableHeader']);
        var value = invoiceLabels[field];

        if (field == 'custom_value1') {
            if (invoice.has_custom_item_value1) {
                value = NINJA.getCustomLabel(account.custom_fields.product1);
            } else {
                continue;
            }
        } else if (field == 'custom_value2') {
            if (invoice.has_custom_item_value2) {
                value = NINJA.getCustomLabel(account.custom_fields.product2);
            } else {
                continue;
            }
        } else if (field == 'tax' && ! invoice.has_item_taxes) {
            continue;
        } else if (field == 'discount' && ! invoice.has_item_discounts) {
            continue;
        } else if (field == 'unit_cost' || field == 'rate' || field == 'hours') {
            headerStyles.push('cost');
        }

        if (i == 0) {
            headerStyles.push('firstColumn');
        } else if (i == fields.length - 1) {
            headerStyles.push('lastColumn');
        }

        grid[0].push({text: value, style: headerStyles});
    }

    for (var i=0; i<invoice.invoice_items.length; i++) {
        var row = [];
        var item = invoice.invoice_items[i];
        var cost = NINJA.parseFloat(item.cost) ? formatMoneyInvoice(NINJA.parseFloat(item.cost), invoice, null, getPrecision(NINJA.parseFloat(item.cost))) : ' ';
        var qty = NINJA.parseFloat(item.qty) ? formatMoneyInvoice(NINJA.parseFloat(item.qty), invoice, 'none', getPrecision(NINJA.parseFloat(item.qty))) + '' : ' ';
        var discount = roundToTwo(NINJA.parseFloat(item.discount));
        var notes = item.notes;
        var productKey = item.product_key;
        var tax1 = '';
        var tax2 = '';
        var customValue1 = item.custom_value1;
        var customValue2 = item.custom_value2;

        if (isTasks) {
            if (item.invoice_item_type_id != 2) {
                continue;
            }
        } else {
            if (item.invoice_item_type_id == 2) {
                continue;
            }
        }

        if (parseFloat(item.tax_rate1) != 0) {
            tax1 = parseFloat(item.tax_rate1);
        }
        if (parseFloat(item.tax_rate2) != 0) {
            tax2 = parseFloat(item.tax_rate2);
        }

        // show at most one blank line
        if (shownItem && !notes && !productKey && !item.cost) {
            continue;
        }

        shownItem = true;

        // process date variables
        if (invoice.is_recurring) {
            notes = processVariables(notes);
            productKey = processVariables(productKey);
            customValue1 = processVariables(item.custom_value1);
            customValue2 = processVariables(item.custom_value2);
        }

        var lineTotal = roundSignificant(NINJA.parseFloat(item.cost) * NINJA.parseFloat(item.qty));

        if (discount != 0) {
            if (parseInt(invoice.is_amount_discount)) {
                lineTotal -= discount;
            } else {
                lineTotal -= (lineTotal * discount / 100);
            }
        }

        if (account.include_item_taxes_inline == '1'  && account.inclusive_taxes != '1') {
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

        if (lineTotal != 0) {
            lineTotal = formatMoneyInvoice(lineTotal, invoice);
        }
        rowStyle = (grid.length % 2 == 0) ? 'even' : 'odd';

        for (var j=0; j<fields.length; j++) {
            var field = fields[j].split('.')[1]; // split to remove 'product.'

            if (invoice.is_delivery_note && skipFields.indexOf(fields[j]) >= 0) {
                continue;
            }

            var value = item[field];
            var styles = [snakeToCamel(field), rowStyle];

            if (field == 'custom_value1' && ! invoice.has_custom_item_value1) {
                continue;
            } else if (field == 'custom_value2' && ! invoice.has_custom_item_value2) {
                continue;
            } else if (field == 'tax' && ! invoice.has_item_taxes) {
                continue;
            } else if (field == 'discount' && ! invoice.has_item_discounts) {
                continue;
            }

            if (field == 'item' || field == 'service') {
                value = productKey;
                styles.push('productKey');
            } else if (field == 'description') {
                value = notes;
            } else if (field == 'unit_cost' || field == 'rate') {
                value = cost;
                styles.push('cost');
            } else if (field == 'quantity' || field == 'hours') {
                value = qty;
                if (field == 'hours') {
                    styles.push('cost');
                }
            } else if (field == 'custom_value1') {
                value = customValue1;
            } else if (field == 'custom_value2') {
                value = customValue2;
            } else if (field == 'discount') {
                if (NINJA.parseFloat(discount)) {
                    if (parseInt(invoice.is_amount_discount)) {
                        value = formatMoneyInvoice(discount, invoice);
                    } else {
                        if (discount) {
                            value = discount + '%';
                        }
                    }
                } else {
                    value = '';
                }
            } else if (field == 'tax') {
                value = ' ';
                if (item.tax_name1) {
                    value += (tax1 || '0') + '%';
                }
                if (item.tax_name2) {
                    if (item.tax_name1) {
                        value += '  ';
                    }
                    value += (tax2 || '0') + '%';
                }
            } else if (field == 'line_total') {
                value = lineTotal;
            }

            if (j == 0) {
                styles.push('firstColumn');
            } else if (j == fields.length - 1) {
                styles.push('lastColumn');
            }

            row.push({text:value || ' ', style:styles});
        }

        grid.push(row);
    }

    //console.log(JSON.stringify(grid));
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

NINJA.subtotals = function(invoice, hideBalance)
{
    if (! invoice || invoice.is_delivery_note) {
        return [[]];
    }

    var account = invoice.account;
    var data = [];
    data.push([{text: invoiceLabels.subtotal, style: ['subtotalsLabel', 'subtotalLabel']}, {text: formatMoneyInvoice(invoice.subtotal_amount, invoice), style: ['subtotals', 'subtotal']}]);

    if (invoice.discount_amount != 0) {
        data.push([{text: invoiceLabels.discount , style: ['subtotalsLabel', 'discountLabel']}, {text: formatMoneyInvoice(invoice.discount_amount, invoice), style: ['subtotals', 'discount']}]);
    }

    var customValue1 = NINJA.parseFloat(invoice.custom_value1);
    var customValue1Label = account.custom_fields.invoice1 || invoiceLabels.surcharge;

    var customValue2 = NINJA.parseFloat(invoice.custom_value2);
    var customValue2Label = account.custom_fields.invoice2 || invoiceLabels.surcharge;

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

    if (parseFloat(invoice.tax_rate1 || 0) != 0 || invoice.tax_name1) {
        var taxStr = invoice.tax_name1 + ' ' + (invoice.tax_rate1*1).toString() + '%';
        data.push([{text: taxStr, style: ['subtotalsLabel', 'tax1Label']}, {text: formatMoneyInvoice(invoice.tax_amount1, invoice), style: ['subtotals', 'tax1']}]);
    }
    if (parseFloat(invoice.tax_rate2 || 0) != 0 || invoice.tax_name2) {
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
    if (invoice.is_delivery_note) {
        return [[]];
    }

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
    if (invoice.is_delivery_note) {
        var skipFields = [
            'invoice.due_date',
            'invoice.balance_due',
            'invoice.partial_due',
        ];
        if (skipFields.indexOf(field) >= 0) {
            return false;
        }
    }

    var client = invoice.client;
    if (!client) {
        return false;
    }
    var account = invoice.account;
    var contact = invoice.contact || client.contacts[0];
    var clientName = client.name || (contact.first_name || contact.last_name ? ((contact.first_name || '') + ' ' + (contact.last_name || '')) : contact.email);

    var label = false;
    var value = false;

    if (field == 'client.client_name') {
        value = clientName || ' ';
    } else if (field == 'client.contact_name') {
        value = (contact.first_name || contact.last_name) ? (contact.first_name || '') + ' ' + (contact.last_name || '') : false;
    } else if (field == 'client.id_number') {
        value = client.id_number;
        if (invoiceLabels.id_number_orig) {
            label = invoiceLabels.id_number;
        }
    } else if (field == 'client.vat_number') {
        value = client.vat_number;
        if (invoiceLabels.vat_number_orig) {
            label = invoiceLabels.vat_number;
        }
    } else if (field == 'client.address1') {
        if (invoice.is_delivery_note && client.shipping_address1) {
            value = client.shipping_address1;
        } else {
            value = client.address1;
        }
    } else if (field == 'client.address2') {
        if (invoice.is_delivery_note && client.shipping_address1) {
            value = client.shipping_address2;
        } else {
            value = client.address2;
        }
    } else if (field == 'client.city_state_postal') {
        var cityStatePostal = '';
        if (invoice.is_delivery_note && client.shipping_address1) {
            if (client.shipping_city || client.shipping_state || client.shipping_postal_code) {
                var swap = client.shipping_country && client.shipping_country.swap_postal_code;
                cityStatePostal = formatAddress(client.shipping_city, client.shipping_state, client.shipping_postal_code, swap);
            }
        } else {
            if (client.city || client.state || client.postal_code) {
                var swap = client.country && client.country.swap_postal_code;
                cityStatePostal = formatAddress(client.city, client.state, client.postal_code, swap);
            }
        }
        value = cityStatePostal;
    } else if (field == 'client.postal_city_state') {
        var postalCityState = '';
        if (invoice.is_delivery_note && client.shipping_address1) {
            if (client.shipping_city || client.shipping_state || client.shipping_postal_code) {
                postalCityState = formatAddress(client.shipping_city, client.shipping_state, client.shipping_postal_code, true);
            }
        } else {
            if (client.city || client.state || client.postal_code) {
                postalCityState = formatAddress(client.city, client.state, client.postal_code, true);
            }
        }
        value = postalCityState;
    } else if (field == 'client.country') {
        if (invoice.is_delivery_note && client.shipping_address1) {
            value = client.shipping_country ? client.shipping_country.name : '';
        } else {
            value = client.country ? client.country.name : '';
        }
    } else if (field == 'client.website') {
        value = client.website;
    } else if (field == 'client.email') {
        value = contact.email == clientName ? '' : contact.email;
    } else if (field == 'client.phone') {
        value = contact.phone;
    } else if (field == 'client.work_phone') {
        value = client.work_phone;
    } else if (field == 'client.custom_value1') {
        if (account.custom_fields.client1 && client.custom_value1) {
            label = NINJA.getCustomLabel(account.custom_fields.client1);
            value = client.custom_value1;
        }
    } else if (field == 'client.custom_value2') {
        if (account.custom_fields.client2 && client.custom_value2) {
            label = NINJA.getCustomLabel(account.custom_fields.client2);
            value = client.custom_value2;
        }
    } else if (field == 'contact.custom_value1') {
        if (account.custom_fields.contact1 && contact.custom_value1) {
            label = NINJA.getCustomLabel(account.custom_fields.contact1);
            value = contact.custom_value1;
        }
    } else if (field == 'contact.custom_value2') {
        if (account.custom_fields.contact2 && contact.custom_value2) {
            label = NINJA.getCustomLabel(account.custom_fields.contact2);
            value = contact.custom_value2;
        }
    } else if (field == 'account.company_name') {
        value = account.name + ' ';
    } else if (field == 'account.id_number') {
        value = account.id_number;
        if (invoiceLabels.id_number_orig) {
            label = invoiceLabels.id_number;
        }
    } else if (field == 'account.vat_number') {
        value = account.vat_number;
        if (invoiceLabels.vat_number_orig) {
            label = invoiceLabels.vat_number;
        }
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
        if (invoice.account.custom_fields.account1 && invoice.account.custom_value1) {
            label = invoice.account.custom_fields.account1;
            value = invoice.account.custom_value1;
        }
    } else if (field == 'account.custom_value2') {
        if (invoice.account.custom_fields.account2 && invoice.account.custom_value2) {
            label = invoice.account.custom_fields.account2;
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
        if (invoice.partial_due_date) {
            value = invoice.partial_due_date;
        } else {
            value = invoice.due_date;
        }
    } else if (field == 'invoice.custom_text_value1') {
        if (invoice.custom_text_value1 && account.custom_fields.invoice_text1) {
            label = NINJA.getCustomLabel(invoice.account.custom_fields.invoice_text1);
            value = invoice.is_recurring ? processVariables(invoice.custom_text_value1) : invoice.custom_text_value1;
        }
    } else if (field == 'invoice.custom_text_value2') {
        if (invoice.custom_text_value2 && account.custom_fields.invoice_text2) {
            label = NINJA.getCustomLabel(invoice.account.custom_fields.invoice_text2);
            value = invoice.is_recurring ? processVariables(invoice.custom_text_value2) : invoice.custom_text_value2;
        }
    } else if (field == 'invoice.balance_due') {
        label = invoice.is_quote || invoice.balance_amount < 0 ? invoiceLabels.total : invoiceLabels.balance_due;
        value = formatMoneyInvoice(invoice.total_amount, invoice);
    } else if (field == 'invoice.partial_due') {
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
    if (! item.style) {
        item.style = [];
    }

    item.style.push(section);

    // make sure numbers aren't wrapped
    if (item.text && item.length && item.length < 20 && item.text.match && item.text.match(/\d[.,]\d\d($| [A-Z]{3}$)/)) {
        item.style.push('noWrap');
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
        ['^#(.*)', {'style': 'fullheader'}] // # Subheader
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
    var lastIndex = -1;

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

NINJA.getCustomLabel = function(value) {
    if (value && value.indexOf('|') > 0) {
        return value.split('|')[0];
    } else {
        return value;
    }
}
