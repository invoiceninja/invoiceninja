API
===

Invoice Ninja provides a REST based API, `click here <https://app.invoiceninja.com/api-docs#/>`_ to see the full list of methods available.

To access the API you first need to create a token using the "Tokens” page under "Advanced Settings”.

- **Zapier** [hosted or self-host]: https://zapier.com/zapbook/invoice-ninja/
- **Integromat**: https://www.integromat.com/en/integrations/invoiceninja
- **PHP SDK**: https://github.com/invoiceninja/sdk-php
- **Zend Framework**: https://github.com/alexz707/InvoiceNinjaModule

.. NOTE:: Replace ninja.dev with https://app.invoiceninja.com to access a hosted account.

Reading Data
""""""""""""

Here’s an example of reading the list of clients using cURL from the command line.

.. code-block:: shell

  curl -X GET ninja.dev/api/v1/clients -H "X-Ninja-Token: TOKEN"

For invoices, quotes, tasks and payments simply change the object type.

.. code-block:: shell

  curl -X GET ninja.dev/api/v1/invoices -H "X-Ninja-Token: TOKEN"

You can search clients by their email address or id number and invoices by their invoice number.

.. code-block:: shell

  curl -X GET ninja.dev/api/v1/clients?email=<value> -H "X-Ninja-Token: TOKEN"
  curl -X GET ninja.dev/api/v1/clients?id_number=<value> -H "X-Ninja-Token: TOKEN"
  curl -X GET ninja.dev/api/v1/invoices?invoice_number=<value> -H "X-Ninja-Token: TOKEN"

To load a single record specify the Id in the URL.

.. code-block:: shell

  curl -X GET ninja.dev/api/v1/invoices/1 -H "X-Ninja-Token: TOKEN"

You can specify additional relationships to load using the ``include`` parameter.

.. code-block:: shell

  curl -X GET ninja.dev/api/v1/clients/1?include=invoices.invitations -H "X-Ninja-Token: TOKEN"

You can download a PDF using the following URL

.. code-block:: shell

  curl -X GET ninja.dev/api/v1/download/1 -H "X-Ninja-Token: TOKEN"

Optional Settings
"""""""""""""""""

The following are optional query parameter settings:

- ``serializer``: Either array (the default) or `JSON <http://jsonapi.org/>`_.
- ``include``: A comma-separated list of nested relationships to include.
- ``client_id``: If set the results will be filtered by the client.
- ``page``: The page number of results to return when the results are paginated.
- ``per_page``: The number of results to return per page.
- ``updated_at``: Timestamp used as a filter to only show recently updated records.

Creating Data
"""""""""""""

.. TIP:: Add ``-H "X-Requested-With: XMLHttpRequest"`` to see validation errors in the response.

Here’s an example of creating a client. Note that email address is a property of the client’s contact not the client itself.

.. code-block:: shell

  curl -X POST ninja.dev/api/v1/clients -H "Content-Type:application/json" \
    -d '{"name":"Client","contact":{"email":"test@example.com"}}' -H "X-Ninja-Token: TOKEN"

You can also update a client by specifying a value for ‘id’. Next, here’s an example of creating an invoice.

.. code-block:: shell

  curl -X POST ninja.dev/api/v1/invoices -H "Content-Type:application/json" \
    -d '{"client_id":"1", "invoice_items":[{"product_key": "ITEM", "notes":"Test", "cost":10, "qty":1}]}' \
    -H "X-Ninja-Token: TOKEN"

If the product_key is set and matches an existing record the product fields will be auto-populated. If the email field is set then we’ll search for a matching client. If no matches are found a new client will be created.

Options
^^^^^^^

The following options are available when creating an invoice.

- ``email_invoice``: Email the invoice to the client.
- ``auto_bill``: Attempt to auto-bill the invoice using stored payment methods or credits.
- ``paid``: Create a payment for the defined amount.

Updating Data
"""""""""""""

.. NOTE:: When updating a client it's important to include the contact ids.

.. code-block:: shell

  curl -X PUT ninja.dev/api/v1/clients/1 -H "Content-Type:application/json" \
    -d '{"name":"test", "contacts":[{"id": 1, "first_name": "test"}]}' \
    -H "X-Ninja-Token: TOKEN"

You can archive, delete or restore an entity by setting ``action`` in the request

.. code-block:: shell

  curl -X PUT ninja.dev/api/v1/invoices/1?action=archive \
    -H "X-Ninja-Token: TOKEN"

Emailing Invoices
"""""""""""""""""

To email an invoice use the email_invoice command passing the id of the invoice.

.. code-block:: shell

  curl -X POST ninja.dev/api/v1/email_invoice -d '{"id":1}' \
    -H "Content-Type:application/json" -H "X-Ninja-Token: TOKEN"

Subscriptions
"""""""""""""

You can use subscriptions to have Invoice Ninja POST newly created records to a third-party application. To enable this feature you need to manually add a record to the subscriptions table. To determine the event_id find the associated EVENT_CREATE_ value from app/Constants.php.
