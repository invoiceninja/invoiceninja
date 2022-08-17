<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Mail;

use App\Libraries\MultiDB;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\Mime\MessageConverter;

class MailSentListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle(MessageSent $event)
    {

        $message_id = $event->sent->getMessageId();

        $message = MessageConverter::toEmail($event->sent->getOriginalMessage());

        $invitation_key = $message->getHeaders()->get('x-invitation')->getValue();

        if($message_id && $invitation_key)
        {

            $invitation = $this->discoverInvitation($invitation_key);

            if(!$invitation)
                return;

            $invitation->message_id = $message_id;
            $invitation->save();   
        }

    }

    private function discoverInvitation($key)
    {
        
        $invitation = false;

        foreach (MultiDB::$dbs as $db) 
        {

            if($invitation = InvoiceInvitation::on($db)->where('key', $key)->first())
                return $invitation;
            elseif($invitation = QuoteInvitation::on($db)->where('key', $key)->first())
                return $invitation;
            elseif($invitation = RecurringInvoiceInvitation::on($db)->where('key', $key)->first())
                return $invitation;
            elseif($invitation = CreditInvitation::on($db)->where('key', $key)->first())
                return $invitation;
            elseif($invitation = PurchaseOrderInvitation::on($db)->where('key', $key)->first())
                return $invitation;

        }

        return $invitation;

    }

}
