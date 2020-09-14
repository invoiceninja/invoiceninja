<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */
namespace Tests\Integration;

use App\Jobs\Invoice\EmailInvoice;
use App\Jobs\Util\SendFailedEmails;
use App\Jobs\Util\SystemLogger;
use App\Models\SystemLog;
use Illuminate\Foundation\Testing\Concerns\InteractsWithDatabase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 * @covers App\Jobs\Util\SendFailedEmails
 */
class SendFailedEmailsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public function setUp() :void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testReminderFires()
    {
        $invitation = $this->invoice->invitations->first();
        $reminder_template = $this->invoice->calculateTemplate();

        $sl = [
            'entity_name' => \App\Models\InvoiceInvitation::class,
            'invitation_key' => $invitation->key,
            'reminder_template' => $reminder_template,
            'subject' => '',
            'body' => '',
        ];

        $system_log = new SystemLog;
        $system_log->company_id = $this->invoice->company_id;
        $system_log->client_id = $this->invoice->client_id;
        $system_log->category_id = SystemLog::CATEGORY_MAIL;
        $system_log->event_id = SystemLog::EVENT_MAIL_RETRY_QUEUE;
        $system_log->type_id = SystemLog::TYPE_QUOTA_EXCEEDED;
        $system_log->log = $sl;
        $system_log->save();

        $sys_log = SystemLog::where('event_id', SystemLog::EVENT_MAIL_RETRY_QUEUE)->first();

        $this->assertNotNull($sys_log);

        // Queue::fake();
        SendFailedEmails::dispatch();

        //Queue::assertPushed(SendFailedEmails::class);
        //Queue::assertPushed(EmailInvoice::class);
        //$this->expectsJobs(EmailInvoice::class);
    }
}
