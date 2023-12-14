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
use App\Models\Expense;
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
use Postmark\PostmarkClient;
use Turbo124\Beacon\Facades\LightLogs;

class ProcessMailgunInboundWebhook implements ShouldQueue
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
            ->whereJsonContains('log', ['MessageID' => $message_id])
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

        // match companies
        if (array_key_exists('ToFull', $this->request))
            throw new \Exception('invalid body');

        $toEmails = [];
        foreach ($this->request['ToFull'] as $toEmailEntry)
            $toEmails[] = $toEmailEntry['Email'];

        // create expense for each company
        $expense = new Expense();

        $expense->company_id;
    }
    // {
    //     "FromName": "Postmarkapp Support",
    //     "MessageStream": "inbound",
    //     "From": "support@postmarkapp.com",
    //     "FromFull": {
    //       "Email": "support@postmarkapp.com",
    //       "Name": "Postmarkapp Support",
    //       "MailboxHash": ""
    //     },
    //     "To": "\"Firstname Lastname\" <yourhash+SampleHash@inbound.postmarkapp.com>",
    //     "ToFull": [
    //       {
    //         "Email": "yourhash+SampleHash@inbound.postmarkapp.com",
    //         "Name": "Firstname Lastname",
    //         "MailboxHash": "SampleHash"
    //       }
    //     ],
    //     "Cc": "\"First Cc\" <firstcc@postmarkapp.com>, secondCc@postmarkapp.com>",
    //     "CcFull": [
    //       {
    //         "Email": "firstcc@postmarkapp.com",
    //         "Name": "First Cc",
    //         "MailboxHash": ""
    //       },
    //       {
    //         "Email": "secondCc@postmarkapp.com",
    //         "Name": "",
    //         "MailboxHash": ""
    //       }
    //     ],
    //     "Bcc": "\"First Bcc\" <firstbcc@postmarkapp.com>, secondbcc@postmarkapp.com>",
    //     "BccFull": [
    //       {
    //         "Email": "firstbcc@postmarkapp.com",
    //         "Name": "First Bcc",
    //         "MailboxHash": ""
    //       },
    //       {
    //         "Email": "secondbcc@postmarkapp.com",
    //         "Name": "",
    //         "MailboxHash": ""
    //       }
    //     ],
    //     "OriginalRecipient": "yourhash+SampleHash@inbound.postmarkapp.com",
    //     "Subject": "Test subject",
    //     "MessageID": "73e6d360-66eb-11e1-8e72-a8904824019b",
    //     "ReplyTo": "replyto@postmarkapp.com",
    //     "MailboxHash": "SampleHash",
    //     "Date": "Fri, 1 Aug 2014 16:45:32 -04:00",
    //     "TextBody": "This is a test text body.",
    //     "HtmlBody": "<html><body><p>This is a test html body.<\/p><\/body><\/html>",
    //     "StrippedTextReply": "This is the reply text",
    //     "Tag": "TestTag",
    //     "Headers": [
    //       {
    //         "Name": "X-Header-Test",
    //         "Value": ""
    //       },
    //       {
    //         "Name": "X-Spam-Status",
    //         "Value": "No"
    //       },
    //       {
    //         "Name": "X-Spam-Score",
    //         "Value": "-0.1"
    //       },
    //       {
    //         "Name": "X-Spam-Tests",
    //         "Value": "DKIM_SIGNED,DKIM_VALID,DKIM_VALID_AU,SPF_PASS"
    //       }
    //     ],
    //     "Attachments": [
    //       {
    //         "Name": "test.txt",
    //         "Content": "VGhpcyBpcyBhdHRhY2htZW50IGNvbnRlbnRzLCBiYXNlLTY0IGVuY29kZWQu",
    //         "ContentType": "text/plain",
    //         "ContentLength": 45
    //       }
    //     ]
    //   }
}
