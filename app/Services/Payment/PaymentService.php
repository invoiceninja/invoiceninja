<?php
/**
 * payment Ninja (https://paymentninja.com).
 *
 * @link https://github.com/paymentninja/paymentninja source repository
 *
 * @copyright Copyright (c) 2022. payment Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Payment;

use App\Factory\PaymentFactory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Utils\Traits\MakesHash;

class PaymentService
{
    use MakesHash;

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
        $payment->saveQuietly();

        $payment->invoices()->attach($invoice->id, [
            'amount' => $payment->amount,
        ]);

        event('eloquent.created: App\Models\Payment', $payment);

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
                $invoice->service()
                        ->updateBalance($invoice->pivot->amount)
                        ->updatePaidToDate($invoice->pivot->amount * -1)
                        ->setStatus(Invoice::STATUS_SENT)
                        ->save();
            }
        });

        $this->payment
             ->ledger()
             ->updatePaymentBalance($this->payment->amount);

        $client->service()
            ->updateBalance($this->payment->amount)
            ->updatePaidToDate($this->payment->amount * -1)
            ->save();

        return $this;
    }

    public function refundPayment(array $data) :?Payment
    {
        return ((new RefundPayment($this->payment, $data)))->run();
    }

    public function deletePayment() :?Payment
    {
        return (new DeletePayment($this->payment))->run();
    }

    public function updateInvoicePayment(PaymentHash $payment_hash) :?Payment
    {
        return ((new UpdateInvoicePayment($this->payment, $payment_hash)))->run();
    }

    public function applyNumber()
    {
        $this->payment = (new ApplyNumber($this->payment))->run();

        return $this;
    }

    public function applyCredits($payment_hash)
    {
        /* Iterate through the invoices and apply credits to them */
        collect($payment_hash->invoices())->each(function ($payable_invoice) use ($payment_hash) {
            $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($payable_invoice->invoice_id));

            $amount = $payable_invoice->amount;

            $credits = $payment_hash->fee_invoice
                                    ->client
                                    ->service()
                                    ->getCredits();

            foreach ($credits as $credit) {
                //starting invoice balance
                $invoice_balance = $invoice->balance;

                //credit payment applied
                $credit->service()->applyPayment($invoice, $amount, $this->payment);

                //amount paid from invoice calculated
                $remaining_balance = ($invoice_balance - $invoice->fresh()->balance);

                //reduce the amount to be paid on the invoice from the NEXT credit
                $amount -= $remaining_balance;

                //break if the invoice is no longer PAYABLE OR there is no more amount to be applied
                if (! $invoice->isPayable() || (int) $amount == 0) {
                    break;
                }
            }
        });

        return $this;
    }

    public function save()
    {
        $this->payment->saveQuietly();

        return $this->payment->fresh();
    }
}
