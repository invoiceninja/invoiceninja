{
    "content": [
    {
        "columns": [
        {
            "image": "$accountLogo",
            "width": 80,
            "margin": [60, -40, 0, 0]
        },
        {
            "width": 300,
            "stack": [
            {"text":"$yourInvoiceUpper", "style": "yourInvoice"},
            "$clientDetails"
            ],
            "margin": [-32, 150, 0, 0]
        },
        {
            "canvas": [
            { 
                "type": "rect", 
                "x": 0, 
                "y": 0, 
                "w": 225, 
                "h": 80,
                "r":0, 
                "lineWidth": 1,
                "color": "#36a399"
            }
            ],
            "width":10,
            "margin":[-10,150,0,0]
        },
        {	
            "table": { 
                "body": "$invoiceDetails"
            },
            "layout": "noBorders",
            "margin": [0, 160, 0, 0]
        }
        ]
    },
    {
        "style": "invoiceLineItemsTable",
        "table": {
            "headerRows": 1,
            "widths": ["15%", "*", "auto", "auto", "auto"],
            "body": "$invoiceLineItems"
        },
        "layout": {
            "hLineWidth": "$borderNone",
            "vLineWidth": "$borderNone",
            "paddingLeft": "$padding:8", 
            "paddingRight": "$padding:8", 
            "paddingTop": "$padding:14", 
            "paddingBottom": "$padding:14"            
        }
    },    
    {
        "columns": [
        {
            "width": 46,
            "text": " "
        },
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
        }]
    }
    ],
    "footer": [
        {"canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 600, "y2": 0,"lineWidth": 100,"lineColor":"#2e2b2b"}]},
        {
            "text": "$invoiceFooter",
            "margin": [40, -20, 40, 0],
            "alignment": "left",
            "color": "#FFFFFF"
        }
    ],
    "header": [
        {"canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 50, "y2":0,"lineWidth": 200,"lineColor":"#2e2b2b"}],"width":100,"margin":[0,0,0,0]},
        {"canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 150, "y2":0,"lineWidth": 60,"lineColor":"#2e2b2b"}],"width":100,"margin":[0,0,0,0]},
        {"canvas": [{ "type": "line", "x1": 149, "y1": 0, "x2": 600, "y2":0,"lineWidth": 200,"lineColor":"#2e2b2b"}],"width":10,"margin":[0,0,0,0]},
        {
            "stack": "$accountDetails",
            "margin": [380, 16, 0, 0]
        }
    ],
    "defaultStyle": {
            "fontSize": "$fontSize",
            "margin": [8, 4, 8, 4]
        },
        "styles": {
            "primaryColor":{
                "color": "$primaryColor:#299CC2"
            },
            "accountName": {
                "margin": [4, 2, 4, 2],
                "color": "$primaryColor:#299CC2"
            },
            "accountDetails": {
                "margin": [4, 2, 4, 2],
                "color": "#AAA9A9"
            },
            "odd": {
                "fillColor": "#ebebeb",
                "margin": [0,0,0,0]
            },
            "productKey": {
                "color": "$primaryColor:#299CC2"
            },
            "balanceDueLabel": {
                "fontSize": "$fontSizeLargest",
                "bold": true
            },
            "balanceDue": {
                "fontSize": "$fontSizeLargest",
                "color": "$primaryColor:#299CC2",
                "bold": true
            },
            "invoiceDetails": {
                "color": "#ffffff"
            },
            "invoiceNumber": {
                "bold": true
            },
            "itemTableHeader": {
                "margin": [40,0,0,0]
            },
            "totalTableHeader": {
                "margin": [0,0,40,0]
            },
            "tableHeader": {
                "fontSize": 12,
                "bold": true
            },
            "productKey": {
                "color": "$primaryColor:#299CC2",
                "margin": [40,0,0,0],
                "bold": true
            },
            "yourInvoice": {
                "bold": true, 
                "fontSize": 14, 
                "color": "#36a399",
                "margin": [0,0,0,8]
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
                "alignment": "right",
                "margin": [0,0,40,0]
            },
            "subtotals": {
                "alignment": "right",
                "margin": [0,0,40,0]
            },            
            "termsLabel": {
                "bold": true,
                "margin": [0, 10, 0, 4]
            }            
        },
        "pageMargins": [0, 80, 0, 40]
    }