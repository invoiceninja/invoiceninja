<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Util;

use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Client as ClientModel;
use App\Models\SystemLog;
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

    private $company;

    public $tries = 3; //number of retries

    public $backoff = 10; //seconds to wait until retry

    public $deleteWhenMissingModels = true;

    private string $includes;

    /**
     * Create a new job instance.
     *
     * @param $event_id
     * @param $entity
     */
    public function __construct($event_id, $entity, $company, $includes = '')
    {
        $this->event_id = $event_id;
        $this->entity = $entity;
        $this->company = $company;
        $this->includes = $includes;
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle()
    {//todo set multidb here

        MultiDB::setDb($this->company->db);

        if (! $this->company || $this->company->is_disabled) {
            return true;
        }

        $subscriptions = Webhook::where('company_id', $this->company->id)
                                    ->where('event_id', $this->event_id)
                                    ->get();

        if (! $subscriptions || $subscriptions->count() == 0) {
            return;
        }

        $subscriptions->each(function ($subscription) {
            $this->process($subscription);
        });
    }

    private function process($subscription)
    {
        $this->entity->refresh();

        // generate JSON data
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $manager->parseIncludes($this->includes);

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

        try {
            $response = $client->post($subscription->target_url, [
                RequestOptions::JSON => $data, // or 'json' => [...]
            ]);

            SystemLogger::dispatch(
                array_merge((array) $response, $data),
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_SUCCESS,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            );

            // if ($response->getStatusCode() == 410)
            //      return true; $subscription->delete();
        } catch (\Exception $e) {
            nlog($e->getMessage());

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_RESPONSE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company,
            );
        }
    }

    private function resolveClient()
    {
        //make sure it isn't an instance of the Client Model
        if ((! $this->entity instanceof ClientModel) && $this->entity->client()->exists()) {
            return $this->entity->client;
        }

        return $this->company->clients()->first();
    }

    public function failed($exception)
    {
        nlog(print_r($exception->getMessage(), 1));
    }
}
