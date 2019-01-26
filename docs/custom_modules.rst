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

Extending Core Views
""""""""""""""""""""

You can extend base views in various ways.  Currently, you can:

- dynamically include views on main entity pages by defining a view in the proper namespace and also defining the relation(s) needed on the core entity.

For example, to add fields to the Product model, you define a view in your module at Resources/views/products/edit.blade.php that displays the fields.  You then create a new configuration file under Config/ called relations.php with content such as:

.. code-block:: php

    <?php

    return [
        'product' => [
            'MyProductExtras' => function ($self) {
                return $self->hasOne('Modules\MyProductExtras\Models\MyProductExtras');
           }
        ],
    ];

The inverse relationship is defined locally in the module entity, e.g. MyProductExtras in the above example.


Settings
""""""""

If your module has settings, you can have them automatically added to the main settings page.  To do so, you need to:

- create a Blade template named 'settings.blade.php' under the /Resources folder;
- add whatever routes are needed to implement/save your settings.

.. Tip:: You can run the Artisan command ``ninja:make-module-settings`` to generate a stub settings template, and optionally add routes to your module routes.php.

Components
""""""""""

There are UI widgets that can be re-used as part of a custom module implementation.

To render the widget, use the fully-qualified class name anywhere above the @stack declaration:

.. code-block:: php

    @render('App\Http\ViewComponents\ComponentName', [$variables])

Depending on the widget, certain variables will need to be passed via the second parameter of the @render statement.

.. NOTE::  Any data required by the widget must be passed in @render statement.  This means the module developer must ensure to perform any data access in the controller and pass it into the enclosing view.

Currently, the following widgets exist:

**SimpleSelectComponent** ``App\Http\ViewComponents\SimpleSelectComponent``
  *Displays a select box for an entity*


    ================== ===========================================================
    Parameter          Parameter Details
    ================== ===========================================================
    entityType         * entity type
    items              * list of entities
    itemLabel          * attribute of item to use as primary field value
    fieldLabel         * label for the field
    secondaryItemLabel * attribute of item to display in conjunction with itemLabel;
                       * can be a reference to a JavaScript function;
                       * field name must begin with 'entity', e.g. 'entity.notes';
                       * defaults to null
    module             * name of module, if applicable;
                       * used to perform translation for localization;
                       * defaults to null
    selectId           * ID of the input;
                       * defaults to fieldLabel appended with '_id'
    ================== ===========================================================


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
