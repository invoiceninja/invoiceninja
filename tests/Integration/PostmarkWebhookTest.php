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

use App\Jobs\PostMark\ProcessPostmarkWebhook;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * @test
 */
class PostmarkWebhookTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp() :void
    {
        parent::setUp();

        if (! config('postmark.secret')) {
            $this->markTestSkipped('Postmark Secret Set');
        }

        $this->makeTestData();
    }

    public function testDeliveryReport()
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->message_id = '00000000-0000-0000-0000-000000000000';
        $invitation->save();

        $data = [
            'RecordType' => 'Delivery',
            'ServerID' => '23',
            'MessageStream' => 'outbound',
            'MessageID' => '00000000-0000-0000-0000-000000000000',
            'Recipient' => 'john@example.com',
            'Tag' => $this->company->company_key,
            'DeliveredAt' => '2021-02-21T16:34:52Z',
            'Details' => 'Test delivery webhook details',
        ];

        $response = $this->post('/api/v1/postmark_webhook', $data);

        $response->assertStatus(403);

        $response = $this->withHeaders([
            'X-API-SECURITY' => config('postmark.secret'),
        ])->post('/api/v1/postmark_webhook', $data);

        $response->assertStatus(200);
    }

    public function testDeliveryJob()
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->message_id = '00000000-0000-0000-0000-000000000000';
        $invitation->save();

        ProcessPostmarkWebhook::dispatchSync([
            'RecordType' => 'Delivery',
            'ServerID' => '23',
            'MessageStream' => 'outbound',
            'MessageID' => '00000000-0000-0000-0000-000000000000',
            'Recipient' => 'john@example.com',
            'Tag' => $this->company->company_key,
            'DeliveredAt' => '2021-02-21T16:34:52Z',
            'Details' => 'Test delivery webhook details',
        ]);

        $this->assertEquals('delivered', $invitation->fresh()->email_status);
    }

    public function testSpamReport()
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->message_id = '00000000-0000-0000-0000-000000000001';
        $invitation->save();

        $data = [
            'RecordType' => 'SpamComplaint',
            'ServerID' => '23',
            'MessageStream' => 'outbound',
            'MessageID' => '00000000-0000-0000-0000-000000000001',
            'Recipient' => 'john@example.com',
            'Tag' => $this->company->company_key,
            'DeliveredAt' => '2021-02-21T16:34:52Z',
            'Details' => 'Test delivery webhook details',
        ];

        $response = $this->post('/api/v1/postmark_webhook', $data);

        $response->assertStatus(403);

        $response = $this->withHeaders([
            'X-API-SECURITY' => config('postmark.secret'),
        ])->post('/api/v1/postmark_webhook', $data);

        $response->assertStatus(200);
    }

    public function testSpamJob()
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->message_id = '00000000-0000-0000-0000-000000000001';
        $invitation->save();

        ProcessPostmarkWebhook::dispatchSync([
            'RecordType' => 'SpamComplaint',
            'ServerID' => '23',
            'MessageStream' => 'outbound',
            'MessageID' => '00000000-0000-0000-0000-000000000001',
            'From' => 'john@example.com',
            'Tag' => $this->company->company_key,
            'DeliveredAt' => '2021-02-21T16:34:52Z',
            'Details' => 'Test delivery webhook details',
        ]);

        $this->assertEquals('spam', $invitation->fresh()->email_status);
    }
}
