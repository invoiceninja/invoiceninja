<?php namespace App\Providers;

use AltThree\Bus\Dispatcher;
use Illuminate\Support\ServiceProvider;

/**
 * Class BusServiceProvider
 */
class BusServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @param  Dispatcher $dispatcher
     * @return void
     */
    public function boot(Dispatcher $dispatcher)
    {
        $dispatcher->mapUsing(function ($command) {
            return Dispatcher::simpleMapping(
                $command, 'App\Commands', 'App\Handlers\Commands'
            );
        });
    }

    /**
     * Register any application services.
     */
    public function register()
    {
    }
}
