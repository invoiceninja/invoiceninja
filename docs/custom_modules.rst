Custom Modules
==============

Invoice Ninja support customs modules using https://github.com/nWidart/laravel-modules

You can watch this `short video <https://www.youtube.com/watch?v=8jJ-PYuq85k>`_ for a quick overview of the feature.

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

Run the following command to create a CRUD module:

.. code-block:: php

    php artisan ninja:make-module <module> <fields>

.. code-block:: php

    php artisan ninja:make-module Inventory 'name:string,description:text'

To edit the migration before it's run add ``--migrate=false``

.. code-block:: php

    php artisan ninja:make-module <module> <fields> --migrate=false

After making adjustments to the migration file you can run:

.. code-block:: php

    php artisan module:migrate <module>


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
