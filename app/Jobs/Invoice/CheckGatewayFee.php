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

namespace App\Jobs\Invoice;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class CheckGatewayFee implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    /**
     * @param $invoice_id
     * @param string $db
     */
    public function __construct(public int $invoice_id, public string $db)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {

        MultiDB::setDb($this->db);

        $i = Invoice::withTrashed()->find($this->invoice_id);

        if (!$i) {
            return;
        }

        if ($i->status_id == Invoice::STATUS_SENT) {
            $i->service()->removeUnpaidGatewayFees();
        }
    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->invoice_id.$this->db))];
    }
}
