{
    "content": [
    {
        "columns": [
    		{
    			"stack": "$clientDetails"
    		},
            {
                "stack": "$accountDetails"
            },
            {
                "stack": "$accountAddress"
            },
            {
                "image": "$accountLogo",
                "fit": [120, 80],
                "alignment": "right"
            }
        ],
        "margin": [0, 0, 0, 20]
    },
	{
		"columns": [
            {"text":
                [
                    {"text": "$entityTypeUC", "style": "header1"},
                    {"text": "#", "style": "header2"},
                    {"text": "$invoiceNumber", "style":"header2"}
                ],
                "width": "*"
            },
    		{
    			"width":200,
                "table": {
                    "body": "$invoiceDetails"
                },
                "layout": "noBorders",
    			"margin": [16, 4, 0, 0]
    		}
		],
        "margin": [0, 0, 0, 20]
	},
	{"canvas": [{ "type": "line", "x1": 0, "y1": 5, "x2": 515, "y2": 5, "lineWidth": 3,"lineColor":"$primaryColor:#AE1E54"}]},
    {
        "style": "invoiceLineItemsTable",
        "table": {
            "headerRows": 1,
            "widths": "$invoiceLineItemColumns",
            "body": "$invoiceLineItems"
        },
        "layout": {
            "hLineWidth": "$none",
            "vLineWidth": "$none",
            "hLineColor": "$primaryColor:#E8E8E8",
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
		"canvas": [{ "type": "line", "x1": 0, "y1": 20, "x2": 515, "y2": 20, "lineWidth": 3,"lineColor":"$primaryColor:#AE1E54"}],
        "margin": [0, -8, 0, -8]
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
        "stack": [
            "$invoiceDocuments"
        ],
        "style": "invoiceDocuments"
    }
    ],
    "footer": {
        "columns": [
            {
                "text": "$invoiceFooter",
                "alignment": "left"
            }
        ],
        "margin": [40, -20, 40, 0]
    },
    "defaultStyle": {
        "fontSize": "$fontSize",
        "margin": [8, 4, 8, 4]
    },
    "styles": {
        "primaryColor":{
            "color": "$primaryColor:#AE1E54"
        },
        "accountName": {
            "margin": [4, 2, 4, 2],
            "color": "$primaryColor:#AE1E54",
            "bold": true
        },
        "accountDetails": {
            "margin": [4, 2, 4, 2]
        },
        "accountAddress": {
            "margin": [4, 2, 4, 2]
        },
        "odd": {
            "fillColor":"#F4F4F4"
        },
        "productKey": {
            "bold": true
        },
        "subtotalsBalanceDueLabel": {
            "fontSize": "$fontSizeLargest",
            "margin": [320,20,0,0]
        },
        "subtotalsBalanceDue": {
            "fontSize": "$fontSizeLargest",
            "color": "$primaryColor:#AE1E54",
            "bold": true,
            "margin":[0,-10,10,0],
            "alignment": "right"
        },
        "invoiceDetailBalanceDue": {
            "bold": true,
            "color": "$primaryColor:#AE1E54"
        },
        "invoiceDetailBalanceDueLabel": {
            "bold": true
        },
        "tableHeader": {
            "bold": true,
            "color": "$primaryColor:#AE1E54",
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
        "clientName": {
            "bold": true
        },
        "clientDetails": {
            "margin": [0,2,0,1]
        },
        "header1": {
            "bold": true,
            "margin": [0, 30, 0, 16],
            "fontSize": 46
        },
        "header2": {
            "margin": [0, 30, 0, 16],
            "fontSize": 46,
            "italics": true,
            "color": "$primaryColor:#AE1E54"
        },
        "invoiceLineItemsTable": {
            "margin": [0, 4, 0, 16]
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
