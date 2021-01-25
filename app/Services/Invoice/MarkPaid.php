<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Jobs\Payment\EmailPayment;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;

class MarkPaid extends AbstractService
{
    use GeneratesCounter;

    private $client_service;

    private $invoice;

    public function __construct(ClientService $client_service, Invoice $invoice)
    {
        $this->client_service = $client_service;

        $this->invoice = $invoice;
    }

    public function run()
    {
        if ($this->invoice->status_id == Invoice::STATUS_DRAFT) {
            $this->invoice->service()->markSent();
        }

        /*Don't double pay*/
        if ($this->invoice->statud_id == Invoice::STATUS_PAID) {
            return $this->invoice;
        }

        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $this->invoice->balance;
        $payment->applied = $this->invoice->balance;
        $payment->number = $this->getNextPaymentNumber($this->invoice->client);
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = ctrans('texts.manual_entry');
        $payment->currency_id = $this->invoice->client->getSetting('currency_id');
        $payment->is_manual = true;
        /* Create a payment relationship to the invoice entity */
        $payment->save();

        $payment->invoices()->attach($this->invoice->id, [
            'amount' => $payment->amount,
        ]);

        $this->invoice->next_send_date = null;
        
        $this->invoice->service()
                ->updateBalance($payment->amount * -1)
                ->updatePaidToDate($payment->amount)
                ->setStatus(Invoice::STATUS_PAID)
                ->applyNumber()
                ->save();

        if ($this->invoice->client->getSetting('client_manual_payment_notification')) 
            $payment->service()->sendEmail();
        
        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));
        event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars()));

        $payment->ledger()
                ->updatePaymentBalance($payment->amount * -1);

        $this->client_service
            ->updateBalance($payment->amount * -1)
            ->updatePaidToDate($payment->amount)
            ->save();

        return $this->invoice;
    }
}
