<?php

namespace App\Jobs\Util;

use App\Models\Webhook;
use App\Transformers\ArraySerializer;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

class WebhookHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $entity;

    private $event_id;

    /**
     * Create a new job instance.
     *
     * @param $event_id
     * @param $entity
     */
    public function __construct($event_id, $entity)
    {
        $this->event_id = $event_id;
        $this->entity = $entity;
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle() :bool
    {
        if (! $this->entity->company || $this->entity->company->company_users->first()->is_migrating == true) {
            return true;
        }

        $subscriptions = Webhook::where('company_id', $this->entity->company_id)
                                    ->where('event_id', $this->event_id)
                                    ->get();

        if (! $subscriptions || $subscriptions->count() == 0) {
            return true;
        }

        $subscriptions->each(function ($subscription) {
            $this->process($subscription);
        });

        return true;
    }

    private function process($subscription)
    {
        // generate JSON data
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());

        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->entity));

        $transformer = new $class();

        $resource = new Item($this->entity, $transformer, $this->entity->getEntityType());
        $data = $manager->createData($resource)->toArray();

        $this->postData($subscription, $data, []);
    }

    private function postData($subscription, $data, $headers = [])
    {
        $base_headers = [
            'Content-Length' => strlen(json_encode($data)),
            'Accept'         => 'application/json',
        ];

        $client = new Client(['headers' => array_merge($base_headers, $headers)]);

        $response = $client->post($subscription->target_url, [
                        RequestOptions::JSON => $data, // or 'json' => [...]
                    ]);

        if ($response->getStatusCode() == 410 || $response->getStatusCode() == 200) {
            $subscription->delete();
        }
    }

    public function failed($exception)
    {
        info(print_r($exception->getMessage(), 1));
    }
}
