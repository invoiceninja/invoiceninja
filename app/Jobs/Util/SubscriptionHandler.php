<?php

namespace App\Jobs\Util;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Subscription;

class SubscriptionHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $entity;

    private $event_id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    
    public function __construct($event_id, $entity)
    {
        $this->event_id = $event_id;
        $this->entity = $entity;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $subscriptions = Subscription::where('company_id', $this->entity->company_id)
                                    ->where('event_id', $this->event_id)
                                    ->get();

        if(!$subscriptions)
            return;

        $subscriptions->each(function($subscription) {
            $this->process($subscription);
        });
    }

    private function process($subscription)
    {

    }
}
