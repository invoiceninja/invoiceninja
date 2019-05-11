<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Providers;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\User;
use App\Policies\ClientPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotePolicy;
use App\Policies\RecurringInvoicePolicy;
use App\Policies\RecurringQuotePolicy;
use Auth;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Client::class => ClientPolicy::class,
        Product::class => ProductPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        RecurringInvoice::class => RecurringInvoicePolicy::class,
        RecurringQuote::class => RecurringQuotePolicy::class,
        Quote::class => QuotePolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */

    public function boot()
    {
        $this->registerPolicies();

        Auth::provider('users', function ($app, array $config) {
            return new MultiDatabaseUserProvider($this->app['hash'], $config['model']);
        });

        Auth::provider('contacts', function ($app, array $config) {
            return new MultiDatabaseUserProvider($this->app['hash'], $config['model']);

        });

        Gate::define('view-list', function ($user, $entity) {

            $entity = strtolower(class_basename($entity));

                return $user->hasPermission('view_' . $entity) || $user->isAdmin();

        });

    }

}
