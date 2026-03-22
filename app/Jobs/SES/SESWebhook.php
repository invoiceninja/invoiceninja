<?php

namespace App\Jobs\SES;

use App\Models\Company;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Jobs\Util\SystemLogger;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Facades\LightLogs;
use App\Models\PurchaseOrderInvitation;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\RecurringInvoiceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\DataMapper\Analytics\Mail\EmailSpam;
use App\DataMapper\Analytics\Mail\EmailBounce;
use App\Notifications\Ninja\EmailSpamNotification;
use App\Notifications\Ninja\EmailBounceNotification;

class SESWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $invitation;

    private $entity;

    private array $default_response = [
        'recipients' => '',
        'subject' => 'Message not found.',
        'entity' => '',
        'entity_id' => '',
        'events' => [],
        'event_type' => 'unknown',
        'timestamp' => '',
        'message_id' => '',
    ];

    private ?Company $company = null;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(private array $request) {}

    private function getSystemLog(string $message_id): ?SystemLog
    {
        return SystemLog::query()
            ->where('company_id', $this->invitation->company_id)
            ->where('type_id', SystemLog::TYPE_WEBHOOK_RESPONSE)
            ->where('category_id', SystemLog::CATEGORY_MAIL)
            ->where('log->MessageID', $message_id)
            ->orderBy('id', 'desc')
            ->first();
    }

    private function updateSystemLog(SystemLog $system_log, array $data): void
    {
        // Get existing log data or initialize empty array
        $existing_log = $system_log->log ?? [];
        $existing_history = $existing_log['history'] ?? [];
        $existing_events = $existing_history['events'] ?? [];

        // Get new event data from the current webhook
        $new_event = $this->extractEventData();

        // Check if this event type already exists in events array
        $event_type = $this->getCurrentEventType();
        $event_exists = false;

        foreach ($existing_events as $event) {
            if (isset($event['event_type']) && $event['event_type'] === $event_type) {
                $event_exists = true;
                break;
            }
        }

        // Only add new event if this event type doesn't already exist
        if (!$event_exists && !empty($new_event)) {
            $existing_events[] = $new_event;
        }

        // Update the history with existing events plus any new event
        $updated_history = array_merge($existing_history, [
            'events' => $existing_events,
        ]);

        // Update the log with existing data plus updated history
        $system_log->log = array_merge($existing_log, [
            'history' => $updated_history,
            'last_updated' => now()->toISOString(),
            'last_event_type' => $event_type,
        ]);

        $system_log->save();
    }

    /**
     * Get the current event type being processed
     */
    private function getCurrentEventType(): string
    {
        $notification_type = $this->request['eventType'] ?? $this->request['Type'] ?? $this->request['notificationType'] ?? '';

        switch ($notification_type) {
            case 'Delivery':
            case 'Received':
                return 'delivery';
            case 'Bounce':
                return 'bounce';
            case 'Complaint':
                return 'complaint';
            case 'Open':
            case 'Rendering Failure':
                return 'open';
            default:
                return 'unknown';
        }
    }

    /**
     * Extract company key from SES message tags or metadata
     */
    private function extractCompanyKey(): ?string
    {
        // Check various possible locations for company key

        // Check mail tags
        if (isset($this->request['mail']['tags']['company_key'])) {
            nlog("SESWebhook: Found company key in mail tags: " . $this->request['mail']['tags']['company_key']);
            return $this->request['mail']['tags']['company_key'];
        }

        // Check email headers - specifically X-Tag which contains the company key
        if (isset($this->request['mail']['headers'])) {
            nlog("SESWebhook: Checking mail headers for X-Tag", [
                'headers_count' => count($this->request['mail']['headers']),
                'headers' => $this->request['mail']['headers'],
            ]);

            foreach ($this->request['mail']['headers'] as $header) {
                if (isset($header['name']) && $header['name'] === 'X-Tag' && isset($header['value'])) {
                    nlog("SESWebhook: Found X-Tag header: " . $header['value']);
                    return $header['value'];
                }
            }

            nlog("SESWebhook: X-Tag header not found in mail headers");
        }

        // Check common headers
        if (isset($this->request['mail']['commonHeaders']['x-company-key'])) {
            nlog("SESWebhook: Found company key in common headers: " . $this->request['mail']['commonHeaders']['x-company-key']);
            return $this->request['mail']['commonHeaders']['x-company-key'];
        }

        // Check if company key is in the main request
        if (isset($this->request['company_key'])) {
            nlog("SESWebhook: Found company key in main request: " . $this->request['company_key']);
            return $this->request['company_key'];
        }

        nlog("SESWebhook: No company key found in any location", [
            'mail_headers_exists' => isset($this->request['mail']['headers']),
            'mail_common_headers_exists' => isset($this->request['mail']['commonHeaders']),
            'request_keys' => array_keys($this->request),
        ]);

        return null;
    }

    /**
     * Extract message ID from SES notification
     */
    private function extractMessageId(): ?string
    {
        // Check various possible locations for message ID
        if (isset($this->request['mail']['messageId'])) {
            nlog("SESWebhook: Found message ID in mail: " . $this->request['mail']['messageId']);
            return $this->request['mail']['messageId'];
        }

        if (isset($this->request['messageId'])) {
            nlog("SESWebhook: Found message ID in main request: " . $this->request['messageId']);
            return $this->request['messageId'];
        }

        nlog("SESWebhook: No message ID found in any location");
        return null;
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        nlog("SESWebhook: Processing SES webhook data", ['request' => $this->request]);

        // Extract company key from SES message tags or metadata
        $company_key = $this->extractCompanyKey();

        if (!$company_key) {
            nlog("SESWebhook: No company key found in webhook data");
            return;
        }

        MultiDB::findAndSetDbByCompanyKey($company_key);
        $this->company = Company::query()->where('company_key', $company_key)->first();

        if (!$this->company) {
            nlog("SESWebhook: Company not found for key: " . $company_key);
            return;
        }

        // Extract message ID from SES notification
        $message_id = $this->extractMessageId();

        if (!$message_id) {
            nlog("SESWebhook: No message ID found in webhook data");
            return;
        }

        $this->invitation = $this->discoverInvitation($message_id);

        if (!$this->invitation) {
            nlog("SESWebhook: No invitation found for message ID: " . $message_id);
            return;
        }

        // Handle different SES notification types
        $notification_type = $this->request['eventType'] ?? $this->request['Type'] ?? $this->request['notificationType'] ?? '';

        switch ($notification_type) {
            case 'Delivery':
            case 'Received':
                return $this->processDelivery();
            case 'Bounce':
                return $this->processBounce();
            case 'Complaint':
                return $this->processComplaint();
            case 'Open':
            case 'Rendering Failure':
                return $this->processOpen();
            default:
                nlog("SESWebhook: Unknown notification type: " . $notification_type);
                break;
        }
    }

    /**
     * Process email delivery confirmation
     */
    private function processDelivery()
    {
        $this->invitation->email_status = 'delivered';
        $this->invitation->saveQuietly();

        $this->request['MessageID'] = $this->extractMessageId();
        $data = array_merge($this->request, [
            'history' => $this->fetchMessage(),
            'MessageID' => $this->extractMessageId(),
        ]);

        $sl = $this->getSystemLog($this->extractMessageId());

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (
            new SystemLogger(
                $data,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_DELIVERY,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->invitation->contact->client,
                $this->invitation->company
            )
        )->handle();
    }

    /**
     * Process email bounce
     */
    private function processBounce()
    {
        $this->invitation->email_status = 'bounced';
        $this->invitation->saveQuietly();

        // Check if this is a confirmation email bounce
        $subject = $this->request['bounce']['bouncedRecipients'][0]['email'] ?? '';
        if ($subject == ctrans('texts.confirmation_subject')) {
            $this->company->notification(new EmailBounceNotification($subject))->ninja();
        }

        $bounce = new EmailBounce(
            $this->extractCompanyKey(),
            $this->request['mail']['source'] ?? '',
            $this->extractMessageId()
        );

        LightLogs::create($bounce)->send();

        $data = array_merge($this->request, [
            'history' => $this->fetchMessage(),
            'MessageID' => $this->extractMessageId(),
        ]);

        $sl = $this->getSystemLog($this->extractMessageId());

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (
            new SystemLogger(
                $data,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_BOUNCED,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->invitation->contact->client,
                $this->invitation->company
            )
        )->handle();
    }

    /**
     * Process spam complaint
     */
    private function processComplaint()
    {
        $this->invitation->email_status = 'spam';
        $this->invitation->saveQuietly();

        if (config('ninja.notification.slack')) {
            $this->company->notification(new EmailSpamNotification($this->company))->ninja();
        }

        $spam = new EmailSpam(
            $this->extractCompanyKey(),
            $this->request['mail']['source'] ?? '',
            $this->extractMessageId()
        );

        LightLogs::create($spam)->send();

        $data = array_merge($this->request, [
            'history' => $this->fetchMessage(),
            'MessageID' => $this->extractMessageId(),
        ]);

        $sl = $this->getSystemLog($this->extractMessageId());

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (
            new SystemLogger(
                $data,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_SPAM_COMPLAINT,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->invitation->contact->client,
                $this->invitation->company
            )
        )->handle();
    }

    /**
     * Process email open
     */
    private function processOpen()
    {
        $this->invitation->opened_date = now();
        $this->invitation->saveQuietly();

        $data = array_merge($this->request, [
            'history' => $this->fetchMessage(),
            'MessageID' => $this->extractMessageId(),
        ]);

        $sl = $this->getSystemLog($this->extractMessageId());

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (
            new SystemLogger(
                $data,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_OPENED,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->invitation->contact->client,
                $this->invitation->company
            )
        )->handle();
    }

    /**
     * Discover invitation by message ID
     */
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

    /**
     * Fetch message details and create response data
     */
    private function fetchMessage(): array
    {
        $message_id = $this->extractMessageId();

        if (strlen($message_id) < 1) {
            return $this->default_response;
        }

        try {
            // Extract information from SES webhook data
            $recipients = $this->extractRecipients();
            $subject = $this->extractSubject();
            $events = $this->extractEvents();
            $event_type = $this->getCurrentEventType();

            return [
                'recipients' => $recipients,
                'subject' => $subject,
                'entity' => $this->entity ?? '',
                'entity_id' => $this->invitation->{$this->entity}->hashed_id ?? '',
                'events' => [$this->extractEventData()], // Start with single event in array
                'event_type' => $event_type,
                'timestamp' => now()->toISOString(),
                'message_id' => $message_id,
            ];

        } catch (\Exception $e) {
            nlog("SESWebhook: Error fetching message: " . $e->getMessage());
            return $this->default_response;
        }
    }

    /**
     * Extract recipients from SES webhook data
     */
    private function extractRecipients(): string
    {
        if (isset($this->request['mail']['destination'])) {
            return is_array($this->request['mail']['destination'])
                ? implode(',', $this->request['mail']['destination'])
                : $this->request['mail']['destination'];
        }

        if (isset($this->request['bounce']['bouncedRecipients'])) {
            return collect($this->request['bounce']['bouncedRecipients'])
                ->pluck('emailAddress')
                ->implode(',');
        }

        if (isset($this->request['complaint']['complainedRecipients'])) {
            return collect($this->request['complaint']['complainedRecipients'])
                ->pluck('emailAddress')
                ->implode(',');
        }

        return '';
    }

    /**
     * Extract subject from SES webhook data
     */
    private function extractSubject(): string
    {
        return $this->request['mail']['commonHeaders']['subject']
               ?? $this->request['bounce']['bouncedRecipients'][0]['email']
               ?? '';
    }

    /**
     * Extract events from the webhook data
     */
    private function extractEvents(): array
    {
        $event_type = $this->getCurrentEventType();
        $message_id = $this->extractMessageId();

        switch ($event_type) {
            case 'delivery':
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Delivered',
                    'delivery_message' => $this->request['delivery']['smtpResponse'] ?? 'Successfully delivered',
                    'server' => $this->request['delivery']['processingTimeMillis'] ?? '',
                    'server_ip' => $this->request['delivery']['remoteMtaIp'] ?? '',
                    'date' => $this->request['delivery']['timestamp'] ?? now()->toISOString(),
                ];

            case 'bounce':
                $bounce_data = $this->request['bounce'] ?? [];
                return [
                    'bounce_id' => $bounce_data['bounceId'] ?? '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Bounced',
                    'bounce_type' => $bounce_data['bounceType'] ?? '',
                    'bounce_sub_type' => $bounce_data['bounceSubType'] ?? '',
                    'date' => $bounce_data['timestamp'] ?? now()->toISOString(),
                ];

            case 'complaint':
                $complaint_data = $this->request['complaint'] ?? [];
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Spam Complaint',
                    'complaint_type' => $complaint_data['complaintFeedbackType'] ?? '',
                    'date' => $complaint_data['timestamp'] ?? now()->toISOString(),
                ];

            case 'open':
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Opened',
                    'date' => now()->toISOString(),
                ];

            default:
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Unknown',
                    'date' => now()->toISOString(),
                ];
        }
    }

    /**
     * Extract event data for the current webhook
     */
    private function extractEventData(): array
    {
        $event_type = $this->getCurrentEventType();
        $message_id = $this->extractMessageId();

        switch ($event_type) {
            case 'delivery':
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Delivered',
                    'delivery_message' => $this->request['delivery']['smtpResponse'] ?? 'Successfully delivered',
                    'server' => $this->request['delivery']['processingTimeMillis'] ?? '',
                    'server_ip' => $this->request['delivery']['remoteMtaIp'] ?? '',
                    'date' => $this->request['delivery']['timestamp'] ?? now()->toISOString(),
                    'event_type' => $event_type,
                    'timestamp' => now()->toISOString(),
                    'message_id' => $message_id,
                ];

            case 'bounce':
                $bounce_data = $this->request['bounce'] ?? [];
                return [
                    'bounce_id' => $bounce_data['bounceId'] ?? '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Bounced',
                    'bounce_type' => $bounce_data['bounceType'] ?? '',
                    'bounce_sub_type' => $bounce_data['bounceSubType'] ?? '',
                    'date' => $bounce_data['timestamp'] ?? now()->toISOString(),
                    'event_type' => $event_type,
                    'timestamp' => now()->toISOString(),
                    'message_id' => $message_id,
                ];

            case 'complaint':
                $complaint_data = $this->request['complaint'] ?? [];
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Spam Complaint',
                    'complaint_type' => $complaint_data['complaintFeedbackType'] ?? '',
                    'date' => $complaint_data['timestamp'] ?? now()->toISOString(),
                    'event_type' => $event_type,
                    'timestamp' => now()->toISOString(),
                    'message_id' => $message_id,
                ];

            case 'open':
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Opened',
                    'date' => now()->toISOString(),
                    'event_type' => $event_type,
                    'timestamp' => now()->toISOString(),
                    'message_id' => $message_id,
                ];

            default:
                return [
                    'bounce_id' => '',
                    'recipient' => $this->extractRecipients(),
                    'status' => 'Unknown',
                    'date' => now()->toISOString(),
                    'event_type' => $event_type,
                    'timestamp' => now()->toISOString(),
                    'message_id' => $message_id,
                ];
        }
    }

    /**
     * Handle job failure
     */
    public function failed($exception = null)
    {
        if ($exception) {
            nlog("SESWebhook:: " . $exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }
}
