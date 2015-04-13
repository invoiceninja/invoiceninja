function GetPdfMake(invoice, javascript, callback) {
//var docDefinition = { content: 'This is an sample PDF printed wwęęwith pdfMake ĄČĘĖĮŠŲŪŽąčęėįšųž€' };
//eval(javascript);

//pdfmake
var cb = callback;
var account = invoice.account;
  $.get( "../js/templates/clean.js", null, null,  'text')
  .done(function( data ) {
      //var account = invoice.account;
    eval(data);
    //var dd = {};
    doc = pdfMake.createPdf(dd);
    doc.getDataUrl(cb);
  }).fail(function(xhr, status, error) {
    console.log(error);
      var err = eval("(" + xhr.responseText + ")");
  alert(err.Message);
  });
  return;
  var dd = {
    content: [
      {
        columns: [
          {
            text: ""
          },
          {
            text: [account.name, account.id_number, account.vat_number, account.work_email, account.work_phone].join('\n')
          },
          {
            text: [
              concatStrings(account.address1, account.address2) + "\n",
              concatStrings(account.city, account.state, account.postal_code)
            ]
          }
        ]
      },
      'INVOICE',
      "-",
      {
        style: 'tableExample',
        table: {
          headerRows: 1,
          body: [
            ['Sample value 1 \n x',
              'Sample value 2 \n x',
              'Sample value 3 \n x'
            ],
          ]
        },
        layout: 'noBorders'
      },
      "-",
      'Another paragraph, this time a little bit longer to make sure, this line will be divided into at least two lines',
      {
        style: 'tableExample',
        table: {
          headerRows: 1,
          widths: ['auto', '*', 'auto', 'auto', 'auto'],
          body: [
            [{text: 'Header 1', style: 'tableHeader'}, {text: 'Header 2', style: 'tableHeader'}, {text: 'Header 3', style: 'tableHeader'}, {text: 'Header 3', style: 'tableHeader'}, {text: 'Header 3', style: 'tableHeader'}],
            ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', 'Sample value 3'],
            ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', 'Sample value 3'],
            ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', 'Sample value 3'],
            ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', 'Sample value 3'],
            ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', 'Sample value 3'],
          ]
        },
        layout: 'lightHorizontalLines'
      },
    ]
  };

  return doc;
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
