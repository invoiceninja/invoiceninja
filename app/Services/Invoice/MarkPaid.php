<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Company\UpdateCompanyLedgerWithPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;

class MarkPaid
{
    private $client_service;

    public function __construct($client_service)
    {
        $this->client_service = $client_service;
    }

  	public function __invoke($invoice)
  	{

        if($invoice->status_id == Invoice::STATUS_DRAFT)
            $invoice->markSent();

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

        $invoice->service()
                ->updateBalance($payment->amount*-1)
                ->setStatus(Invoice::STATUS_PAID)
                ->save();

        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company));

        UpdateCompanyLedgerWithPayment::dispatchNow($payment, ($payment->amount*-1), $payment->company);
        
        $this->client_service
            ->updateBalance($payment->amount*-1)
            ->updatePaidToDate($payment->amount)
            ->save();

        return $invoice;
  	}

}

