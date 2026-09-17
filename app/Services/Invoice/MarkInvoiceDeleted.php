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

namespace App\Services\Invoice;

use App\Listeners\Invoice\InvoiceTransactionEventEntryCash;
use App\Jobs\Inventory\AdjustProductInventory;
use App\Models\Invoice;
use App\Models\Paymentable;
use App\Models\Quote;
use App\Models\TransactionEvent;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Str;

class MarkInvoiceDeleted extends AbstractService
{
    use GeneratesCounter;

    private $adjustment_amount = 0;

    private $total_payments = 0;

    private $balance_adjustment = 0;

    /** @var array<int, array<string, mixed>> */
    private array $tax_event_snapshots = [];

    private string $tax_mutation_key;

    public function __construct(public Invoice $invoice) {}

    public function run()
    {
        $this->tax_mutation_key = 'invoice_deleted:'.Str::uuid();
        $this->refreshInvoiceForDeletion();

        if ($this->invoice->company->track_inventory) {
            (new AdjustProductInventory($this->invoice->company, $this->invoice, []))->handleDeletedInvoice();
        }

        $this->cleanup()
             ->setAdjustmentAmount()
             ->captureTaxEventSnapshots()
             ->deletePaymentables()
             ->adjustPayments()
             ->adjustPaidToDateAndBalance()
             ->adjustLedger()
             ->triggeredActions();

        $this->invoice->delete();
        $this->writeTaxEventCorrections();

        event('eloquent.updated: App\Models\Invoice', $this->invoice);

        event(new \App\Events\Invoice\InvoiceWasDeleted($this->invoice, $this->invoice->company, \App\Utils\Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->invoice;
    }

    private function captureTaxEventSnapshots(): self
    {
        $effective_date = now($this->invoice->company->timezone()?->name ?: config('app.timezone'))->toDateString();

        Paymentable::query()
            ->with(['payment' => fn ($query) => $query->withTrashed()])
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $this->invoice->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (Paymentable $paymentable) use ($effective_date): void {
                $amount = (float) $paymentable->amount - (float) $paymentable->refunded;

                if (abs($amount) < 0.0001) {
                    return;
                }

                $source = app(InvoiceTransactionEventEntryCash::class)
                    ->runForPaymentable($this->invoice, $paymentable);

                if (! $source) {
                    return;
                }

                $this->tax_event_snapshots[] = [
                    'source_event_id' => $source->id,
                    'paymentable_id' => $paymentable->id,
                    'amount' => abs($amount),
                    'effective_date' => $effective_date,
                    'correction_key' => sha1("{$this->tax_mutation_key}|{$paymentable->id}"),
                ];
            });

        return $this;
    }

    private function writeTaxEventCorrections(): void
    {
        foreach ($this->tax_event_snapshots as $snapshot) {
            $source = TransactionEvent::query()->find($snapshot['source_event_id']);

            if (! $source) {
                throw new \RuntimeException('Invoice deletion tax source event is unavailable.');
            }

            app(InvoiceTransactionEventEntryCash::class)->writeCorrection(
                source: $source,
                effective_date: $snapshot['effective_date'],
                sign: -1,
                kind: 'payment_deleted',
                correction_key: $snapshot['correction_key'],
                context: [
                    'mutation_key' => $this->tax_mutation_key,
                    'mutation_type' => 'invoice_deleted',
                    'invoice_id' => $this->invoice->id,
                ],
                amount: $snapshot['amount'],
            );
        }
    }

    private function refreshInvoiceForDeletion(): self
    {
        \DB::connection(config('database.default'))->transaction(function () {
            $this->invoice = Invoice::withTrashed()
                                    ->where('id', $this->invoice->id)
                                    ->lockForUpdate()
                                    ->firstOrFail();
        }, 2);

        return $this;
    }

    private function adjustLedger()
    {
        $this->invoice->ledger()->updatePaymentBalance($this->balance_adjustment * -1, 'Invoice Deleted - reducing ledger balance'); //reduces the payment balance by payment totals

        return $this;
    }

    private function adjustPaidToDateAndBalance()
    {

        $ba = $this->balance_adjustment * -1;
        $aa = $this->adjustment_amount * -1;
        $cb = $this->invoice->client->balance;

        nlog("APB => {$this->invoice->number} - BA={$ba} - AA={$aa} - CB={$cb}");

        $this->invoice
             ->client
             ->service()
             ->updateBalanceAndPaidToDate($ba, $aa)
             ->save();

        return $this;
    }

    /* Adjust the payment amounts */
    private function adjustPayments()
    {
        //if total payments = adjustment amount - that means we need to delete the payments as well.
        if ($this->adjustment_amount == $this->total_payments) {
            $this->invoice->payments()->update(['payments.deleted_at' => now(), 'payments.is_deleted' => true]);
        }


        //adjust payments down by the amount applied to the invoice payment.
        $this->invoice->payments->each(function ($payment) {
            $payment_adjustment = $payment->paymentables
                                            ->where('paymentable_type', '=', 'invoices')
                                            ->where('paymentable_id', $this->invoice->id)
                                            ->sum('amount');

            $payment_adjustment -= $payment->paymentables
                                            ->where('paymentable_type', '=', 'invoices')
                                            ->where('paymentable_id', $this->invoice->id)
                                            ->sum('refunded');

            //14-07-2023 - Do not include credits in the payment adjustment.
            $payment_adjustment -= $payment->paymentables
                                            ->where('paymentable_type', '=', 'App\Models\Credit')
                                            ->sum('amount');

            $payment->amount -= $payment_adjustment;
            $payment->applied -= $payment_adjustment;
            $payment->save();
        });


        return $this;
    }

    /**
     * Set the values of two variables
     *
     * $this->adjustment_amount - sum of the invoice paymentables
     * $this->total_payments - sum of the invoice payments
     */
    private function setAdjustmentAmount()
    {
        foreach ($this->invoice->payments as $payment) {
            $this->adjustment_amount += $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum('amount');

            $this->adjustment_amount -= $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum('refunded');
        }

        $this->total_payments = $this->invoice->payments->sum('amount') - $this->invoice->payments->sum('refunded');

        $this->balance_adjustment = $this->invoice->status_id == Invoice::STATUS_CANCELLED ? 0 : $this->invoice->balance;

        /** necessary guard for no invoice line items! */
        if (! is_iterable($this->invoice->line_items)) {
            return $this;
        }

        $pre_count = count((array) $this->invoice->line_items);

        /**
         * Strips pending gateway fees written by the previous design.
         *
         * TRANSITIONAL, and a no-op once no type 3 line exists anywhere. Remove alongside
         * the drain, on the same criterion.
         *
         * @deprecated Gateway fees are no longer written before confirmation.
         * @see \App\Services\Invoice\InvoiceService::removeUnpaidGatewayFees()
         */
        $items = collect((array) $this->invoice->line_items)
                    ->filter(function ($item) {
                        return $item->type_id != '3';
                    })->toArray();

        if (count($items) < $pre_count) {
            $this->invoice->line_items = array_values($items);
            $this->invoice = $this->invoice->calc()->getInvoice();
        }

        return $this;
    }

    /*
     *
     * This sets the invoice number to _deleted
     * and also removes the links to existing entities
     *
     */
    private function cleanup()
    {
        $check = false;

        $x = 0;

        do {
            $number = $this->calcNumber($x);
            $check = $this->checkNumberAvailable(Invoice::class, $this->invoice, $number);
            $x++;
        } while (! $check);

        $this->invoice->number = $number;

        //wipe references to invoices from related entities.
        $this->invoice->tasks()->update(['invoice_id' => null]);
        $this->invoice->expenses()->update(['invoice_id' => null]);

        return $this;
    }

    private function calcNumber($x)
    {
        if ($x == 0) {
            $number = $this->invoice->number . '_' . ctrans('texts.deleted');
        } else {
            $number = $this->invoice->number . '_' . ctrans('texts.deleted') . '_' . $x;
        }

        return $number;
    }

    /* Touches all paymentables as deleted */
    private function deletePaymentables()
    {
        $this->invoice->payments->each(function ($payment) {
            $payment->paymentables()
                    ->where('paymentable_type', '=', 'invoices')
                    ->where('paymentable_id', $this->invoice->id)
                    ->update(['deleted_at' => now()]);

            $pp = \App\Models\Paymentable::where('payment_id', $payment->id)
                                ->where('paymentable_type', \App\Models\Credit::class)
                                ->where('amount', $this->invoice->amount)
                                ->first();

            if ($pp) {
                $pp->delete();
            }

        });

        return $this;
    }

    private function triggeredActions(): self
    {
        if ($this->invoice->quote) {
            $this->invoice->quote->invoice_id = null;
            $this->invoice->quote->status_id = Quote::STATUS_SENT;
            $this->invoice->pushQuietly();
        }

        return $this;
    }
}
