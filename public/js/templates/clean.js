{
    "content": [{
        "columns": [
            {
                "image": "$accountLogo",
                "width": 100
            },
            {
                "stack": "$accountDetails",
                "margin": [80, 0, 0, 0]
            },
            {
                "stack": "$accountAddress"
            }
        ]
    },
    {
        "text": "$entityTypeUpper",
        "margin": [8, 50, 8, 5],
        "style": "entityTypeLabel"
        
    },
    {
        "table": {
            "headerRows": 1,
            "widths": ["auto", "auto", "*"],
            "body": [
                [
                {
                    "table": { 
                        "body": "$invoiceDetails"
                    },
                    "margin": [0, 4, 12, 4],            
                    "layout": "noBorders"
                }, 
                {
                    "stack": "$clientDetails"
                },
                {
                    "text": ""
                }
                ]
            ]
        },
        "layout": {
            "hLineWidth": "$borderTopAndBottom:.5",
            "vLineWidth": "$borderNone",
            "hLineColor": "#D8D8D8",
            "paddingLeft": "$padding:8", 
            "paddingRight": "$padding:8", 
            "paddingTop": "$padding:4", 
            "paddingBottom": "$padding:4"            
        }
    },
    {
        "style": "invoiceLineItemsTable",
        "table": {
            "headerRows": 1,
            "widths": "$invoiceLineItemColumns",
            "body": "$invoiceLineItems"
        },
        "layout": {
            "hLineWidth": "$borderNotTop:.5",
            "vLineWidth": "$borderNone",
            "hLineColor": "#D8D8D8",
            "paddingLeft": "$padding:8", 
            "paddingRight": "$padding:8", 
            "paddingTop": "$padding:14", 
            "paddingBottom": "$padding:14"            
        }
    },
    {
        "columns": [
            "$notesAndTerms",
            {
                "table": {
                    "widths": ["*", "*"],
                    "body": "$subtotals"
                },
                "layout": {
                    "hLineWidth": "$borderNone",
                    "vLineWidth": "$borderNone",
                    "paddingLeft": "$padding:8", 
                    "paddingRight": "$padding:8", 
                    "paddingTop": "$padding:4", 
                    "paddingBottom": "$padding:4" 
                }
            }
        ]
    }
    ],
    "defaultStyle": {
        "fontSize": "$fontSize",
        "margin": [8, 4, 8, 4]
    },
    "footer": {
        "columns": [
            {
                "text": "$invoiceFooter",
                "alignment": "left",
                "margin": [0, 0, 0, 12]

            }
        ],
        "margin": [40, -20, 40, 40]
    },
    "styles": {
        "entityTypeLabel": {
            "fontSize": "$fontSizeLargest",
            "color": "$primaryColor:#37a3c6"
        },
        "primaryColor":{
            "color": "$primaryColor:#37a3c6"
        },
        "accountName": {
            "color": "$primaryColor:#37a3c6",
            "bold": true
        },
        "accountDetails": {
            "margin": [0, 2, 0, 2]
        },
        "clientDetails": {
            "margin": [0, 2, 0, 2]
        },
        "notesAndTerms": {
            "margin": [0, 2, 0, 2]
        },
        "accountAddress": {
            "margin": [0, 2, 0, 2]
        },
        "odd": {
            "fillColor": "#fbfbfb"
        },
        "productKey": {
            "color": "$primaryColor:#37a3c6",
            "bold": true
        },
        "balanceDueLabel": {
            "fontSize": "$fontSizeLargest"
        },
        "balanceDue": {
            "fontSize": "$fontSizeLargest",
            "color": "$primaryColor:#37a3c6"
        },  
        "invoiceNumber": {
            "bold": true
        },
        "tableHeader": {
            "bold": true,
            "fontSize": "$fontSizeLargest"
        },
        "invoiceLineItemsTable": {
            "margin": [0, 16, 0, 16]
        },
        "clientName": {
            "bold": true
        },
        "cost": {
            "alignment": "right"
        },
        "quantity": {
            "alignment": "right"
        },
        "tax": {
            "alignment": "right"
        },
        "lineTotal": {
            "alignment": "right"
        },
        "subtotals": {
            "alignment": "right"
        },            
        "termsLabel": {
            "bold": true,
            "margin": [0, 0, 0, 4]
        }           
    },
    "pageMargins": [40, 40, 40, 60]
}