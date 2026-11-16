<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\DataMapper\CompanySettings;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Engine\BaseEmailEngine;
use App\Mail\TemplateEmail;
use App\Mail\VendorTemplateEmail;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\Email\Email as EmailJob;
use App\Services\Email\EmailDefaults;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailer;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Contracts\View\Factory;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $defaults = new EmailDefaults(new EmailJob($email_object, new Company()));
        (new \ReflectionProperty(EmailDefaults::class, 'settings'))->setValue($defaults, $settings);
        (new \ReflectionMethod(EmailDefaults::class, 'setHeaders'))->invoke($defaults);

        return new EmailMailable($email_object);
    }

    private function applyCallbacks(Mailable $mailable): Email
    {
        $message = new Email();

        foreach ($mailable->callbacks as $callback) {
            $callback($message);
        }

        return $message;
    }

    private function makeMailer(): Mailer
    {
        $views = \Mockery::mock(Factory::class);
        $views->shouldReceive('make->render')->andReturn('Test message');

        return new Mailer('receipts', $views, new ArrayTransport());
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

    public function testDefaultsCanBeAppliedTwiceWithoutDuplicatingReceiptHeaders(): void
    {
        $email_object = new EmailObject();
        $email_object->settings = (object) ['disposition_notification_to' => 'receipts@example.com'];
        $defaults = new EmailDefaults(new EmailJob($email_object, new Company()));
        (new \ReflectionProperty(EmailDefaults::class, 'settings'))->setValue($defaults, $email_object->settings);
        $set_headers = new \ReflectionMethod(EmailDefaults::class, 'setHeaders');
        $set_headers->invoke($defaults);
        $set_headers->invoke($defaults);

        // Header values survive serialization independently of the settings.
        $email_object->settings = null;
        $mailable = new EmailMailable(unserialize(serialize($email_object)));
        $headers = $this->applyCallbacks($mailable)->getHeaders();

        $this->assertCount(1, iterator_to_array($headers->all('Disposition-Notification-To')));
        $this->assertCount(1, iterator_to_array($headers->all('Return-Receipt-To')));
        $this->assertSame(['receipts@example.com'], $headers->get('Disposition-Notification-To')->getAddressStrings());
    }

    public function testEmailMailableSendsTypedReceiptHeadersAlongsideTextHeaders(): void
    {
        $mailable = $this->makeMailable(
            (object) ['disposition_notification_to' => 'receipts@example.com', 'email_sending_method' => 'smtp'],
            ['x-invitation' => 'abc123']
        );
        $mailable->email_object->from = new Address('sender@example.com');
        $mailable->email_object->to = [new Address('recipient@example.com')];
        $mailable = new class ($mailable->email_object) extends EmailMailable {
            public function content()
            {
                return new Content(htmlString: '<p>Test message</p>');
            }

            public function attachments()
            {
                return [];
            }
        };

        $message = $mailable->send($this->makeMailer())->getOriginalMessage();
        $headers = $message->getHeaders();
        $this->assertSame('abc123', $headers->get('x-invitation')->getBodyAsString());
        foreach (['Disposition-Notification-To', 'Return-Receipt-To'] as $name) {
            $this->assertCount(1, iterator_to_array($headers->all($name)));
            $this->assertSame(['receipts@example.com'], $headers->get($name)->getAddressStrings());
        }
    }

    public function testJobDoesNotAddReceiptHeadersToOtherMailables(): void
    {
        $nmo = new NinjaMailerObject();
        $nmo->mailable = new Mailable();
        $nmo->settings = (object) ['disposition_notification_to' => 'receipts@example.com'];
        (new \ReflectionMethod(NinjaMailerJob::class, 'setHeaders'))->invoke(new NinjaMailerJob($nmo));

        $headers = $this->applyCallbacks($nmo->mailable)->getHeaders();
        $this->assertFalse($headers->has('Disposition-Notification-To'));
        $this->assertFalse($headers->has('Return-Receipt-To'));
    }

    public static function templateProvider(): array
    {
        return [
            'client receipts' => [false, 'receipts@example.com'],
            'vendor receipts' => [true, 'receipts@example.com'],
            'client receipts disabled' => [false, ''],
            'vendor receipts disabled' => [true, ''],
        ];
    }

    #[DataProvider('templateProvider')]
    public function testJobAddsReceiptHeadersOnceWhenTemplateIsBuiltTwice(bool $vendor, string $receipt_to): void
    {
        config(['ninja.environment' => 'hosted', 'mail.from.address' => 'sender@example.com']);
        $settings = new CompanySettings();
        $settings->email_from_name = 'Test Company';
        $settings->disposition_notification_to = $receipt_to;

        $account = \Mockery::mock(Account::class)->makePartial();
        $account->shouldReceive('isPaid')->andReturn(true);
        $company = \Mockery::mock(Company::class)->makePartial();
        $company->settings = $settings;
        $company->setRelation('account', $account);
        $company->shouldReceive('present->logo')->andReturn('');
        $user = new User();
        $user->setRelation('account', $account);
        $recipient = \Mockery::mock($vendor ? Vendor::class : Client::class)->makePartial();
        $recipient->setRelation('company', $company);
        $recipient->setRelation('user', $user);
        if (!$vendor) {
            $recipient->shouldReceive('getMergedSettings')->andReturn($settings);
        }
        $contact = \Mockery::mock($vendor ? VendorContact::class : ClientContact::class)->makePartial();
        $contact->setRelation($vendor ? 'vendor' : 'client', $recipient);
        $contact->setRelation('company', $company);
        $contact->shouldReceive('present->name')->andReturn('Recipient');
        $builder = (new BaseEmailEngine())->setTemplate('light')->setBody('Test message')->setSubject('Test subject');
        $mailable = $vendor ? new VendorTemplateEmail($builder, $contact) : new TemplateEmail($builder, $contact);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = $mailable;
        $nmo->settings = $settings;
        $nmo->invitation = (object) ['key' => 'abc123'];
        $job = new NinjaMailerJob($nmo);
        (new \ReflectionMethod(NinjaMailerJob::class, 'setHeaders'))->invoke($job);

        $mailable->to('recipient@example.com');
        $mailer = $this->makeMailer();

        // Hosted delivery pre-builds; Laravel builds again on each send, including fallback.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $mailable->build();
            $message = $mailable->send($mailer)->getOriginalMessage();
            $headers = $message->getHeaders();
            $this->assertSame('abc123', $headers->get('x-invitation')->getBodyAsString());
            foreach (['Disposition-Notification-To', 'Return-Receipt-To'] as $name) {
                $this->assertCount($receipt_to === '' ? 0 : 1, iterator_to_array($headers->all($name)));
                if ($receipt_to !== '') {
                    $this->assertSame([$receipt_to], $headers->get($name)->getAddressStrings());
                }
            }
        }
    }
}
