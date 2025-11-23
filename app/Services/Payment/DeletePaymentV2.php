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
use App\Utils\BcMath;
use Illuminate\Contracts\Container\BindingResolutionException;

class DeletePaymentV2
{
    private string $_paid_to_date_deleted = '0';

    private string $total_payment_amount = '0';
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


    private function setStatus($status): self
    {
        $this->payment->status_id = $status;

        return $this;
    }

    /**
     * Iterates through the credit pivot records and updates the balance and paid to date.
     *
     * @return self
     */
    private function updateCreditables(): self
    {
        if ($this->payment->credits()->exists()) {
            $this->payment->credits()->where('is_deleted', 0)->each(function ($paymentable_credit) {
                $multiplier = 1;

                //balance remaining on the credit that can offset the paid to date.
                $net_credit_amount = BcMath::sub($paymentable_credit->pivot->amount, $paymentable_credit->pivot->refunded, 2);

                //Updates the Global Total Payment Amount that can later be used to adjust the paid to date.
                $this->total_payment_amount = BcMath::add($this->total_payment_amount, $net_credit_amount, 2);

                //Negative payments need cannot be "subtracted" from the paid to date. so the operator needs to be reversed.
                if (BcMath::lessThan($net_credit_amount, 0, 2)) {
                    $multiplier = -1;
                }

                //Reverses the operator for the balance and paid to date this allows the amount to be "subtracted" from the paid to date.
                $balance_multiplier = BcMath::mul($multiplier, -1, 0);

                $balance_net_credit_amount = BcMath::mul($net_credit_amount, $balance_multiplier, 2);

                $paymentable_credit->service()
                                   ->updateBalance($balance_net_credit_amount)
                                   ->updatePaidToDate($balance_net_credit_amount)
                                   ->setStatus(Credit::STATUS_SENT)
                                   ->save();

                $client = $this->payment->client->fresh();

                $client->service()
                        ->adjustCreditBalance($net_credit_amount)
                        ->save();
            });
        }

        return $this;
    }

    /**
     * Iterates through the invoice pivot records and updates the balance and paid to date.
     *
     * @return self
     */
    private function adjustInvoices(): self
    {
        if ($this->payment->invoices()->exists()) {

            //Updates the Global Total Payment Amount that can later be used to adjust the paid to date.
            $this->total_payment_amount = BcMath::add($this->total_payment_amount, BcMath::sub($this->payment->amount, $this->payment->refunded, 2), 2);

            $this->payment->invoices()->each(function ($paymentable_invoice) {

                if ($paymentable_invoice->status_id == Invoice::STATUS_REVERSED) {
                    $this->update_client_paid_to_date = false;
                    return;
                }

                $net_deletable = BcMath::sub($paymentable_invoice->pivot->amount, $paymentable_invoice->pivot->refunded, 2);

                $this->_paid_to_date_deleted = BcMath::add($this->_paid_to_date_deleted, $net_deletable, 2);

                $paymentable_invoice = $paymentable_invoice->fresh();

                /** For cancelled invoices, we only reduce the paid to date - balance never changes */
                if ($paymentable_invoice->status_id == Invoice::STATUS_CANCELLED) {
                    $is_trashed = false;

                    if ($paymentable_invoice->trashed()) {
                        $is_trashed = true;
                        $paymentable_invoice->restore();
                    }

                    $paymentable_invoice->service()
                                        ->updatePaidToDate(BcMath::mul($net_deletable, -1, 2))
                                        ->save();

                    if ($net_deletable > 0) {
                        $this->payment
                             ->client
                             ->service()
                             ->updatePaidToDate(BcMath::mul($net_deletable, -1, 2))
                             ->save();
                    }

                    if ($is_trashed) {
                        $paymentable_invoice->delete();
                    }

                } elseif (!$paymentable_invoice->is_deleted) {
                    $paymentable_invoice->restore();

                    $paymentable_invoice->service()
                                        ->updateBalance($net_deletable)
                                        ->updatePaidToDate(BcMath::mul($net_deletable, -1, 2))
                                        ->save();

                    $paymentable_invoice->ledger()
                                        ->updateInvoiceBalance($net_deletable, "Adjusting invoice {$paymentable_invoice->number} due to deletion of Payment {$this->payment->number}")
                                        ->save();

                    //Negative Payments need to be dealt with differently.
                    if ($net_deletable > 0) {
                        $_applicable_paid_to_date = BcMath::mul($net_deletable, -1, 2);
                    } else {
                        $_applicable_paid_to_date = '0';
                    }

                    $this->payment
                         ->client
                         ->service()
                         ->updateBalanceAndPaidToDate($net_deletable, $_applicable_paid_to_date) // if negative, set to 0, the paid to date will be reduced further down.
                         ->save();

                    if (BcMath::equal($paymentable_invoice->balance, $paymentable_invoice->amount, 2)) {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_SENT)->save();
                    } elseif (BcMath::equal($paymentable_invoice->balance, 0, 2)) {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_PAID)->save();
                    } else {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_PARTIAL)->save();
                    }
                } else {
                    $paymentable_invoice->restore();
                    $paymentable_invoice->service()
                                        ->updatePaidToDate(BcMath::mul($net_deletable, -1, 2))
                                        ->save();
                    $paymentable_invoice->delete();

                }

                PaymentTransactionEventEntry::dispatch($this->payment, [$paymentable_invoice->id], $this->payment->company->db, $net_deletable, true);

            });

        } elseif (BcMath::equal($this->payment->amount, $this->payment->applied, 2)) {
            $this->update_client_paid_to_date = false;

        }


        if ($this->update_client_paid_to_date) {

            if ($this->payment->amount < 0) {
                $reduced_paid_to_date = BcMath::mul($this->payment->amount, -1, 2);
            } else {
                $_payment_sub = BcMath::sub($this->payment->amount, $this->payment->refunded, 2);
                $reduced_paid_to_date = min(0, (BcMath::mul(BcMath::sub($_payment_sub, $this->_paid_to_date_deleted, 2), -1, 2)));
            }

            /** handle the edge case where a partial credit + unapplied payment is deleted */
            if (!BcMath::equal($this->total_payment_amount, $this->_paid_to_date_deleted, 2)) {
                $reduced_paid_to_date = min(0, BcMath::mul(BcMath::sub($this->total_payment_amount, $this->_paid_to_date_deleted, 2), -1, 2));
            }

            if (!BcMath::equal($reduced_paid_to_date, '0', 2)) {
                $this->payment
                    ->client
                    ->service()
                    ->updatePaidToDate($reduced_paid_to_date)
                    ->save();
            }
        }

        return $this;
    }

    private function deletePaymentables(): self
    {
        $this->payment->paymentables()
                ->each(function ($pp) {
                    $pp->forceDelete();
                });

        return $this;
    }


}
