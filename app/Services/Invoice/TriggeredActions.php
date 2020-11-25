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

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Jobs\Invoice\EmailEntity;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;
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
        if ($this->request->has('auto_bill') && $this->request->input('auto_bill') == 'true') {
            $this->invoice = $this->invoice->service()->autoBill()->save();
        }

        if ($this->request->has('paid') && $this->request->input('paid') == 'true') {
            $this->invoice = $this->invoice->service()->markPaid()->save();
        }

        if ($this->request->has('send_email') && $this->request->input('send_email') == 'true') {
            $this->sendEmail();
        }

        if ($this->request->has('mark_sent') && $this->request->input('mark_sent') == 'true') {
            $this->invoice = $this->invoice->service()->markSent()->save();
        }

        return $this->invoice;
    }

    private function sendEmail()
    {

        //$reminder_template = $this->invoice->calculateTemplate('invoice');
        $reminder_template = 'payment';

        $this->invoice->invitations->load('contact.client.country', 'invoice.client.country', 'invoice.company')->each(function ($invitation) use ($reminder_template) {
            EmailEntity::dispatch($invitation, $this->invoice->company, $reminder_template);
        });

        if ($this->invoice->invitations->count() > 0) {
            event(new InvoiceWasEmailed($this->invoice->invitations->first(), $this->invoice->company, Ninja::eventVars()));
        }
    }
}
