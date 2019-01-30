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

.. TIP:: One a module is installed it can enabled/disabled on Settings > Account Management


Create Module
"""""""""""""

Run the following command to create a CRUD module:

.. code-block:: php

    php artisan ninja:make-module <module> <fields>

For example:

.. code-block:: php

    php artisan ninja:make-module Inventory 'name:string,description:text'

To run the database migration use:

.. code-block:: php

    php artisan module:migrate <module>


.. Tip:: You can specify the module icon by setting a value from http://fontawesome.io/icons/ for "icon" in module.json.

There are two types of modules: you can either create a standard module which displays a list of a new entity type or you can create a blank module which adds functionality. For example, a custom integration with a third-party app. If you do not want an entry in the application navigation sidebar, add "no-sidebar": 1 to the custom module's module.json. 

If you're looking for a module to work on you can see suggested issues `listed here <https://github.com/invoiceninja/invoiceninja/issues?q=is%3Aissue+is%3Aopen+label%3A%22custom+module%22>`_.

.. NOTE:: Our module implemention is currenty being actively worked on, you can join the discussion on our Slack group: http://slack.invoiceninja.com/

Share Module
""""""""""""

To share your module create a new project on GitHub and then run the following code:

.. code-block:: php

    cd Modules/<module>
    git init
    git add .
    git commit -m "Initial commit"
    git remote add origin git@github.com:<vendor/module>.git
    git push -f origin master

.. Tip:: Add ``"type": "invoiceninja-module"`` to the composer.json file to help people find your module.

Finally, submit the project to https://packagist.org.
