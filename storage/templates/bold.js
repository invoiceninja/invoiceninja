{
    "content": [
    {
        "columns": [
        {
            "width": 380,
            "stack": [
            {"text":"$yourInvoiceLabelUC", "style": "yourInvoice"},
            "$clientDetails"
            ],
            "margin": [60, 100, 0, 10]
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
            "margin":[-10,100,0,10]
        },
        {	
            "table": { 
                "body": "$invoiceDetails"
            },
            "layout": "noBorders",
            "margin": [0, 110, 0, 0]
        }
        ]
    },
    {
        "style": "invoiceLineItemsTable",
        "table": {
            "headerRows": 1,
            "widths": ["22%", "*", "14%", "$quantityWidth", "$taxWidth", "22%"],
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
                "widths": ["*", "40%"],
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
    },
        {
            "stack": [
                "$invoiceDocuments"
            ],
            "style": "invoiceDocuments"
        }
    ],
    "footer":
    [
        {"canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 600, "y2": 0,"lineWidth": 100,"lineColor":"$secondaryColor:#292526"}]},
        {
            "columns":
                [
                    {
                        "text": "$invoiceFooter",
                        "margin": [40, -40, 40, 0],
                        "alignment": "left",
                        "color": "#FFFFFF"
                    }
                ]
        }
    ],
    "header": [
          {
            "canvas": [
              {
                "type": "line",
                "x1": 0,
                "y1": 0,
                "x2": 600,
                "y2": 0,
                "lineWidth": 200,
                "lineColor": "$secondaryColor:#292526"
              }
            ],
            "width": 10
          },
          {
            "columns": [
              { 
                "image": "$accountLogo",
                "fit": [120, 60],
                "margin": [30, 16, 0, 0]
              },
              {
                "stack": "$accountDetails",
                "margin": [
                  0,
                  16,
                  0,
                  0
                ],
                "width": 140
              },
              {
                "stack": "$accountAddress",
                "margin": [
                  20,
                  16,
                  0,
                  0
                ]
              }
            ]
          }
        ],
    "defaultStyle": {
            "font": "$bodyFont",
            "fontSize": "$fontSize",
            "margin": [8, 4, 8, 4]
        },
        "styles": {
            "primaryColor":{
                "color": "$primaryColor:#36a498"
            },
            "accountName": {
                "bold": true,
                "margin": [4, 2, 4, 1],
                "color": "$primaryColor:#36a498"
            },
            "accountDetails": {
                "margin": [4, 2, 4, 1],
                "color": "#FFFFFF"
            },
            "accountAddress": {
                "margin": [4, 2, 4, 1],
                "color": "#FFFFFF"
            },
            "clientDetails": {
                "margin": [0, 2, 0, 1]
            },
            "odd": {
                "fillColor": "#ebebeb",
                "margin": [0,0,0,0]
            },
            "productKey": {
                "color": "$primaryColor:#36a498"
            },
            "subtotalsBalanceDueLabel": {
                "fontSize": "$fontSizeLargest",
                "bold": true
            },
            "subtotalsBalanceDue": {
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
            "costTableHeader": {
                "alignment": "right"
            },
            "qtyTableHeader": {
                "alignment": "right"
            },
            "taxTableHeader": {
                "alignment": "right"
            },
            "lineTotalTableHeader": {
                "alignment": "right",
                "margin": [0, 0, 40, 0]
            },
            "productKey": {
                "color": "$primaryColor:#36a498",
                "margin": [40,0,0,0],
                "bold": true
            },
            "yourInvoice": {
                "font": "$headerFont",
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
                "margin": [0, 0, 40, 0]
            },
            "subtotals": {
                "alignment": "right",
                "margin": [0,0,40,0]
            },
            "termsLabel": {
                "bold": true,
                "margin": [0, 0, 0, 4]
            },
            "header": {
                "font": "$headerFont",
                "fontSize": "$fontSizeLargest",
                "bold": true
            },
            "subheader": {
                "font": "$headerFont",
                "fontSize": "$fontSizeLarger"
            },
            "help": {
                "fontSize": "$fontSizeSmaller",
                "color": "#737373"
            },
            "invoiceDocuments": {
                "margin": [47, 0, 47, 0]
            },
            "invoiceDocument": {
                "margin": [0, 10, 0, 10]
            }
        },
        "pageMargins": [0, 80, 0, 40]
    }