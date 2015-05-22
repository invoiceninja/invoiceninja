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
            stack: NINJA.accountDetails(account)
        },
        {
            stack: NINJA.accountAddress(account)
        }
        ]
    },
    {
        text:(NINJA.getEntityLabel(invoice)).toUpperCase(),
        margin: [8, 70, 8, 16],
        style: 'primaryColor',
        fontSize: NINJA.fontSize + 2
    },
    {
        table: {
            headerRows: 1,
            widths: ['auto', 'auto', '*'],
            body: [
                [
                {
                    table: { 
                        body: NINJA.invoiceDetails(invoice),
                    },
                    layout: 'noBorders',
                }, 
                {
                    table: { 
                        body: NINJA.clientDetails(invoice), 
                    },
                    layout: 'noBorders',
                },                 
                ''
                ]
            ]
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
            body: NINJA.invoiceLines(invoice),
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
        NINJA.notesAndTerms(invoice),
        {
            style: 'subtotals',
            table: {
                widths: ['*', '*'],
                body: NINJA.subtotals(invoice),
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

    defaultStyle: {
        fontSize: NINJA.fontSize,
        margin: [8, 4, 8, 4]
    },
    styles: {
        primaryColor:{
            color: NINJA.getPrimaryColor('#299CC2')
        },
        accountName: {
            margin: [4, 2, 4, 2],
            color: NINJA.getPrimaryColor('#299CC2') 
        },
        accountDetails: {
            margin: [4, 2, 4, 2],
            color: '#AAA9A9'
        },
        even: {
        },
        odd: {
            fillColor:'#F4F4F4'
        },
        productKey: {
            color: NINJA.getPrimaryColor('#299CC2')      
        },
        tableHeader: {
            bold: true
        },
        balanceDueLabel: {
            fontSize: NINJA.fontSize + 2 
        },
        balanceDueValue: {
            fontSize: NINJA.fontSize + 2,
            color: NINJA.getPrimaryColor('#299CC2')      
        },
    },
    pageMargins: [40, 40, 40, 40],
};