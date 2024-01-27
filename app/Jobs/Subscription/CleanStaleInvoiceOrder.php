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

namespace App\Jobs\Subscription;

use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Repositories\InvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;

class CleanStaleInvoiceOrder implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    /**
     * Create a new job instance.
     *
     */
    public function __construct()
    {
    }

    /**
     * @param InvoiceRepository $repo
     * @return void
     */
    public function handle(InvoiceRepository $repo): void
    {
        nlog("Cleaning Stale Invoices:");

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            Invoice::query()
                    ->withTrashed()
                    ->where('is_proforma', 1)
                    ->where('created_at', '<', now()->subHour())
                    ->cursor()
                    ->each(function ($invoice) use ($repo) {
                        $invoice->is_proforma = false;
                        $repo->delete($invoice);
                    });

            Invoice::query()
                   ->withTrashed()
                   ->where('status_id', Invoice::STATUS_SENT)
                   ->whereBetween('created_at', [now()->subHours(1), now()->subMinutes(30)])
                   ->where('balance', '>', 0)
                   ->cursor()
                   ->each(function ($invoice) {

                       if (collect($invoice->line_items)->contains('type_id', 3)) {
                           $invoice->service()->removeUnpaidGatewayFees();
                       }

                   });

            return;
        }


        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            Invoice::query()
                    ->withTrashed()
                    ->where('is_proforma', 1)
                    ->whereBetween('created_at', [now()->subHours(1), now()->subMinutes(10)])
                    ->cursor()
                    ->each(function ($invoice) use ($repo) {
                        $invoice->is_proforma = false;
                        $repo->delete($invoice);
                    });
        }
    }

    public function failed($exception = null)
    {
    }
}
