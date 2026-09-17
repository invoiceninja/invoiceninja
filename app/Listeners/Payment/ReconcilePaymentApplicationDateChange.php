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

namespace App\Listeners\Payment;

use App\Events\Payment\PaymentApplicationDateChanged;
use App\Libraries\MultiDB;
use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Paymentable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ReconcilePaymentApplicationDateChange implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 180];

    public function __construct(private InvoiceTransactionEventEntryCash $cash_reconciler) {}

    public function handle(PaymentApplicationDateChanged $event): void
    {
        MultiDB::setDb($event->db);

        $payment = Payment::withTrashed()->with(['client.country', 'client.company', 'company'])->find($event->payment_id);

        if (! $payment) {
            return;
        }

        $paymentableIds = collect($event->paymentable_ids)
            ->map(fn($id): int => (int) $id)
            ->filter(fn(int $id): bool => $id > 0)
            ->unique()
            ->sort()
            ->values();

        if ($paymentableIds->isEmpty()) {
            return;
        }

        $paymentables = Paymentable::withTrashed()
            ->where('payment_id', $payment->id)
            ->whereIn('id', $paymentableIds->all())
            ->where('paymentable_type', 'invoices')
            ->get()
            ->groupBy(fn(Paymentable $paymentable): int => (int) $paymentable->paymentable_id);

        if ($paymentables->isEmpty()) {
            return;
        }

        $invoices = Invoice::withTrashed()
            ->with(['client.country', 'client.company', 'company'])
            ->where('company_id', $payment->company_id)
            ->whereIn('id', $paymentables->keys()->all())
            ->get()
            ->keyBy('id');

        foreach ($paymentables->sortKeys() as $invoiceId => $invoicePaymentables) {
            $invoice = $invoices->get($invoiceId);

            if (! $invoice) {
                continue;
            }

            $ids = $invoicePaymentables->pluck('id')->map(fn($id): int => (int) $id)->sort()->values()->all();

            try {
                $this->cash_reconciler->reconcileApplicationDateChange(
                    $invoice->id,
                    $payment->id,
                    $event->old_date,
                    $event->new_date,
                    $ids,
                );

            } catch (Throwable $exception) {
                report($exception);

                throw $exception;
            }
        }
    }

    /**
     * @return array<int, object>
     */
    public function middleware(PaymentApplicationDateChanged $event): array
    {
        $ids = collect($event->paymentable_ids)->map(fn($id): int => (int) $id)->sort()->implode(',');
        $key = 'payment-application-date:' . sha1($event->db . '|' . $event->payment_id . '|' . $ids);

        return [
            (new WithoutOverlapping($key))
                ->shared()
                ->releaseAfter(10)
                ->expireAfter(300),
        ];
    }
}
