{
    "content": [
    {
        "image": "$accountLogo",
        "fit": [120, 80],
        "alignment": "center",
        "margin": [0, 0, 0, 30]
    },
    {"canvas": [{ "type": "line", "x1": 0, "y1": 5, "x2": 515, "y2": 5, "lineWidth": 2}]},
    {"canvas": [{ "type": "line", "x1": 0, "y1": 3, "x2": 515, "y2": 3, "lineWidth": 1}]},
    {
        "columns": [
        {
            "width": 120,
            "stack": [
                {"text": "$invoiceToLabel", "style": "header", "margin": [0, 0, 0, 6]},
                "$clientDetails"
            ]
        },
        {
            "width": 10,
            "canvas": [{ "type": "line", "x1": -2, "y1": 18, "x2": -2, "y2": 80, "lineWidth": 1,"dash": { "length": 2 }}]
        },
        {
            "width": 120,
            "stack": "$accountDetails",
            "margin": [0, 20, 0, 0]
        },
        {
            "width": 110,
            "stack": "$accountAddress",
            "margin": [0, 20, 0, 0]
        },
        {
            "stack": [
                {"text": "$detailsLabel", "style": "header", "margin": [0, 0, 0, 6]},
                {
                    "width":180,
                    "table": {
                        "body": "$invoiceDetails"
                    },
                    "layout": "noBorders"
                }
            ]
        }],
        "margin": [0, 20, 0, 0]
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
            "vLineWidth": "$none",
            "paddingLeft": "$amount:8",
            "paddingRight": "$amount:8",
            "paddingTop": "$amount:12",
            "paddingBottom": "$amount:12"
        }
    },
    {
        "columns": [
        "$notesAndTerms",
        {
            "style": "subtotals",
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
        "canvas": [{ "type": "line", "x1": 270, "y1": 20, "x2": 515, "y2": 20, "lineWidth": 1,"dash": { "length": 2 }}]
    },
    {
        "text": "$balanceDueLabel",
        "style": "subtotalsBalanceDueLabel"
    },
    {
        "text": "$balanceDue",
        "style": "subtotalsBalanceDue"
    },
    {
        "canvas": [{ "type": "line", "x1": 270, "y1": 20, "x2": 515, "y2": 20, "lineWidth": 1,"dash": { "length": 2 }}]
    },
    {
        "stack": [
            "$invoiceDocuments"
        ],
        "style": "invoiceDocuments"
    }],
    "footer": [
    {
        "columns": [
            {
                "text": "$invoiceFooter",
                "alignment": "left"
            }
        ],
        "margin": [40, -20, 40, 0]
    },
    {"canvas": [{ "type": "line", "x1": 35, "y1": 5, "x2": 555, "y2": 5, "lineWidth": 2,"margin": [30,0,0,0]}]},
    {"canvas": [{ "type": "line", "x1": 35, "y1": 3, "x2": 555, "y2": 3, "lineWidth": 1,"margin": [30,0,0,0]}]}
    ],
    "defaultStyle": {
        "fontSize": "$fontSize",
        "margin": [8, 4, 8, 4]
    },
    "styles": {
        "accountDetails": {
            "margin": [0, 2, 0, 1]
        },
        "clientDetails": {
            "margin": [0, 2, 0, 1]
        },
        "accountAddress": {
            "margin": [0, 2, 0, 1]
        },
        "clientName": {
            "bold": true
        },
        "accountName": {
            "bold": true
        },
        "odd": {
        },
        "subtotalsBalanceDueLabel": {
            "fontSize": "$fontSizeLargest",
            "color": "$primaryColor:#5a7b61",
            "margin": [320,20,0,0]
        },
        "subtotalsBalanceDue": {
            "fontSize": "$fontSizeLargest",
            "color": "$primaryColor:#5a7b61",
            "style": true,
            "margin":[0,-14,8,0],
            "alignment":"right"
        },
        "invoiceDetailBalanceDue": {
            "color": "$primaryColor:#5a7b61",
            "bold": true
        },
        "header": {
            "fontSize": 14,
            "bold": true
        },
        "tableHeader": {
            "bold": true,
            "color": "$primaryColor:#5a7b61",
            "fontSize": "$fontSizeLargest"
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
        "invoiceLineItemsTable": {
            "margin": [0, 40, 0, 16]
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
        "header": {
            "fontSize": "$fontSizeLargest",
            "bold": true
        },
        "subheader": {
            "fontSize": "$fontSizeLarger"
        },
        "help": {
            "fontSize": "$fontSizeSmaller",
            "color": "#737373"
        }
    },
    "pageMargins": [40, 40, 40, 40]
}
