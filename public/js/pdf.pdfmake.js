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
   //grid.push(['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', invoice.has_taxes?'Sample value 2':'','Sample value 3']);
   //grid.push(['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', invoice.has_taxes?'Sample value 2':'','Sample value 3']);
    
  var line = 1;
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
/*
  y = tableTop + (line * layout.tableRowHeight) + (3 * layout.tablePadding);

  if (invoice.invoice_design_id == 8) {
    doc.setDrawColor(30, 30, 30);
    doc.setLineWidth(0.5);

    var topX = tableTop - 14;
    doc.line(layout.marginLeft - 10, topX, layout.marginLeft - 10, y);
    doc.line(layout.descriptionLeft - 8, topX, layout.descriptionLeft - 8, y);
    doc.line(layout.unitCostRight - 55, topX, layout.unitCostRight - 55, y);
    doc.line(layout.qtyRight - 50, topX, layout.qtyRight - 50, y);
    if (invoice.has_taxes) {
      doc.line(layout.taxRight - 28, topX, layout.taxRight - 28, y);
    }
    doc.line(totalX - 25, topX, totalX - 25, y + 90);
    doc.line(totalX + 45, topX, totalX + 45, y + 90);
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
*/
  return grid;
}


/*
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
 
 eval(javascript);
 
 // add footer
 if (invoice.invoice_footer) {
 doc.setFontType('normal');
 doc.setFontSize('8');
 SetPdfColor(invoice.invoice_design_id == 2 || invoice.invoice_design_id == 3 ? 'White' : 'Black',doc);
 var top = doc.internal.pageSize.height - layout.marginLeft;
 var numLines = invoice.invoice_footer.split("\n").length - 1;
 doc.text(layout.marginLeft, top - (numLines * 8), invoice.invoice_footer);
 }
 
 return doc;
 }
 */
