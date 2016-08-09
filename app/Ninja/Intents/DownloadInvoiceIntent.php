<?php namespace App\Ninja\Intents;

use App\Models\EntityModel;
use App\Models\Invoice;
use Auth;

class DownloadInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->invoice();

        $message = trans('texts.' . $invoice->getEntityType()) . ' ' . $invoice->invoice_number;
        $message = link_to('/download/' . $invoice->invitations[0]->invitation_key, $message);

        return view('bots.skype.message', [
                'message' => $message
            ])->render();
    }
}
