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

use App\Events\Client\ClientWasCreated;
use App\Events\Invoice\InvoiceWasMarkedSent;
use App\Events\User\UserCreated;
use App\Listeners\Client\CreatedClientActivity;
use App\Listeners\Invoice\CreateInvoiceInvitations;
use App\Listeners\SendVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        UserCreated::class => [
            SendVerificationNotification::class,
        ],

        // Clients
        ClientWasCreated::class => [
            CreatedClientActivity::class,
           // 'App\Listeners\SubscriptionListener@createdClient',
        ],
        'App\Events\ClientWasArchived' => [
            'App\Listeners\ActivityListener@archivedClient',
        ],
        'App\Events\ClientWasUpdated' => [
            'App\Listeners\SubscriptionListener@updatedClient',
        ],
        'App\Events\ClientWasDeleted' => [
            'App\Listeners\ActivityListener@deletedClient',
            'App\Listeners\SubscriptionListener@deletedClient',
            'App\Listeners\HistoryListener@deletedClient',
        ],
        'App\Events\ClientWasRestored' => [
            'App\Listeners\ActivityListener@restoredClient',
        ],

        //Invoices
        [
        InvoiceWasMarkedSent::class => [
            CreateInvoiceInvitations::class,
            ]
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array
     */
    protected $subscribe = [

    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        //
    }
}
