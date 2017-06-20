{
    "content": [
    {
        "columns": [
		{
			"image": "$accountLogo",
			"fit": [120, 80]
		},
		{"canvas": [{ "type": "rect", "x": 0, "y": 0, "w": 190, "h": "$invoiceDetailsHeight","r":5, "lineWidth": 1,"color":"$primaryColor:#009d91"}],"width":10,"margin":[200,0,0,0]},
		{
			"width":400,
            "table": { 
                "body": "$invoiceDetails"
            },
            "layout": "noBorders",
			"margin": [210, 10, 10, 0]
		}
        ] 
    },
	{
        "margin": [0, 18, 0, 0],
        "columnGap": 50,
		"columns": [
			{
				"width": 212,
				"stack": [
                    {"text": "$invoiceToLabel:", "style": "toLabel"},
                    {
                        "canvas": [{ "type": "line", "x1": 0, "y1": 4, "x2": 150, "y2": 4, "lineWidth": 1,"dash": { "length": 3 },"lineColor":"$primaryColor:#009d91"}],
                        "margin": [0, 0, 0, 4]
                    },
                    "$clientDetails",
                    {"canvas": [{ "type": "line", "x1": 0, "y1": 9, "x2": 150, "y2": 9, "lineWidth": 1,"dash": { "length": 3 },"lineColor":"$primaryColor:#009d91"}]}
                ]
			},
			{
                "width": "*",
				"stack": [
                    {"text": "$fromLabel:", "style": "fromLabel"},
                    {
                        "canvas": [{ "type": "line", "x1": 0, "y1": 4, "x2": 250, "y2": 4, "lineWidth": 1,"dash": { "length": 3 },"lineColor":"$primaryColor:#009d91"}],
                        "margin": [0, 0, 0, 4]
                    },
                    {"columns":[
                        "$accountDetails",
                        "$accountAddress"    
                    ], "columnGap": 4},                    
                    {"canvas": [{ "type": "line", "x1": 0, "y1": 9, "x2": 250, "y2": 9, "lineWidth": 1,"dash": { "length": 3 },"lineColor":"$primaryColor:#009d91"}]}
                ]
			}
		]
	},
	{"canvas": [{ "type": "rect", "x": 0, "y": 0, "w": 515, "h": 35,"r":6, "lineWidth": 1,"color":"$primaryColor:#009d91"}],"width":10,"margin":[0,30,0,-30]},
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
            "hLineColor": "$primaryColor:#009d91",
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
            "r": 4,
            "lineWidth": 1,
            "color": "$primaryColor:#009d91"
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
    "footer": [
        {"canvas": [{ "type": "line", "x1": 0, "y1": 38, "x2": 68, "y2": 38, "lineWidth": 6,"lineColor":"#009d91"}]},
        {"canvas": [{ "type": "line", "x1": 68, "y1": 0, "x2": 135, "y2": 0, "lineWidth": 6,"lineColor":"#1d766f"}]},
        {"canvas": [{ "type": "line", "x1": 135, "y1": 0, "x2": 201, "y2": 0, "lineWidth": 6,"lineColor":"#ffb800"}]},
        {"canvas": [{ "type": "line", "x1": 201, "y1": 0, "x2": 267, "y2": 0, "lineWidth": 6,"lineColor":"#bf9730"}]},
        {"canvas": [{ "type": "line", "x1": 267, "y1": 0, "x2": 333, "y2": 0, "lineWidth": 6,"lineColor":"#ac2b50"}]},
        {"canvas": [{ "type": "line", "x1": 333, "y1": 0, "x2": 399, "y2": 0, "lineWidth": 6,"lineColor":"#e60042"}]},
        {"canvas": [{ "type": "line", "x1": 399, "y1": 0, "x2": 465, "y2": 0, "lineWidth": 6,"lineColor":"#ffb800"}]},
        {"canvas": [{ "type": "line", "x1": 465, "y1": 0, "x2": 532, "y2": 0, "lineWidth": 6,"lineColor":"#009d91"}]},
        {"canvas": [{ "type": "line", "x1": 532, "y1": 0, "x2": 600, "y2": 0, "lineWidth": 6,"lineColor":"#ac2b50"}]},
        {
            "text": "$invoiceFooter",
            "alignment": "left",
            "margin": [40, -60, 40, 0]
        }
    ],
    "header": [
        {"canvas": [{ "type": "line", "x1": 0, "y1": 0, "x2": 68, "y2": 0, "lineWidth": 9,"lineColor":"#009d91"}]},
        {"canvas": [{ "type": "line", "x1": 68, "y1": 0, "x2": 135, "y2": 0, "lineWidth": 9,"lineColor":"#1d766f"}]},
        {"canvas": [{ "type": "line", "x1": 135, "y1": 0, "x2": 201, "y2": 0, "lineWidth": 9,"lineColor":"#ffb800"}]},
        {"canvas": [{ "type": "line", "x1": 201, "y1": 0, "x2": 267, "y2": 0, "lineWidth": 9,"lineColor":"#bf9730"}]},
        {"canvas": [{ "type": "line", "x1": 267, "y1": 0, "x2": 333, "y2": 0, "lineWidth": 9,"lineColor":"#ac2b50"}]},
        {"canvas": [{ "type": "line", "x1": 333, "y1": 0, "x2": 399, "y2": 0, "lineWidth": 9,"lineColor":"#e60042"}]},
        {"canvas": [{ "type": "line", "x1": 399, "y1": 0, "x2": 465, "y2": 0, "lineWidth": 9,"lineColor":"#ffb800"}]},
        {"canvas": [{ "type": "line", "x1": 465, "y1": 0, "x2": 532, "y2": 0, "lineWidth": 9,"lineColor":"#009d91"}]},
        {"canvas": [{ "type": "line", "x1": 532, "y1": 0, "x2": 600, "y2": 0, "lineWidth": 9,"lineColor":"#ac2b50"}]}
    ],
    "defaultStyle": {
        "fontSize": "$fontSize",
        "margin": [8, 4, 8, 4]
    },
    "styles": {
        "accountName": {
            "color": "$secondaryColor:#bb3328"
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
        "clientName": {
            "color": "$secondaryColor:#bb3328"
        },
        "even": {
			"fillColor":"#E8E8E8"
        },
        "odd": {
            "fillColor":"#F7F7F7"
        },
        "productKey": {
            "color": "$secondaryColor:#bb3328"
        },
        "lineTotal": {
            "bold": true
        },
        "tableHeader": {
            "bold": true,
            "fontSize": "$fontSizeLargest",
            "color": "#FFFFFF"
        },
        "secondTableHeader": {
	    "color": "$primaryColor:#009d91"
        },
        "costTableHeader": {
            "alignment": "right"
        },
        "qtyTableHeader": {
            "alignment": "right"
        },
        "lineTotalTableHeader": {
            "alignment": "right"
        },        
        "subtotalsBalanceDueLabel": {
            "fontSize": "$fontSizeLargest",
            "color":"#FFFFFF",
            "bold": true
        },
        "subtotalsBalanceDue": {
            "fontSize": "$fontSizeLargest",
            "bold": true,
            "color":"#FFFFFF",
            "alignment":"right"
        },
        "invoiceDetails": {
            "color": "#FFFFFF"
        },
        "invoiceLineItemsTable": {
            "margin": [0, 0, 0, 16]
        },
        "invoiceDetailBalanceDueLabel": {
            "bold": true
        },
        "invoiceDetailBalanceDue": {
            "bold": true
        },
        "fromLabel": {
            "color": "$primaryColor:#009d91"
        },
        "toLabel": {
            "color": "$primaryColor:#009d91"
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
