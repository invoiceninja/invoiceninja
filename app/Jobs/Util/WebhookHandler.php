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

use App\Jobs\Util\SystemLogger;
use App\Jobs\Util\WebhookSingle;
use App\Libraries\MultiDB;
use App\Models\Client as ClientModel;
use App\Models\SystemLog;
use App\Models\Webhook;
use App\Transformers\ArraySerializer;
use App\Utils\Ninja;
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

    public $tries = 1; //number of retries

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
    {

        MultiDB::setDb($this->company->db);

        //If the company is disabled, or if on hosted, the user is not a paid hosted user return early
        if (! $this->company || $this->company->is_disabled || (Ninja::isHosted() && !$this->company->account->isPaidHostedClient())) {
            return true;
        }

        $subscriptions = Webhook::where('company_id', $this->company->id)
                                    ->where('event_id', $this->event_id)
                                    ->cursor()
                                    ->each(function ($subscription) {

            // $this->process($subscription);

            WebhookSingle::dispatch($subscription->id, $this->entity, $this->company->db, $this->includes);

        });

    }

    public function failed($exception)
    {
        
        nlog(print_r($exception->getMessage(), 1));

    }
}
