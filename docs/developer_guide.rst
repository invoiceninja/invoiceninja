Developer Guide
===============

This guide will provide an overview of Invoice Ninja. If anything’s unclear please send us an email, we’re always working to improve it.

The application is written in PHP using the `Laravel <http://laravel.com/>`_ framework, the full list of libraries can be found on our `GitHub <https://github.com/invoiceninja/invoiceninja>`_ page.

If you’re running the app for your own use you can white label the client portal and emails by purchasing an annual white label license from within the application. If you’d like to white label the admin pages to re-sell the application please send us an email to learn about our `affiliate program <https://github.com/invoiceninja/invoiceninja#affiliates-programs>`_.

We try to follow the `PSR-2 <http://www.php-fig.org/psr/psr-2/>`_ style guidelines and are using the `Git-Flow <http://nvie.com/posts/a-successful-git-branching-model/>`_ model of branching and releasing, please create pull requests against the develop branch.

Code
""""

When setting up the app you can choose to either use the self hosted zip or checkout the code from GitHub. The zip includes all third party libraries, whereas checking out the code from GitHub requires using Composer and Bower.

We use Gulp to concatenate the JavasScript and CSS files. After making any changes you need to run gulp to re-generate the files.

Most of the system tables are cached (ie, currencies, languages, etc). If you make any changes you need to clear the cache either by loading any page with ?clear_cache=true added at the end of the URL.

Database
""""""""

The following are the main entities, you can browse the `app/Models <https://github.com/invoiceninja/invoiceninja/tree/master/app/Models>`_ folder for the complete list.

- Accounts +users
- Clients +contacts
- Invoices +invoice_items
- Payments
- Credits

The best places to start when reviewing the code are `app/Http/routes.php <https://github.com/invoiceninja/invoiceninja/blob/master/app/Http/routes.php>`_ and `app/Providers/EventServiceProvider.php <https://github.com/invoiceninja/invoiceninja/blob/master/app/Providers/EventServiceProvider.php>`_.

To enable each account to have it’s own incrementing Ids (ie, /clients/1) all account entity classes extend the custom EntityModel.php class. This gives each entity a public_id field. You can read more about it in `this post <http://hillelcoren.com/2014/02/11/friendly-urls-with-per-account-incrementing-ids-in-laravel/>`_.

All actions are tracked in the activities table. Example of actions are creating a client, viewing an invoice or entering a payment. This is implemented using Laravel model events. An example can be seen at the bottom of `app/Models/Invoice.php <https://github.com/invoiceninja/invoiceninja/blob/master/app/Models/Invoice.php>`_.

Laravel supplies `soft delete <http://laravel.com/docs/4.2/eloquent#soft-deleting>`_ functionality, however in order to ensure referential integrity records are only deleted when a user cancels their account. To support this we’ve added an is_deleted field. When the deleted_at field is set the entity has been archived, when is_deleted is true the entity has been deleted.

Automated Tests
"""""""""""""""

To run the `Codeception <http://codeception.com/>`_ tests you’ll need to install `PhantomJS <http://phantomjs.org/>`_.

- Create config file: ``cp tests/_bootstrap.php.default tests/_bootstrap.php``
- Create test user: ``php artisan db:seed --class=UserTableSeeder``
- Start the PhantomJS web server: ``phantomjs --webdriver=4444``
- Run the tests: ``sudo ./vendor/codeception/codeception/codecept run --debug``
