function GetPdfMake(invoice, javascript, callback) {
  var account = invoice.account;
  eval(javascript);
  var fonts = {
        Roboto: {
                normal: 'Roboto-Regular.ttf',
                bold: 'Roboto-Medium.ttf',
                italics: 'Roboto-Italic.ttf',
                bolditalics: 'Roboto-Italic.ttf'
        }
    };

  doc = pdfMake.createPdf(dd);
  doc.save = function(fileName) {
    this.download(fileName);
  };
  return doc;
}
function notesAndTerms(invoice)
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

function invoiceLines(invoice) {
  var grid = 
          [[{text: invoiceLabels.item, style: 'tableHeader'}, 
            {text: invoiceLabels.description, style: 'tableHeader'}, 
            {text: invoiceLabels.unit_cost, style: 'tableHeader'}, 
            {text: invoiceLabels.quantity, style: 'tableHeader'}, 
            {text: invoice.has_taxes?invoiceLabels.tax:'', style: 'tableHeader'}, 
            {text: invoiceLabels.line_total, style: 'tableHeader'}]];
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

function subtotals(invoice)
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
 
 function accountDetails(account) {
  var data = [];
  if(account.name) data.push({text:account.name, style:'accountDetails'});
  if(account.id_number) data.push({text:account.id_number, style:'accountDetails'});
  if(account.vat_number) data.push({text:account.vat_number, style:'accountDetails'});
  if(account.work_email) data.push({text:account.work_email, style:'accountDetails'});
  if(account.work_phone) data.push({text:account.work_phone, style:'accountDetails'});
  return data;
}

function accountAddress(account) {
  var data = [];
  if(account.address1) data.push({text:account.address1, style:'accountDetails'});
  if(account.address2) data.push({text:account.address2, style:'accountDetails'});
  if(account.city) data.push({text:account.city, style:'accountDetails'});
  if(account.state) data.push({text:account.state, style:'accountDetails'});
  if(account.postal_code) data.push({text:account.postal_code, style:'accountDetails'});
  return data;
}

function primaryColor( defaultColor) {
  return NINJA.primaryColor?NINJA.primaryColor:defaultColor;
}

function secondaryColor( defaultColor) {
  return NINJA.primaryColor?NINJA.secondaryColor:defaultColor;
}