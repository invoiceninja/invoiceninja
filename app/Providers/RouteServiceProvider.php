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

use App\Models\GroupSetting;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    use MakesHash;
    /**
     * This namespace is applied to your controller routes.
     *
     * In addition, it is set as the URL generator's root namespace.
     *
     * @var string
     */
    protected $namespace = 'App\Http\Controllers';

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        //           


        parent::boot();

        Route::bind('client', function ($value) {
            $client = \App\Models\Client::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
            return $client;
        });

        Route::bind('user', function ($value) {
            $user = \App\Models\User::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
            return $user;
        });

        Route::bind('invoice', function ($value) {
            return \App\Models\Invoice::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('recurring_invoice', function ($value) {
            return \App\Models\RecurringInvoice::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('recurring_quote', function ($value) {
            return \App\Models\RecurringQuote::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('quote', function ($value) {
            return \App\Models\Quote::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('payment', function ($value) {
            return \App\Models\Payment::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('product', function ($value) {
            return \App\Models\Product::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('company', function ($value) {
            return \App\Models\Company::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('company_gateway', function ($value) {
            return \App\Models\CompanyGateway::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('companies', function ($value) {
            return \App\Models\Company::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });
    
        Route::bind('account', function ($value) {
            return \App\Models\Account::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('client_contact', function ($value) {
            return \App\Models\ClientContact::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('expense', function ($value) {
            return \App\Models\Expense::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('invoice_invitation', function ($value) {
            return \App\Models\InvoiceInvitation::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('recurring_invoice_invitation', function ($value) {
            return \App\Models\RecurringInvoiceInvitation::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('quote_invitation', function ($value) {
            return \App\Models\QuoteInvitation::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('task', function ($value) {
            return \App\Models\Task::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('tax_rate', function ($value) {
            return \App\Models\TaxRate::withTrashed()->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('proposal', function ($value) {
            return \App\Models\Proposal::where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        Route::bind('groupo_setting', function ($value) {
            return \App\Models\GroupSetting::where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });



    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapContactApiRoutes();

        $this->mapClientApiRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->namespace($this->namespace)
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }
    
   /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapContactApiRoutes()
    {
        Route::prefix('')
             ->middleware('contact')
             ->namespace($this->namespace)
             ->group(base_path('routes/contact.php'));
    }

    /**
     * Define the "client" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapClientApiRoutes()
    {
        Route::prefix('')
             ->middleware('client')
             ->namespace($this->namespace)
             ->group(base_path('routes/client.php'));
    }
    
}
