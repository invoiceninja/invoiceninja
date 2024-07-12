<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use App\DataMapper\Analytics\EmailFailure;
use App\DataMapper\Analytics\EmailSuccess;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Turbo124\Beacon\Facades\LightLogs;

class AdminEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;

    public $tries = 4;

    public $deleteWhenMissingModels = true;

    public bool $override;

    protected ?string $client_postmark_secret = null;

    protected ?string $client_mailgun_secret = null;

    protected ?string $client_mailgun_domain = null;

    protected ?string $client_mailgun_endpoint = null;

    protected ?string $client_brevo_secret = null;

    private string $mailer = 'default';

    public Mailable $mailable;

    public function __construct(public EmailObject $email_object, public Company $company)
    {
    }

    /**
     * The backoff time between retries.
     *
     * @return array
     */
    public function backoff()
    {
        return [rand(10, 20), rand(30, 45), rand(60, 79), rand(160, 400)];
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $this->setOverride()
            ->buildMailable();

        if ($this->preFlightChecksFail()) {
            return;
        }

        $this->email();

    }

    /**
     * Sets the override flag
     *
     * @return self
     */
    public function setOverride(): self
    {
        $this->override = $this->email_object->override;

        return $this;
    }

    /**
     * Populates the mailable
     *
     * @return self
     */
    public function buildMailable(): self
    {
        $this->mailable = new AdminEmailMailable($this->email_object);

        return $this;
    }

    /**
     * Attempts to send the email
     *
     * @return void
     */
    public function email()
    {
        $this->setMailDriver();

        /* Init the mailer*/
        $mailer = Mail::mailer($this->mailer);

        /* Additional configuration if using a client third party mailer */
        if ($this->client_postmark_secret) {
            $mailer->postmark_config($this->client_postmark_secret);
        }

        if ($this->client_mailgun_secret) {
            $mailer->mailgun_config($this->client_mailgun_secret, $this->client_mailgun_domain, $this->client_mailgun_endpoint);
        }

        if ($this->client_brevo_secret) {
            $mailer->brevo_config($this->client_brevo_secret);
        }

        /* Attempt the send! */
        try {
            nlog("Using mailer => " . $this->mailer . " " . now()->toDateTimeString());

            $mailer->send($this->mailable);

            Cache::increment("email_quota" . $this->company->account->key);

            LightLogs::create(new EmailSuccess($this->company->company_key, $this->mailable->subject))
                ->send();

        } catch (\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
            nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
            $this->fail();
            $this->cleanUpMailers();
            $this->logMailError($e->getMessage(), $this->company->clients()->first());
            return;
        } catch (\Symfony\Component\Mime\Exception\LogicException $e) {
            nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
            $this->fail();
            $this->cleanUpMailers();
            $this->logMailError($e->getMessage(), $this->company->clients()->first());
            return;
        } catch (\Exception | \RuntimeException | \Google\Service\Exception $e) {
            nlog("Mailer failed with {$e->getMessage()}");
            $message = $e->getMessage();

            if (stripos($e->getMessage(), 'code 406') || stripos($e->getMessage(), 'code 300') || stripos($e->getMessage(), 'code 413')) {
                $message = "Either Attachment too large, or recipient has been suppressed.";

                $this->fail();
                $this->logMailError($e->getMessage(), $this->company->clients()->first());
                $this->cleanUpMailers();

                return;
            }

            /**
             * Post mark buries the proper message in a a guzzle response
             * this merges a text string with a json object
             * need to harvest the ->Message property using the following
             */
            if ($e instanceof ClientException) { //postmark specific failure
                $response = $e->getResponse();
                $message_body = json_decode($response->getBody()->getContents());

                if ($message_body && property_exists($message_body, 'Message')) {
                    $message = $message_body->Message;
                    nlog($message);
                }

                $this->fail();
                $this->cleanUpMailers();
                return;
            }

            //only report once, not on all tries
            if ($this->attempts() == $this->tries) {
                /* If the is an entity attached to the message send a failure mailer */
                $this->entityEmailFailed($message);

                /* Don't send postmark failures to Sentry */
                if (Ninja::isHosted() && (!$e instanceof ClientException)) { //@phpstan-ignore-line
                    app('sentry')->captureException($e);
                }
            }

            sleep(rand(0, 3));

            $this->release($this->backoff()[$this->attempts() - 1]);

            $message = null;
        }

        $this->cleanUpMailers();
    }

    /**
     * On the hosted platform we scan all outbound email for
     * spam. This sequence processes the filters we use on all
     * emails.
     *
     * @return bool
     */
    public function preFlightChecksFail(): bool
    {
        /* Always send if disabled */
        if ($this->override) {
            return false;
        }

        /* If we are migrating data we don't want to fire any emails */
        if ($this->company->is_disabled) {
            return true;
        }

        if (Ninja::isSelfHost()) {
            return false;
        }

        /* To handle spam users we drop all emails from flagged accounts */
        if ($this->company->account && $this->company->account->is_flagged) {
            return true;
        }

        /* On the hosted platform we set default contacts a @example.com email address - we shouldn't send emails to these types of addresses */
        if ($this->hasInValidEmails()) {
            return true;
        }

        /* GMail users are uncapped */
        if (in_array($this->email_object->settings->email_sending_method, ['gmail', 'office365', 'client_postmark', 'client_mailgun', 'client_brevo'])) {
            return false;
        }

        /* On the hosted platform, if the user is over the email quotas, we do not send the email. */
        if ($this->company->account && $this->company->account->emailQuotaExceeded()) {
            return true;
        }

        /* If the account is verified, we allow emails to flow */
        if ($this->company->account && $this->company->account->is_verified_account) {
            //11-01-2022

            /* Continue to analyse verified accounts in case they later start sending poor quality emails*/
            // if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            //     (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

            return false;
        }

        /* On the hosted platform if the user has not verified their account we fail here - but still check what they are trying to send! */
        if ($this->company->account && !$this->company->account->account_sms_verified) {
            if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
                return (new \Modules\Admin\Jobs\Account\EmailFilter($this->email_object, $this->company))->run();
            }

            return true;
        }

        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
            return (new \Modules\Admin\Jobs\Account\EmailFilter($this->email_object, $this->company))->run();
        }

        return false;
    }

    /**
     * hasInValidEmails
     *
     * @return bool
     */
    private function hasInValidEmails(): bool
    {
        foreach ($this->email_object->to as $address_object) {
            if (strpos($address_object->address, '@example.com') !== false) {
                return true;
            }

            if (!str_contains($address_object->address, "@")) {
                return true;
            }

            if ($address_object->address == " ") {
                return true;
            }
        }


        return false;
    }

    /**
     * Sets the mail driver to use and applies any specific configuration
     * the the mailable
     */
    private function setMailDriver(): self
    {
        switch ($this->email_object->settings->email_sending_method) {
            case 'default':
                $this->mailer = config('mail.default');
                break;
            case 'gmail':
                $this->mailer = 'gmail';
                $this->setGmailMailer();
                return $this;
            case 'office365':
            case 'microsoft':
                $this->mailer = 'office365';
                $this->setOfficeMailer();
                return $this;
            case 'client_postmark':
                $this->mailer = 'postmark';
                $this->setPostmarkMailer();
                return $this;
            case 'client_mailgun':
                $this->mailer = 'mailgun';
                $this->setMailgunMailer();
                return $this;
            case 'client_brevo':
                $this->mailer = 'brevo';
                $this->setBrevoMailer();
                return $this;

            default:
                $this->mailer = config('mail.default');
                return $this;
        }

        if (Ninja::isSelfHost()) {
            $this->setSelfHostMultiMailer();
        }

        return $this;
    }

    /**
     * Allows configuration of multiple mailers
     * per company for use by self hosted users
     */
    private function setSelfHostMultiMailer(): void
    {
        if (env($this->company->id . '_MAIL_HOST')) {
            config([
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => env($this->company->id . '_MAIL_HOST'),
                    'port' => env($this->company->id . '_MAIL_PORT'),
                    'username' => env($this->company->id . '_MAIL_USERNAME'),
                    'password' => env($this->company->id . '_MAIL_PASSWORD'),
                ],
            ]);

            if (env($this->company->id . '_MAIL_FROM_ADDRESS')) {
                $this->mailable
                    ->from(env($this->company->id . '_MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS')), env($this->company->id . '_MAIL_FROM_NAME', env('MAIL_FROM_NAME')));
            }
        }
    }


    /**
     * Ensure we discard any data that is not required
     *
     * @return void
     */
    private function cleanUpMailers(): void
    {
        $this->client_postmark_secret = null;

        $this->client_mailgun_secret = null;

        $this->client_mailgun_domain = null;

        $this->client_mailgun_endpoint = null;

        $this->client_brevo_secret = null;

        //always dump the drivers to prevent reuse
        app('mail.manager')->forgetMailers();
    }


    /**
     * Check to ensure no cross account
     * emails can be sent.
     *
     * @param User $user
     */
    private function checkValidSendingUser($user)
    {
        /* Always ensure the user is set on the correct account */
        if ($user->account_id != $this->company->account_id) {
            $this->email_object->settings->email_sending_method = 'default';

            return $this->setMailDriver();
        }
    }

    /**
     * Resolves the sending user
     * when configuring the Mailer
     * on behalf of the client
     *
     * @return User $user
     */
    private function resolveSendingUser(): ?User
    {
        $sending_user = $this->email_object->settings->gmail_sending_user_id;

        if ($sending_user == "0") {
            $user = $this->company->owner();
        } else {
            $user = User::find($this->decodePrimaryKey($sending_user));
        }

        return $user;
    }
    /**
     * Configures Mailgun using client supplied secret
     * as the Mailer
     */
    private function setMailgunMailer()
    {
        if (strlen($this->email_object->settings->mailgun_secret) > 2 && strlen($this->email_object->settings->mailgun_domain) > 2) {
            $this->client_mailgun_secret = $this->email_object->settings->mailgun_secret;
            $this->client_mailgun_domain = $this->email_object->settings->mailgun_domain;
            $this->client_mailgun_endpoint = $this->email_object->settings->mailgun_endpoint;

        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->email_object->settings->custom_sending_email) && stripos($this->email_object->settings->custom_sending_email, "@")) ? $this->email_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->email_object->settings->email_from_name) && strlen($this->email_object->settings->email_from_name) > 2) ? $this->email_object->settings->email_from_name : $user->name();

        $this->mailable
            ->from($sending_email, $sending_user);
    }
    /**
     * Configures Brevo using client supplied secret
     * as the Mailer
     */
    private function setBrevoMailer()
    {
        if (strlen($this->email_object->settings->brevo_secret) > 2) {
            $this->client_brevo_secret = $this->email_object->settings->brevo_secret;

        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->email_object->settings->custom_sending_email) && stripos($this->email_object->settings->custom_sending_email, "@")) ? $this->email_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->email_object->settings->email_from_name) && strlen($this->email_object->settings->email_from_name) > 2) ? $this->email_object->settings->email_from_name : $user->name();

        $this->mailable
            ->from($sending_email, $sending_user);
    }

    /**
     * Configures Postmark using client supplied secret
     * as the Mailer
     */
    private function setPostmarkMailer()
    {
        if (strlen($this->email_object->settings->postmark_secret) > 2) {
            $this->client_postmark_secret = $this->email_object->settings->postmark_secret;
        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->email_object->settings->custom_sending_email) && stripos($this->email_object->settings->custom_sending_email, "@")) ? $this->email_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->email_object->settings->email_from_name) && strlen($this->email_object->settings->email_from_name) > 2) ? $this->email_object->settings->email_from_name : $user->name();

        $this->mailable
            ->from($sending_email, $sending_user);
    }

    /**
     * Configures Microsoft via Oauth
     * as the Mailer
     */
    private function setOfficeMailer()
    {
        $user = $this->resolveSendingUser();

        $this->checkValidSendingUser($user);

        nlog("Sending via {$user->name()}");

        $token = $this->refreshOfficeToken($user);

        if ($token) {
            $user->oauth_user_token = $token;
            $user->save();
        } else {
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->mailable
            ->from($user->email, $user->name())
            ->withSymfonyMessage(function ($message) use ($token) {
                $message->getHeaders()->addTextHeader('gmailtoken', $token);
            });
    }

    /**
     * Configures GMail via Oauth
     * as the Mailer
     */
    private function setGmailMailer()
    {
        $user = $this->resolveSendingUser();

        $this->checkValidSendingUser($user);

        nlog("Sending via {$user->name()}");

        $google = (new Google())->init();

        try {
            if ($google->getClient()->isAccessTokenExpired()) {
                $google->refreshToken($user);
                $user = $user->fresh();
            }

            $google->getClient()->setAccessToken(json_encode($user->oauth_user_token));
        } catch (\Exception $e) {
            $this->logMailError('Gmail Token Invalid', $this->company->clients()->first());
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /**
         * If the user doesn't have a valid token, notify them
         */

        if (!$user->oauth_user_token) {
            $this->company->account->gmailCredentialNotification();
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /*
         *  Now that our token is refreshed and valid we can boot the
         *  mail driver at runtime and also set the token which will persist
         *  just for this request.
         */

        $token = $user->oauth_user_token->access_token;

        if (!$token) {
            $this->company->account->gmailCredentialNotification();
            $this->email_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->mailable
            ->from($user->email, $user->name())
            ->withSymfonyMessage(function ($message) use ($token) {
                $message->getHeaders()->addTextHeader('gmailtoken', $token);
            });
    }

    /**
     * Logs any errors to the SystemLog
     *
     * @param  string $errors
     * @param  null | \App\Models\Client $recipient_object
     * @return void
     */
    private function logMailError($errors, $recipient_object): void
    {
        (
            new SystemLogger(
                $errors,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_SEND,
                SystemLog::TYPE_FAILURE,
                $recipient_object,
                $this->company
            )
        )->handle();

        $job_failure = new EmailFailure($this->company->company_key);
        $job_failure->string_metric5 = 'failed_email';
        $job_failure->string_metric6 = substr($errors, 0, 150);

        LightLogs::create($job_failure)
            ->send();

        $job_failure = null;
    }

    /**
     * Attempts to refresh the Microsoft refreshToken
     *
     * @param \App\Models\User $user
     * @return mixed
     */
    private function refreshOfficeToken(User $user): mixed
    {
        $expiry = $user->oauth_user_token_expiry ?: now()->subDay();

        if ($expiry->lt(now())) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => config('ninja.o365.client_id'),
                    'client_secret' => config('ninja.o365.client_secret'),
                    'scope' => 'email Mail.Send offline_access profile User.Read openid',
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $user->oauth_user_refresh_token
                ],
            ])->getBody()->getContents());

            if ($token) {
                $user->oauth_user_refresh_token = property_exists($token, 'refresh_token') ? $token->refresh_token : $user->oauth_user_refresh_token;
                $user->oauth_user_token = $token->access_token;
                $user->oauth_user_token_expiry = now()->addSeconds($token->expires_in);
                $user->save();

                return $token->access_token;
            }

            return false;
        }

        return $user->oauth_user_token;
    }

    /**
     * Entity notification when an email fails to send
     *
     * @param  string $message
     * @return void
     */
    private function entityEmailFailed($message): void
    {
        $class = get_class($this->email_object->entity);

        switch ($class) {
            case Invoice::class:
                event(new InvoiceWasEmailedAndFailed($this->email_object->invitation, $this->company, $message, $this->email_object->html_template, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            case Payment::class:
                event(new PaymentWasEmailedAndFailed($this->email_object->entity, $this->company, $message, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            default:
                # code...
                break;
        }

        if ($this->email_object->client) {
            $this->logMailError($message, $this->email_object->client);
        }
    }


    public function failed($exception = null)
    {
        if ($exception) {
            nlog($exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }
}
