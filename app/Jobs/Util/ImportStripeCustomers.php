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

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\CompanyGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportStripeCustomers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $company;

    private $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param $event_id
     * @param $entity
     */
    public function __construct($company)
    {
        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $cgs = CompanyGateway::where('company_id', $this->company->id)
                            ->where('is_deleted', 0)
                            ->whereIn('gateway_key', $this->stripe_keys)
                            ->get();

        $cgs->each(function ($company_gateway) {
            $company_gateway->driver(new Client)->importCustomers();
        });
    }

    public function failed($exception)
    {
        nlog('Stripe import customer methods exception');
        nlog($exception->getMessage());
    }
}
