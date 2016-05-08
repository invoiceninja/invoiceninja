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
    
        // Clients
        'App\Events\ClientWasCreated' => [
            'App\Listeners\ActivityListener@createdClient',
            'App\Listeners\SubscriptionListener@createdClient',
        ],
        'App\Events\ClientWasArchived' => [
            'App\Listeners\ActivityListener@archivedClient',
        ],
        'App\Events\ClientWasDeleted' => [
            'App\Listeners\ActivityListener@deletedClient',
        ],
        'App\Events\ClientWasRestored' => [
            'App\Listeners\ActivityListener@restoredClient',
        ],

        // Invoices
        'App\Events\InvoiceWasCreated' => [
            'App\Listeners\ActivityListener@createdInvoice',
            'App\Listeners\SubscriptionListener@createdInvoice',
            'App\Listeners\InvoiceListener@createdInvoice',
        ],
        'App\Events\InvoiceWasUpdated' => [
            'App\Listeners\ActivityListener@updatedInvoice',
            'App\Listeners\InvoiceListener@updatedInvoice',
        ],
        'App\Events\InvoiceWasArchived' => [
            'App\Listeners\ActivityListener@archivedInvoice',
        ],
        'App\Events\InvoiceWasDeleted' => [
            'App\Listeners\ActivityListener@deletedInvoice',
            'App\Listeners\TaskListener@deletedInvoice',
        ],
        'App\Events\InvoiceWasRestored' => [
            'App\Listeners\ActivityListener@restoredInvoice',
        ],
        'App\Events\InvoiceWasEmailed' => [
            'App\Listeners\NotificationListener@emailedInvoice',
        ],
        'App\Events\InvoiceInvitationWasEmailed' => [
            'App\Listeners\ActivityListener@emailedInvoice',
        ],
        'App\Events\InvoiceInvitationWasViewed' => [
            'App\Listeners\ActivityListener@viewedInvoice',
            'App\Listeners\NotificationListener@viewedInvoice',
            'App\Listeners\InvoiceListener@viewedInvoice',
        ],

        // Quotes
        'App\Events\QuoteWasCreated' => [
            'App\Listeners\ActivityListener@createdQuote',
            'App\Listeners\SubscriptionListener@createdQuote',
        ],
        'App\Events\QuoteWasUpdated' => [
            'App\Listeners\ActivityListener@updatedQuote',
        ],
        'App\Events\QuoteWasArchived' => [
            'App\Listeners\ActivityListener@archivedQuote',
        ],
        'App\Events\QuoteWasDeleted' => [
            'App\Listeners\ActivityListener@deletedQuote',
        ],
        'App\Events\QuoteWasRestored' => [
            'App\Listeners\ActivityListener@restoredQuote',
        ],
        'App\Events\QuoteWasEmailed' => [
            'App\Listeners\NotificationListener@emailedQuote',
        ],
        'App\Events\QuoteInvitationWasEmailed' => [
            'App\Listeners\ActivityListener@emailedQuote',
        ],
        'App\Events\QuoteInvitationWasViewed' => [
            'App\Listeners\ActivityListener@viewedQuote',
            'App\Listeners\NotificationListener@viewedQuote',
            'App\Listeners\QuoteListener@viewedQuote',
        ],
        'App\Events\QuoteInvitationWasApproved' => [
            'App\Listeners\ActivityListener@approvedQuote',
            'App\Listeners\NotificationListener@approvedQuote',
        ],

        // Payments
        'App\Events\PaymentWasCreated' => [
            'App\Listeners\ActivityListener@createdPayment',
            'App\Listeners\SubscriptionListener@createdPayment',
            'App\Listeners\InvoiceListener@createdPayment',
            'App\Listeners\NotificationListener@createdPayment',
            'App\Listeners\AnalyticsListener@trackRevenue',
        ],
        'App\Events\PaymentWasArchived' => [
            'App\Listeners\ActivityListener@archivedPayment',
        ],
        'App\Events\PaymentWasDeleted' => [
            'App\Listeners\ActivityListener@deletedPayment',
            'App\Listeners\InvoiceListener@deletedPayment',
            'App\Listeners\CreditListener@deletedPayment',
        ],
        'App\Events\PaymentWasRestored' => [
            'App\Listeners\ActivityListener@restoredPayment',
            'App\Listeners\InvoiceListener@restoredPayment',
        ],

        // Credits
        'App\Events\CreditWasCreated' => [
            'App\Listeners\ActivityListener@createdCredit',
            'App\Listeners\SubscriptionListener@createdCredit',
        ],
        'App\Events\CreditWasArchived' => [
            'App\Listeners\ActivityListener@archivedCredit',
        ],
        'App\Events\CreditWasDeleted' => [
            'App\Listeners\ActivityListener@deletedCredit',
        ],
        'App\Events\CreditWasRestored' => [
            'App\Listeners\ActivityListener@restoredCredit',
        ],

        // User events
        'App\Events\UserSignedUp' => [
            'App\Listeners\HandleUserSignedUp',
        ],
        'App\Events\UserLoggedIn' => [
            'App\Listeners\HandleUserLoggedIn',
        ],
        'App\Events\UserSettingsChanged' => [
            'App\Listeners\HandleUserSettingsChanged',
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
