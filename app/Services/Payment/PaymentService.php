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
use App\Models\Payment;


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
        /* Create a payment relationship to the invoice entity */
        $payment->save();

        $payment->invoices()->attach($invoice->id, [
            'amount' => $payment->amount
        ]);

        return $payment;
    }
    
    public function sendEmail($contact = null)
    {
        $send_email = new SendEmail($this->payment);

        return $send_email->run(null, $contact);
    }
}
