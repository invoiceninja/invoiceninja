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

namespace Tests\Integration;

use App\Jobs\Util\SendFailedEmails;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Jobs\Util\SendFailedEmails
 */
class SendFailedEmailsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testReminderFires()
    {
        $invitation = $this->invoice->invitations->first();
        $reminder_template = $this->invoice->calculateTemplate('invoice');

        $sl = [
            'entity_name' => \App\Models\InvoiceInvitation::class,
            'invitation_key' => $invitation->key,
            'reminder_template' => $reminder_template,
            'subject' => '',
            'body' => '',
        ];

        $system_log = new SystemLog();
        $system_log->company_id = $this->invoice->company_id;
        $system_log->client_id = $this->invoice->client_id;
        $system_log->category_id = SystemLog::CATEGORY_MAIL;
        $system_log->event_id = SystemLog::EVENT_MAIL_RETRY_QUEUE;
        $system_log->type_id = SystemLog::TYPE_QUOTA_EXCEEDED;
        $system_log->log = $sl;
        $system_log->save();

        $sys_log = SystemLog::where('event_id', SystemLog::EVENT_MAIL_RETRY_QUEUE)->first();

        $this->assertNotNull($sys_log);

        SendFailedEmails::dispatch();
    }
}
