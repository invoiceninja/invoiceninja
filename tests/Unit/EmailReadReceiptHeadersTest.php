<?php

namespace Tests\Unit;

use Tests\TestCase;
use Symfony\Component\Mime\Email;
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

    private function applyCallbacks(EmailMailable $mailable): Email
    {
        $message = new Email();

        foreach ($mailable->callbacks as $callback) {
            $callback($message);
        }

        return $message;
    }

    public function testReadReceiptHeadersAreAddedWhenSettingIsSet(): void
    {
        $mailable = $this->makeMailable((object) ['disposition_notification_to' => 'receipts@example.com']);
        $headers = $this->applyCallbacks($mailable)->getHeaders();

        $this->assertSame(['receipts@example.com'], $headers->get('Disposition-Notification-To')->getAddressStrings());
        $this->assertSame(['receipts@example.com'], $headers->get('Return-Receipt-To')->getAddressStrings());
    }

    public function testReadReceiptHeadersAreNotAddedWhenSettingIsEmpty(): void
    {
        $mailable = $this->makeMailable((object) ['disposition_notification_to' => '']);
        $headers = $this->applyCallbacks($mailable)->getHeaders();

        $this->assertFalse($headers->has('Disposition-Notification-To'));
        $this->assertFalse($headers->has('Return-Receipt-To'));
    }

    public function testReadReceiptHeadersAreNotAddedWhenSettingsAreMissing(): void
    {
        $mailable = $this->makeMailable(null);
        $headers = $this->applyCallbacks($mailable)->getHeaders();

        $this->assertFalse($headers->has('Disposition-Notification-To'));
        $this->assertFalse($headers->has('Return-Receipt-To'));
    }

    public function testExistingTextHeadersArePreserved(): void
    {
        $mailable = $this->makeMailable(
            (object) ['disposition_notification_to' => 'receipts@example.com'],
            ['x-invitation' => 'abc123']
        );

        $this->assertSame('abc123', $mailable->headers()->text['x-invitation']);
        $this->assertArrayNotHasKey('Disposition-Notification-To', $mailable->headers()->text);
    }
}
