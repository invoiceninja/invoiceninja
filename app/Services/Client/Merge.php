<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Client;

use App\Factory\CompanyLedgerFactory;
use App\Models\Activity;
use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class Merge extends AbstractService
{

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function run(Client $mergable_client)
    {
        
        $this->client->balance += $mergable_client->balance;
        $this->client->paid_to_date += $mergable_client->paid_to_date;
        $this->client->save();

        $this->updateLedger($mergable_client->balance);

        $mergable_client->activities()->update(['client_id' => $this->client->id]);
        $mergable_client->contacts()->update(['client_id' => $this->client->id]);
        $mergable_client->gateway_tokens()->update(['client_id' => $this->client->id]);
        $mergable_client->credits()->update(['client_id' => $this->client->id]);
        $mergable_client->expenses()->update(['client_id' => $this->client->id]);
        $mergable_client->invoices()->update(['client_id' => $this->client->id]);
        $mergable_client->payments()->update(['client_id' => $this->client->id]);
        $mergable_client->projects()->update(['client_id' => $this->client->id]);
        $mergable_client->quotes()->update(['client_id' => $this->client->id]);
        $mergable_client->recurring_invoices()->update(['client_id' => $this->client->id]);
        $mergable_client->tasks()->update(['client_id' => $this->client->id]);
        $mergable_client->contacts()->update(['client_id' => $this->client->id]);
        $mergable_client->documents()->update(['client_id' => $this->client->id]);

        $mergable_client->forceDelete();

        return $this;
    }

    private function updateLedger($adjustment)
    {
        $balance = 0;

        $company_ledger = CompanyLedger::whereClientId($this->client->id)
                                ->orderBy('id', 'DESC')
                                ->first();
    
        $company_ledger = $this->ledger();

        if ($company_ledger) {
            $balance = $company_ledger->balance;
        }

        $company_ledger = CompanyLedgerFactory::create($this->client->company_id, $this->client->user_id);
        $company_ledger->client_id = $this->client->id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->notes = "Balance update after merging " . $mergable_client->present()->name();
        $company_ledger->balance = $balance + $adjustment;
        $company_ledger->activity_id = Activity::UPDATE_CLIENT
        $company_ledger->save();

    }

}