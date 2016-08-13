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
        $invoice = $this->stateInvoice();

        if ( ! Auth::user()->can('edit', $invoice)) {
            throw new Exception(trans('texts.not_allowed'));
        }

        $contactMailer = app('App\Ninja\Mailers\ContactMailer');
        $contactMailer->sendInvoice($invoice);

        $message = trans('texts.bot_emailed_' . $invoice->getEntityType());

        if (Auth::user()->notify_viewed) {
            $message .= '<br/>' . trans('texts.bot_emailed_notify_viewed');
        } elseif (Auth::user()->notify_paid) {
            $message .= '<br/>' . trans('texts.bot_emailed_notify_paid');
        }

        return SkypeResponse::message($message);
    }
}
