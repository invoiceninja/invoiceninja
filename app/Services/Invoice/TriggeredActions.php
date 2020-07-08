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

use App\Events\Invoice\InvoiceWasEmailed;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Helpers\Email\InvoiceEmail;
use App\Jobs\Invoice\EmailInvoice;
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Services\Client\ClientService;
use App\Services\Payment\PaymentService;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Http\Request;

class TriggeredActions extends AbstractService
{
    use GeneratesCounter;

    private $request;

    private $invoice;

    public function __construct(Invoice $invoice, Request $request)
    {
        $this->request = $request;
        
        $this->invoice = $invoice;
    }

    public function run()
    {

        if($this->request->has('auto_bill')) {
            $this->invoice->service()->autoBill()->save();
        }
        
        if($this->request->has('paid') && (bool)$this->request->input('paid') !== false) {
            $this->invoice->service()->markPaid()->save();
        }

        if($this->request->has('send_email') && (bool)$this->request->input('send_email') !== false) {
            $this->sendEmail();
        }

        return $this->invoice;
    }

    private function sendEmail()
    {

        $reminder_template = $this->invoice->calculateTemplate();

        $this->invoice->invitations->load('contact.client.country','invoice.client.country','invoice.company')->each(function ($invitation) use($reminder_template){

            $email_builder = (new InvoiceEmail())->build($invitation, $reminder_template);

            EmailInvoice::dispatch($email_builder, $invitation, $this->invoice->company);

        });

        if ($this->invoice->invitations->count() > 0) {
            event(new InvoiceWasEmailed($this->invoice->invitations->first(), $this->invoice->company));
        }

    }
}
