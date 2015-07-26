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
            {"text":"$yourInvoiceLabelUC", "style": "yourInvoice"},
            "$clientDetails"
            ],
            "margin": [-32, 120, 0, 0]
        },
        {
            "canvas": [
            { 
                "type": "rect", 
                "x": 0, 
                "y": 0, 
                "w": 225, 
                "h": "$invoiceDetailsHeight",
                "r":0, 
                "lineWidth": 1,
                "color": "$primaryColor:#36a498"
            }
            ],
            "width":10,
            "margin":[-10,120,0,0]
        },
        {	
            "table": { 
                "body": "$invoiceDetails"
            },
            "layout": "noBorders",
            "margin": [0, 130, 0, 0]
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
            "hLineWidth": "$none",
            "vLineWidth": "$none",
            "paddingLeft": "$amount:8", 
            "paddingRight": "$amount:8", 
            "paddingTop": "$amount:14", 
            "paddingBottom": "$amount:14"            
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
                "hLineWidth": "$none",
                "vLineWidth": "$none",
                "paddingLeft": "$amount:8", 
                "paddingRight": "$amount:8", 
                "paddingTop": "$amount:4", 
                "paddingBottom": "$amount:4"  
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
            "columns": [
                {
                    "text": " ",
                    "width": 260
                },
                {
                    "stack": "$accountDetails",
                    "margin": [0, 16, 0, 0],
                    "width": 140
                },
                {
                    "stack": "$accountAddress",
                    "margin": [20, 16, 0, 0]
                }
            ]
        }
    ],
    "defaultStyle": {
            "fontSize": "$fontSize",
            "margin": [8, 4, 8, 4]
        },
        "styles": {
            "primaryColor":{
                "color": "$primaryColor:#36a498"
            },
            "accountName": {
                "bold": true,
                "margin": [4, 2, 4, 2],
                "color": "$primaryColor:#36a498"
            },
            "accountDetails": {
                "margin": [4, 2, 4, 2],
                "color": "#AAA9A9"
            },
            "accountAddress": {
                "margin": [4, 2, 4, 2],
                "color": "#AAA9A9"
            },
            "odd": {
                "fillColor": "#ebebeb",
                "margin": [0,0,0,0]
            },
            "productKey": {
                "color": "$primaryColor:#36a498"
            },
            "balanceDueLabel": {
                "fontSize": "$fontSizeLargest",
                "bold": true
            },
            "balanceDue": {
                "fontSize": "$fontSizeLargest",
                "color": "$primaryColor:#36a498",
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
                "color": "$primaryColor:#36a498",
                "margin": [40,0,0,0],
                "bold": true
            },
            "yourInvoice": {
                "bold": true, 
                "fontSize": 14, 
                "color": "$primaryColor:#36a498",
                "margin": [0,0,0,8]
            },
            "invoiceLineItemsTable": {
                "margin": [0, 26, 0, 16]
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
                "margin": [0, 0, 0, 4]
            }            
        },
        "pageMargins": [0, 80, 0, 40]
    }