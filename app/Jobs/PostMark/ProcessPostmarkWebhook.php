<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\PostMark;

use App\DataMapper\Analytics\Mail\EmailBounce;
use App\DataMapper\Analytics\Mail\EmailSpam;
use App\Events\Payment\PaymentWasEmailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\Engine\PaymentEmailEngine;
use App\Mail\TemplateEmail;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Models\SystemLog;
use App\Notifications\Ninja\EmailBounceNotification;
use App\Notifications\Ninja\EmailSpamNotification;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Turbo124\Beacon\Facades\LightLogs;

class ProcessPostmarkWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    private array $request;

    public $invitation;
    /**
     * Create a new job instance.
     *
     * @param Payment $payment
     * @param $email_builder
     * @param $contact
     * @param $company
     */
    public function __construct(array $request)
    {
        $this->request = $request;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {

        MultiDB::findAndSetDbByCompanyKey($this->request['Tag']);
        
        $this->invitation = $this->discoverInvitation($this->request['MessageID']);

        if(!$this->invitation)
            return;

        $this->invitation->email_error = $this->request['Details'];
        
        switch ($this->request['RecordType']) 
        {
            case 'Delivery':
                return $this->processDelivery();
            case 'Bounce':
                return $this->processBounce();
            case 'SpamComplaint':
                return $this->processSpamComplaint();
            default:
                # code...
                break;
        }

    }

// {
//   "RecordType": "Delivery",
//   "ServerID": 23,
//   "MessageStream": "outbound",
//   "MessageID": "00000000-0000-0000-0000-000000000000",
//   "Recipient": "john@example.com",
//   "Tag": "welcome-email",
//   "DeliveredAt": "2021-02-21T16:34:52Z",
//   "Details": "Test delivery webhook details",
//   "Metadata": {
//     "example": "value",
//     "example_2": "value"
//   }
// }
    private function processDelivery()
    {
        $this->invitation->email_status = 'delivered';
        $this->invitation->save();

        SystemLogger::dispatch($this->request, 
            SystemLog::CATEGORY_MAIL, 
            SystemLog::EVENT_MAIL_DELIVERY, 
            SystemLog::TYPE_WEBHOOK_RESPONSE, 
            $this->invitation->contact->client,
            $this->invitation->company
        );
    }

// {
//   "Metadata": {
//     "example": "value",
//     "example_2": "value"
//   },
//   "RecordType": "Bounce",
//   "ID": 42,
//   "Type": "HardBounce",
//   "TypeCode": 1,
//   "Name": "Hard bounce",
//   "Tag": "Test",
//   "MessageID": "00000000-0000-0000-0000-000000000000",
//   "ServerID": 1234,
//   "MessageStream": "outbound",
//   "Description": "The server was unable to deliver your message (ex: unknown user, mailbox not found).",
//   "Details": "Test bounce details",
//   "Email": "john@example.com",
//   "From": "sender@example.com",
//   "BouncedAt": "2021-02-21T16:34:52Z",
//   "DumpAvailable": true,
//   "Inactive": true,
//   "CanActivate": true,
//   "Subject": "Test subject",
//   "Content": "Test content"
// }

    private function processBounce()
    {
        $this->invitation->email_status = 'bounced';
        $this->invitation->save();

        $bounce = new EmailBounce(
            $this->request['Tag'],
            $this->request['From'],
            $this->request['MessageID']
        );

        LightLogs::create($bounce)->queue();

        SystemLogger::dispatch($this->request, SystemLog::CATEGORY_MAIL, SystemLog::EVENT_MAIL_BOUNCED, SystemLog::TYPE_WEBHOOK_RESPONSE, $this->invitation->contact->client, $this->invitation->company);

        if(config('ninja.notification.slack'))
            $this->invitation->company->notification(new EmailBounceNotification($this->invitation->company->account))->ninja();

    }

// {
//   "Metadata": {
//     "example": "value",
//     "example_2": "value"
//   },
//   "RecordType": "SpamComplaint",
//   "ID": 42,
//   "Type": "SpamComplaint",
//   "TypeCode": 100001,
//   "Name": "Spam complaint",
//   "Tag": "Test",
//   "MessageID": "00000000-0000-0000-0000-000000000000",
//   "ServerID": 1234,
//   "MessageStream": "outbound",
//   "Description": "The subscriber explicitly marked this message as spam.",
//   "Details": "Test spam complaint details",
//   "Email": "john@example.com",
//   "From": "sender@example.com",
//   "BouncedAt": "2021-02-21T16:34:52Z",
//   "DumpAvailable": true,
//   "Inactive": true,
//   "CanActivate": false,
//   "Subject": "Test subject",
//   "Content": "Test content"
// }
    private function processSpamComplaint()
    {

        $this->invitation->email_status = 'spam';
        $this->invitation->save();

        $spam = new EmailSpam(
            $this->request['Tag'],
            $this->request['From'],
            $this->request['MessageID']
        );

        LightLogs::create($spam)->queue();

        SystemLogger::dispatch($this->request, SystemLog::CATEGORY_MAIL, SystemLog::EVENT_MAIL_SPAM_COMPLAINT, SystemLog::TYPE_WEBHOOK_RESPONSE, $this->invitation->contact->client, $this->invitation->company);

        if(config('ninja.notification.slack'))
            $this->invitation->company->notification(new EmailSpamNotification($this->invitation->company->account))->ninja();

    }

    private function discoverInvitation($message_id)
    {
        $invitation = false;

        if($invitation = InvoiceInvitation::where('message_id', $message_id)->first())
            return $invitation;
        elseif($invitation = QuoteInvitation::where('message_id', $message_id)->first())
            return $invitation;
        elseif($invitation = RecurringInvoiceInvitation::where('message_id', $message_id)->first())
            return $invitation;
        elseif($invitation = CreditInvitation::where('message_id', $message_id)->first())
            return $invitation;
        else
            return $invitation;
    }
}