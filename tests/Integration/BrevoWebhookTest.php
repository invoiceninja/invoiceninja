<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Integration;

use App\Jobs\Brevo\ProcessBrevoWebhook;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * Brevo sends webhook events with the message-id wrapped in angle brackets
 * (eg. "<202610051640.xxx@smtp-relay.mailin.fr>"), matching the raw
 * Message-ID header format. Invoice Ninja strips those brackets when it
 * records the id at send time (see MailSentListener), so the stored
 * invitation.message_id is always bracket-free. ProcessBrevoWebhook must
 * normalize the incoming id the same way before matching it against an
 * invitation, otherwise every real webhook event silently fails to find
 * its invitation.
 */
class BrevoWebhookTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testDeliveredEventMatchesInvitationDespiteAngleBrackets()
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->message_id = '202610051640.test-delivered@smtp-relay.mailin.fr';
        $invitation->email_status = null;
        $invitation->save();

        ProcessBrevoWebhook::dispatchSync([
            'event' => 'delivered',
            'email' => 'john@gmail.com',
            'message-id' => '<202610051640.test-delivered@smtp-relay.mailin.fr>',
            'tags' => [$this->company->company_key],
            'subject' => 'Test delivery webhook',
            'date' => now()->toDateTimeString(),
        ]);

        $this->assertEquals('delivered', $invitation->fresh()->email_status);
    }

    public function testOpenedEventSetsOpenedDateDespiteAngleBrackets()
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->message_id = '202610051640.test-opened@smtp-relay.mailin.fr';
        $invitation->opened_date = null;
        $invitation->save();

        ProcessBrevoWebhook::dispatchSync([
            'event' => 'opened',
            'email' => 'john@gmail.com',
            'message-id' => '<202610051640.test-opened@smtp-relay.mailin.fr>',
            'tags' => [$this->company->company_key],
            'subject' => 'Test open webhook',
            'date' => now()->toDateTimeString(),
        ]);

        $this->assertNotNull($invitation->fresh()->opened_date);
    }

    public function testBounceEventMatchesInvitationDespiteAngleBrackets()
    {
        $invitation = $this->invoice->invitations->first();
        $invitation->message_id = '202610051640.test-bounced@smtp-relay.mailin.fr';
        $invitation->email_status = null;
        $invitation->save();

        ProcessBrevoWebhook::dispatchSync([
            'event' => 'hard_bounce',
            'email' => 'john@gmail.com',
            'message-id' => '<202610051640.test-bounced@smtp-relay.mailin.fr>',
            'tags' => [$this->company->company_key],
            'subject' => 'Test bounce webhook',
            'sender_email' => 'noreply@invoiceninja.com',
            'reason' => 'mailbox unavailable',
            'date' => now()->toDateTimeString(),
        ]);

        $this->assertEquals('bounced', $invitation->fresh()->email_status);
    }
}
