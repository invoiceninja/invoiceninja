//pdfmake
var templateFonts = {
	sans: {
		normal: 'FreeSans.ttf',
		bold: 'FreeSansBold.ttf',
		italics: 'FreeSansOblique.ttf',
		bolditalics: 'FreeSansBoldOblique.ttf'
	}
};
window.pdfMake.fonts = templateFonts;
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
    {
      style: 'tableExample',
      table: {
        headerRows: 1,
        widths: ['auto', 'auto', '*'],
        body: [
          ['Invoice Number', {style: 'bold', text: invoice.invoice_number}, ""],
          ['Invoice Date', invoice.invoice_date, ""],
          ['Balance Due', formatMoney(invoice.balance_amount, invoice.client.currency_id), ""],
        ]
      },
      layout: {
        hLineWidth: function (i, node) {
          return (i === 0 || i === node.table.body.length) ? 1 : 0;
        },
        vLineWidth: function (i, node) {
          return 0;//(i === 0 || i === node.table.widths.length) ? 2 : 1;
        },
        hLineColor: function (i, node) {
          return '#D8D8D8';//(i === 0 || i === node.table.body.length) ? 'black' : 'gray';
        },
        /*vLineColor: function (i, node) {
          return (i === 0 || i === node.table.widths.length) ? 'black' : 'gray';
        },*/
        // paddingLeft: function(i, node) { return 4; },
        // paddingRight: function(i, node) { return 4; },
        // paddingTop: function(i, node) { return 2; },
        // paddingBottom: function(i, node) { return 2; }
      }
    },
    '\n',
    {
      style: 'tableExample',
      table: {
        headerRows: 1,
        widths: ['auto', '*', 'auto', 'auto', 'auto', 'auto'],
        body:invoiceLines(invoice),
        /*body: [
          [{text: 'Item', style: 'tableHeader'}, 
            {text: 'Description', style: 'tableHeader'}, 
            {text: 'Unit Cost', style: 'tableHeader'}, 
            {text: 'Quantity', style: 'tableHeader'}, 
            {text: invoice.has_taxes?'Tax':'', style: 'tableHeader'}, 
            {text: 'Line Total', style: 'tableHeader'}]
          /*['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', invoice.has_taxes?'Sample value 2':'','Sample value 3'],
          ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', invoice.has_taxes?'Sample value 2':'','Sample value 3'],
          ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', invoice.has_taxes?'Sample value 2':'','Sample value 3'],
          ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', invoice.has_taxes?'Sample value 2':'','Sample value 3'],
          ['Sample value 1', 'Sample value 2', 'Sample value 3', 'Sample value 2', invoice.has_taxes?'Sample value 2':'','Sample value 3']*
        ].push(invoiceLines(invoice))*/
      },
      layout: {
        hLineWidth: function (i, node) {
          return i === 0 ? 0 : 1;
        },
        vLineWidth: function (i, node) {
          return 0;
        },
        hLineColor: function (i, node) {
          return '#D8D8D8';
        }
      },
    }
  ],
  defaultStyle: {
    font: 'sans'
  },
  styles: {
    bold: {
      bold: true
    },
    even: {
    },
    odd: {
      fillColor:'#F4F4F4'
    },
    cost: {
      alignment: 'right'
    }

  }
};