<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Providers;

use App\Models\Activity;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Expense;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Subscription;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Policies\ActivityPolicy;
use App\Policies\ClientPolicy;
use App\Policies\CompanyGatewayPolicy;
use App\Policies\CompanyPolicy;
use App\Policies\CompanyTokenPolicy;
use App\Policies\CreditPolicy;
use App\Policies\DesignPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\GroupSettingPolicy;
use App\Policies\InvoicePolicy;
use App\Policies\PaymentPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotePolicy;
use App\Policies\RecurringInvoicePolicy;
use App\Policies\RecurringQuotePolicy;
use App\Policies\SubscriptionPolicy;
use App\Policies\TaxRatePolicy;
use App\Policies\UserPolicy;
use App\Policies\VendorPolicy;
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
        Activity::class => ActivityPolicy::class,
        Client::class => ClientPolicy::class,
        Company::class => CompanyPolicy::class,
        CompanyToken::class => CompanyTokenPolicy::class,
        CompanyGateway::class => CompanyGatewayPolicy::class,
        Credit::class => CreditPolicy::class,
        Design::class => DesignPolicy::class,
        Expense::class => ExpensePolicy::class,
        GroupSetting::class => GroupSettingPolicy::class,
        Invoice::class => InvoicePolicy::class,
        Payment::class => PaymentPolicy::class,
        Product::class => ProductPolicy::class,
        Quote::class => QuotePolicy::class,
        RecurringInvoice::class => RecurringInvoicePolicy::class,
        RecurringQuote::class => RecurringQuotePolicy::class,
        Subscription::class => SubscriptionPolicy::class,
        TaxRate::class => TaxRatePolicy::class,
        User::class => UserPolicy::class,
        Vendor::class => VendorPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */

    public function boot()
    {
        $this->registerPolicies();
        /*
                Auth::provider('users', function ($app, array $config) {
                    return new MultiDatabaseUserProvider($this->app['hash'], $config['model']);
                });

                Auth::provider('contacts', function ($app, array $config) {
                    return new MultiDatabaseUserProvider($this->app['hash'], $config['model']);

                });
        */
        Gate::define('view-list', function ($user, $entity) {
            $entity = strtolower(class_basename($entity));

            return $user->hasPermission('view_' . $entity) || $user->isAdmin();
        });
    }
}
