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

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutoBill implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public int $invoice_id;

    public string $db;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(int $invoice_id, ?string $db)
    {
        $this->invoice_id = $invoice_id;
        $this->db = $db;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle() : void
    {
        set_time_limit(0);

        if ($this->db) {
            MultiDB::setDb($this->db);
        }

        try {
            nlog("autobill {$this->invoice_id}");
            
            $invoice = Invoice::withTrashed()->find($this->invoice_id);

            $invoice->service()->autoBill();
        } catch (\Exception $e) {
            nlog("Failed to capture payment for {$this->invoice_id} ->".$e->getMessage());
        }
    }
}
