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

namespace App\Jobs\Cron;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use Illuminate\Foundation\Bus\Dispatchable;

class AutoBill
{
    use Dispatchable;

    public $tries = 1;

    public Invoice $invoice;

    public string $db;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice, ?string $db)
    {
        $this->invoice = $invoice;
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
            nlog("autobill {$this->invoice->id}");

            $this->invoice->service()->autoBill();
        } catch (\Exception $e) {
            nlog("Failed to capture payment for {$this->invoice->company_id} - {$this->invoice->number} ->".$e->getMessage());
        }
    }
}
