Custom Modules
==============

Invoice Ninja support customs modules using https://github.com/nWidart/laravel-modules

Install Module
""""""""""""""

To install a module run:

.. code-block:: php

    php artisan module:install <vendor/module> --type=github

For example:

.. code-block:: php

    php artisan module:install invoiceninja/sprockets --type=github

You can check the current module status with:

.. code-block:: php

    php artisan module:list


Create Module
"""""""""""""

Run the following command to create a module:

.. code-block:: php

    php artisan ninja:make-module Inventory 'name:string,description:text'

You can make adjustments to the migration file and then run:

.. code-block:: php

    php artisan module:migrate Inventory

To create and migrate in one step add ``--migrate=true``

.. code-block:: php

    php artisan ninja:make-module Inventory 'name:string,description:text' --migrate=true

.. Tip:: You can specify the module icon by setting a value from http://fontawesome.io/icons/ for "icon" in modules.json.

Share Module
""""""""""""

To share your module create a new project on GitHub and then commit the code:

.. code-block:: php

    cd Modules/<module>
    git init
    git add .
    git commit -m "Initial commit"
    git remote add origin git@github.com:<vendor/module>.git
    git push -f origin master

.. Tip:: Add ``"type": "invoiceninja-module"`` to the composer.json file to help people find your module.

Finally, submit the project to https://packagist.org.
