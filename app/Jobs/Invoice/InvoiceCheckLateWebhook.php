<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Invoice;

use App\Jobs\Util\WebhookHandler;
use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Models\Webhook;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InvoiceCheckLateWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        nlog("sending overdue webhooks for invoices");

        if (! config('ninja.db.multi_db_enabled')) {
            $company_ids = Webhook::where('event_id', Webhook::EVENT_LATE_INVOICE)
                                  ->where('is_deleted', 0)
                                  ->pluck('company_id');

            Invoice::query()
                 ->where('invoices.is_deleted', false)
                 ->whereNull('invoices.deleted_at')
                 ->whereNotNull('invoices.due_date')
                 ->whereIn('invoices.status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                 ->where('invoices.balance', '>', 0)
                 ->whereIn('invoices.company_id', $company_ids)
                //  ->whereHas('client', function ($query) {
                //      $query->where('is_deleted', 0)
                //             ->where('deleted_at', null);
                //  })
                //     ->whereHas('company', function ($query) {
                //         $query->where('is_disabled', 0);
                //     })
                ->leftJoin('clients', function ($join) {
                    $join->on('invoices.client_id', '=', 'clients.id')
                        ->where('clients.is_deleted', 0)
                        ->whereNull('clients.deleted_at');
                })
                ->leftJoin('companies', function ($join) {
                    $join->on('invoices.company_id', '=', 'companies.id')
                        ->where('companies.is_disabled', 0);
                })
                 ->whereBetween('invoices.due_date', [now()->subDay()->startOfDay(), now()->startOfDay()->subSecond()])
                 ->cursor()
                 ->each(function ($invoice) {
                     (new WebhookHandler(Webhook::EVENT_LATE_INVOICE, $invoice, $invoice->company, 'client'))->handle();
                 });
        } else {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $company_ids = Webhook::where('event_id', Webhook::EVENT_LATE_INVOICE)
                                      ->where('is_deleted', 0)
                                      ->pluck('company_id');

                Invoice::query()
                     ->where('invoices.is_deleted', false)
                     ->whereNull('invoices.deleted_at')
                     ->whereNotNull('invoices.due_date')
                     ->whereIn('invoices.status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                     ->where('invoices.balance', '>', 0)
                     ->whereIn('invoices.company_id', $company_ids)
                    //  ->whereHas('client', function ($query) {
                    //      $query->where('is_deleted', 0)
                    //             ->where('deleted_at', null);
                    //  })
                    //     ->whereHas('company', function ($query) {
                    //         $query->where('is_disabled', 0);
                    //     })
                    ->leftJoin('clients', function ($join) {
                        $join->on('invoices.client_id', '=', 'clients.id')
                            ->where('clients.is_deleted', 0)
                            ->whereNull('clients.deleted_at');
                    })
                    ->leftJoin('companies', function ($join) {
                        $join->on('invoices.company_id', '=', 'companies.id')
                            ->where('companies.is_disabled', 0);
                    })
                     ->whereBetween('invoices.due_date', [now()->subDay()->startOfDay(), now()->startOfDay()->subSecond()])
                     ->cursor()
                     ->each(function ($invoice) {
                         (new WebhookHandler(Webhook::EVENT_LATE_INVOICE, $invoice, $invoice->company, 'client'))->handle();
                     });
            }
        }
    }
}
