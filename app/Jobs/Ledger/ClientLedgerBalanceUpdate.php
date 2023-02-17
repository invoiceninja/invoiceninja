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

namespace App\Jobs\Ledger;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ClientLedgerBalanceUpdate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public $tries = 1;
    public $deleteWhenMissingModels = true;
    public function __construct(public Company $company, public Client $client)
    {
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle() :void
    {
        // nlog("Updating company ledger for client ". $this->client->id);

        MultiDB::setDb($this->company->db);

        CompanyLedger::where('balance', 0)->where('client_id', $this->client->id)->orderBy('updated_at', 'ASC')->cursor()->each(function ($company_ledger) {
            if ($company_ledger->balance == 0) {
                $last_record = CompanyLedger::where('client_id', $company_ledger->client_id)
                                ->where('company_id', $company_ledger->company_id)
                                ->where('balance', '!=', 0)
                                ->orderBy('id', 'DESC')
                                ->first();

                if (! $last_record) {
                    $last_record = CompanyLedger::where('client_id', $company_ledger->client_id)
                    ->where('company_id', $company_ledger->company_id)
                    ->orderBy('id', 'DESC')
                    ->first();
                }
            }

            $company_ledger->balance = $last_record->balance + $company_ledger->adjustment;
            $company_ledger->save();
        });
    }


    public function middleware()
    {
        return [(new WithoutOverlapping($this->client->id))->dontRelease()];
    }
}
