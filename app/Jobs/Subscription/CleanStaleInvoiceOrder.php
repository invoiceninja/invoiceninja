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
    public function __construct() {}

    /**
     * @param InvoiceRepository $repo
     * @return void
     */
    public function handle(InvoiceRepository $repo): void
    {
        nlog("Cleaning Stale Invoices:");

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            $this->run($repo);
            return;
        }

        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            $this->run($repo);

            \DB::connection($db)->table('password_resets')->where('created_at', '<', now()->subHours(12))->delete();

        }
    }

    private function run($repo)
    {
        $proforma =Invoice::query()
                        ->withTrashed()
                        ->where('status_id', Invoice::STATUS_SENT)
                        ->where('is_proforma', 1)
                        ->whereBetween('created_at', [now()->subHours(3), now()->subHour()])
                        ->get();
        
        $proforma->each(function ($invoice) use ($repo) {
            $invoice->is_proforma = false;
            $invoice->save();
            $repo->delete($invoice);
        });
        

        $stale = Invoice::query()
                        ->withTrashed()
                        ->whereBetween('updated_at', [now()->subDay(), now()->subHour()])
                        ->where('status_id', Invoice::STATUS_SENT)
                        ->where('balance', '>', 0)
                        ->get();

            //    ->whereJsonContains('line_items', ['type_id' => '3'])
            $stale->each(function ($invoice) {
                $invoice->service()->removeUnpaidGatewayFees();
            });

        $confirmed = Invoice::query()
            ->withTrashed()
            ->whereIn('status_id', [Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
            ->whereBetween('updated_at', [now()->subHours(3), now()->subHour()])
            ->get();

            //    ->whereJsonContains('line_items', ['type_id' => '3'])
            //    ->cursor()
            $confirmed->each(function ($invoice) {

                $items = $invoice->line_items;

                foreach ($items as $key => $value) {

                    if ($value->type_id == "3" && isset($value->unit_code) && $ph = \App\Models\PaymentHash::where('hash', $value->unit_code)->first()) {

                        if ($ph->payment_id && in_array($ph->payment?->status_id, [\App\Models\Payment::STATUS_COMPLETED, \App\Models\Payment::STATUS_PENDING])) {
                            $items[$key]->type_id = "4";
                        }
                    }

                }

                $invoice->line_items = array_values($items);
                $invoice = $invoice->calc()->getInvoice();
                $invoice->service()->removeUnpaidGatewayFees();
            });

    }

    public function failed($exception = null) {}
}
