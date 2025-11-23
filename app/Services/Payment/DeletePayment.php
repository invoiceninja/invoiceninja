<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Payment;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\BankTransaction;
use App\Listeners\Payment\PaymentTransactionEventEntry;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 *
 * @deprecated in favour of DeletePaymentV2
 *
 */
class DeletePayment
{
    private float $_paid_to_date_deleted = 0;

    private float $total_payment_amount = 0;
    /**
     * @param Payment $payment
     * @return void
     */
    public function __construct(public Payment $payment, private bool $update_client_paid_to_date)
    {
    }

    /**
     * @return Payment
     * @throws BindingResolutionException
     */
    public function run()
    {
        \DB::connection(config('database.default'))->transaction(function () {
            $this->payment = Payment::withTrashed()->where('id', $this->payment->id)->lockForUpdate()->first();

            if ($this->payment && !$this->payment->is_deleted) {
                $this->setStatus(Payment::STATUS_CANCELLED) //sets status of payment
                    ->updateCreditables() //return the credits first
                    ->adjustInvoices()
                    ->deletePaymentables()
                    ->cleanupPayment()
                    ->save();
            }
        }, 2);

        return $this->payment;
    }

    /** @return $this  */
    private function cleanupPayment()
    {

        $this->payment->is_deleted = true;
        $this->payment->delete();

        BankTransaction::query()->where('payment_id', $this->payment->id)->cursor()->each(function ($bt) {
            $bt->invoice_ids = null;
            $bt->payment_id = null;
            $bt->status_id = 1;
            $bt->save();
        });

        return $this;
    }

    /** @return $this  */
    private function deletePaymentables()
    {

        $this->payment->paymentables()
                ->each(function ($pp) {
                    $pp->forceDelete();
                });

        return $this;
    }

