<?php namespace App\Ninja\Intents;

use Auth;
use App\Models\EntityModel;
use App\Models\Invoice;
use App\Libraries\Skype\SkypeResponse;

class DownloadInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->invoice();

        $message = trans('texts.' . $invoice->getEntityType()) . ' ' . $invoice->invoice_number;
        $message = link_to('/download/' . $invoice->invitations[0]->invitation_key, $message);

        return SkypeResponse::message($message);
    }
}
