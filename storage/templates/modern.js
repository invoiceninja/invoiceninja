{
    "content": [
        {
            "columns": [
            {
                "image": "$accountLogo",
                "fit": [120, 80],
                "margin": [0, 60, 0, 30]
            },
            {
                "stack": "$clientDetails",
                "margin": [0, 60, 0, 0]
            }
            ]
        },
        {
            "style": "invoiceLineItemsTable",
            "table": {
                "headerRows": 1,
                "widths": "$invoiceLineItemColumns",
                "body": "$invoiceLineItems"
            },
            "layout": {
                "hLineWidth": "$notFirst:.5",
                "vLineWidth": "$notFirstAndLastColumn:.5",
                "hLineColor": "#888888",
                "vLineColor": "#FFFFFF",
                "paddingLeft": "$amount:8",
                "paddingRight": "$amount:8",
                "paddingTop": "$amount:8",
                "paddingBottom": "$amount:8"
            }
        },
        {
            "columns": [
            "$notesAndTerms",
            {
                "table": {
                    "widths": ["*", "40%"],
                    "body": "$subtotalsWithoutBalance"
                },
                "layout": {
                    "hLineWidth": "$none",
                    "vLineWidth": "$none",
                    "paddingLeft": "$amount:34",
                    "paddingRight": "$amount:8",
                    "paddingTop": "$amount:4",
                    "paddingBottom": "$amount:4"
                }
            }
            ]
        },
        {
            "columns": [
            {
                "canvas": [
                {
                    "type": "rect",
                    "x": 0,
                    "y": 0,
                    "w": 515,
                    "h": 26,
                    "r": 0,
                    "lineWidth": 1,
                    "color": "$secondaryColor:#403d3d"
                }
                ],
                "width": 10,
                "margin": [
                0,
                10,
                0,
                0
                ]
            },
            {
                "text": "$balanceDueLabel",
                "style": "subtotalsBalanceDueLabel",
                "margin": [0, 16, 0, 0],
                "width": 370
            },
            {
                "text": "$balanceDue",
                "style": "subtotalsBalanceDue",
                "margin": [0, 16, 8, 0]
            }
            ]
        },
        {
            "stack": [
                "$invoiceDocuments"
            ],
            "style": "invoiceDocuments"
        }
    ],
    "footer": [
    {
        "canvas": [
        {
            "type": "line", "x1": 0, "y1": 0, "x2": 600, "y2": 0,"lineWidth": 100,"lineColor":"$primaryColor:#f26621"
            }]
            ,"width":10
        },
        {
        "columns": [
        {
            "width": 350,
            "stack": [
            {
                "text": "$invoiceFooter",
                "margin": [40, -40, 40, 0],
                "alignment": "left",
                "color": "#FFFFFF"

            }
            ]
        },
        {
            "stack": "$accountDetails",
            "margin": [0, -40, 0, 0],
            "width": "*"
        },
        {
            "stack": "$accountAddress",
            "margin": [0, -40, 0, 0],
            "width": "*"
        }
        ]
    }
    ],
    "header": [
    {
        "canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 600, "y2": 0,"lineWidth": 200,"lineColor":"$primaryColor:#f26621"}],"width":10
    },
    {
        "columns": [
        {
            "text": "$accountName", "bold": true,"font":"$headerFont","fontSize":30,"color":"#ffffff","margin":[40,20,0,0],"width":350
        }
        ]
    },
    {
        "width": 300,
        "table": {
            "body": "$invoiceDetails"
        },
        "layout": "noBorders",
        "margin": [400, -40, 0, 0]
    }
    ],
    "defaultStyle": {
        "font": "$bodyFont",
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
            "color": "#FFFFFF"
        },
        "accountAddress": {
            "margin": [4, 2, 4, 2],
            "color": "#FFFFFF"
        },
        "clientDetails": {
            "margin": [0, 2, 4, 2]
        },
        "invoiceDetails": {
            "color": "#FFFFFF"
        },
        "invoiceLineItemsTable": {
            "margin": [0, 0, 0, 16]
        },
        "productKey": {
            "bold": true
        },
        "clientName": {
            "bold": true
        },
        "tableHeader": {
            "bold": true,
            "color": "#FFFFFF",
            "fontSize": "$fontSizeLargest",
            "fillColor": "$secondaryColor:#403d3d"
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
            "alignment": "right"
        },
        "subtotalsBalanceDueLabel": {
            "fontSize": "$fontSizeLargest",
            "color":"#FFFFFF",
            "alignment":"right",
            "bold": true
        },
        "subtotalsBalanceDue": {
            "fontSize": "$fontSizeLargest",
            "color":"#FFFFFF",
            "bold": true,
            "alignment":"right"
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
        },
        "invoiceNumberLabel": {
            "bold": true
        },
        "invoiceNumber": {
            "bold": true
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
            "margin": [7, 0, 7, 0]
        },
        "invoiceDocument": {
            "margin": [0, 10, 0, 10]
        }
    },
    "pageMargins": [40, 120, 40, 50]
}
