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

            try {
                $this->run($repo);
            }
            catch(\Throwable $e) {
                nlog("Error cleaning stale invoices: " . $e->getMessage());
                app('sentry')->captureException($e);
            }

            \DB::connection($db)->table('password_resets')->where('created_at', '<', now()->subHours(12))->delete();

        }
    }

    private function run($repo)
    {
        Invoice::query()
            ->withTrashed()
            ->where('status_id', Invoice::STATUS_SENT)
            ->where('is_proforma', 1)
            ->where('is_deleted', 0)
            ->whereBetween('created_at', [now()->subHours(2), now()->subHour()])
            ->cursor()
            ->each(function ($invoice) use ($repo) {
                $invoice->is_proforma = false;
                $invoice->save();
                $repo->delete($invoice);
            });

        Invoice::query()
            ->withTrashed()
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
            ->where('is_deleted', 0)
            ->whereBetween('updated_at', [now()->subHours(2), now()->subHour()])
            ->cursor()
            ->each(function ($invoice) {

                if (! collect($invoice->line_items)->contains('type_id', '3')) {
                    return;
                }

                $invoice->refresh();

                /**
                 * Drains pending fees written by the previous design. Promotes a fee whose
                 * payment landed, removes the rest. Retained for one release.
                 *
                 * @see \App\Services\Invoice\ConfirmGatewayFee
                 */
                $invoice->service()->removeUnpaidGatewayFees();
            });

    }

    public function failed($exception = null) {}
}
