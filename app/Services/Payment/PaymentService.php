<?php
/**
 * payment Ninja (https://paymentninja.com)
 *
 * @link https://github.com/paymentninja/paymentninja source repository
 *
 * @copyright Copyright (c) 2020. payment Ninja LLC (https://paymentninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Payment;

use App\Factory\PaymentFactory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payment\ApplyNumber;
use App\Services\Payment\DeletePayment;
use App\Services\Payment\RefundPayment;
use App\Services\Payment\UpdateInvoicePayment;

class PaymentService
{
    private $payment;

    public function __construct($payment)
    {
        $this->payment = $payment;
    }

    public function manualPayment($invoice) :?Payment
    {
        /* Create Payment */
        $payment = PaymentFactory::create($invoice->company_id, $invoice->user_id);

        $payment->amount = $invoice->balance;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $invoice->client_id;
        $payment->transaction_reference = ctrans('texts.manual_entry');
        $payment->currency_id = $invoice->client->getSetting('currency_id');
        /* Create a payment relationship to the invoice entity */
        $payment->save();

        $payment->invoices()->attach($invoice->id, [
            'amount' => $payment->amount
        ]);

        return $payment;
    }
    
    public function sendEmail($contact = null)
    {
        return (new SendEmail($this->payment, $contact))->run();
    }

    public function reversePayment()
    {
        $invoices = $this->payment->invoices()->get();
        $client = $this->payment->client;

        $invoices->each(function ($invoice) {
            if ($invoice->pivot->amount > 0) {
                $invoice->status_id = Invoice::STATUS_SENT;
                $invoice->balance = $invoice->pivot->amount;
                $invoice->save();
            }
        });

        $this->payment->ledger()->updatePaymentBalance($this->payment->amount);

        $client->service()
            ->updateBalance($this->payment->amount)
            ->updatePaidToDate($this->payment->amount*-1)
            ->save();
    }

    public function refundPayment(array $data) :?Payment
    {
        return ((new RefundPayment($this->payment, $data)))->run();
    }

    public function deletePayment() :?Payment
    {
        return (new DeletePayment($this->payment))->run();
    }

    public function updateInvoicePayment($payment_hash = null) :?Payment
    {
        return ((new UpdateInvoicePayment($this->payment, $payment_hash)))->run();
    }

    public function applyNumber()
    {
        $this->payment = (new ApplyNumber($this->payment))->run();

        return $this;
    }

    public function save()
    {
        $this->payment->save();

        return $this->payment->fresh();
    }

}
