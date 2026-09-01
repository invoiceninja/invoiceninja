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

namespace Tests\Feature;

use App\DataMapper\CompanySettings;
use App\Http\Controllers\MailerController;
use App\Http\Requests\Mailer\CheckMailerRequest;
use App\Mail\TestMailServer;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MailerCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ninja.auth.google.client_id' => 'google-client-id',
            'ninja.auth.google.client_secret' => 'google-client-secret',
            'ninja.o365.client_id' => 'microsoft-client-id',
            'ninja.o365.client_secret' => 'microsoft-client-secret',
        ]);
    }

    public function testMicrosoftMailerCanSendTestEmail(): void
    {
        [$user] = $this->microsoftUserAndCompany();

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]),
            'graph.microsoft.com/*' => Http::response([], 202),
        ]);

        $response = (new MailerController())->check(new CheckMailerRequest([
            'mailer' => 'microsoft',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('new-refresh-token', $user->oauth_user_refresh_token);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://graph.microsoft.com/v1.0/me/sendMail'
            && $request['message']['toRecipients'][0]['emailAddress']['address'] === $user->email);
    }

    public function testGmailMailerCanSendTestEmail(): void
    {
        [$user] = $this->oauthUserAndCompany('google');

        Http::fake([
            'oauth2.googleapis.com/*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3600,
            ]),
            'gmail.googleapis.com/*' => Http::response(['id' => 'message-id']),
        ]);

        $response = (new MailerController())->check(new CheckMailerRequest([
            'mailer' => 'gmail',
        ]));

        $this->assertSame(200, $response->getStatusCode());
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            && is_string($request['raw'])
            && $request['raw'] !== '');
    }

    #[DataProvider('hostedMailerProvider')]
    public function testMailerCheckRejectsHostedMailer(string $mailer): void
    {
        $request = new CheckMailerRequest([
            'mailer' => $mailer,
            'from_address' => 'sender@example.com',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mailer', $validator->errors()->toArray());
    }

    #[DataProvider('requiredMailerPropertyProvider')]
    public function testMailerCheckRequiresProviderProperty(string $mailer, string $property): void
    {
        $request = new CheckMailerRequest([
            'mailer' => $mailer,
            'from_address' => 'sender@example.com',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertArrayHasKey($property, $validator->errors()->toArray());
    }

    public function testNonOauthMailerRequiresFromAddress(): void
    {
        $request = new CheckMailerRequest([
            'mailer' => 'client_brevo',
            'brevo_secret' => 'brevo-secret',
        ]);

        $validator = Validator::make($request->all(), $request->rules());

        $this->assertArrayHasKey('from_address', $validator->errors()->toArray());
    }

    public function testSmtpMailerRejectsMetadataAddress(): void
    {
        $request = new CheckMailerRequest([
            'mailer' => 'smtp',
            'from_address' => 'sender@example.com',
            'smtp_host' => '169.254.169.254',
            'smtp_port' => 587,
            'smtp_username' => 'username',
            'smtp_password' => 'password',
        ]);
        $validator = Validator::make($request->all(), $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('smtp_host', $validator->errors()->toArray());
    }

    #[DataProvider('apiMailerProvider')]
    public function testApiMailerIsConfiguredAndSends(array $payload, string $driver, string $configurationMethod, array $arguments): void
    {
        [$user] = $this->oauthUserAndCompany('microsoft');
        $pendingMail = Mockery::mock();
        $configuredMailer = Mockery::mock();

        Mail::shouldReceive('mailer')->once()->with($driver)->andReturn($configuredMailer);
        Mail::shouldReceive('forgetMailers')->once();
        $configuredMailer
            ->shouldReceive($configurationMethod)
            ->once()
            ->withArgs($arguments)
            ->andReturnSelf();
        $configuredMailer
            ->shouldReceive('to')
            ->once()
            ->with($user->email, $user->name())
            ->andReturn($pendingMail);
        $pendingMail
            ->shouldReceive('send')
            ->once()
            ->with(Mockery::type(TestMailServer::class));

        $response = (new MailerController())->check(new CheckMailerRequest($payload));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function testMailerCheckReportsTokenRefreshFailure(): void
    {
        $this->microsoftUserAndCompany('invalid-refresh-token');

        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'error' => 'invalid_grant',
            ], 400),
        ]);

        $response = (new MailerController())->check(new CheckMailerRequest([
            'mailer' => 'office365',
        ]));

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(
            'Could not send a test email using the selected mailer.',
            json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR)['message']
        );
    }

    /**
     * @return array{User, Company}
     */
    private function microsoftUserAndCompany(string $refreshToken = 'old-refresh-token'): array
    {
        return $this->oauthUserAndCompany('microsoft', $refreshToken);
    }

    /**
     * @return array{User, Company}
     */
    private function oauthUserAndCompany(string $provider, string $refreshToken = 'old-refresh-token'): array
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill([
            'id' => 1,
            'account_id' => 1,
            'email' => 'admin@example.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'oauth_provider_id' => $provider,
            'oauth_user_refresh_token' => $refreshToken,
        ]);
        $user->shouldReceive('save')->andReturnTrue();

        $company = Mockery::mock(Company::class)->makePartial();
        $company->forceFill([
            'id' => 1,
            'account_id' => 1,
            'settings' => CompanySettings::defaults(),
        ]);
        $company->shouldReceive('owner')->andReturn($user);

        $user->shouldReceive('company')->andReturn($company);
        app('auth')->setUser($user);

        return [$user, $company];
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function requiredMailerPropertyProvider(): array
    {
        return [
            'client brevo secret' => ['client_brevo', 'brevo_secret'],
            'mailgun secret' => ['client_mailgun', 'mailgun_secret'],
            'mailgun domain' => ['client_mailgun', 'mailgun_domain'],
            'client postmark secret' => ['client_postmark', 'postmark_secret'],
            'ses access key' => ['client_ses', 'ses_access_key'],
            'ses secret key' => ['client_ses', 'ses_secret_key'],
            'ses region' => ['client_ses', 'ses_region'],
            'smtp host' => ['smtp', 'smtp_host'],
            'smtp port' => ['smtp', 'smtp_port'],
            'smtp username' => ['smtp', 'smtp_username'],
            'smtp password' => ['smtp', 'smtp_password'],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string, array<int, mixed>}>
     */
    public static function apiMailerProvider(): array
    {
        return [
            'client brevo' => [[
                'mailer' => 'client_brevo',
                'from_address' => 'sender@example.com',
                'brevo_secret' => 'brevo-secret',
            ], 'brevo', 'brevo_config', ['brevo-secret']],
            'client mailgun' => [[
                'mailer' => 'client_mailgun',
                'from_address' => 'sender@example.com',
                'mailgun_secret' => 'mailgun-secret',
                'mailgun_domain' => 'mg.example.com',
                'mailgun_endpoint' => 'api.eu.mailgun.net',
            ], 'mailgun', 'mailgun_config', ['mailgun-secret', 'mg.example.com', 'api.eu.mailgun.net']],
            'client postmark' => [[
                'mailer' => 'client_postmark',
                'from_address' => 'sender@example.com',
                'postmark_secret' => 'postmark-secret',
            ], 'postmark', 'postmark_config', ['postmark-secret']],
            'client ses' => [[
                'mailer' => 'client_ses',
                'from_address' => 'sender@example.com',
                'ses_access_key' => 'ses-access-key',
                'ses_secret_key' => 'ses-secret-key',
                'ses_region' => 'us-east-1',
                'ses_topic_arn' => 'topic-arn',
            ], 'ses', 'ses_config', ['ses-access-key', 'ses-secret-key', 'us-east-1', 'topic-arn']],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hostedMailerProvider(): array
    {
        return [
            'default' => ['default'],
            'hosted mailgun' => ['mailgun'],
            'hosted ses' => ['ses'],
            'hosted brevo' => ['brevo'],
            'hosted postmark' => ['postmark'],
            'sendmail' => ['sendmail'],
        ];
    }
}
