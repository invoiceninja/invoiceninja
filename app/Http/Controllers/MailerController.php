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
namespace App\Http\Controllers;

use App\Http\Requests\Mailer\CheckMailerRequest;
use App\Mail\TestMailServer;
use App\Models\Company;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MailerController extends BaseController
{
    use MakesHash;

    private const TIMEOUT_SECONDS = 10;

    private const OAUTH_MAILERS = ['gmail', 'microsoft', 'office365'];

    public function check(CheckMailerRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $company = $user->company();
        $mailer = $request->string('mailer')->toString();

        try {
            if (in_array($mailer, self::OAUTH_MAILERS, true)) {
                $sendingUser = $this->resolveSendingUser($company);
                $this->assertProviderMatches($sendingUser, $mailer);
                $accessToken = $this->refreshAccessToken($sendingUser, $mailer);
                $this->sendOauthTestEmail($sendingUser, $user, $mailer, $accessToken)->throw();
            } else {
                $this->sendConfiguredMailerTest($request, $user, $mailer);
            }
        } catch (Throwable $exception) {
            nlog('OAuth mailer check failed', [
                'company_id' => $company->id,
                'mailer' => $mailer,
                'error' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Could not send a test email using the selected mailer.'], 400);
        } finally {
            app('mail.manager')->forgetMailers();
        }

        return response()->json([
            'message' => ctrans('texts.test_email_sent'),
            'mailer' => $mailer,
        ]);
    }

    private function resolveSendingUser(Company $company): User
    {
        $sendingUserId = $company->settings->gmail_sending_user_id ?? '0';

        $sendingUser = $sendingUserId === '0'
            ? $company->owner()
            : User::withTrashed()->find($this->decodePrimaryKey($sendingUserId));

        if (!$sendingUser || $sendingUser->account_id !== $company->account_id) {
            throw new RuntimeException('The configured sending user could not be resolved.');
        }

        return $sendingUser;
    }

    private function assertProviderMatches(User $sendingUser, string $mailer): void
    {
        $expectedProvider = $mailer === 'gmail' ? 'google' : 'microsoft';

        if ($sendingUser->oauth_provider_id !== $expectedProvider) {
            throw new RuntimeException('The sending user is not connected to the selected provider.');
        }
    }

    private function refreshAccessToken(User $sendingUser, string $mailer): string
    {
        if (!$sendingUser->oauth_user_refresh_token) {
            throw new RuntimeException('The sending user does not have an OAuth refresh token.');
        }

        $response = $mailer === 'gmail'
            ? $this->refreshGoogleAccessToken($sendingUser)
            : $this->refreshMicrosoftAccessToken($sendingUser);

        $response->throw();
        $token = $response->json();
        $accessToken = $token['access_token'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('The provider did not return an access token.');
        }

        $sendingUser->oauth_user_token = $mailer === 'gmail' ? $token : $accessToken;
        $sendingUser->oauth_user_refresh_token = $token['refresh_token'] ?? $sendingUser->oauth_user_refresh_token;
        $sendingUser->oauth_user_token_expiry = now()->addSeconds((int) ($token['expires_in'] ?? 3600));
        $sendingUser->save();

        return $accessToken;
    }

    private function refreshGoogleAccessToken(User $sendingUser): Response
    {
        return Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('ninja.auth.google.client_id'),
                'client_secret' => config('ninja.auth.google.client_secret'),
                'grant_type' => 'refresh_token',
                'refresh_token' => $sendingUser->oauth_user_refresh_token,
            ]);
    }

    private function refreshMicrosoftAccessToken(User $sendingUser): Response
    {
        return Http::asForm()
            ->timeout(self::TIMEOUT_SECONDS)
            ->post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => config('ninja.o365.client_id'),
                'client_secret' => config('ninja.o365.client_secret'),
                'scope' => 'email Mail.Send offline_access profile User.Read openid',
                'grant_type' => 'refresh_token',
                'refresh_token' => $sendingUser->oauth_user_refresh_token,
            ]);
    }

    private function sendOauthTestEmail(User $sendingUser, User $recipient, string $mailer, string $accessToken): Response
    {
        if ($mailer === 'gmail') {
            return Http::withToken($accessToken)
                ->timeout(self::TIMEOUT_SECONDS)
                ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                    'raw' => $this->gmailMessage($sendingUser, $recipient),
                ]);
        }

        return Http::withToken($accessToken)
            ->timeout(self::TIMEOUT_SECONDS)
            ->post('https://graph.microsoft.com/v1.0/me/sendMail', [
                'message' => [
                    'subject' => 'Invoice Ninja mailer test',
                    'body' => [
                        'contentType' => 'Text',
                        'content' => 'Your Microsoft mailer connection is working.',
                    ],
                    'toRecipients' => [[
                        'emailAddress' => ['address' => $recipient->email],
                    ]],
                ],
                'saveToSentItems' => false,
            ]);
    }

    private function sendConfiguredMailerTest(CheckMailerRequest $request, User $recipient, string $mailer): void
    {
        $driver = str_replace('client_', '', $mailer);

        if ($driver === 'smtp') {
            $this->configureSmtpMailer($request);
        }

        $configuredMailer = Mail::mailer($driver);

        match ($driver) {
            'brevo' => $configuredMailer->brevo_config($request->string('brevo_secret')->toString()),
            'mailgun' => $configuredMailer->mailgun_config(
                $request->string('mailgun_secret')->toString(),
                $request->string('mailgun_domain')->toString(),
                $request->string('mailgun_endpoint', 'api.mailgun.net')->toString(),
            ),
            'postmark' => $configuredMailer->postmark_config($request->string('postmark_secret')->toString()),
            'ses' => $configuredMailer->ses_config(
                $request->string('ses_access_key')->toString(),
                $request->string('ses_secret_key')->toString(),
                $request->string('ses_region')->toString(),
                $request->input('ses_topic_arn'),
            ),
            default => null,
        };

        $fromAddress = $request->string('from_address')->toString();
        $fromName = $request->string('from_name', $recipient->name())->toString();
        $mailable = new TestMailServer('Email Server Works!', $fromAddress);
        $mailable->from($fromAddress, $fromName);

        $configuredMailer
            ->to($recipient->email)
            ->send($mailable);
    }

    private function configureSmtpMailer(CheckMailerRequest $request): void
    {
        config([
            'mail.mailers.smtp' => [
                'transport' => 'smtp',
                'host' => $request->string('smtp_host')->toString(),
                'port' => $request->integer('smtp_port'),
                'username' => $request->string('smtp_username')->toString(),
                'password' => $request->string('smtp_password')->toString(),
                'encryption' => $request->input('smtp_encryption', 'tls'),
                'local_domain' => $request->input('smtp_local_domain'),
                'verify_peer' => $request->boolean('smtp_verify_peer', true),
                'timeout' => self::TIMEOUT_SECONDS,
            ],
        ]);

        app('mail.manager')->forgetMailers();
    }

    private function gmailMessage(User $sendingUser, User $recipient): string
    {
        $message = implode("\r\n", [
            "From: {$sendingUser->email}",
            "To: {$recipient->email}",
            'Subject: Invoice Ninja mailer test',
            'Message-ID: <'.Str::uuid().'@invoiceninja.com>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            '',
            'Your Gmail mailer connection is working.',
        ]);

        return rtrim(strtr(base64_encode($message), '+/', '-_'), '=');
    }
}
