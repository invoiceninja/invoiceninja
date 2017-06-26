{
    "content": [
    {
        "columns":
        [
            {
        		"image": "$accountLogo",
        		"fit": [120, 80]
    		},
            {
                "width": 300,
                "stack": "$accountDetails",
                "margin": [140, 0, 0, 0]
        	},
        	{
                "width": 150,
                "stack": "$accountAddress"
        	}
        ]
    },
    {
    	"columns": [
		{
			"width": 120,
			"stack": [
                {"text": "$invoiceIssuedToLabel", "style":"issuedTo"},
                "$clientDetails"
            ],
			"margin": [0, 20, 0, 0]
		},
		{
            "canvas": [{ "type": "rect", "x": 20, "y": 0, "w": 174, "h": "$invoiceDetailsHeight","r":10, "lineWidth": 1,"color":"$primaryColor:#eb792d"}],
            "width":30,
            "margin":[200,25,0,0]
        },
		{
            "table": {
                "widths": [70, 76],
                "body": "$invoiceDetails"
            },
            "layout": "noBorders",
			"margin": [200, 34, 0, 0]
		}
	]
    },
    {"canvas": [{ "type": "rect", "x": 0, "y": 0, "w": 515, "h": 32,"r":8, "lineWidth": 1,"color":"$secondaryColor:#374e6b"}],"width":10,"margin":[0,20,0,-45]},
    {
        "style": "invoiceLineItemsTable",
        "table": {
            "headerRows": 1,
            "widths": "$invoiceLineItemColumns",
            "body": "$invoiceLineItems"
        },
        "layout": {
            "hLineWidth": "$notFirst:1",
            "vLineWidth": "$notFirst:.5",
            "hLineColor": "#FFFFFF",
            "vLineColor": "#FFFFFF",
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
        "stack": [
          {
            "style": "subtotals",
            "table": {
              "widths": ["*", "35%"],
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
          },
        {
          "canvas": [
          {
            "type": "rect",
            "x": 76,
            "y": 20,
            "w": 182,
            "h": 30,
            "r": 7,
            "lineWidth": 1,
            "color": "$secondaryColor:#374e6b"
          }
        ]
        },
          {
            "style": "subtotalsBalance",
            "table": {
                "widths": ["*", "35%"],
                "body": "$subtotalsBalance"
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
        "primaryColor":{
            "color": "$primaryColor:#299CC2"
        },
        "accountName": {
            "bold": true
        },
        "accountDetails": {
            "color": "#AAA9A9",
            "margin": [0,2,0,1]
        },
        "accountAddress": {
            "color": "#AAA9A9",
            "margin": [0,2,0,1]
        },
        "even": {
            "fillColor":"#E8E8E8"
        },
        "odd": {
            "fillColor":"#F7F7F7"
        },
        "productKey": {
            "bold": true
        },
        "subtotalsBalanceDueLabel": {
            "fontSize": "$fontSizeLargest",
            "color": "#ffffff",
            "bold": true
        },
        "subtotalsBalanceDue": {
            "fontSize": "$fontSizeLargest",
            "bold": true,
            "color":"#ffffff",
            "alignment":"right"
        },
        "invoiceDetails": {
            "color": "#ffffff"
        },
        "tableHeader": {
            "color": "#ffffff",
            "fontSize": "$fontSizeLargest",
            "bold": true
        },
	"secondTableHeader": {
	    "color": "$secondaryColor:#374e6b"
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
        "issuedTo": {
            "margin": [0,2,0,1],
            "bold": true,
            "color": "#374e6b"
        },
        "clientDetails": {
            "margin": [0,2,0,1]
        },
        "clientName": {
            "color": "$primaryColor:#eb792d"
        },
        "invoiceLineItemsTable": {
            "margin": [0, 10, 0, 10]
        },
        "invoiceDetailsValue": {
            "alignment": "right"
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
        "subtotalsBalance": {
            "alignment": "right",
            "margin": [0, -25, 0, 0]
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
