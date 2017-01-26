Configure
=========

Review the `.env.example <https://github.com/invoiceninja/invoiceninja/blob/master/.env.example>`_ file to see additional settings.

Recurring invoices and reminder emails
""""""""""""""""""""""""""""""""""""""

Create a cron to call the ninja:send-invoices and ninja:send-reminders Artisan commands once daily.

.. code-block:: shell

   0 8 * * * /usr/local/bin/php /path/to/ninja/artisan ninja:send-invoices
   0 8 * * * /usr/local/bin/php /path/to/ninja/artisan ninja:send-reminders

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

We use phantomjscloud.com to attach PDFs to emails sent by background processes. Check for the following line in the .env file to enable this feature or sign up to increase your daily limit.

.. code-block:: shell

   PHANTOMJS_CLOUD_KEY='a-demo-key-with-low-quota-per-ip-address'

You can install PhantomJS to generate the file locally, to enable it add ``PHANTOMJS_BIN_PATH=/usr/local/bin/phantomjs``. To determine the path you can run ``which phantomjs`` from the command line.

Custom Fonts
""""""""""""

Follow these steps to add custom ttf fonts: ie, Google fonts

- Create a new folder in ``public/fonts/invoice-fonts/`` and copy over the ttf files
- Run ``grunt dump_dir``
- Add the font to ``database/seeds/FontsSeeder.php``
- Run ``php artisan db:seed --class=FontsSeeder``
- Clear the cache by adding ``?clear_cache=true`` to the end of the URL

Google Map
""""""""""

You need to create a Google Maps API key for the Javascript, Geocoding and Embed APIs and then add ``GOOGLE_MAPS_API_KEY=your_key`` to the .env file.

Using a Proxy
"""""""""""""

If you need to set a list of trusted proxies you can add a TRUSTED_PROXIES value in the .env file. ie,

.. code-block:: shell

   TRUSTED_PROXIES='10.0.0.0/8,172.16.0.0/12,192.168.0.0/16'

Customizations
""""""""""""""

Our `developer guide <https://www.invoiceninja.com/knowledgebase/developer-guide/>`_ has more details about our application’s codebase.

You can add currencies and date/time formats by adding records to their respective tables in the database. This data is cached, to clear it load any page with ``?clear_cache=true`` added to the end of the URL.

The JavaScript and CSS files are compiled to built files, you can recompile them by running bower install and then ``gulp``.
