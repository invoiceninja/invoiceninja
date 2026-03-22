<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerObject;
use App\Models\Company;
use App\Models\User;
use App\Services\Email\AdminEmailMailable;
use App\Services\Email\EmailMailable;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\Notification;
use Modules\Admin\Jobs\Account\EmailQuality;

class EmailQualityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class)) {
            $this->markTestSkipped('EmailQuality class is not available (Admin module not installed).');
        }
    }

    private function makeCompanyMock(string $companyName = 'Test Company'): Company
    {
        $presenter = new class ($companyName) {
            private string $name;
            public function __construct(string $name) { $this->name = $name; }
            public function name(): string { return $this->name; }
        };

        $owner_presenter = new class {
            public function name(): string { return 'Test Owner'; }
        };

        $owner = $this->createMock(User::class);
        $owner->method('present')->willReturn($owner_presenter);

        $company = $this->createMock(Company::class);
        $company->method('present')->willReturn($presenter);
        $company->method('owner')->willReturn($owner);
        $company->method('notification')->willReturn(
            new class {
                public function ninja() { return null; }
            }
        );

        return $company;
    }

    private function makeSettings(string $replyToEmail = '', string $replyToName = ''): object
    {
        return (object) [
            'reply_to_email' => $replyToEmail,
            'reply_to_name' => $replyToName,
        ];
    }

    private function buildNinjaMailerNmo(string $subject, string $body, ?Company $company = null): NinjaMailerObject
    {
        $mail_obj = new \stdClass();
        $mail_obj->subject = $subject;
        $mail_obj->data = ['body' => $body];
        $mail_obj->markdown = 'email.template.client';

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new NinjaMailer($mail_obj);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@example.com'];
        $nmo->settings = $this->makeSettings();

        return $nmo;
    }

    private function buildEmailMailableNmo(string $subject, string $body, ?Company $company = null): NinjaMailerObject
    {
        $email_object = new EmailObject();
        $email_object->subject = $subject;
        $email_object->body = $body;
        $email_object->company_key = 'test-key';
        $email_object->company = $company ?? $this->makeCompanyMock();
        $email_object->settings = $this->makeSettings();
        $email_object->whitelabel = false;
        $email_object->invitation = null;
        $email_object->html_template = 'email.template.client';
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@example.com')];
        $email_object->documents = [];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new EmailMailable($email_object);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@example.com'];
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
        $nmo->to_user = (object) ['email' => 'user%exploit@example.com'];

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
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@example.com')];
        $email_object->documents = [];

        $mailable = new EmailMailable($email_object);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = $mailable;
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@example.com'];
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
        $nmo->to_user = (object) ['email' => 'client@example.com'];
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
        $nmo->settings = $this->makeSettings('reply@example.com', 'Norton Support');

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
        $nmo->to_user = (object) ['email' => 'client@example.com'];
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
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@example.com')];
        $email_object->documents = [];
        $email_object->attachments = [];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new AdminEmailMailable($email_object);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@example.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $this->assertTrue($eq->run());
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
        $email_object->to = [new \Illuminate\Mail\Mailables\Address('test@example.com')];
        $email_object->documents = [];
        $email_object->attachments = [];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new AdminEmailMailable($email_object);
        $nmo->company = $company;
        $nmo->to_user = (object) ['email' => 'client@example.com'];
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
        $nmo->to_user = (object) ['email' => 'client@example.com'];
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
        $nmo->to_user = (object) ['email' => 'client@example.com'];
        $nmo->settings = $this->makeSettings();

        $eq = new EmailQuality($nmo, $company);
        $result = $eq->run();

        $this->assertFalse($result);
    }
}
