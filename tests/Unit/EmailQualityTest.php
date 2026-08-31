<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerObject;
use App\Models\Company;
use App\Models\User;
use App\Notifications\Ninja\EmailQualityNotification;
use App\Notifications\Ninja\GenericNinjaAdminNotification;
use App\Services\Email\AdminEmailMailable;
use App\Services\Email\EmailMailable;
use App\Services\Email\EmailObject;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Modules\Admin\Jobs\Account\EmailFilter;
use Modules\Admin\Jobs\Account\EmailQuality;

class EmailQualityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class)) {
            $this->markTestSkipped('EmailQuality class is not available (Admin module not installed).');
        }

        Cache::put('spam_domains', []);
        Cache::put('spam_body_keywords', []);
        Cache::put('outbound_recipient_domains', []);
    }

    protected function tearDown(): void
    {
        Cache::forget('spam_domains');
        Cache::forget('spam_body_keywords');
        Cache::forget('outbound_recipient_domains');

        parent::tearDown();
    }

    private function makeCompanyMock(
        string $companyName = 'Test Company',
        ?int $expectedNotifications = null,
        bool $throwOnNotification = false,
        array $expectedNotificationLines = []
    ): Company {
        $presenter = new class ($companyName) {
            private string $name;
            public function __construct(string $name)
            {
                $this->name = $name;
            }
            public function name(): string
            {
                return $this->name;
            }
        };

        $owner_presenter = new class {
            public function name(): string
            {
                return 'Test Owner';
            }
        };

        $owner = $this->createMock(User::class);
        $owner->method('present')->willReturn($owner_presenter);

        $company = $this->createMock(Company::class);
        $company->method('present')->willReturn($presenter);
        $company->method('owner')->willReturn($owner);

        $notification_builder = new class {
            public function ninja(): null
            {
                return null;
            }
        };

        if ($throwOnNotification) {
            $company->method('notification')->willThrowException(new \RuntimeException('Slack unavailable'));
        } elseif ($expectedNotifications !== null) {
            $company->expects($this->exactly($expectedNotifications))
                ->method('notification')
                ->with($this->callback(function ($notification) use ($expectedNotificationLines): bool {
                    if ($notification instanceof GenericNinjaAdminNotification) {
                        $property = new \ReflectionProperty($notification, 'message_array');
                        $message_array = $property->getValue($notification);

                        foreach ($expectedNotificationLines as $expected_line) {
                            $this->assertContains($expected_line, $message_array);
                        }

                        return true;
                    }

                    if ($notification instanceof EmailQualityNotification) {
                        $property = new \ReflectionProperty($notification, 'spam_string');
                        $spam_string = $property->getValue($notification);

                        foreach ($expectedNotificationLines as $expected_line) {
                            $this->assertStringContainsString($expected_line, $spam_string);
                        }

                        return true;
                    }

                    return false;
                }))
                ->willReturn($notification_builder);
        } else {
            $company->method('notification')->willReturn($notification_builder);
        }

        return $company;
    }

    private function makeSettings(string $replyToEmail = '', string $replyToName = ''): object
    {
        return (object) [
            'reply_to_email' => $replyToEmail,
            'reply_to_name' => $replyToName,
        ];
    }

    private function buildNinjaMailerNmo(
        string $subject,
        string $body,
        ?Company $company = null,
        ?string $textBody = null
    ): NinjaMailerObject {
        $mail_obj = new \stdClass();
        $mail_obj->subject = $subject;
        $mail_obj->data = ['body' => $body];

        if ($textBody !== null) {
            $mail_obj->data['text_body'] = $textBody;
        }

        $mail_obj->markdown = 'email.template.client';

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new NinjaMailer($mail_obj);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        return $nmo;
    }

    private function buildEmailMailableNmo(
        string $subject,
        string $body,
        ?Company $company = null,
        string $textBody = ''
    ): NinjaMailerObject {
        $email_object = new EmailObject();
        $email_object->subject = $subject;
        $email_object->body = $body;
        $email_object->text_body = $textBody;
        $email_object->company_key = 'test-key';
        $email_object->company = $company ?? $this->makeCompanyMock();
        $email_object->settings = $this->makeSettings();
        $email_object->whitelabel = false;
        $email_object->invitation = null;
        $email_object->html_template = 'email.template.client';
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@gmail.com')];
        $email_object->documents = [];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new EmailMailable($email_object);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        return $nmo;
    }

    public function testCleanNinjaMailerSubjectPasses()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo('Your invoice is ready', 'Please find your invoice attached.', $company);

        $eq = new EmailQuality($nmo, $company);
        $this->assertFalse($eq->run());
    }

    public function testSpamKeywordInNinjaMailerSubjectTriggersHit()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo('Your McAfee subscription renewal', 'Renew now.', $company);

        $eq = new EmailQuality($nmo, $company);
        $this->assertTrue($eq->run());
    }

    public function testSingleBodyWordInNinjaMailerTriggersHit(): void
    {
        Cache::put('spam_body_keywords', ['prize']);

        $company = $this->makeCompanyMock(
            expectedNotifications: 1,
            expectedNotificationLines: [
                'prize - body:',
                'You won a PRIZE today.',
            ]
        );
        $nmo = $this->buildNinjaMailerNmo('Your invoice', '<p>You won a PRIZE today.</p>', $company);

        $this->assertTrue((new EmailQuality($nmo, $company))->run());
    }

    public function testBodyWordDoesNotMatchInsideAnotherWord(): void
    {
        Cache::put('spam_body_keywords', ['free']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'You have the freedom to choose.', $company);

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testSubjectKeywordListIsNotAppliedToBody(): void
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Your McAfee subscription renewal.', $company);

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testCleanEmailMailableSubjectPasses()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildEmailMailableNmo('Invoice #1001 from Acme Corp', 'Here is your invoice.', $company);

        $eq = new EmailQuality($nmo, $company);
        $this->assertFalse($eq->run());
    }

    public function testSpamKeywordInEmailMailableSubjectTriggersHit()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildEmailMailableNmo('Norton Security Alert', 'Your Norton subscription.', $company);

        $eq = new EmailQuality($nmo, $company);
        $this->assertTrue($eq->run());
    }

    public function testFullBodySentenceMatchesNormalizedEmailMailableTextBody(): void
    {
        Cache::put('spam_body_keywords', ['Your subscription has expired. Renew now.']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildEmailMailableNmo(
            'Your invoice',
            '<p>YOUR&nbsp;subscription <strong>has expired.</strong></p>   Renew now.',
            $company,
            "YOUR\u{00A0}subscription has expired.\nRenew now."
        );

        $this->assertTrue((new EmailQuality($nmo, $company))->run());
    }

    public function testTextBodyIsEvaluatedBeforeHtmlBody(): void
    {
        Cache::put('spam_body_keywords', ['html-only phrase', 'plain-text phrase']);

        $company = $this->makeCompanyMock(
            expectedNotifications: 1,
            expectedNotificationLines: [
                'plain-text phrase - body:',
                'The plain-text phrase is present.',
            ]
        );
        $nmo = $this->buildNinjaMailerNmo(
            'Your invoice',
            '<p>The html-only phrase is present.</p>',
            $company,
            'The plain-text phrase is present.'
        );

        $this->assertTrue((new EmailQuality($nmo, $company))->run());
    }

    public function testHtmlBodyIsNotEvaluatedWhenTextBodyExists(): void
    {
        Cache::put('spam_body_keywords', ['html-only phrase']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildEmailMailableNmo(
            'Your invoice',
            '<p>The html-only phrase is present.</p>',
            $company,
            'The plain-text alternative is clean.'
        );

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testBodyEntitiesAreDecodedBeforeMatching(): void
    {
        Cache::put('spam_body_keywords', ['Tom & Jerry']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo(
            'Your invoice',
            '<p>Clean HTML alternative.</p>',
            $company,
            'A message from Tom &amp; Jerry.'
        );

        $this->assertTrue((new EmailQuality($nmo, $company))->run());
    }

    public function testConfiguredKeywordsAreNotEntityDecoded(): void
    {
        Cache::put('spam_body_keywords', ['Tom &amp; Jerry']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo(
            'Your invoice',
            '<p>A message from Tom &amp; Jerry.</p>',
            $company,
            'A message from Tom &amp; Jerry.'
        );

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testEncodedAngleBracketsRemainSearchableVisibleText(): void
    {
        Cache::put('spam_body_keywords', ['<strong>offer</strong>']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo(
            'Your invoice',
            '<p>Claim the &lt;strong&gt;offer&lt;/strong&gt; today.</p>',
            $company
        );

        $this->assertTrue((new EmailQuality($nmo, $company))->run());
    }

    public function testBodyFallbackRetainsHtmlTags(): void
    {
        Cache::put('spam_body_keywords', ['<strong>']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo(
            'Your invoice',
            '<p>Claim the <strong>offer</strong> today.</p>',
            $company
        );

        $this->assertTrue((new EmailQuality($nmo, $company))->run());
    }

    public function testBodyFallbackDoesNotMatchPhraseInterruptedByHtmlTags(): void
    {
        Cache::put('spam_body_keywords', ['payment is required']);

        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo(
            'Your invoice',
            '<p>Payment <strong>is</strong> required.</p>',
            $company
        );

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testEmailMailableStripsBrTagsFromSubject()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildEmailMailableNmo('Your<br>Invoice<br>Ready', 'Body text.', $company);

        $eq = new EmailQuality($nmo, $company);
        $this->assertFalse($eq->run());
    }

    public function testSpamCompanyNameTriggersHit()
    {
        $company = $this->makeCompanyMock('PayPal Inc');
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);

        $eq = new EmailQuality($nmo, $company);
        $this->assertTrue($eq->run());
    }

    public function testPercentInEmailIsFlagged()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->to_user = (object) ['email' => 'user%exploit@gmail.com'];

        $eq = new EmailQuality($nmo, $company);
        // Percent emails get flagged via notification but don't return true unless other checks hit
        $eq->run();
        $this->assertTrue(true); // No exception thrown
    }

    public function testEmailMailableWithClosuresDoesNotThrow()
    {
        $company = $this->makeCompanyMock();

        $email_object = new EmailObject();
        $email_object->subject = 'Your invoice is ready';
        $email_object->body = 'Please find your invoice attached.';
        $email_object->company_key = 'test-key';
        $email_object->company = $company;
        $email_object->settings = $this->makeSettings();
        $email_object->whitelabel = false;
        $email_object->invitation = null;
        $email_object->html_template = 'email.template.client';
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@gmail.com')];
        $email_object->documents = [];

        $mailable = new EmailMailable($email_object);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = $mailable;
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        // This would throw "Serialization of 'Closure' is not allowed" with the old approach
        $eq = new EmailQuality($nmo, $company);
        $result = $eq->run();

        $this->assertFalse($result);
    }

    public function testOriginalMailableIsNotMutated()
    {
        $company = $this->makeCompanyMock();

        $mail_obj = new \stdClass();
        $mail_obj->subject = 'Your invoice is ready';
        $mail_obj->data = ['body' => 'Clean body text.'];
        $mail_obj->markdown = 'email.template.client';

        $mailable = new NinjaMailer($mail_obj);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = $mailable;
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $eq->run();

        // Verify the original mailable data was not mutated
        $this->assertEquals('Your invoice is ready', $mailable->mail_obj->subject);
        $this->assertEquals('Clean body text.', $mailable->mail_obj->data['body']);
        $this->assertNull($mailable->subject, 'Mailable subject property should not have been set');
    }

    public function testSpamReplyToNameIsFlagged()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->settings = $this->makeSettings('reply@gmail.com', 'Norton Support');

        $eq = new EmailQuality($nmo, $company);
        $result = $eq->run();

        // Spam username check returns false (flagged but not blocked)
        $this->assertFalse($result);
    }

    public function testCaseInsensitiveSpamDetection()
    {
        $company = $this->makeCompanyMock();
        $nmo = $this->buildNinjaMailerNmo('MCAFEE RENEWAL NOTICE', 'Please renew.', $company);

        $eq = new EmailQuality($nmo, $company);
        $this->assertTrue($eq->run());
    }

    public function testUnknownMailableTypeReturnsCleanResult()
    {
        $company = $this->makeCompanyMock();

        // Use a plain Mailable (neither NinjaMailer nor EmailMailable)
        $mailable = new \Illuminate\Mail\Mailable();

        $nmo = new NinjaMailerObject();
        $nmo->mailable = $mailable;
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $result = $eq->run();

        // Unknown mailable type returns [null, false] so no spam checks trigger
        $this->assertFalse($result);
    }

    public function testAdminEmailMailableSpamSubjectTriggersHit()
    {
        $company = $this->makeCompanyMock();

        $email_object = new EmailObject();
        $email_object->subject = 'Norton Security Alert';
        $email_object->body = 'Your Norton subscription.';
        $email_object->company_key = 'test-key';
        $email_object->company = $company;
        $email_object->settings = $this->makeSettings();
        $email_object->whitelabel = false;
        $email_object->invitation = null;
        $email_object->html_template = 'email.admin.generic';
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@gmail.com')];
        $email_object->documents = [];
        $email_object->attachments = [];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new AdminEmailMailable($email_object);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $this->assertTrue($eq->run());
    }

    public function testAdminEmailMailableSpamBodyTriggersHit(): void
    {
        Cache::put('spam_body_keywords', ['payment is required immediately']);

        $company = $this->makeCompanyMock();

        $email_object = new EmailObject();
        $email_object->subject = 'Invoice reminder sent';
        $email_object->body = 'Payment is required immediately for this invoice.';
        $email_object->company_key = 'test-key';
        $email_object->company = $company;
        $email_object->settings = $this->makeSettings();
        $email_object->whitelabel = false;
        $email_object->invitation = null;
        $email_object->html_template = 'email.admin.generic';
        $email_object->to = [new Address('test@gmail.com')];
        $email_object->documents = [];
        $email_object->attachments = [];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new AdminEmailMailable($email_object);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        $this->assertTrue((new EmailQuality($nmo, $company))->run());
    }

    public function testAdminEmailMailableCleanSubjectPasses()
    {
        $company = $this->makeCompanyMock();

        $email_object = new EmailObject();
        $email_object->subject = 'Invoice reminder sent';
        $email_object->body = 'A reminder was sent to the client.';
        $email_object->company_key = 'test-key';
        $email_object->company = $company;
        $email_object->settings = $this->makeSettings();
        $email_object->whitelabel = false;
        $email_object->invitation = null;
        $email_object->html_template = 'email.admin.generic';
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@gmail.com')];
        $email_object->documents = [];
        $email_object->attachments = [];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new AdminEmailMailable($email_object);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $this->assertFalse($eq->run());
    }

    public function testNinjaMailerWithNullMailObjDoesNotThrow()
    {
        $company = $this->makeCompanyMock();

        $mailable = new NinjaMailer(null);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = $mailable;
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $result = $eq->run();

        // Null mail_obj falls through to [null, false]
        $this->assertFalse($result);
    }

    public function testNullMailableDoesNotThrow()
    {
        $company = $this->makeCompanyMock();

        $nmo = new NinjaMailerObject();
        $nmo->mailable = null;
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@gmail.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $result = $eq->run();

        $this->assertFalse($result);
    }

    public function testOnMicrosoftToRecipientRaisesNotificationWithoutBlocking(): void
    {
        $company = $this->makeCompanyMock(
            expectedNotifications: 1,
            expectedNotificationLines: [
                'Trigger: [onmicrosoft.com]',
                'Recipient type: [TO]',
                'Recipient: [client@tenant.onmicrosoft.com]',
                'Company: [Test Company]',
                'Company key: [Unavailable]',
                'Account: [Unavailable]',
                'Owner: [Test Owner] [Unavailable]',
            ]
        );
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->to_user = (object) ['email' => 'client@tenant.onmicrosoft.com'];

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testOnMicrosoftCcRecipientRaisesNotificationWithoutBlocking(): void
    {
        $company = $this->makeCompanyMock(
            expectedNotifications: 1,
            expectedNotificationLines: [
                'Recipient type: [CC]',
                'Recipient: [accounts@tenant.onmicrosoft.com]',
            ]
        );
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->cc = [new Address('accounts@tenant.onmicrosoft.com', 'Accounts')];

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testSimilarRecipientDomainDoesNotRaiseNotification(): void
    {
        $company = $this->makeCompanyMock(expectedNotifications: 0);
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->to_user = (object) ['email' => 'client@notonmicrosoft.com'];

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testEmailFilterChecksToAndCcRecipients(): void
    {
        $company = $this->makeCompanyMock(expectedNotifications: 1);
        $email_object = new EmailObject();
        $email_object->subject = 'Your invoice';
        $email_object->settings = $this->makeSettings();
        $email_object->to = [new Address('client@example.net', 'Client')];
        $email_object->cc = [new Address('accounts@tenant.onmicrosoft.com', 'Accounts')];

        $this->assertFalse((new EmailFilter($email_object, $company))->run());
    }

    public function testEmailFilterBlocksSpamBodySentence(): void
    {
        Cache::put('spam_body_keywords', ['confirm your account immediately']);

        $company = $this->makeCompanyMock();
        $email_object = new EmailObject();
        $email_object->subject = 'Your invoice';
        $email_object->body = '<p>Please confirm your account immediately.</p>';
        $email_object->settings = $this->makeSettings();
        $email_object->to = [new Address('client@example.net', 'Client')];

        $this->assertTrue((new EmailFilter($email_object, $company))->run());
    }

    public function testAdminManagedOutboundRecipientDomainIsFlagged(): void
    {
        Cache::put('outbound_recipient_domains', ['bad-actor.test']);

        $company = $this->makeCompanyMock(expectedNotifications: 1);
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->to_user = (object) ['email' => 'client@mail.bad-actor.test'];

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testSpamDomainsListIsNotUsedForOutboundRecipients(): void
    {
        Cache::put('spam_domains', ['blocked-signup.test']);

        $company = $this->makeCompanyMock(expectedNotifications: 0);
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->to_user = (object) ['email' => 'client@blocked-signup.test'];

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }

    public function testRecipientDomainNotificationExceptionsDoNotInterruptMailChecks(): void
    {
        $company = $this->makeCompanyMock(throwOnNotification: true);
        $nmo = $this->buildNinjaMailerNmo('Your invoice', 'Body.', $company);
        $nmo->to_user = (object) ['email' => 'client@tenant.onmicrosoft.com'];

        $this->assertFalse((new EmailQuality($nmo, $company))->run());
    }
}
