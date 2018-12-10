<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    use \App\Utils\Traits\MakesHash;
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
            $client = \App\Models\Client::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
            $client->load('contacts', 'primary_contact');

            return $client;

        });


        Route::bind('c', function ($value) {
            $client = \App\Models\Client::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
            $client->load('contacts', 'primary_contact');

            return $client;

        });


        Route::bind('invoice', function ($value) {
                return \App\Models\Invoice::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('payment', function ($value) {
            return \App\Models\Payment::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('product', function ($value) {
            return \App\Models\Product::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('company', function ($value) {
            return \App\Models\Company::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('account', function ($value) {
            return \App\Models\Account::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('client_contact', function ($value) {
            return \App\Models\ClientContact::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('client_location', function ($value) {
            return \App\Models\ClientLocation::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('expense', function ($value) {
            return \App\Models\Expense::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('invitation', function ($value) {
            return \App\Models\Invitation::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('task', function ($value) {
            return \App\Models\Task::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('tax_rate', function ($value) {
            return \App\Models\TaxRate::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
        });

        Route::bind('proposal', function ($value) {
            return \App\Models\Proposal::where('id', $this->decodePrimaryKey($value))->first() ?? abort(404);
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

        //
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
        Route::prefix('api')
             ->middleware('api')
             ->namespace($this->namespace)
             ->group(base_path('routes/api.php'));
    }
}
