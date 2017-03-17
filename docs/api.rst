API
===

Invoice Ninja provides a REST based API, `click here <https://app.invoiceninja.com/api-docs#/>`_ to see the full list of methods available.

To access the API you first need to create a token using the "Tokens” page under "Advanced Settings”.

- **Zapier**: https://zapier.com/zapbook/invoice-ninja/
- **PHP SDK**: https://github.com/invoiceninja/sdk-php

.. NOTE:: Replace ninja.dev with https://app.invoiceninja.com to access a hosted account.

Reading Data
""""""""""""

Here’s an example of reading the list of clients using cURL from the command line.

  ``curl -X GET ninja.dev/api/v1/clients -H "X-Ninja-Token: TOKEN" -H "X-Requested-With: XMLHttpRequest"``

For invoices, quotes, tasks and payments simply change the object type. ie,

  ``curl -X GET ninja.dev/api/v1/invoices -H "X-Ninja-Token: TOKEN" -H "X-Requested-With: XMLHttpRequest"``

To load a single record specify the Id in the URL. Note: you can add ?invoice_number=0001 to search invoices by invoice number.

  ``curl -X GET ninja.dev/api/v1/invoices/1 -H "X-Ninja-Token: TOKEN" -H "X-Requested-With: XMLHttpRequest"``

You can download a PDF using the following URL

  ``curl -X GET ninja.dev/api/v1/download/1 -H "X-Ninja-Token: TOKEN" -H "X-Requested-With: XMLHttpRequest"``

Creating Data
"""""""""""""

Here’s an example of creating a client. Note that email address is a property of the client’s contact not the client itself.

  ``curl -X POST ninja.dev/api/v1/clients -H "Content-Type:application/json" -d '{"name":"Client","contact":{"email":"test@gmail.com"}}' -H "X-Ninja-Token: TOKEN" -H "X-Requested-With: XMLHttpRequest"``

You can also update a client by specifying a value for ‘id’. Next, here’s an example of creating an invoice.

  ``curl -X POST ninja.dev/api/v1/invoices -H "Content-Type:application/json" -d '{"client_id":"1", "invoice_items":[{"product_key": "ITEM", "notes":"Test", "cost":10, "qty":1}]}' -H "X-Ninja-Token: TOKEN" -H "X-Requested-With: XMLHttpRequest"``

If the product_key is set and matches an existing record the product fields will be auto-populated. If the email field is set then we’ll search for a matching client. If no matches are found a new client will be created. To email the invoice set email_invoice to true.

To set the invoice date field you can either use the "invoice_date” field passing a date formatted the same way the company is configured or use "invoice_date_sql” to pass a SQL formatted date (ie, "YYYY-MM-DD”).

Emailing Invoices
"""""""""""""""""

To email an invoice use the email_invoice command passing the id of the invoice.

  ``curl -X POST ninja.dev/api/v1/email_invoice -H "Content-Type:application/json" -d '{"id":1}' -H "X-Ninja-Token: TOKEN" -H "X-Requested-With: XMLHttpRequest"``

Optional Settings
"""""""""""""""""

The following are optional query parameter settings:

- **``serializer``**: Either array (the default) or json. If json is selected the data is returned using the `JSON API <http://jsonapi.org/>`_ format.
- **``include``**: A comma-separated list of nested relationships to include.
- **``client_id``**: If set the results will be filtered by the client.
- **``page``**: The page number of results to return when the results are paginated.
- **``per_page``**: The number of results to return per page.
- **``updated_at``**: Timestamp used as a filter to only show recently updated records.

Subscriptions
"""""""""""""

You can use subscriptions to have Invoice Ninja POST newly created records to a third-party application. To enable this feature you need to manually add a record to the subscriptions table. To determine the event_id find the associated EVENT_CREATE_ value from app/Constants.php.
