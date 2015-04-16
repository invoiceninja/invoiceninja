<?php namespace App\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider {

	/**
	 * The event handler mappings for the application.
	 *
	 * @var array
	 */
	protected $listen = [
        'App\Events\UserLoggedIn' => [
            'App\Listeners\HandleUserLoggedIn',
        ],
        'App\Events\UserSettingsChanged' => [
            'App\Listeners\HandleUserSettingsChanged',
        ],
        'App\Events\InvoiceSent' => [
            'App\Listeners\HandleInvoiceSent',
        ],
        'App\Events\InvoiceViewed' => [
            'App\Listeners\HandleInvoiceViewed',
        ],
        'App\Events\InvoicePaid' => [
            'App\Listeners\HandleInvoicePaid',
        ],
        'App\Events\QuoteApproved' => [
            'App\Listeners\HandleQuoteApproved',
        ],
	];

	/**
	 * Register any other events for your application.
	 *
	 * @param  \Illuminate\Contracts\Events\Dispatcher  $events
	 * @return void
	 */
	public function boot(DispatcherContract $events)
	{
		parent::boot($events);

		//
	}

}
