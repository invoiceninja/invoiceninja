Install
=======

Thanks for taking the time to setup Invoice Ninja.

.. Note:: The applications requires PHP 7.0, 7.1 or 7.2 and MySQL.

Detailed Guides
^^^^^^^^^^^^^^^

- Ubuntu and Nginx: `websiteforstudents.com <https://websiteforstudents.com/install-invoiceninja-on-ubuntu-17-04-17-10-with-nginx-mariadb-and-php-support/>`_

- Ubuntu and Apache: `technerdservices.com <http://blog.technerdservices.com/index.php/2015/04/techpop-how-to-install-invoice-ninja-on-ubuntu-14-04/>`_

- Debian and Nginx: `rosehosting.com <https://www.rosehosting.com/blog/install-invoice-ninja-on-a-debian-7-vps/>`_

- CentOS and Nginx: `thishosting.rocks <https://thishosting.rocks/how-to-install-invoice-ninja-on-centos/>`_

- HostGator: `carlosthomas.net <https://carlosthomas.net/blog/2018/10/setup-invoice-ninja-on-hostgator-shared/>`_


Automatic Install/Update
^^^^^^^^^^^^^^^^^^^^^^^^

- Ansible: `github.com <https://github.com/invoiceninja/ansible-installer>`_

- Dockerfile: `docker.com <https://hub.docker.com/r/invoiceninja/invoiceninja/>`_

- Cloudron: `cloudron.io <https://cloudron.io/store/com.invoiceninja.cloudronapp.html>`_

- Softaculous: `softaculous.com <https://www.softaculous.com/apps/ecommerce/Invoice_Ninja>`_

.. Tip:: You can use `github.com/turbo124/Plane2Ninja <https://github.com/turbo124/Plane2Ninja>`_ to migrate your data from InvoicePlane.

Manual Install
^^^^^^^^^^^^^^

Step 1: Download the code
"""""""""""""""""""""""""

You can either download the zip file below or checkout the code from our GitHub repository. The zip includes all third party libraries whereas using GitHub requires you to use Composer to install the dependencies.

https://download.invoiceninja.com

.. Note:: All Pro and Enterprise features from our hosted app are included in both the zip file and the GitHub repository. We offer a $30 per year white-label license to remove our branding.

- Release Notes: `github.com/invoiceninja/invoiceninja/releases <https://github.com/invoiceninja/invoiceninja/releases>`_

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

See the guides listed above for detailed information on configuring Apache or Nginx.

Once you can access the site the initial setup screen will enable you to configure the database and email settings as well as create the initial admin user.

.. Tip:: To remove public/ from the URL map the webroot to the /public folder, alternatively you can uncomment ``RewriteRule ^(.*)$ public/$1 [L]`` in the .htaccess file. There is more info `here <https://www.invoiceninja.com/forums/topic/clean-4-4-3-self-hosted-install-url-configuration-clarification/#post-14186>`_.

Step 5: Configure the application
"""""""""""""""""""""""""""""""""

See the `details here <https://invoice-ninja.readthedocs.io/en/latest/configure.html>`_ for additional configuration options.

Step 6: Enable auto updates
"""""""""""""""""""""""""""

Use this `shell script <https://github.com/titan-fail/Ninja_Update>`_ to automate the update process.

You can run it as a daily cron to automatically keep your app up to date.

Troubleshooting
^^^^^^^^^^^^^^^

- Check your webserver log (ie, /var/log/apache2/error.log) and the application logs (storage/logs/laravel-error.log) for more details or set ``APP_DEBUG=true`` in .env
- If you see "Whoops, looks like something went wrong" this `blog post <https://bobcares.com/blog/laravel-something-went-wrong/>`_ may be helpful.
- To resolve ``[Symfony\Component\Debug\Exception\FatalErrorException] Class 'SomeClass' not found`` try running php artisan optimize
- To resolve ``file_put_contents(...): failed to open stream: Permission denied`` run ``chmod -R 777 storage`` then ``chmod -R 755 storage``
- If index.php is in the URL it likely means that mod_rewrite needs to be enabled.
- Running ``composer install`` and ``composer dump-autoload`` can sometimes help with composer problems.
- If you’re using a subdomain. ie, invoice.mycompany.com You will need to add ``RewriteBase /`` to public/.htaccess otherwise it may fail with ``Request exceeded the limit of 10 internal redirects due to probable configuration error.`` messages in the logs.
- Composer install error: ``Fatal error: Allowed memory size of...`` Try the following: ``php -d memory_limit=-1 /usr/local/bin/composer install``
- PHP Fatal error: ``Call to undefined method Illuminate\Support\Facades\Session::get()`` try deleting bootstrap/cache/services.php. If the file doesn't exist the steps `here <https://stackoverflow.com/a/37266353/497368>`_ may help.
- To support invoices with many line items you may need to increase the value of max_input_vars in the php.ini file.
- Some webservers run filtering software which can cause errors, you can test adding this code to your .htaccess file to test if it's related.

.. code-block:: shell

   <IfModule mod_security.c>
     SecFilterEngine Off
     SecFilterScanPOST Off
   </IfModule>
