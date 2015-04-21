//pdfmake
var dd = {
  content: [
    {
      columns: [
        [
          invoice.image?
          {
              image: invoice.image,
              fit: [150, 80]
          }:""
        ],
        {
          stack: accountDetails(account)
        },
        {
          stack: accountAddress(account)
        }
      ]
    },
    {
      text:(invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice).toUpperCase(),
      margin: [8, 16, 8, 16],
      style: 'primaryColor'
    },
    {
      style: 'tableExample',
      table: {
        headerRows: 1,
        widths: ['auto', 'auto', '*'],
        body: [
          [invoice.is_quote ? invoiceLabels.quote_number:invoiceLabels.invoice_number, {style: 'bold', text: invoice.invoice_number}, ""],
          [invoice.is_quote ? invoiceLabels.quote_date:invoiceLabels.invoice_date, invoice.invoice_date, ""],
          [invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due, formatMoney(invoice.balance_amount, invoice.client.currency_id), ""],
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
        paddingLeft: function(i, node) { return 8; },
        paddingRight: function(i, node) { return 8; },
        paddingTop: function(i, node) { return 4; },
        paddingBottom: function(i, node) { return 4; }
      }
    },
    '\n',
    {
      table: {
        headerRows: 1,
        widths: ['auto', '*', 'auto', 'auto', 'auto', 'auto'],
        body:invoiceLines(invoice),
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
        },
        paddingLeft: function(i, node) { return 8; },
        paddingRight: function(i, node) { return 8; },
        paddingTop: function(i, node) { return 8; },
        paddingBottom: function(i, node) { return 8; }      
      },
    },    
    '\n',
    {
      columns: [
        notesAndTerms(invoice),
        {
          style: 'subtotals',
          table: {
            widths: ['*', '*'],
            body: subtotals(invoice),
          },
          layout: {
            hLineWidth: function (i, node) {
              return 0;
            },
            vLineWidth: function (i, node) {
              return 0;
            },
            paddingLeft: function(i, node) { return 8; },
            paddingRight: function(i, node) { return 8; },
            paddingTop: function(i, node) { return 4; },
            paddingBottom: function(i, node) { return 4; }      
          },
        }
      ]
    },
  ],

  footer: function(){
    f = [{ text:invoice.invoice_footer?invoice.invoice_footer:"", margin: [72, 0]}]
    if (!invoice.is_pro && logoImages.imageLogo1) {
      f.push({
              image: logoImages.imageLogo1,
              width: 150,
              margin: [72,0]
            });
    }
    return f;
  },
  
  defaultStyle: {
    //font: 'Roboto',
    fontSize: 9,
    margin: [8, 4, 8, 4]
  },
  styles: {
    primaryColor:{
      color: primaryColor('#299CC2')
    },
    accountDetails: {
      margin: [4, 2, 4, 2],
      color: '#AAA9A9'
    },
    bold: {
      bold: true
    },
    even: {
    },
    odd: {
      fillColor:'#F4F4F4'
    },
    productKey: {
      color:primaryColor('#299CC2')      
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
    tableHeader: {
      bold: true
    },
    balanceDueLabel: {
     fontSize: 11 
    },
    balanceDueValue: {
      fontSize: 11,
      color:primaryColor('#299CC2')      
    },
    notes: {
    },
    terms: {
      
    },
    termsLabel: {
      bold: true,
      fontSize: 10,
      margin: [0, 10, 0, 4]
    }
  },
  pageMargins: [72, 40, 40, 80]
};