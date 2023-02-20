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
use App\Models\Webhook;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class WebhookHandler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public $tries = 1; //number of retries

    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param $event_id
     * @param $entity
     */
    public function __construct(private int $event_id, private $entity, private Company $company, private string $includes = '')
    {
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

        Webhook::where('company_id', $this->company->id)
                ->where('event_id', $this->event_id)
                ->cursor()
                ->each(function ($subscription) {
                    WebhookSingle::dispatch($subscription->id, $this->entity, $this->company->db, $this->includes);
                });
    }

    public function failed($exception = null)
    {
    }
}
