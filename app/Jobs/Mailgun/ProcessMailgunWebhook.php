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

namespace App\Jobs\Mailgun;

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
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Mailgun\Mailgun;
use Postmark\PostmarkClient;
use Turbo124\Beacon\Facades\LightLogs;

// TODO

class ProcessMailgunWebhook implements ShouldQueue
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
            ->whereJsonContains('log', ['id' => $message_id])
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
        MultiDB::findAndSetDbByCompanyKey($this->request['Tag']);

        $this->invitation = $this->discoverInvitation($this->request['message']['headers']['message-id']);

        if (!$this->invitation) {
            return;
        }

        if (array_key_exists('Details', $this->request)) {
            $this->invitation->email_error = $this->request['Details'];
        }

        switch ($this->request['event'] ?? $this->request['severity']) {
            case 'delivered':
                return $this->processDelivery();
            case 'failed':
            case 'permanent':
            case 'temporary':
                return $this->processBounce();
            case 'complained':
                return $this->processSpamComplaint();
            case 'opened':
                return $this->processOpen();
            default:
                # code...
                break;
        }
    }

    // {
    //   "event": "opened",
    //   "id": "-laxIqj9QWubsjY_3pTq_g",
    //   "timestamp": 1377047343.042277,
    //   "log-level": "info",
    //   "recipient": "recipient@example.com",
    //   "geolocation": {
    //     "country": "US",
    //     "region": "Texas",
    //     "city": "Austin"
    //   },
    //   "tags": [],
    //   "campaigns": [],
    //   "user-variables": {},
    //   "ip": "111.111.111.111",
    //   "client-info": {
    //     "client-type": "mobile browser",
    //     "client-os": "iOS",
    //     "device-type": "mobile",
    //     "client-name": "Mobile Safari",
    //     "user-agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 6_1 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Mobile/10B143",
    //     "bot": ""
    //   },
    //   "message": {
    //     "headers": {
    //       "message-id": "20130821005614.19826.35976@samples.mailgun.org"
    //     }
    //   },
    // }

    private function processOpen()
    {
        $this->invitation->opened_date = now();
        $this->invitation->save();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message']['headers']['message-id']);

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
    //   "event": "delivered",
    //   "id": "hK7mQVt1QtqRiOfQXta4sw",
    //   "timestamp": 1529692199.626182,
    //   "log-level": "info",
    //   "envelope": {
    //     "transport": "smtp",
    //     "sender": "sender@example.org",
    //     "sending-ip": "123.123.123.123",
    //     "targets": "john@example.com"
    //   },
    //   "flags": {
    //     "is-routed": false,
    //     "is-authenticated": false,
    //     "is-system-test": false,
    //     "is-test-mode": false
    //   },
    //   "delivery-status": {
    //     "tls": true,
    //     "mx-host": "aspmx.l.example.com",
    //     "code": 250,
    //     "description": "",
    //     "session-seconds": 0.4367079734802246,
    //     "utf8": true,
    //     "attempt-no": 1,
    //     "message": "OK",
    //     "certificate-verified": true
    //   },
    //   "message": {
    //     "headers": {
    //       "to": "team@example.org",
    //       "message-id": "20180622182958.1.48906CB188F1A454@exmple.org",
    //       "from": "sender@exmple.org",
    //       "subject": "Test Subject"
    //     },
    //     "attachments": [],
    //     "size": 586
    //   },
    //     "storage": {
    //             "url": "https://storage-us-west1.api.mailgun.net/v3/domains/...",
    //             "region": "us-west1",
    //             "key": "AwABB...",
    //             "env": "production"
    //   },
    //   "recipient": "john@example.com",
    //   "recipient-domain": "example.com",
    //   "campaigns": [],
    //   "tags": [],
    //   "user-variables": {}
    // }
    private function processDelivery()
    {
        $this->invitation->email_status = 'delivered';
        $this->invitation->save();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message']['headers']['message-id']);

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
    //   "event": "failed", || "temporary" || "permanent"
    //   "id": "pl271FzxTTmGRW8Uj3dUWw",
    //   "timestamp": 1529701969.818328,
    //   "log-level": "error",
    //   "severity": "permanent",
    //   "reason": "suppress-bounce",
    //   "envelope": {
    //     "sender": "john@example.org",
    //     "transport": "smtp",
    //     "targets": "joan@example.com"
    //   },
    //   "flags": {
    //     "is-routed": false,
    //     "is-authenticated": true,
    //     "is-system-test": false,
    //     "is-test-mode": false
    //   },
    //   "delivery-status": {
    //     "attempt-no": 1,
    //     "message": "",
    //     "code": 605,
    //     "description": "Not delivering to previously bounced address",
    //     "session-seconds": 0.0
    //   },
    //   "message": {
    //     "headers": {
    //       "to": "joan@example.com",
    //       "message-id": "20180622211249.1.2A6098970A380E12@example.org",
    //       "from": "john@example.org",
    //       "subject": "Test Subject"
    //     },
    //     "attachments": [],
    //     "size": 867
    //   },
    //   "storage": {
    //     "url": "https://se.api.mailgun.net/v3/domains/example.org/messages/eyJwI...",
    //     "key": "eyJwI..."
    //   },
    //   "recipient": "slava@mailgun.com",
    //   "recipient-domain": "mailgun.com",
    //   "campaigns": [],
    //   "tags": [],
    //   "user-variables": {}
    // }
    private function processBounce()
    {
        $this->invitation->email_status = 'bounced';
        $this->invitation->save();

        $bounce = new EmailBounce(
            $this->request['tags']->implode(','),
            $this->request['message']['headers']['from'],
            $this->request['message']['headers']['message-id']
        );

        LightLogs::create($bounce)->send();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message']['headers']['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (new SystemLogger($data, SystemLog::CATEGORY_MAIL, SystemLog::EVENT_MAIL_BOUNCED, SystemLog::TYPE_WEBHOOK_RESPONSE, $this->invitation->contact->client, $this->invitation->company))->handle();

        // if(config('ninja.notification.slack'))
        // $this->invitation->company->notification(new EmailBounceNotification($this->invitation->company->account))->ninja();
    }

    // {
    //   "event": "opened",
    //   "id": "-laxIqj9QWubsjY_3pTq_g",
    //   "timestamp": 1377047343.042277,
    //   "log-level": "info",
    //   "recipient": "recipient@example.com",
    //   "geolocation": {
    //     "country": "US",
    //     "region": "Texas",
    //     "city": "Austin"
    //   },
    //   "tags": [],
    //   "campaigns": [],
    //   "user-variables": {},
    //   "ip": "111.111.111.111",
    //   "client-info": {
    //     "client-type": "mobile browser",
    //     "client-os": "iOS",
    //     "device-type": "mobile",
    //     "client-name": "Mobile Safari",
    //     "user-agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 6_1 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Mobile/10B143",
    //     "bot": ""
    //   },
    //   "message": {
    //     "headers": {
    //       "message-id": "20130821005614.19826.35976@samples.mailgun.org"
    //     }
    //   },
    // }
    private function processSpamComplaint()
    {
        $this->invitation->email_status = 'spam';
        $this->invitation->save();

        $spam = new EmailSpam(
            $this->request['tags'],
            $this->request['From'],
            $this->request['message']['headers']['message-id']
        );

        LightLogs::create($spam)->send();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message']['headers']['message-id']);

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

        $postmark = new PostmarkClient(config('services.postmark.token'));
        $messageDetail = $postmark->getOutboundMessageDetails($message_id);
        return $messageDetail;

    }


    // TODO: unknown
    public function getBounceId(string $message_id): ?int
    {

        $messageDetail = $this->getRawMessage($message_id);


        $event = collect($messageDetail->messageevents)->first(function ($event) {

            return $event?->Details?->BounceID ?? false;

        });

        return $event?->Details?->BounceID ?? null;

    }

    private function fetchMessage(): array
    {
        if (strlen($this->request['message']['headers']['message-id']) < 1) {
            return $this->default_response;
        }

        try {

            $mailgun = new Mailgun(config('services.mailgun.token'), config('services.mailgun.endpoint'));
            $messageDetail = $mailgun->messages()->show($this->request['message']['headers']['message-id']);

            $recipients = collect($messageDetail->getRecipients())->flatten()->implode(',');
            $subject = $messageDetail->getSubject() ?? '';

            $events = collect($mailgun->events()->get(config('services.mailgun.domain'), [
                "message-id" => $this->request['message']['headers']['message-id'],
            ])->getItems())->map(function ($event) {

                return [
                    'bounce_id' => array_key_exists("id", $event) ? $event["id"] : '', // TODO: unknown
                    'recipient' => array_key_exists("recipient", $event) ? $event["recipient"] : '',
                    'status' => array_key_exists("delivery-status", $event) && array_key_exists("code", $event["delivery-status"]) ? $event["delivery-status"]["code"] : '',
                    'delivery_message' => array_key_exists("delivery-status", $event) && array_key_exists("message", $event["delivery-status"]) ? $event["delivery-status"]["message"] : (array_key_exists("delivery-status", $event) && array_key_exists("description", $event["delivery-status"]) ? $event["delivery-status"]["description"] : ''),
                    'server' => array_key_exists("delivery-status", $event) && array_key_exists("mx-host", $event["delivery-status"]) ? $event["delivery-status"]["mx-host"] : '',
                    'server_ip' => array_key_exists("ip", $event) ? $event["ip"] : '',
                    'date' => \Carbon\Carbon::parse($event["timestamp"])->format('Y-m-d H:i:s') ?? '',
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
