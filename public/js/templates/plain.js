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
                "width": 100
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
            "hLineWidth": "$borderNotTop:1",
            "vLineWidth": "$borderNone",
            "hLineColor": "#e6e6e6",
            "paddingLeft": "$padding:8", 
            "paddingRight": "$padding:8", 
            "paddingTop": "$padding:8", 
            "paddingBottom": "$padding:8"            
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
    "footer": {
        "text": "$invoiceFooter",
        "margin": [40, -40, 40, 0],
        "alignment": "left"
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
            "margin": [0, 10, 0, 4]
        }           
     },
    "pageMargins": [40, 40, 40, 40]
}