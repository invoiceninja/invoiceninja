<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\SystemLog;
use App\Models\Webhook;
use App\Transformers\ArraySerializer;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use League\Fractal\Manager;
use League\Fractal\Resource\Item;

class WebhookSingle implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $entity;

    private string $db;

    private int $subscription_id;

    public $tries = 5; //number of retries

    public $deleteWhenMissingModels = true;

    private string $includes;

    private Company $company;
    
    /**
     * Create a new job instance.
     *
     * @param $event_id
     * @param $entity
     */
    public function __construct($subscription_id, $entity, $db, $includes = '')
    {
        $this->entity = $entity;
        $this->db = $db;
        $this->includes = $includes;
        $this->subscription_id = $subscription_id;
    }

    public function backoff()
    {
        return [10, 30, 60, 180, 3600];
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle()
    {
        MultiDB::setDb($this->db);

        $subscription = Webhook::with('company')->find($this->subscription_id);

        if ($subscription) {
            nlog("firing event ID {$subscription->event_id} company_id {$subscription->company_id}");
        }
        
        if (!$subscription) {
            $this->fail();
            
            nlog("failed to fire event, could not find webhook ID {$this->subscription_id}");

            return;
        }

        $this->company = $subscription->company;

        $this->entity->refresh();

        // generate JSON data
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $manager->parseIncludes($this->includes);

        $class = sprintf('App\\Transformers\\%sTransformer', class_basename($this->entity));

        $transformer = new $class();

        $resource = new Item($this->entity, $transformer, $this->entity->getEntityType());
        $data = $manager->createData($resource)->toArray();
        
        $headers = is_array($subscription->headers) ? $subscription->headers : [];

        $this->postData($subscription, $data, $headers);
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

            (new SystemLogger(
                array_merge((array) $response, $data),
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_SUCCESS,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch(\GuzzleHttp\Exception\ConnectException $e) {
            nlog("connection problem");
            nlog($e->getCode());
            nlog($e->getMessage());

            (new SystemLogger(
                ['message' => "Error connecting to ". $subscription->target_url],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch (BadResponseException $e) {
            if ($e->getResponse()->getStatusCode() >= 400 && $e->getResponse()->getStatusCode() < 500) {
                $message = "There was a problem when connecting to {$subscription->target_url} => status code ". $e->getResponse()->getStatusCode();
                
                nlog($message);

                (new SystemLogger(
                    ['message' => $message],
                    SystemLog::CATEGORY_WEBHOOK,
                    SystemLog::EVENT_WEBHOOK_FAILURE,
                    SystemLog::TYPE_WEBHOOK_RESPONSE,
                    $this->resolveClient(),
                    $this->company
                ))->handle();

                /* Some 400's should never be repeated */
                if (in_array($e->getResponse()->getStatusCode(), [404, 410])) {
                    $this->fail();
                    return;
                }

                $this->release($this->backoff()[$this->attempts()-1]);
            }

            if ($e->getResponse()->getStatusCode() >= 500) {
                nlog("endpoint returned a 500, failing");

                $message = "The was a problem when connecting to {$subscription->target_url} => status code ". $e->getResponse()->getStatusCode(). " no retry attempted.";

                (new SystemLogger(
                    ['message' => $message],
                    SystemLog::CATEGORY_WEBHOOK,
                    SystemLog::EVENT_WEBHOOK_FAILURE,
                    SystemLog::TYPE_WEBHOOK_RESPONSE,
                    $this->resolveClient(),
                    $this->company
                ))->handle();

                $this->fail();
                return;
            }
        } catch (ServerException $e) {
            nlog("Server exception");
            $error = json_decode($e->getResponse()->getBody()->getContents());

            (new SystemLogger(
                ['message' => $error],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch (ClientException $e) {
            nlog("Client exception");
            $error = json_decode($e->getResponse()->getBody()->getContents());

            (new SystemLogger(
                ['message' => $error],
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company
            ))->handle();
        } catch (\Exception $e) {
            nlog("Exception handler => " . $e->getMessage());
            nlog($e->getCode());

            (new SystemLogger(
                $e->getMessage(),
                SystemLog::CATEGORY_WEBHOOK,
                SystemLog::EVENT_WEBHOOK_FAILURE,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->resolveClient(),
                $this->company,
            ))->handle();

            $this->release($this->backoff()[$this->attempts()-1]);
        }
    }

    private function resolveClient()
    {
        //make sure it isn't an instance of the Client Model
        if (!$this->entity instanceof \App\Models\Client &&
            !$this->entity instanceof \App\Models\Vendor &&
            !$this->entity instanceof \App\Models\Product &&
            !$this->entity instanceof \App\Models\PurchaseOrder &&
            $this->entity->client()->exists()) {
            return $this->entity->client;
        }

        return null;
    }

    public function failed($exception = null)
    {
        config(['queue.failed.driver' => null]);
    }
}
