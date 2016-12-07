Custom Modules
==============

Invoice Ninja support customs modules using https://github.com/nWidart/laravel-modules

Install Module
""""""""""""""

To install a module run:

.. code-block:: ruby

    php artisan module:install <vendor/module> --type=github

For example:

.. code-block:: ruby

    php artisan module:install invoiceninja/sprockets --type=github

You can check the current module status with:

.. code-block:: ruby

    php artisan module:list


Create Module
"""""""""""""

Run the following command to create a module:

.. code-block:: ruby

    php artisan module:make <module>

.. Note:: To use the standard app layout change the top of Modules/<module>/Resources/views/index.blade.php to ``@extends('header')``

.. Tip:: You can specify the module icon by setting a value from http://fontawesome.io/icons/ for "icon" in modules.json.

Share Module
""""""""""""

To share your module create a new project on GitHub and then commit the code:

.. code-block:: ruby

    cd Modules/<module>
    git init
    git add .
    git commit -m "Initial commit"
    git remote add origin git@github.com:<vendor/module>.git
    git push -f origin master

.. Tip:: Add ``"type": "invoiceninja-module"`` to the composer.json file to help people find your module.

Finally, submit the project to https://packagist.org. 
