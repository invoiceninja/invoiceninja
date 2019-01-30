Update
======

.. NOTE:: We recommend backing up your database with mysqldump before updating the app.

To update the app you just need to copy over the latest code. The app tracks the current version in a file called version.txt, if it notices a change it loads ``/update`` to run the database migrations.

https://download.invoiceninja.com

If you have trouble updating you can manually load /update to check for errors.

.. TIP:: We recommend using this `shell script <https://pastebin.com/j657uv9A>`_ to automate the update process, run it as a daily cron to automatically keep your app up to date.

If you're moving servers make sure to copy over the .env file.

You can manually run the update with the following commands. Once completed add ``?clear_cache=true`` to the end of the URL to clear the application cache.

.. code-block:: shell

   composer dump-autoload --optimize
   php artisan optimize --force
   php artisan migrate
   php artisan db:seed --class=UpdateSeeder

A common error with shared hosting is "open_basedir restriction in effect", if you see this you'll need to either temporarily modify your open_basedir settings or run the update from the command line.

.. NOTE:: If you've downloaded the code from GitHub you also need to run ``composer install``

.. TIP:: You can see the detailed changes for each release on our `GitHub release notes <https://github.com/invoiceninja/invoiceninja/releases>`_.

Version 4.3
"""""""""""

You may need to manually delete ``bootstrap/cache/compiled.php``.

Version 4.0
"""""""""""

The minimum PHP version is now 7.0.0

If you're using a rijndael cipher run ``php artisan ninja:update-key --legacy=true`` to change to AES-256-CBC.

Version 3.2
"""""""""""

An import folder has been adding to storage/, you may need to run ``sudo chown -R www-data:www-data storage``

Version 2.5
"""""""""""

The minimum PHP version is now 5.5.9

Version 2.0
"""""""""""

Copy .env.example to .env and set config settings

If unset, set the app cipher to ``rijndael-256``.

Check that ``/path/to/ninja/storage`` has 755 permissions and is owned by the webserver user
