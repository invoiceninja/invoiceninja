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
      margin: [8, 70, 8, 16],
      style: 'primaryColor',
      fontSize: 11
    },
    {
      table: {
        headerRows: 1,
        widths: ['auto', 'auto', '*'],
        body: [[
            {
                table: { 
                    body: invoiceDetails(invoice),
                },
                layout: 'noBorders',                    
            }, 
            clientDetails(invoice), 
            ''
        ]]
      },
      layout: {
        hLineWidth: function (i, node) {
          return (i === 0 || i === node.table.body.length) ? .5 : 0;
        },
        vLineWidth: function (i, node) {
          return 0;
        },
        hLineColor: function (i, node) {
          return '#D8D8D8';
        },
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
        widths: ['15%', '*', 'auto', 'auto', 'auto', 'auto'],
        body:invoiceLines(invoice),
      },
      layout: {
        hLineWidth: function (i, node) {
          return i === 0 ? 0 : .5;
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
  
  defaultStyle: {
    //font: 'Roboto',
    fontSize: 9,
    margin: [8, 4, 8, 4]
  },
  styles: {
    primaryColor:{
      color: primaryColor('#299CC2')
    },
    accountName: {
      margin: [4, 2, 4, 2],
      color:primaryColor('#299CC2') 
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
  }  
};