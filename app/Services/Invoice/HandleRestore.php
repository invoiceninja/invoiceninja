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
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Str;

class HandleRestore extends AbstractService
{
    use GeneratesCounter;

    private $payment_total = 0;

    private $total_payments = 0;

    private $adjustment_amount = 0;

    public function __construct(private Invoice $invoice) {}

    public function run()
    {
        $this->invoice->restore();

        if (! $this->invoice->is_deleted) {
            return $this->invoice;
        }

        //cannot restore an invoice with a deleted payment
        foreach ($this->invoice->payments as $payment) {
            if (($this->invoice->paid_to_date == 0) && $payment->is_deleted) {
                $this->invoice->delete();
                return $this->invoice;
            }
        }

        //adjust ledger balance
        $this->invoice->ledger()->updateInvoiceBalance($this->invoice->balance, "Restored invoice {$this->invoice->number}")->save();

        //@todo
        $this->invoice->client
                      ->service()
                      ->updateBalanceAndPaidToDate($this->invoice->balance, $this->invoice->paid_to_date)
                      ->save();

        $this->windBackInvoiceNumber();

        $this->invoice->is_deleted = false;
        $this->invoice->save();

        $this->restorePaymentables()
             ->setAdjustmentAmount()
             ->adjustPayments();

        $this->writeTaxEventCorrections();

        if ($this->invoice->company->track_inventory) {
            (new AdjustProductInventory($this->invoice->company, $this->invoice, []))->handleRestoredInvoice();
        }


        return $this->invoice;
    }

    private function writeTaxEventCorrections(): void
    {
        $effective_date = now($this->invoice->company->timezone()?->name ?: config('app.timezone'))->toDateString();
        $mutation_key = 'invoice_restored:'.Str::uuid();

        Paymentable::query()
            ->with(['payment' => fn ($query) => $query->withTrashed()])
            ->where('paymentable_type', 'invoices')
            ->where('paymentable_id', $this->invoice->id)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->each(function (Paymentable $paymentable) use ($effective_date, $mutation_key): void {
                $amount = (float) $paymentable->amount - (float) $paymentable->refunded;

                if (abs($amount) < 0.0001) {
                    return;
                }

                $source = app(InvoiceTransactionEventEntryCash::class)
                    ->findSourceEvent($this->invoice->id, $paymentable->id);

                if (! $source) {
                    return;
                }

                app(InvoiceTransactionEventEntryCash::class)->writeCorrection(
                    source: $source,
                    effective_date: $effective_date,
                    sign: 1,
                    kind: 'invoice_restored',
                    correction_key: sha1("{$mutation_key}|{$paymentable->id}"),
                    context: [
                        'mutation_key' => $mutation_key,
                        'mutation_type' => 'invoice_restored',
                        'invoice_id' => $this->invoice->id,
                    ],
                    amount: abs($amount),
                );
            });
    }

    /* Touches all paymentables as deleted */
    private function restorePaymentables()
    {
        $this->invoice->payments->each(function ($payment) {
            Paymentable::query()
            ->withTrashed()
            ->where('payment_id', $payment->id)
            ->update(['deleted_at' => null]);
        });

        return $this;
    }


    private function setAdjustmentAmount()
    {
        foreach ($this->invoice->payments as $payment) {
            $this->adjustment_amount += $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum('amount');

            //14/07/2023 - do not include credits in the payment amount
            $this->adjustment_amount -= $payment->paymentables
                                            ->where('paymentable_type', '=', 'App\Models\Credit')
                                            ->sum('amount');

            nlog("Adjustment amount: {$this->adjustment_amount}");
        }

        $this->total_payments = $this->invoice->payments->sum('amount') - $this->invoice->payments->sum('refunded');

        return $this;
    }

    private function adjustPayments()
    {
        //if total payments = adjustment amount - that means we need to delete the payments as well.

        if ($this->adjustment_amount == $this->total_payments) {
            $this->invoice->payments()->update(['payments.deleted_at' => null, 'payments.is_deleted' => false]);
        } else {
            $this->invoice->net_payments()->update(['payments.deleted_at' => null, 'payments.is_deleted' => false]);
        }

        //adjust payments down by the amount applied to the invoice payment.

        $this->invoice->net_payments()->each(function ($payment) {
            $payment_adjustment = $payment->paymentables
                                            ->where('paymentable_type', '=', 'invoices')
                                            ->where('paymentable_id', $this->invoice->id)
                                            ->sum('amount');

            $payment_adjustment -= $payment->paymentables
                                            ->where('paymentable_type', '=', 'invoices')
                                            ->where('paymentable_id', $this->invoice->id)
                                            ->sum('refunded');

            $payment_adjustment -= $payment->paymentables
                        ->where('paymentable_type', '=', 'App\Models\Credit')
                        ->sum('amount');

            $payment->amount += $payment_adjustment;
            $payment->applied += $payment_adjustment;
            $payment->is_deleted = false;
            $payment->restore();
            $payment->saveQuietly();

        });

        return $this;
    }


    private function windBackInvoiceNumber()
    {
        $findme = '_' . ctrans('texts.deleted');

        $pos = strpos($this->invoice->number, $findme);

        $new_invoice_number = substr($this->invoice->number, 0, $pos);

        if (strlen($new_invoice_number) == 0) {
            $new_invoice_number = null;
        }

        try {
            $exists = Invoice::query()->where(['company_id' => $this->invoice->company_id, 'number' => $new_invoice_number])->exists();

            if ($exists) {
                $this->invoice->number = $this->getNextInvoiceNumber($this->invoice->client, $this->invoice, isset($this->invoice->recurring_id));
            } else {
                $this->invoice->number = $new_invoice_number;
            }

            $this->invoice->saveQuietly();
        } catch (\Exception $e) {
            nlog('I could not wind back the invoice number');

            if (Ninja::isHosted()) {
                \Sentry\captureMessage('I could not wind back the invoice number');
                app('sentry')->captureException($e);
            }
        }
    }
}
