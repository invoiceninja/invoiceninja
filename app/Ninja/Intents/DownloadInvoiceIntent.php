<?php

namespace App\Ninja\Intents;

use App\Libraries\Skype\SkypeResponse;
use App\Models\Invoice;

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
