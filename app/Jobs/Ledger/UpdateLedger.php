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
use App\Models\CompanyLedger;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class UpdateLedger implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $deleteWhenMissingModels = true;

    public function __construct(private int $company_ledger_id, private float $start_amount, private string $company_key, private string $db)
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
        MultiDB::setDb($this->db);

        $cl = CompanyLedger::find($this->company_ledger_id);
        $entity = $cl->company_ledgerable;
        $balance = $entity->calc()->getBalance();
        $cl->adjustment = $balance - $this->start_amount;
        
            $parent_ledger = CompanyLedger::query()
                ->where('id', '<', $cl->id)
                ->where('company_id', $cl->company_id)
                ->where('client_id', $cl->client_id)
                ->orderBy('id', 'DESC')
                ->first();

        $cl->balance = $parent_ledger ? $parent_ledger->balance + $cl->adjustment : 0;
        $cl->save();


        // CompanyLedger::query()
        //             ->where('company_id', $cl->company_id)
        //             ->where('client_id', $cl->client_id)
        //             ->where('balance', 0)
        //             ->orderBy('updated_at', 'ASC')
        //             ->cursor()
        //             ->each(function ($company_ledger) {

        //                 $last_record = null;

        //                 if ($company_ledger->balance == 0) {
        //                     $last_record = CompanyLedger::query()
        //                                     ->where('company_id', $company_ledger->company_id)
        //                                     ->where('client_id', $company_ledger->client_id)
        //                                     ->where('balance', '!=', 0)
        //                                     ->orderBy('id', 'DESC')
        //                                     ->first();

        //                     if (! $last_record) {
        //                         $last_record = CompanyLedger::query()
        //                                         ->where('company_id', $company_ledger->company_id)
        //                                         ->where('client_id', $company_ledger->client_id)
        //                                         ->orderBy('id', 'DESC')
        //                                         ->first();
        //                     }
        //                 }

        //                 if($last_record) {
        //                     $company_ledger->balance = $last_record->balance + $company_ledger->adjustment;
        //                     $company_ledger->save();
        //                 }
        // });


    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->company_key))->dontRelease()];
    }
}
