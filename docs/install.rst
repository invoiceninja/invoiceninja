Install
=======

Thanks for taking the time to setup Invoice Ninja.

.. Note:: The applications requires PHP >= 5.5.9 and MySQL.

Detailed Guides
^^^^^^^^^^^^^^^

- Ubuntu and Apache: `technerdservices.com <http://blog.technerdservices.com/index.php/2015/04/techpop-how-to-install-invoice-ninja-on-ubuntu-14-04/>`_

- Debian and Nginx: `rosehosting.com <https://www.rosehosting.com/blog/install-invoice-ninja-on-a-debian-7-vps/>`_

- CentOS, Nginx, MariaDB and PHP 7: `thishosting.rocks <https://thishosting.rocks/how-to-install-invoice-ninja-on-centos/>`_

Automated Installers
^^^^^^^^^^^^^^^^^^^^

- Dockerfile: `github.com/invoiceninja/dockerfiles <https://github.com/invoiceninja/dockerfiles>`_

- Softaculous: `softaculous.com <https://www.softaculous.com/apps/ecommerce/Invoice_Ninja>`_

.. Tip:: You can use `github.com/turbo124/Plane2Ninja <https://github.com/turbo124/Plane2Ninja>`_ to migrate your data from InvoicePlane.

Steps to Install
^^^^^^^^^^^^^^^^

Step 1: Download the code
"""""""""""""""""""""""""

You can either download the zip file below or checkout the code from our GitHub repository. The zip includes all third party libraries whereas using GitHub requires you to use Composer to install the dependencies.

https://download.invoiceninja.com

.. Note:: All Pro and Enterprise features from our hosted app are included in both the zip file and the GitHub repository. We offer a $20 per year white-label license to remove our branding.

- Release Notes: `github.com/invoiceninja/invoiceninja/releases <https://github.com/invoiceninja/invoiceninja/releases>`_

- Roadmap: `trello.com/b/63BbiVVe/invoice-ninja <https://trello.com/b/63BbiVVe/invoice-ninja>`_

Step 2: Upload the code to your server
""""""""""""""""""""""""""""""""""""""

Copy the ZIP file to your server and then check that the storage folder has 755 permissions and is owned by the webserver user.

.. code-block:: shell

   cd /path/to/ninja/code
   chmod -R 755 storage
   sudo chown -R www-data:www-data storage bootstrap public/logo

Step 3: Setup the database
""""""""""""""""""""""""""

You’ll need to create a new database along with a user to access it. Most hosting companies provide an interface to handle this or you can run the SQL statements below.

.. code-block:: shell

   CREATE DATABASE ninja;
   CREATE USER 'ninja'@'localhost' IDENTIFIED BY 'ninja';
   GRANT ALL PRIVILEGES ON ninja.* TO 'ninja'@'localhost';

Step 4: Configure the web server
""""""""""""""""""""""""""""""""

Please see these guides for detailed information on configuring Apache or Nginx.

Once you can access the site the initial setup screen will enable you to configure the database and email settings as well as create the initial admin user.

.. Tip:: To remove public/ from the URL map the webroot to the /public folder, alternatively you can uncomment ``RewriteRule ^(.*)$ public/$1 [L]`` in the .htaccess file.

Troubleshooting
^^^^^^^^^^^^^^^

- Check your webserver log (ie, /var/log/apache2/error.log) and the application logs (storage/logs/laravel-error.log) for more details or set ``APP_DEBUG=true`` in .env
- To resolve ``[Symfony\Component\Debug\Exception\FatalErrorException] Class 'SomeClass' not found`` try running php artisan optimize
- To resolve ``file_put_contents(...): failed to open stream: Permission denied`` run ``chmod -R 777 storage`` then ``chmod -R 755 storage``
- If index.php is in the URL it likely means that mod_rewrite needs to be enabled.
- Running ``composer install`` and ``composer dump-autoload`` can sometimes help with composer problems.
- If you’re using a subdomain. ie, invoice.mycompany.com You will need to add ``RewriteBase /`` to public/.htaccess otherwise it may fail with ``Request exceeded the limit of 10 internal redirects due to probable configuration error.`` messages in the logs.
- Composer install error: ``Fatal error: Allowed memory size of...`` Try the following: ``php -d memory_limit=-1 /usr/local/bin/composer install``
- PHP Fatal error: ``Call to undefined method Illuminate\Support\Facades\Session::get()`` try deleting bootstrap/cache/services.php
