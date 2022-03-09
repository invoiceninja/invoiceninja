<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Payment;

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ActivityRepository;

class DeletePayment
{
    public $payment;

    private $activity_repository;

    public function __construct($payment)
    {
        $this->payment = $payment;

        $this->activity_repository = new ActivityRepository();
    }

    public function run()
    {
        if ($this->payment->is_deleted) {
            return $this->payment;
        }

        return $this->setStatus(Payment::STATUS_CANCELLED) //sets status of payment
            ->updateCreditables() //return the credits first
            ->adjustInvoices()
            ->updateClient()
            ->deletePaymentables()
            ->cleanupPayment()
            ->save();
    }

    //reverse paymentables->invoices

    //reverse paymentables->credits

    //set refunded to amount

    //set applied amount to 0

    private function cleanupPayment()
    {
        $this->payment->is_deleted = true;
        $this->payment->delete();
        
        return $this;
    }

    private function deletePaymentables()
    {
        $this->payment->paymentables()->update(['deleted_at' => now()]);

        return $this;
    }

    private function updateClient()
    {
        //$this->payment->client->service()->updatePaidToDate(-1 * $this->payment->amount)->save();

        return $this;
    }

    private function adjustInvoices()
    {
        if ($this->payment->invoices()->exists()) {

            $this->payment->invoices()->each(function ($paymentable_invoice) {

                $net_deletable = $paymentable_invoice->pivot->amount - $paymentable_invoice->pivot->refunded;
                
                $client = $this->payment->client->fresh();

                nlog("net deletable amount - refunded = {$net_deletable}");
                
                if(!$paymentable_invoice->is_deleted)
                {
                    $paymentable_invoice->service()
                                        ->updateBalance($net_deletable)
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();

                    $paymentable_invoice->ledger()
                                        ->updateInvoiceBalance($net_deletable, "Adjusting invoice {$paymentable_invoice->number} due to deletion of Payment {$this->payment->number}")
                                        ->save();

                    $client = $client->service()
                                     ->updateBalance($net_deletable)
                                     ->save();

                    if ($paymentable_invoice->balance == $paymentable_invoice->amount) {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_SENT)->save();
                    } else {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_PARTIAL)->save();
                    }
                }
                else {

                    //If the invoice is deleted we only update the meta data on the invoice
                    //and reduce the clients paid to date
                    $paymentable_invoice->service()
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();


                }

            });
        }
        // else {

            /* If there are no invoices - then we need to still adjust the total client->paid_to_date amount*/

            $this->payment
            ->client
            ->service()
            ->updatePaidToDate(($this->payment->amount - $this->payment->refunded)*-1)
            ->save();

        // }
        return $this;
    }

    private function updateCreditables()
    {
        if ($this->payment->credits()->exists()) {
            $this->payment->credits()->each(function ($paymentable_credit) {
                
                $multiplier = 1;

                    if($paymentable_credit->pivot->amount < 0)
                        $multiplier = -1;

                $paymentable_credit->service()
                                   ->updateBalance($paymentable_credit->pivot->amount*$multiplier*-1)
                                   ->updatePaidToDate($paymentable_credit->pivot->amount*$multiplier)
                                   ->setStatus(Credit::STATUS_SENT)
                                   ->save();

                    $this->payment
                    ->client
                    ->service()
                    ->updatePaidToDate(($paymentable_credit->pivot->amount)*-1)
                    ->save();

                    //01-03-2022
                // $paymentable_credit->service()
                //                    ->updateBalance($paymentable_credit->pivot->amount*$multiplier)
                //                    ->updatePaidToDate($paymentable_credit->pivot->amount*-1)
                //                    ->setStatus(Credit::STATUS_SENT)
                //                    ->save();
            });
        }

        return $this;
    }

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
