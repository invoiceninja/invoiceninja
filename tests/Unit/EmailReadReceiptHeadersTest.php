<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\Email\EmailMailable;
use App\Services\Email\EmailObject;

class EmailReadReceiptHeadersTest extends TestCase
{
    private function makeMailable(?object $settings, array $headers = []): EmailMailable
    {
        $email_object = new EmailObject();
        $email_object->company_key = 'test-company-key';
        $email_object->settings = $settings;
        $email_object->headers = $headers;

        return new EmailMailable($email_object);
    }

    public function testReadReceiptHeadersAreAddedWhenSettingIsSet(): void
    {
        $mailable = $this->makeMailable((object) ['disposition_notification_to' => 'receipts@example.com']);

        $this->assertSame([
            'Disposition-Notification-To' => 'receipts@example.com',
            'Return-Receipt-To' => 'receipts@example.com',
        ], $mailable->headers()->text);
    }

    public function testReadReceiptHeadersAreNotAddedWhenSettingIsEmpty(): void
    {
        $mailable = $this->makeMailable((object) ['disposition_notification_to' => '']);

        $this->assertArrayNotHasKey('Disposition-Notification-To', $mailable->headers()->text);
        $this->assertArrayNotHasKey('Return-Receipt-To', $mailable->headers()->text);
    }

    public function testReadReceiptHeadersAreNotAddedWhenSettingsAreMissing(): void
    {
        $mailable = $this->makeMailable(null);

        $this->assertArrayNotHasKey('Disposition-Notification-To', $mailable->headers()->text);
        $this->assertArrayNotHasKey('Return-Receipt-To', $mailable->headers()->text);
    }

    public function testExistingHeadersArePreserved(): void
    {
        $mailable = $this->makeMailable(
            (object) ['disposition_notification_to' => 'receipts@example.com'],
            ['x-invitation' => 'abc123']
        );

        $this->assertSame('abc123', $mailable->headers()->text['x-invitation']);
        $this->assertSame('receipts@example.com', $mailable->headers()->text['Disposition-Notification-To']);
        $this->assertSame('receipts@example.com', $mailable->headers()->text['Return-Receipt-To']);
    }
}
