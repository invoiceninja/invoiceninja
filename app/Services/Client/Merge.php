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

namespace App\Services\Client;

use App\Factory\CompanyLedgerFactory;
use App\Models\Activity;
use App\Models\Client;
use App\Models\CompanyLedger;
use App\Models\TransactionEvent;
use App\Services\AbstractService;
use App\Services\EDocument\Standards\France\FranceScopeInvalidationRecorder;
use Illuminate\Support\Facades\DB;

class Merge extends AbstractService
{
    public $client;

    public $mergable_client;

    public function __construct(Client $client, Client $mergable_client)
    {
        $this->client = $client;
        $this->mergable_client = $mergable_client;
    }

    public function run()
    {
        $mergeableClient = $this->mergable_client->present()->name();
        $eventVars = \App\Utils\Ninja::eventVars(auth()->user() ? auth()->user()->id : null);
        $eventVars['client_hash'] = $this->mergable_client->client_hash;
        $client = $this->mergeRecords();

        event(new \App\Events\Client\ClientWasMerged(
            $mergeableClient,
            $client,
            $client->company,
            $eventVars,
        ));

        return $client;
    }

    /**
     * Deliberately takes no client-level lock up front. The codebase acquires
     * entity rows before the client row (MarkPaid, DeletePaymentV2); locking the
     * client first here would invert that order and deadlock against them. The
     * mass updates below X-lock the moved rows, and ClientService takes the
     * client row last, which keeps this consistent with every other caller.
     */
    private function mergeRecords()
    {
        return DB::transaction(fn() => $this->applyMerge(), attempts: 3);
    }

    private function applyMerge()
    {
        $this->mergable_client->activities()->update(['client_id' => $this->client->id]);
        $this->mergable_client->contacts()->update(['client_id' => $this->client->id]);
        $this->mergable_client->gateway_tokens()->update(['client_id' => $this->client->id]);
        $this->mergable_client->credits()->update(['client_id' => $this->client->id]);
        $this->mergable_client->expenses()->update(['client_id' => $this->client->id]);
        /** Payments are reassigned before invoices to match the payment -> invoice
         * lock order used by DeletePaymentV2, so the two cannot deadlock. */
        $this->mergable_client->payments()->update(['client_id' => $this->client->id]);
        $this->mergable_client->invoices()->update(['client_id' => $this->client->id]);
        $this->mergable_client->projects()->update(['client_id' => $this->client->id]);
        $this->mergable_client->quotes()->update(['client_id' => $this->client->id]);
        $this->mergable_client->recurring_invoices()->update(['client_id' => $this->client->id]);
        $this->mergable_client->recurring_expenses()->update(['client_id' => $this->client->id]);
        $this->mergable_client->tasks()->update(['client_id' => $this->client->id]);
        $this->mergable_client->documents()->update(['documentable_id' => $this->client->id]);
        $this->mergable_client->transaction_events()->update(['client_id' => $this->client->id]);
        

        /* Loop through contacts an only merge distinct contacts by email */
        $this->mergable_client->contacts->each(function ($contact) {
            $exist = $this->client->contacts->contains(function ($client_contact) use ($contact) {
                return $client_contact->email == $contact->email || empty($contact->email) || $contact->email == ' ';
            });

            if ($exist) {
                $contact->delete();
                $contact->save();
            }
        });


        $this->mergable_client->forceDelete();

        $old_balance = $this->client->balance;

        $this->client = $this->client->service()->calculateBalance()->calculatePaidToDate()->updatePaymentBalance()->save();
        $this->client->credit_balance = $this->client->service()->getCreditBalance();
        $this->client->saveQuietly();

        $this->updateLedger($this->client->balance - $old_balance);

        if ((bool) $this->client->company->getSetting('france_reporting_enabled')) {
            app(FranceScopeInvalidationRecorder::class)->recordAndDispatch(
                company: $this->client->company,
                clientId: $this->client->id,
            );
        }

        return $this->client;
    }

    private function updateLedger($adjustment)
    {
        $balance = 0;

        $company_ledger = CompanyLedger::query()->whereClientId($this->client->id)
                                ->orderBy('id', 'DESC')
                                ->first();

        if ($company_ledger) {
            $balance = $company_ledger->balance;
        }

        $company_ledger = CompanyLedgerFactory::create($this->client->company_id, $this->client->user_id);
        $company_ledger->client_id = $this->client->id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->notes = 'Balance update after merging ' . $this->mergable_client->present()->name();
        $company_ledger->balance = $balance + $adjustment;
        $company_ledger->activity_id = Activity::UPDATE_CLIENT;
        $company_ledger->save();
    }
}
