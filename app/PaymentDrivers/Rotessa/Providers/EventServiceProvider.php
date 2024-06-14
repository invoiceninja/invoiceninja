<?php

namespace App\PaymentDrivers\Rotessa\Providers;

use App\PaymentDrivers\Rotessa\Events\CacheGateways as Event;
use App\PaymentDrivers\Rotessa\Listeners\CacheGateways as Listener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
     /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Event::class => [
           Listener::class,
        ],
    ];
}
