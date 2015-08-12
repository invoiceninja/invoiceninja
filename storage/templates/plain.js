{
    "content": [
    {
        "columns": [
		{
            "stack": "$accountDetails"
        },
        {
            "stack": "$accountAddress"
        },
        [
            {
                "image": "$accountLogo",
                "fit": [120, 80]
            }
        ]        
    ]},
	{
	"columns": [
			{
				"width": 340,
				"stack": "$clientDetails",
				"margin": [0,40,0,0]
			},
			{
                "canvas": [{ "type": "rect", "x": 0, "y": 0, "w": 175, "h": 15, "r":0, "lineWidth": 1, "color":"#e6e6e6"}],
                "width":10,
                "margin":[0,70,0,0]
            },
			{
				"width":200,
                "table": { 
                    "body": "$invoiceDetails"
                },
                "layout": "noBorders",
				"margin":[0,40,0,0]
			}
		]
	},	
	{
        "canvas": [{ "type": "rect", "x": 0, "y": 0, "w": 515, "h": 25,"r":0, "lineWidth": 1,"color":"#e6e6e6"}],"width":10,"margin":[0,30,0,-43]
    },
    {
        "style": "invoiceLineItemsTable",
        "table": {
            "headerRows": 1,
            "widths": "$invoiceLineItemColumns",
            "body": "$invoiceLineItems"
        },
        "layout": {
            "hLineWidth": "$notFirst:1",
            "vLineWidth": "$none",
            "hLineColor": "#e6e6e6",
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
                "canvas": [{ "type": "rect", "x": -360, "y": 0, "w": 200, "h": 20,"r":0, "lineWidth": 2, "color":"#e6e6e6","lineColor":"#e6e6e6"}],"width":10,"margin":[420,37,0,0]
            },
            {
                "style": "subtotals",
                "table": {
                    "widths": ["*", "40%"],
                    "body": "$subtotals"
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
    }
    ],
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
    "defaultStyle": {
        "fontSize": "$fontSize",
        "margin": [8, 4, 8, 4]
    },
    "styles": {
        "primaryColor":{
            "color": "$primaryColor:#299CC2"
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
        "tableHeader": {
            "bold": true
        },
        "invoiceLineItemsTable": {
            "margin": [0, 16, 0, 16]
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