    /** @return $this  */
    private function adjustInvoices()
    {
        $this->_paid_to_date_deleted = 0;

        if ($this->payment->invoices()->exists()) {

            $invoice_ids = $this->payment->invoices()->pluck('invoices.id')->toArray();

            $this->total_payment_amount = ($this->payment->amount - $this->payment->refunded) + ($this->payment->paymentables->where('paymentable_type', 'App\Models\Credit')->sum('amount') - $this->payment->paymentables->where('paymentable_type', 'App\Models\Credit')->sum('refunded'));

            $this->payment->invoices()->each(function ($paymentable_invoice) {
                $net_deletable = $paymentable_invoice->pivot->amount - $paymentable_invoice->pivot->refunded;

                $this->_paid_to_date_deleted += $net_deletable;
                $paymentable_invoice = $paymentable_invoice->fresh();

                nlog("net deletable amount - refunded = {$net_deletable}");

                if ($paymentable_invoice->status_id == Invoice::STATUS_CANCELLED) {

                    $is_trashed = false;

                    if ($paymentable_invoice->trashed()) {
                        $is_trashed = true;
                        $paymentable_invoice->restore();
                    }

                    $paymentable_invoice->service()
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();

                    // 2025-03-26 - If we are deleting a negative payment, then there is an edge case where the paid to date will be reduced further down.
                    // for this scenario, we skip the update to the client paid to date at this point.
                    $this->payment
                         ->client
                         ->service()
                         ->updatePaidToDate(($net_deletable * -1) > 0 ? 0 : ($net_deletable * -1)) // if negative, set to 0, the paid to date will be reduced further down.
                         ->save();

                    if ($is_trashed) {
                        $paymentable_invoice->delete();
                    }

                } elseif (! $paymentable_invoice->is_deleted) {
                    $paymentable_invoice->restore();

                    $paymentable_invoice->service()
                                        ->updateBalance($net_deletable)
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();

                    $paymentable_invoice->ledger()
                                        ->updateInvoiceBalance($net_deletable, "Adjusting invoice {$paymentable_invoice->number} due to deletion of Payment {$this->payment->number}")
                                        ->save();


                    // 2025-03-26 - If we are deleting a negative payment, then there is an edge case where the paid to date will be reduced further down.
                    // for this scenario, we skip the update to the client paid to date at this point.

                    //2025-08-19 - if there is an unapplied amount, we need to subtract it from the paid to date.
                    $this->payment
                         ->client
                         ->service()
                         ->updateBalanceAndPaidToDate($net_deletable, ($net_deletable * -1) > 0 ? 0 : ($net_deletable * -1)) // if negative, set to 0, the paid to date will be reduced further down.
                        //  ->updateBalanceAndPaidToDate($net_deletable, ($net_deletable * -1) > 0 ? 0 : ($net_deletable * -1 - ($this->payment->amount - $this->payment->applied))) // if negative, set to 0, the paid to date will be reduced further down.
                         ->save();

                    if (abs(floatval($paymentable_invoice->balance) - floatval($paymentable_invoice->amount)) < 0.005) {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_SENT)->save();
                    } elseif (abs(floatval($paymentable_invoice->balance)) < 0.005) {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_PAID)->save();
                    } else {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_PARTIAL)->save();
                    }
                } else {
                    $paymentable_invoice->restore();
                    $paymentable_invoice->service()
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();
                    $paymentable_invoice->delete();

                }

                PaymentTransactionEventEntry::dispatch($this->payment, [$paymentable_invoice->id], $this->payment->company->db, $net_deletable, true);

            });

        } elseif (floatval($this->payment->amount) == floatval($this->payment->applied)) {
            // If there are no invoices associated with the payment, we should not be updating the clients paid to date amount
            // The edge case handled here is when an invoice has been "reversed" an associated credit note is created, this is effectively the same
            // payment which can then be used _again_. So the first payment of a reversed invoice should NEVER reduce the paid to date amount.
            $this->update_client_paid_to_date = false;
        }

        //sometimes the payment is NOT created properly, this catches the payment and prevents the paid to date reducing inappropriately.
        if ($this->update_client_paid_to_date) {

            // $reduced_paid_to_date = $this->payment->amount < 0 ? $this->payment->amount * -1 : min(0, ($this->payment->amount - $this->payment->refunded - $this->_paid_to_date_deleted) * -1);
            $reduced_paid_to_date = $this->payment->amount < 0 ? $this->payment->amount * -1 : min(0, ($this->payment->amount - $this->payment->refunded - $this->_paid_to_date_deleted) * -1);

            /** handle the edge case where a partial credit + unapplied payment is deleted */
            if (floatval($this->total_payment_amount) != floatval($this->_paid_to_date_deleted)) {
                $reduced_paid_to_date = min(0, ($this->total_payment_amount - $this->_paid_to_date_deleted) * -1);
            }

            nlog("reduced paid to date: {$reduced_paid_to_date}");
            if ($reduced_paid_to_date != 0) {
                $this->payment
                    ->client
                    ->service()
                    ->updatePaidToDate($reduced_paid_to_date)
                    ->save();
            }
        }

        return $this;
    }

    /** @return $this  */
    private function updateCreditables()
    {
        if ($this->payment->credits()->exists()) {
            $this->payment->credits()->where('is_deleted', 0)->each(function ($paymentable_credit) {
                $multiplier = 1;

                if ($paymentable_credit->pivot->amount < 0) {
                    $multiplier = -1;
                }

                $paymentable_credit->service()
                                   ->updateBalance($paymentable_credit->pivot->amount * $multiplier * -1)
                                   ->updatePaidToDate($paymentable_credit->pivot->amount * $multiplier * -1)
                                   ->setStatus(Credit::STATUS_SENT)
                                   ->save();

                $client = $this->payment->client->fresh();

                $client
                ->service()
                ->adjustCreditBalance($paymentable_credit->pivot->amount)
                ->save();
            });
        }

        return $this;
    }

    /**
     * @param mixed $status
     * @return $this
     */
    private function setStatus($status)
    {
        $this->payment->status_id = Payment::STATUS_CANCELLED;

        return $this;
    }

    /**
     * Saves the payment.
     *
     * @return Payment $payment
     */
    private function save()
    {
        $this->payment->save();

        return $this->payment;
    }
}
