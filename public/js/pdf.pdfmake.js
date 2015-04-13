function GetPdfMake(invoice, javascript, callback) {
  var account = invoice.account;
  eval(javascript);
  doc = pdfMake.createPdf(dd);
  doc.getDataUrl(callback);

  return;
}
function notesAndTerms(invoice)
{
  var text = [];
  if (invoice.public_notes) {
    text.push(invoice.public_notes);
  }

  if (invoice.terms) {
    text.push({text:invoiceLabels.terms, style:'bold'});
    text.push(invoice.terms);
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

