function GetPdfMake(invoice, javascript, callback) {
  var account = invoice.account;
  eval(javascript);
  doc = pdfMake.createPdf(dd);
  doc.getDataUrl(callback);

  return;
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
