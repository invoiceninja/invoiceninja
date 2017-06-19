{
    "content": [
    {
        "columns": [
		{
			"width":10,
			"canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 0, "y2": 75, "lineWidth": 0.5}]
		},
        {
			"width":120,
            "stack": [
                {"text": "$fromLabelUC", "style": "fromLabel"}, 
                "$accountDetails" 
            ]
        },
        {
			"width":120,
            "stack": [
                {"text": " "},
                "$accountAddress"
            ],
			"margin": [10, 0, 0, 16]
        },
		{
			"width":10,
			"canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 0, "y2": 75, "lineWidth": 0.5}]
		},
		{
			"stack": [
                {"text": "$toLabelUC", "style": "toLabel"}, 
                "$clientDetails"
            ]
		},
		[
            {
                "image": "$accountLogo",
                "fit": [120, 80]
            }
        ]
        ]
    },
    {
        "text": "$entityTypeUC",
        "margin": [0, 4, 0, 8],
        "bold": "true",
        "fontSize": 42
    },
	{
        "columnGap": 16,
		"columns": [
			{
				"width":"auto",
				"text": ["$invoiceNoLabel"," ","$invoiceNumberValue"],
				"bold": true,
				"color":"$primaryColor:#bc9f2b",
				"fontSize":10
			},
			{
				"width":"auto",
				"text": ["$invoiceDateLabel"," ","$invoiceDateValue"],
				"fontSize":10
			},
			{
				"width":"auto",
				"text": ["$dueDateLabel?"," ","$dueDateValue"],
				"fontSize":10
			},
			{
				"width":"*",
				"text": ["$balanceDueLabel"," ",{"text":"$balanceDue", "bold":true, "color":"$primaryColor:#bc9f2b"}],
				"fontSize":10
			}
		]
	},
    {
		"margin": [0, 26, 0, 0],
	"style": "invoiceLineItemsTable",
        "table": {
            "headerRows": 1,
            "widths": "$invoiceLineItemColumns",
            "body": "$invoiceLineItems"
        },
        "layout": {
            "hLineWidth": "$none",
            "vLineWidth": "$amount:.5",
            "paddingLeft": "$amount:8", 
            "paddingRight": "$amount:8", 
            "paddingTop": "$amount:8", 
            "paddingBottom": "$amount:8"            
        }
    },
    {
        "columns": [
        {
            "stack": "$notesAndTerms",
            "width": "*",
            "margin": [0, 12, 0, 0]
        },
        {
            "width": 200,
            "style": "subtotals",
            "table": {
                "widths": ["*", "36%"],
                "body": "$subtotals"
            },
            "layout": {
                "hLineWidth": "$none",
                "vLineWidth": "$notFirst:.5",
                "paddingLeft": "$amount:8", 
                "paddingRight": "$amount:8", 
                "paddingTop": "$amount:12", 
                "paddingBottom": "$amount:4"            
            }
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
        "accountName": {
            "bold": true
        },
        "clientName": {
            "bold": true
        },
        "subtotalsBalanceDueLabel": {
            "fontSize": "$fontSizeLargest",
            "bold": true
        },
        "subtotalsBalanceDue": {
            "fontSize": "$fontSizeLargest",
            "color": "$primaryColor:#bc9f2b",
            "bold": true
        },
        "tableHeader": {
            "bold": true,
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
        "fromLabel": {
            "color": "$primaryColor:#bc9f2b",
            "bold": true  
        },
        "toLabel": {
            "color": "$primaryColor:#bc9f2b",
            "bold": true  
        },
        "accountDetails": {
            "margin": [0, 2, 0, 1]
        },
        "accountAddress": {
            "margin": [0, 2, 0, 1]
        },
        "clientDetails": {
            "margin": [0, 2, 0, 1]
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
            "margin": [0, 16, 0, 4]
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
