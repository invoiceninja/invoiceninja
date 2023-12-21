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

namespace App\Jobs\Brevo;

use App\DataMapper\Analytics\Mail\EmailBounce;
use App\DataMapper\Analytics\Mail\EmailSpam;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Models\SystemLog;
use App\Notifications\Ninja\EmailSpamNotification;
use Brevo\Client\Configuration;
use Brevo\Client\Model\GetTransacEmailContentEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Brevo\Client\Api\TransactionalEmailsApi;
use Turbo124\Beacon\Facades\LightLogs;

class ProcessBrevoWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $invitation;

    private $entity;

    private array $default_response = [
        'recipients' => '',
        'subject' => 'Message not found.',
        'entity' => '',
        'entity_id' => '',
        'events' => [],
    ];

    /**
     * Create a new job instance.
     *
     */
    public function __construct(private array $request)
    {
    }

    private function getSystemLog(string $message_id): ?SystemLog
    {
        return SystemLog::query()
            ->where('company_id', $this->invitation->company_id)
            ->where('type_id', SystemLog::TYPE_WEBHOOK_RESPONSE)
            ->whereJsonContains('log', ['message-id' => $message_id])
            ->orderBy('id', 'desc')
            ->first();

    }

    private function updateSystemLog(SystemLog $system_log, array $data): void
    {
        $system_log->log = $data;
        $system_log->save();
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->request['tag']);

        $this->invitation = $this->discoverInvitation($this->request['message-id']);

        if (!$this->invitation) {
            return;
        }

        // if (array_key_exists('Details', $this->request)) {
        //     $this->invitation->email_error = $this->request['Details'];
        // } // no details, when error occured

        switch ($this->request['event']) {
            case 'delivered':
                return $this->processDelivery();
            case 'soft_bounce':
            case 'hard_bounce':
            case 'invalid_email':
                return $this->processBounce();
            case 'spam':
                return $this->processSpamComplaint();
            case 'unique_opened':
            case 'opened':
                return $this->processOpen();
            default:
                # code...
                break;
        }
    }

    // {
    //     "event" :  "unique_opened",
    //     "email" :  "example@example.com",
    //     "id" :  1,
    //     "date" :  "yyyy-m-d h:i:s",
    //     "message-id" :  "<xxx@msgid.domain>",
    //     "subject" :  "Test subject",
    //     "tag" :  "<defined-tag>",
    //     "sending_ip" :  "xxx.xx.xxx.xx",
    //     "ts_epoch" :  1534486682000,
    //     "template_id" :  1
    //  }
    //  {
    //     "event" :  "opened",
    //     "email" :  "frichris@hotmail.fr",
    //     "id" :  1,
    //     "date" :  "yyyy-m-d h:i:s",
    //     "message-id" :  "<xxx@msgid.domain>",
    //     "subject" :  "Test subject",
    //     "tag" :  "<defined-tag>",
    //     "sending_ip" :  "xxx.xx.xxx.xx",
    //     "ts_epoch" :  1534486682000,
    //     "template_id" :  1
    //  }

    private function processOpen()
    {
        $this->invitation->opened_date = now();
        $this->invitation->save();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (new SystemLogger(
            $data,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_OPENED,
            SystemLog::TYPE_WEBHOOK_RESPONSE,
            $this->invitation->contact->client,
            $this->invitation->company
        ))->handle();
    }

    // {
    //     "event" :  "delivered",
    //     "email" :  "example@example.com",
    //     "id" :  1,
    //     "date" :  "yyyy-m-d h:i:s",
    //     "message-id" :  "<xxx@msgid.domain>",
    //     "subject" :  "Test subject",
    //     "tag" :  "<defined-tag>",
    //     "sending_ip" :  "xxx.xx.xxx.xx",
    //     "ts_epoch" :  1534486682000,
    //     "template_id" :  1
    //  }
    private function processDelivery()
    {
        $this->invitation->email_status = 'delivered';
        $this->invitation->save();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (new SystemLogger(
            $data,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_DELIVERY,
            SystemLog::TYPE_WEBHOOK_RESPONSE,
            $this->invitation->contact->client,
            $this->invitation->company
        ))->handle();
    }

    // {
    //     "event" :  "soft_bounce",
    //     "email" :  "example@example.com",
    //     "id" :  1,
    //     "date" :  "yyyy-mm-dd hh:i:s",
    //     "message-id" :  "<xxx@msgid.domain>",
    //     "reason" :  "<reason-for-deferred>",
    //     "tag" :  "<defined-tag>",
    //     "sending_ip" :  "xxx.xx.xxx.xx",
    //     "ts_epoch" :  1534486682000,
    //     "template_id" :  1
    //  }
    //  {
    //     "event" :  "hard_bounce",
    //     "email" :  "example@example.com",
    //     "id" :  1,
    //     "date" :  "yyyy-mm-dd hh:i:s",
    //     "message-id" :  "<xxx@msgid.domain>",
    //     "reason" :  "<reason-for-deferred>",
    //     "tag" :  "<defined-tag>",
    //     "sending_ip" :  "xxx.xx.xxx.xx",
    //     "ts_epoch" :  1534486682000,
    //     "template_id" :  1
    //  }
    //  {
    //     "event" :  "invalid_email",
    //     "email" :  "example@example.com",
    //     "id" :  1,
    //     "date" :  "yyyy-mm-dd hh:i:s",
    //     "message-id" :  "<xxx@msgid.domain>",
    //     "subject" :  "Test subject",
    //     "tag" :  "<defined-tag>",
    //     "sending_ip" :  "xxx.xx.xxx.xx",
    //     "ts_epoch" :  1534486682000,
    //     "template_id" :  1
    //  }

    private function processBounce()
    {
        $this->invitation->email_status = 'bounced';
        $this->invitation->save();

        $bounce = new EmailBounce(
            $this->request['tag'],
            $this->request['From'], // TODO: @turbo124 is this the recipent?
            $this->request['message-id']
        );

        LightLogs::create($bounce)->send();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (new SystemLogger($data, SystemLog::CATEGORY_MAIL, SystemLog::EVENT_MAIL_BOUNCED, SystemLog::TYPE_WEBHOOK_RESPONSE, $this->invitation->contact->client, $this->invitation->company))->handle();

        // if(config('ninja.notification.slack'))
        // $this->invitation->company->notification(new EmailBounceNotification($this->invitation->company->account))->ninja();
    }

    // {
    //     "event" :  "spam",
    //     "email" :  "example@example.com",
    //     "id" :  1,
    //     "date" :  "yyyy-mm-dd hh:i:s",
    //     "message-id" :  "<xxx@msgid.domain>",
    //     "tag" :  "<defined-tag>",
    //     "sending_ip" :  "xxx.xx.xxx.xx",
    //  }
    private function processSpamComplaint()
    {
        $this->invitation->email_status = 'spam';
        $this->invitation->save();

        $spam = new EmailSpam(
            $this->request['tag'],
            $this->request['From'], // TODO
            $this->request['message-id']
        );

        LightLogs::create($spam)->send();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (new SystemLogger($data, SystemLog::CATEGORY_MAIL, SystemLog::EVENT_MAIL_SPAM_COMPLAINT, SystemLog::TYPE_WEBHOOK_RESPONSE, $this->invitation->contact->client, $this->invitation->company))->handle();

        if (config('ninja.notification.slack')) {
            $this->invitation->company->notification(new EmailSpamNotification($this->invitation->company->account))->ninja();
        }
    }

    private function discoverInvitation($message_id)
    {
        $invitation = false;

        if ($invitation = InvoiceInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'invoice';
            return $invitation;
        } elseif ($invitation = QuoteInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'quote';
            return $invitation;
        } elseif ($invitation = RecurringInvoiceInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'recurring_invoice';
            return $invitation;
        } elseif ($invitation = CreditInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'credit';
            return $invitation;
        } elseif ($invitation = PurchaseOrderInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'purchase_order';
            return $invitation;
        } else {
            return $invitation;
        }
    }

    public function getRawMessage(string $message_id)
    {

        $Brevo = new TransactionalEmailsApi(null, Configuration::getDefaultConfiguration()->setApiKey('api-key', config('services.brevo.key')));
        $messageDetail = $Brevo->getTransacEmailContent($message_id);
        return $messageDetail;

    }


    public function getBounceId(string $message_id): ?int
    {

        $messageDetail = $this->getRawMessage($message_id);


        $event = collect($messageDetail->getEvents())->first(function ($event) {

            return $event?->Details?->BounceID ?? false;

        });

        return $event?->Details?->BounceID ?? null;

    }

    // TODO
    private function fetchMessage(): array
    {
        if (strlen($this->request['message-id']) < 1) {
            return $this->default_response;
        }

        try {

            $messageDetail = $this->getRawMessage($this->request['message-id']);

            $recipients = collect($messageDetail['recipients'])->flatten()->implode(',');
            $subject = $messageDetail->getSubject() ?? '';

            $events = collect($messageDetail->getEvents())->map(function (GetTransacEmailContentEvents $event) {

                return [
                    'bounce_id' => $event?->Details?->BounceID ?? '',
                    'recipient' => $event->Recipient ?? '',
                    'status' => $event->Type ?? '',
                    'delivery_message' => $event->Details->DeliveryMessage ?? $event->Details->Summary ?? '',
                    'server' => $event->Details->DestinationServer ?? '',
                    'server_ip' => $event->Details->DestinationIP ?? '',
                    'date' => \Carbon\Carbon::parse($event->ReceivedAt)->format('Y-m-d H:i:s') ?? '',
                ];

            })->toArray();

            return [
                'recipients' => $recipients,
                'subject' => $subject,
                'entity' => $this->entity ?? '',
                'entity_id' => $this->invitation->{$this->entity}->hashed_id ?? '',
                'events' => $events,
            ];

        } catch (\Exception $e) {

            return $this->default_response;

        }
    }
}
