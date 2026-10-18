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
                 * payment landed, removes the rest.
                 *
                 * TRANSITIONAL - remove this loop (not the job; the proforma and
                 * password_reset work above is unrelated) one release after the gateway
                 * fee change ships, once the backlog below reads zero:
                 *
                 *   select count(*) from invoices
                 *   where is_deleted = 0 and line_items like '%"type_id":"3"%';
                 *
                 * That count does not fall on its own. The query above only selects
                 * invoices touched between one and two hours ago, so each invoice gets a
                 * single pass shortly after it was last written to. A type 3 line on an
                 * invoice nobody touches again is never collected here - clearing the
                 * standing backlog needs a one off sweep over every invoice still
                 * carrying one, with no updated_at window.
                 *
                 * @deprecated Gateway fees are no longer written before confirmation.
                 * @see \App\Services\Invoice\ConfirmGatewayFee
                 * @see \App\Services\Invoice\InvoiceService::removeUnpaidGatewayFees()
                 */
                $invoice->service()->removeUnpaidGatewayFees();
            });

    }

    public function failed($exception = null) {}
}
