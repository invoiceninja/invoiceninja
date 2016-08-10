<?php namespace App\Ninja\Intents;

use Auth;
use Exception;
use App\Models\EntityModel;
use App\Models\Invoice;
use App\Libraries\Skype\SkypeResponse;

class EmailInvoiceIntent extends InvoiceIntent
{
    public function process()
    {
        $invoice = $this->invoice();

        if ( ! Auth::user()->can('edit', $invoice)) {
            throw new Exception(trans('texts.not_allowed'));
        }

        $contactMailer = app('App\Ninja\Mailers\ContactMailer');
        $contactMailer->sendInvoice($invoice);

        $message = trans('texts.bot_emailed_' . $invoice->getEntityType());

        return SkypeResponse::message($message);
    }
}
