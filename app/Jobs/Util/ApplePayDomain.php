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

use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\CompanyGateway;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ApplePayDomain implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private CompanyGateway $company_gateway;

    private string $db;

    private array $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

    public $tries = 1;

    public function __construct(CompanyGateway $company_gateway, string $db)
    {
        $this->db = $db;

        $this->company_gateway = $company_gateway;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDB($this->db);

        if (in_array($this->company_gateway->gateway_key, $this->stripe_keys)) {
            $domain = $this->getDomain();

            try {
                $this->company_gateway->driver()->setApplePayDomain($domain);
            } catch (\Exception $e) {
                nlog('failed to set Apple Domain with Stripe '.$e->getMessage());
            }
        }
    }

    private function getDomain()
    {
        $domain = '';

        if (Ninja::isHosted()) {
            if ($this->company_gateway->company->portal_mode == 'domain') {
                $domain = $this->company_gateway->company->portal_domain;
            } else {
                $domain = $this->company_gateway->company->subdomain.'.'.config('ninja.app_domain');
            }
        } else {
            $domain = config('ninja.app_url');
        }

        $parsed_url = parse_url($domain);

        return $parsed_url['host'];
    }
}
