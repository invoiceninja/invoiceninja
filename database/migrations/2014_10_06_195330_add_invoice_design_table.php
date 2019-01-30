<?php

use Illuminate\Database\Migrations\Migration;

class AddInvoiceDesignTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('invoice_designs', function ($table) {
            $table->mediumText('javascript')->nullable();
        });

        Schema::table('accounts', function ($table) {
            $table->text('invoice_design')->nullable();
        });

        DB::table('invoice_designs')->where('id', 1)->update([
            'javascript' => "var GlobalY=0;//Y position of line at current page

	    var client = invoice.client;
	    var account = invoice.account;
	    var currencyId = client.currency_id;

	    layout.headerRight = 550;
	    layout.rowHeight = 15;

	    doc.setFontSize(9);

	    if (invoice.image)
	    {
	      var left = layout.headerRight - invoice.imageWidth;
	      doc.addImage(invoice.image, 'JPEG', layout.marginLeft, 30);
	    }
	  
	    if (!invoice.is_pro && logoImages.imageLogo1)
	    {
	      pageHeight=820;
	      y=pageHeight-logoImages.imageLogoHeight1;
	      doc.addImage(logoImages.imageLogo1, 'JPEG', layout.marginLeft, y, logoImages.imageLogoWidth1, logoImages.imageLogoHeight1);
	    }

	    doc.setFontSize(9);
	    SetPdfColor('LightBlue', doc, 'primary');
	    displayAccount(doc, invoice, 220, layout.accountTop, layout);

	    SetPdfColor('LightBlue', doc, 'primary');
	    doc.setFontSize('11');
	    doc.text(50, layout.headerTop, (invoice.is_quote ? invoiceLabels.quote : invoiceLabels.invoice).toUpperCase());


	    SetPdfColor('Black',doc); //set black color
	    doc.setFontSize(9);

	    var invoiceHeight = displayInvoice(doc, invoice, 50, 170, layout);
	    var clientHeight = displayClient(doc, invoice, 220, 170, layout);
	    var detailsHeight = Math.max(invoiceHeight, clientHeight);
	    layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (3 * layout.rowHeight));
	   
	    doc.setLineWidth(0.3);        
	    doc.setDrawColor(200,200,200);
	    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + 6, layout.marginRight + layout.tablePadding, layout.headerTop + 6);
	    doc.line(layout.marginLeft - layout.tablePadding, layout.headerTop + detailsHeight + 14, layout.marginRight + layout.tablePadding, layout.headerTop + detailsHeight + 14);

	    doc.setFontSize(10);
	    doc.setFontType('bold');
	    displayInvoiceHeader(doc, invoice, layout);
	    var y = displayInvoiceItems(doc, invoice, layout);

	    doc.setFontSize(9);
	    doc.setFontType('bold');

	    GlobalY=GlobalY+25;


	    doc.setLineWidth(0.3);
	    doc.setDrawColor(241,241,241);
	    doc.setFillColor(241,241,241);
	    var x1 = layout.marginLeft - 12;
	    var y1 = GlobalY-layout.tablePadding;

	    var w2 = 510 + 24;
	    var h2 = doc.internal.getFontSize()*3+layout.tablePadding*2;

	    if (invoice.discount) {
	        h2 += doc.internal.getFontSize()*2;
	    }
	    if (invoice.tax_amount) {
	        h2 += doc.internal.getFontSize()*2;
	    }

	    //doc.rect(x1, y1, w2, h2, 'FD');

	    doc.setFontSize(9);
	    displayNotesAndTerms(doc, layout, invoice, y);
	    y += displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);


	    doc.setFontSize(10);
	    Msg = invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due;
	    var TmpMsgX = layout.unitCostRight-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());
	    
	    doc.text(TmpMsgX, y, Msg);

	    SetPdfColor('LightBlue', doc, 'primary');
	    AmountText = formatMoney(invoice.balance_amount, currencyId);
	    headerLeft=layout.headerRight+400;
	    var AmountX = layout.lineTotalRight - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
	    doc.text(AmountX, y, AmountText);",
        ]);

        DB::table('invoice_designs')->where('id', 2)->update([
            'javascript' => "  var GlobalY=0;//Y position of line at current page

			  var client = invoice.client;
			  var account = invoice.account;
			  var currencyId = client.currency_id;

			  layout.headerRight = 150;
			  layout.rowHeight = 15;
			  layout.headerTop = 125;
			  layout.tableTop = 300;

			  doc.setLineWidth(0.5);

			  if (NINJA.primaryColor) {
			    setDocHexFill(doc, NINJA.primaryColor);
			    setDocHexDraw(doc, NINJA.primaryColor);
			  } else {
			    doc.setFillColor(46,43,43);
			  }  

			  var x1 =0;
			  var y1 = 0;
			  var w2 = 595;
			  var h2 = 100;
			  doc.rect(x1, y1, w2, h2, 'FD');

			  if (invoice.image)
			  {
			    var left = layout.headerRight - invoice.imageWidth;
			    doc.addImage(invoice.image, 'JPEG', layout.marginLeft, 30);
			  }

			  doc.setLineWidth(0.5);
			  if (NINJA.primaryColor) {
			    setDocHexFill(doc, NINJA.primaryColor);
			    setDocHexDraw(doc, NINJA.primaryColor);
			  } else {
			    doc.setFillColor(46,43,43);
			    doc.setDrawColor(46,43,43);
			  }  

			  // return doc.setTextColor(240,240,240);//select color Custom Report GRAY Colour
			  var x1 = 0;//tableLeft-tablePadding ;
			  var y1 = 750;
			  var w2 = 596;
			  var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;

			  doc.rect(x1, y1, w2, h2, 'FD');
			  if (!invoice.is_pro && logoImages.imageLogo2)
			  {
			      pageHeight=820;
			      var left = 250;//headerRight ;
			      y=pageHeight-logoImages.imageLogoHeight2;
			      var headerRight=370;

			      var left = headerRight - logoImages.imageLogoWidth2;
			      doc.addImage(logoImages.imageLogo2, 'JPEG', left, y, logoImages.imageLogoWidth2, logoImages.imageLogoHeight2);
			  }

			  doc.setFontSize(7);
			  doc.setFontType('bold');
			  SetPdfColor('White',doc);

			  displayAccount(doc, invoice, 300, layout.accountTop, layout);


			  var y = layout.accountTop;
			  var left = layout.marginLeft;
			  var headerY = layout.headerTop;

			  SetPdfColor('GrayLogo',doc); //set black color
			  doc.setFontSize(7);

			  //show left column
			  SetPdfColor('Black',doc); //set black color
			  doc.setFontType('normal');

			  //publish filled box
			  doc.setDrawColor(200,200,200);

			  if (NINJA.secondaryColor) {
			    setDocHexFill(doc, NINJA.secondaryColor);
			  } else {
			    doc.setFillColor(54,164,152);  
			  }  

			  GlobalY=190;
			  doc.setLineWidth(0.5);

			  var BlockLenght=220;
			  var x1 =595-BlockLenght;
			  var y1 = GlobalY-12;
			  var w2 = BlockLenght;
			  var h2 = getInvoiceDetailsHeight(invoice, layout) + layout.tablePadding + 2;

			  doc.rect(x1, y1, w2, h2, 'FD');

			  SetPdfColor('SomeGreen', doc, 'secondary');
			  doc.setFontSize('14');
			  doc.setFontType('bold');
			  doc.text(50, GlobalY, (invoice.is_quote ? invoiceLabels.your_quote : invoiceLabels.your_invoice).toUpperCase());


			  var z=GlobalY;
			  z=z+30;

			  doc.setFontSize('8');        
			  SetPdfColor('Black',doc);			  
        var clientHeight = displayClient(doc, invoice, layout.marginLeft, z, layout);
        layout.tableTop += Math.max(0, clientHeight - 75);
			  marginLeft2=395;

			  //publish left side information
			  SetPdfColor('White',doc);
			  doc.setFontSize('8');
			  var detailsHeight = displayInvoice(doc, invoice, marginLeft2, z-25, layout) + 75;
			  layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (2 * layout.tablePadding));

			  y=z+60;
			  x = GlobalY + 100;
			  doc.setFontType('bold');

			  doc.setFontSize(12);
			  doc.setFontType('bold');
			  SetPdfColor('Black',doc);
			  displayInvoiceHeader(doc, invoice, layout);

			  var y = displayInvoiceItems(doc, invoice, layout);
			  doc.setLineWidth(0.3);
			  displayNotesAndTerms(doc, layout, invoice, y);
			  y += displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);

			  doc.setFontType('bold');

			  doc.setFontSize(12);
			  x += doc.internal.getFontSize()*4;
			  Msg = invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due;
			  var TmpMsgX = layout.unitCostRight-(doc.getStringUnitWidth(Msg) * doc.internal.getFontSize());

			  doc.text(TmpMsgX, y, Msg);

			  //SetPdfColor('LightBlue',doc);
			  AmountText = formatMoney(invoice.balance_amount , currencyId);
			  headerLeft=layout.headerRight+400;
			  var AmountX = headerLeft - (doc.getStringUnitWidth(AmountText) * doc.internal.getFontSize());
			  SetPdfColor('SomeGreen', doc, 'secondary');
			  doc.text(AmountX, y, AmountText);",
            ]);

        DB::table('invoice_designs')->where('id', 3)->update([
                'javascript' => "    var client = invoice.client;
	    var account = invoice.account;
	    var currencyId = client.currency_id;

	    layout.headerRight = 400;
	    layout.rowHeight = 15;


	    doc.setFontSize(7);

	    // add header
	    doc.setLineWidth(0.5);

	    if (NINJA.primaryColor) {
	      setDocHexFill(doc, NINJA.primaryColor);
	      setDocHexDraw(doc, NINJA.primaryColor);
	    } else {
	      doc.setDrawColor(242,101,34);
	      doc.setFillColor(242,101,34);
	    }  

	    var x1 =0;
	    var y1 = 0;
	    var w2 = 595;
	    var h2 = Math.max(110, getInvoiceDetailsHeight(invoice, layout) + 30);
	    doc.rect(x1, y1, w2, h2, 'FD');

	    SetPdfColor('White',doc);

	    //second column
	    doc.setFontType('bold');
	    var name = invoice.account.name;    
	    if (name) {
	        doc.setFontSize('30');
	        doc.setFontType('bold');
	        doc.text(40, 50, name);
	    }

	    if (invoice.image)
	    {
	        y=130;
	        var left = layout.headerRight - invoice.imageWidth;
	        doc.addImage(invoice.image, 'JPEG', layout.marginLeft, y);
	    }

	    // add footer 
	    doc.setLineWidth(0.5);

	    if (NINJA.primaryColor) {
	      setDocHexFill(doc, NINJA.primaryColor);
	      setDocHexDraw(doc, NINJA.primaryColor);
	    } else {
	      doc.setDrawColor(242,101,34);
	      doc.setFillColor(242,101,34);
	    }  

	    var x1 = 0;//tableLeft-tablePadding ;
	    var y1 = 750;
	    var w2 = 596;
	    var h2 = 94;//doc.internal.getFontSize()*length+length*1.1;//+h;//+tablePadding;

	    doc.rect(x1, y1, w2, h2, 'FD');

	    if (!invoice.is_pro && logoImages.imageLogo3)
	    {
	        pageHeight=820;
	      // var left = 25;//250;//headerRight ;
	        y=pageHeight-logoImages.imageLogoHeight3;
	        //var headerRight=370;

	        //var left = headerRight - invoice.imageLogoWidth3;
	        doc.addImage(logoImages.imageLogo3, 'JPEG', 40, y, logoImages.imageLogoWidth3, logoImages.imageLogoHeight3);
	    }

	    doc.setFontSize(10);  
	    var marginLeft = 340;
	    displayAccount(doc, invoice, marginLeft, 780, layout);


	    SetPdfColor('White',doc);    
	    doc.setFontSize('8');
	    var detailsHeight = displayInvoice(doc, invoice, layout.headerRight, layout.accountTop-10, layout);
	    layout.headerTop = Math.max(layout.headerTop, detailsHeight + 50);
	    layout.tableTop = Math.max(layout.tableTop, detailsHeight + 150);

	    SetPdfColor('Black',doc); //set black color
	    doc.setFontSize(7);
	    doc.setFontType('normal');
	    displayClient(doc, invoice, layout.headerRight, layout.headerTop, layout);


	      
	    SetPdfColor('White',doc);    
	    doc.setFontType('bold');

	    doc.setLineWidth(0.3);
	    if (NINJA.secondaryColor) {
	      setDocHexFill(doc, NINJA.secondaryColor);
	      setDocHexDraw(doc, NINJA.secondaryColor);
	    } else {
	      doc.setDrawColor(63,60,60);
	      doc.setFillColor(63,60,60);
	    }  

	    var left = layout.marginLeft - layout.tablePadding;
	    var top = layout.tableTop - layout.tablePadding;
	    var width = layout.marginRight - (2 * layout.tablePadding);
	    var height = 20;
	    doc.rect(left, top, width, height, 'FD');
	    

	    displayInvoiceHeader(doc, invoice, layout);
	    SetPdfColor('Black',doc);
	    var y = displayInvoiceItems(doc, invoice, layout);


	    var height1 = displayNotesAndTerms(doc, layout, invoice, y);
	    var height2 = displaySubtotals(doc, layout, invoice, y, layout.unitCostRight);
	    y += Math.max(height1, height2);


	    var left = layout.marginLeft - layout.tablePadding;
	    var top = y - layout.tablePadding;
	    var width = layout.marginRight - (2 * layout.tablePadding);
	    var height = 20;
	    if (NINJA.secondaryColor) {
	      setDocHexFill(doc, NINJA.secondaryColor);
	      setDocHexDraw(doc, NINJA.secondaryColor);
	    } else {
	      doc.setDrawColor(63,60,60);
	      doc.setFillColor(63,60,60);
	    }  
	    doc.rect(left, top, width, height, 'FD');
	    
	    doc.setFontType('bold');
	    SetPdfColor('White', doc);
	    doc.setFontSize(12);
	    
	    var label = invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due;
	    var labelX = layout.unitCostRight-(doc.getStringUnitWidth(label) * doc.internal.getFontSize());
	    doc.text(labelX, y+2, label);


	    doc.setFontType('normal');
	    var amount = formatMoney(invoice.balance_amount , currencyId);
	    headerLeft=layout.headerRight+400;
	    var amountX = layout.lineTotalRight - (doc.getStringUnitWidth(amount) * doc.internal.getFontSize());
	    doc.text(amountX, y+2, amount);",
        ]);

        DB::table('invoice_designs')->where('id', 4)->update([
            'javascript' => "  var client = invoice.client;
		  var account = invoice.account;
		  var currencyId = client.currency_id;  
		  
      layout.accountTop += 25;
      layout.headerTop += 25;
      layout.tableTop += 25;

		  if (invoice.image)
		  {
		    var left = layout.headerRight - invoice.imageWidth;
		    doc.addImage(invoice.image, 'JPEG', left, 50);
		  } 
		  
		  /* table header */
		  doc.setDrawColor(200,200,200);
		  doc.setFillColor(230,230,230);
		  
		  var detailsHeight = getInvoiceDetailsHeight(invoice, layout);
		  var left = layout.headerLeft - layout.tablePadding;
		  var top = layout.headerTop + detailsHeight - layout.rowHeight - layout.tablePadding;
		  var width = layout.headerRight - layout.headerLeft + (2 * layout.tablePadding);
		  var height = layout.rowHeight + 1;
		  doc.rect(left, top, width, height, 'FD'); 

		  doc.setFontSize(10);
		  doc.setFontType('normal');

		  displayAccount(doc, invoice, layout.marginLeft, layout.accountTop, layout);
		  displayClient(doc, invoice, layout.marginLeft, layout.headerTop, layout);

		  displayInvoice(doc, invoice, layout.headerLeft, layout.headerTop, layout, layout.headerRight);
		  layout.tableTop = Math.max(layout.tableTop, layout.headerTop + detailsHeight + (2 * layout.tablePadding));

		  var headerY = layout.headerTop;
		  var total = 0;

		  doc.setDrawColor(200,200,200);
		  doc.setFillColor(230,230,230);
		  var left = layout.marginLeft - layout.tablePadding;
		  var top = layout.tableTop - layout.tablePadding;
		  var width = layout.headerRight - layout.marginLeft + (2 * layout.tablePadding);
		  var height = layout.rowHeight + 2;
		  doc.rect(left, top, width, height, 'FD');   

		  displayInvoiceHeader(doc, invoice, layout);
		  var y = displayInvoiceItems(doc, invoice, layout);

		  doc.setFontSize(10);

		  displayNotesAndTerms(doc, layout, invoice, y+20);

		  y += displaySubtotals(doc, layout, invoice, y+20, 480) + 20;

		  doc.setDrawColor(200,200,200);
		  doc.setFillColor(230,230,230);
		  
		  var left = layout.footerLeft - layout.tablePadding;
		  var top = y - layout.tablePadding;
		  var width = layout.headerRight - layout.footerLeft + (2 * layout.tablePadding);
		  var height = layout.rowHeight + 2;
		  doc.rect(left, top, width, height, 'FD'); 
		  
		  doc.setFontType('bold');
		  doc.text(layout.footerLeft, y, invoice.is_quote ? invoiceLabels.total : invoiceLabels.balance_due);

		  total = formatMoney(invoice.balance_amount, currencyId);
		  var totalX = layout.headerRight - (doc.getStringUnitWidth(total) * doc.internal.getFontSize());
		  doc.text(totalX, y, total);   

		  if (!invoice.is_pro) {
		    doc.setFontType('normal');
		    doc.text(layout.marginLeft, 790, 'Created by InvoiceNinja.com');
		  }",
          
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('invoice_designs', function ($table) {
            $table->dropColumn('javascript');
        });

        Schema::table('accounts', function ($table) {
            $table->dropColumn('invoice_design');
        });
    }
}
