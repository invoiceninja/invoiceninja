<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Payment;

use App\Exceptions\PaymentRefundFailed;
use App\Factory\CreditFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Activity;
use App\Models\CompanyGateway;
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
        return $this->setStatus(Payment::STATUS_CANCELLED) //sets status of payment
            ->updateCreditables() //return the credits first
            ->adjustInvoices()
            ->updateClient()
            ->deletePaymentables()
            ->save();
    }

    //reverse paymentables->invoices

    //reverse paymentables->credits

    //set refunded to amount

    //set applied amount to 0

    private function deletePaymentables()
    {
        $this->payment->paymentables()->update(['deleted_at' => now()]);

        return $this;
    }

    private function updateClient()
    {
        $this->payment->client->service()->updatePaidToDate(-1 * $this->payment->amount)->save();

        return $this;
    }

    private function adjustInvoices()
    {
        if ($this->payment->invoices()->exists()) {
            $this->payment->invoices()->each(function ($paymentable_invoice) {
                $paymentable_invoice->service()->updateBalance($paymentable_invoice->pivot->amount)->save();
                $paymentable_invoice->ledger()->updateInvoiceBalance($paymentable_invoice->pivot->amount)->save();
                $paymentable_invoice->client->service()->updateBalance($paymentable_invoice->pivot->amount)->save();

                if ($paymentable_invoice->balance == $paymentable_invoice->amount) {
                    $paymentable_invoice->service()->setStatus(Invoice::STATUS_SENT)->save();
                } else {
                    $paymentable_invoice->service()->setStatus(Invoice::STATUS_PARTIAL)->save();
                }

                //fire event for this credit
                //
            });
        }

        return $this;
    }

    private function updateCreditables()
    {
        if ($this->payment->credits()->exists()) {
            $this->payment->credits()->each(function ($paymentable_credit) {
                $paymentable_credit->balance += $paymentable_credit->pivot->amount;
                $paymentable_credit->setStatus(Credit::STATUS_SENT);
                //fire event for this credit
                //
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
