Invoice Settings
================

You can customize your invoice template by pre-defining the various numbering formats, adding new fields for client, contact, company or invoice information, and adding default text to invoice terms, invoice footer, and more. Any changes you make to the Invoice Settings will apply to all your invoices.

The Invoice Settings page has four sections:

- Generated Numbers
- Custom Fields
- Quote Settings
- Defaults

Generated Numbers
"""""""""""""""""

Your invoice and quote numbers are generated automatically. You can adjust the automated numbering system in this section.

The Generated Numbers section contains five tabs. Let's go through them:

Invoice Number
^^^^^^^^^^^^^^

To customize your invoice numbering system, click on the Invoice Number tab.

There are two ways to customize the invoice number: by adding a prefix or creating a pattern.

To add a prefix, select the Prefix button. In the field immediately below, add your chosen prefix. For example, you may choose to add your company initials, such as M&D. The current invoice number appears in the Counter field.

All your invoices will automatically include the numbering change. So if you chose the prefix M&D, your invoice numbers will appear as M&D001, and so on.

To create a pattern, select the Pattern button. In the pattern field, enter the custom variable of your choice. For example, if you create a pattern of {$year}-{$counter}, then your invoices will be numbered with the current year and latest invoice number. To view available options for custom patterns, click on the question mark icon at the right end of the Pattern field.

All your invoices will automatically display invoice numbers according to your customized pattern.

Quote Number
^^^^^^^^^^^^

To customize your quote numbering system, click on the Quote Number tab.

There are two ways to customize the quote number: by adding a prefix or creating a pattern.

- To add a prefix, select the Prefix button. In the field immediately below, add your chosen prefix. The prefix will appear before the quote number on all your quotes.
- To create a pattern, select the Pattern button. In the pattern field, enter the custom variable of your choice. To view available options for custom patterns, click on the question mark icon at the right end of the Pattern field.

All your quotes will automatically display quote numbers according to your customized pattern.

.. TIP:: You can choose to integrate your quote numbers with the invoice number counter. This is an important feature as it allows you to keep the same number when converting a quote to an invoice. So, Quote-001 will automatically become Invoice-001. To number your quotes with your invoice numbering system, check the Share invoice counter button. To number your quotes separately, uncheck the Share invoice counter button.

Client Number
^^^^^^^^^^^^^

If you wish to use a numbering system for your clients, check the Enable box.

You can then define your client numbering system according to the Prefix or Pattern function.

- To add a prefix, select the Prefix button. In the field immediately below, add your chosen prefix. The prefix will appear before the client number on all your invoices.
- To create a pattern, select the Pattern button. In the pattern field, enter the custom variable of your choice. To view available options for custom patterns, click on the question mark icon at the right end of the Pattern field.

Credit Number
^^^^^^^^^^^^^

If you wish to use a numbering system for your credits, check the Enable box.

You can then define your credit numbering system according to the Prefix or Pattern function.

- To add a prefix, select the Prefix button. In the field immediately below, add your chosen prefix. The prefix will appear before the credit number on all your invoices.
- To create a pattern, select the Pattern button. In the pattern field, enter the custom variable of your choice. To view available options for custom patterns, click on the question mark icon at the right end of the Pattern field.

Options
^^^^^^^

There are a few extra options provided to manage the generated numbering systems for your invoices. Click the Options tab to open them. Let's go through the options available:

- Padding: You can 'pad' your invoice numbers by adding as many zeros as you want before the invoice number. This gives you greater flexibility in your future invoicing numbers. To pad your invoice number, enter the amount of zeros you want to add before the invoice number. So if you want to add three zeros, enter the number 3.

- Recurring Prefix: You can choose to add a prefix to all your recurring invoice numbers. This can help you distinguish and organize your recurring invoices separately from your regular invoices. To add a prefix to recurring invoices, enter the prefix in the field.

- Reset Counter: If you want to define a time frame to periodically reset your invoice and quote number counters, you can do so by adjusting the frequency in the Reset Counter field. TIP: The default setting for your Reset Counter is set to Never. To change the setting, click the drop down menu and select a frequency from the list.

Custom Fields
"""""""""""""

You can create new fields for information that appears on your invoices by assigning new field values and labels in the Custom Fields section. All field changes will automatically appear in the PDF invoice.

Client Fields
^^^^^^^^^^^^^

To add fields to your client entries, click on the Client Fields tab.

You have the option of adding up to two new fields for client information. These will appear on the Client/Create and Client/Edit pages. When creating an invoice, the field name and information you entered for the client will be displayed in the Client details section of the PDF invoice.

Contact Fields
^^^^^^^^^^^^^^

To add fields to your contact entries, click on the Contact Fields tab.

You have the option of adding up to two new fields for contact information about your client. These will appear on the Client/Create and Client/Edit pages. When creating an invoice, the field name and information you entered for the contact will be displayed in the Client details section of the PDF invoice.

Company Fields
^^^^^^^^^^^^^^

To add fields to your company details, click on the Company Fields tab. Enter the Field Label and Field Value information in the relevant fields. The information you entered will automatically appear in the Company details section of the PDF invoice.

Product Fields
^^^^^^^^^^^^^^

To add fields to your product entries, click on the Product Fields tab.

You have the option of adding up to two new fields for product information. These will appear on the Product/Create and Product/Edit pages. When creating an invoice, the field name and information you entered for the product will appear in the Item section of the PDF invoice.

Invoice Fields
^^^^^^^^^^^^^^

Want to include customized information in your invoices? To add fields to your invoice entry, click on the Invoice Fields tab. Enter the new field name in the Field Label field. You can add one or two new invoice fields. The new fields will appear in the top part of the Create/Invoice page, and will automatically be included in the PDF invoice.

To add new invoice charge fields, go to the Surcharge Labels section. Enter the new charge in the fields provided. You can add one or two new surcharge fields. The new charge field/s will appear in the Invoice Subtotals section. Amounts entered into these fields during the Create or Edit Invoice process will automatically appear in the PDF invoice. To apply the Tax feature for the new charge, check the Charge taxes button.

Quote Settings
""""""""""""""

Want to convert accepted quotes into invoices at a click of a button? Check the Enable button and the auto convert function will apply. So, when a client approves a quote, it will automatically convert into a quote, saving you time and hassle.

.. TIP:: This feature is extra-helpful if you linked your quote and invoice number counters in the Invoice and Quote Numbers section of the Invoice Settings page.

To disable the auto convert function, uncheck the Enable button.

Defaults
""""""""

Set any customized default text you want to Invoice Terms, Invoice Footer, Quote Terms and Documents. The text you enter will appear in the relevant sections on all future invoices.

Completed all your Invoice Settings? Click the green Save button at the bottom of the page, and your customized changes will appear on all your invoices.
