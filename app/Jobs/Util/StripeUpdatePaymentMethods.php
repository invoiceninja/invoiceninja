<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Util;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class StripeUpdatePaymentMethods implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

    public $tries = 1;

    public $timeout = 3600;
    
    public function __construct(public Company $company)
    {
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $cgs = CompanyGateway::query()
                            ->where('company_id', $this->company->id)
                            ->whereIn('gateway_key', $this->stripe_keys)
                            ->get();

        $cgs->each(function ($company_gateway) {
            $company_gateway->driver(new Client())->updateAllPaymentMethods();
        });
    }


    public function middleware(): array
    {
        return [(new WithoutOverlapping("stripe_update_payment_methods:{$this->company->company_key}"))->expireAfter($this->timeout + 60)->dontRelease()];
    }

    public function failed($exception)
    {
        nlog('Stripe update payment methods exception');
        nlog($exception->getMessage());
    }
}
