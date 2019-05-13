<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2019. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Invoice;

use App\Factory\PaymentFactory;
use App\Jobs\Invoice\ApplyPaymentToInvoice;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\InvoiceRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarkPaid implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $invoice;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Invoice $invoice)
    {

        $this->invoice = $invoice;

    }

    /**
     * Execute the job.
     *
     * 
     * @return void
     */
    public function handle()
    {
        /* Create Payment */
        $payment = new PaymentFactory($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $invoice->balance;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $invoice->client_id;

        $payment->save();

        /* Create a payment relationship to the invoice entity */
        $payment->invoices()->save($this->invoice);


        /* Need to engineer the ability to pass an array of invoices to the activity handler*/
        $data = [
            'payment_id' => $payment->id,
            'invoice_ids' => [
                $this->invoice->id
            ]
        ];

        event(new PaymentWasCreated($data));

        /* Update Invoice balance */
        ApplyPaymentToInvoice::dispatchNow($payment, $invoice);

    }
}
