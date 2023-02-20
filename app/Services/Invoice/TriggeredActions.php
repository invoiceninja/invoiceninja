<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Invoice;

use App\Events\Invoice\InvoiceWasEmailed;
use App\Jobs\Entity\EmailEntity;
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

    private bool $updated = false;

    public function __construct(Invoice $invoice, Request $request)
    {
        $this->request = $request;

        $this->invoice = $invoice;
    }

    public function run()
    {
        if ($this->request->has('auto_bill') && $this->request->input('auto_bill') == 'true') {
            $this->invoice->service()->autoBill(); //update notification sends automatically for this.
        }

        if ($this->request->has('paid') && $this->request->input('paid') == 'true') {
            $this->invoice = $this->invoice->service()->markPaid($this->request->input('reference'))->save(); //update notification sends automatically for this.
        }

        if ($this->request->has('mark_sent') && $this->request->input('mark_sent') == 'true' && $this->invoice->status_id == Invoice::STATUS_DRAFT) {
            $this->invoice = $this->invoice->service()->markSent()->save(); //update notification NOT sent
            $this->updated = false;
        }
        
        if ($this->request->has('amount_paid') && is_numeric($this->request->input('amount_paid'))) {
            $this->invoice = $this->invoice->service()->applyPaymentAmount($this->request->input('amount_paid'), $this->request->input('reference'))->save();
            $this->updated = false;
        }

        if ($this->request->has('send_email') && $this->request->input('send_email') == 'true') {
            $this->invoice->service()->markSent()->touchPdf()->save();
            $this->sendEmail();
            $this->updated = false;
        }

        if ($this->request->has('cancel') && $this->request->input('cancel') == 'true') {
            $this->invoice = $this->invoice->service()->handleCancellation()->save();
            $this->updated = false;
        }

        if ($this->request->has('save_default_footer') && $this->request->input('save_default_footer') == 'true') {
            $company = $this->invoice->company;
            $settings = $company->settings;
            $settings->invoice_footer = $this->invoice->footer;
            $company->settings = $settings;
            $company->save();
        }

        if ($this->request->has('save_default_terms') && $this->request->input('save_default_terms') == 'true') {
            $company = $this->invoice->company;
            $settings = $company->settings;
            $settings->invoice_terms = $this->invoice->terms;
            $company->settings = $settings;
            $company->save();
        }

        if ($this->updated) {
            event('eloquent.updated: App\Models\Invoice', $this->invoice);
        }


        return $this->invoice;
    }

    private function sendEmail()
    {
        $reminder_template = $this->invoice->calculateTemplate('invoice');

        $this->invoice->invitations->load('contact.client.country', 'invoice.client.country', 'invoice.company')->each(function ($invitation) use ($reminder_template) {
            EmailEntity::dispatch($invitation, $this->invoice->company, $reminder_template);
        });

        if ($this->invoice->invitations->count() > 0) {
            event(new InvoiceWasEmailed($this->invoice->invitations->first(), $this->invoice->company, Ninja::eventVars(), 'invoice'));
        }
    }
}
