Update
======

.. Note:: We recommend backing up your database before updating the app.

To update the app you just need to copy over the latest code. The app tracks the current version in a file called version.txt, if it notices a change it loads ``/update`` to run the database migrations.

If the auto-update fails you can manually run the update with the following commands. Once completed add ``?clear_cache=true`` to the end of the URL to clear the application cache.

.. code-block:: shell

   composer dump-autoload --optimize
   php artisan optimize --force
   php artisan migrate
   php artisan db:seed --class=UpdateSeeder

Version 2.6
"""""""""""

Make sure the .env file includes ``APP_CIPHER=rijndael-128``

Version 2.5.1
"""""""""""""
Minimum PHP version is now 5.5.9

Version 2.0
"""""""""""

Copy .env.example to .env and set config settings

Set the app cipher to ``rijndael-256`` to support existing passwords

Check that ``/path/to/ninja/storage`` has 755 permissions and is owned by the webserver user
