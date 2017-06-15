Configure
=========

Review the `.env.example <https://github.com/invoiceninja/invoiceninja/blob/master/.env.example>`_ file to see additional settings.

Recurring invoices and reminder emails
""""""""""""""""""""""""""""""""""""""

Create a cron to call the ``ninja:send-invoices`` and ``ninja:send-reminders`` Artisan commands **once daily**.

.. code-block:: shell

   0 8 * * * /usr/local/bin/php /path/to/ninja/artisan ninja:send-invoices
   0 8 * * * /usr/local/bin/php /path/to/ninja/artisan ninja:send-reminders

Email Queues
""""""""""""

When sending an email in the app the default behavior is to wait for the response, you can use queues to improve the perceived performance. To enable the feature add ``QUEUE_DRIVER=database`` or ``QUEUE_DRIVER=redis`` to the .env file.

.. Note:: You can process the jobs by running ``php artisan queue:listen`` or ``php artisan queue:work --daemon``.

Postmark bounce and open notifications
""""""""""""""""""""""""""""""""""""""

Include the following two setting in the .env file, the rest of the email settings can be commented out.

.. code-block:: shell

   POSTMARK_API_TOKEN=
   MAIL_FROM_ADDRESS=

In your Postmark account settings make sure ‘Open tracking’ is enabled and enter the following values under Settings > Outbound.

- Bounce webhook: https://invoices.example.com/hook/email_bounced
- Open webhook: https://invoices.example.com/hook/email_opened

Social/One-Click Login
""""""""""""""""""""""

Create an application in either Google, Facebook, GitHub or LinkedIn and then set the client id, secret and redirect URL in the .env file. For example:

.. code-block:: shell

   GOOGLE_CLIENT_ID=
   GOOGLE_CLIENT_SECRET=
   GOOGLE_OAUTH_REDIRECT=http://ninja.dev/auth/google

PhantomJS
"""""""""

There are two methods to attach PDFs to emails sent by background processes: phantomjscloud.com or local PhantomJS install.

To use phantomjscloud.com check for the following line in the .env file.

.. code-block:: shell

  PHANTOMJS_CLOUD_KEY='a-demo-key-with-low-quota-per-ip-address'

To use a local PhantomJS install add ``PHANTOMJS_BIN_PATH=/usr/local/bin/phantomjs``.

Troubleshooting
---------------

- Check storage/logs/laravel-error.log for relevant errors.
- To determine the path you can run ``which phantomjs`` from the command line.
- We suggest using PhantomJS version >= 2.1.1, users have reported seeing 'Error: 0' with older versions.
- You can use `this script <https://raw.githubusercontent.com/invoiceninja/invoiceninja/develop/resources/test.pjs>`_ to test from the command line, change ``__YOUR_LINK_HERE__`` to a 'View as recipient' link.
- If you require contacts to enter a password to see their invoice you'll need to set a random value for ``PHANTOMJS_SECRET``.

Custom Fonts
""""""""""""

Follow these steps to add custom ttf fonts: ie, `Google fonts <https://www.google.com/get/noto/>`_

- Create a new folder in ``public/fonts/invoice-fonts/`` and copy over the ttf files
- Run ``grunt dump_dir``
- Add the font to ``database/seeds/FontsSeeder.php``
- Run ``php artisan db:seed --class=FontsSeeder``
- Clear the cache by adding ``?clear_cache=true`` to the end of the URL

Omnipay
"""""""

We use `Omnipay <https://github.com/thephpleague/omnipay-braintree>`_ to support our payment gateway integrations.

Follow these steps to add a driver.

- Add the package to composer.json and then run ``composer install``
- Add a row to the gateways table. ``name`` is used in the gateway select, ``provider`` needs to match the Omnipay driver name
- Clear the cache by adding ``?clear_cache=true`` to the end of the URL

.. NOTE:: Most drivers also require `code changes <https://github.com/invoiceninja/invoiceninja/tree/master/app/Ninja/PaymentDrivers>`_ to work correctly.

Google Map
""""""""""

You need to create a `Google Maps API <https://developers.google.com/maps/documentation/javascript/get-api-key>`_ key for the Javascript, Geocoding and Embed APIs and then add ``GOOGLE_MAPS_API_KEY=your_key`` to the .env file.

You can disable the feature by adding ``GOOGLE_MAPS_ENABLED=false`` to the .env file.

Voice Commands
""""""""""""""

Supporting voice commands requires creating a `LUIS.ai <https://www.luis.ai/home/index>`_ app, once the app is created you can import this `model file <https://download.invoiceninja.com/luis.json>`_.

You'll also need to set the following values in the .env file.

.. code-block:: shell

   SPEECH_ENABLED=true
   MSBOT_LUIS_APP_ID=...
   MSBOT_LUIS_SUBSCRIPTION_KEY=...

Using a Proxy
"""""""""""""

If you need to set a list of trusted proxies you can add a TRUSTED_PROXIES value in the .env file. ie,

.. code-block:: shell

   TRUSTED_PROXIES='10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'

Stay logged in
""""""""""""""

By default the app clears the session when the browser is closed and automatically logs the user out after 8 hours.

This can be modified by setting ``REMEMBER_ME_ENABLED`` and ``AUTO_LOGOUT_SECONDS`` in the .env file.

Customizations
""""""""""""""

Our `developer guide <https://www.invoiceninja.com/knowledgebase/developer-guide/>`_ has more details about our application’s codebase.

You can add currencies and date/time formats by adding records to their respective tables in the database. This data is cached, to clear it load any page with ``?clear_cache=true`` added to the end of the URL.

The JavaScript and CSS files are compiled to built files, you can recompile them by running bower install and then ``gulp``.
