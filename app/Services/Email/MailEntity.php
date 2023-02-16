<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Email;

use App\DataMapper\Analytics\EmailSuccess;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Models\Company;
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

class MailEntity implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public Company $company;

    public Mailable $mailable;

    public Mail $mail;
    
    public ?string $client_postmark_secret = null;
    
    public ?string $client_mailgun_secret = null;
    
    public ?string $client_mailgun_domain = null;
    
    public bool $override = false;
        
    private string $mailer = '';
    
    public int $tries = 4;
    
    public $deleteWhenMissingModels = true;
    
    /**
     * __construct
     *
     * @param  mixed $invitation
     * @param  mixed $db
     * @param  mixed $mail_object
     * @return void
     */
    public function __construct(public mixed $invitation, private ?string $db, public MailObject $mail_object)
    {
    }
    
    /**
     * Handle the job
     *
     * @return void
     */
    public function handle(): void
    {
        MultiDB::setDb($this->db);

        /* Where there are no invitations, we need to harvest the company and also use the correct context to build the mailable*/
        $this->company = $this->invitation->company;

        $this->override = $this->mail_object->override;

        $builder = new MailBuild($this);

        /* Construct Mailable */
        $builder->run($this);

        $this->mailable = $builder->getMailable();

        /* Email quality checks */
        if ($this->preFlightChecksFail()) {
            return;
        }

        /* Try sending email */
        $this->setMailDriver()
             ->configureMailer()
             ->trySending();
    }
        
    /**
     * configureMailer
     *
     * @return self
     */
    public function configureMailer(): self
    {
        
        $this->mail = Mail::mailer($this->mailer);
        
        if ($this->client_postmark_secret) {
            $this->mail->postmark_config($this->client_postmark_secret);
        }

        if ($this->client_mailgun_secret) {
            $this->mail->mailgun_config($this->client_mailgun_secret, $this->client_mailgun_domain);
        }
        
        return $this;
    }


    /**
     * Sets the mail driver to use and applies any specific configuration
     * the the mailable
     */
    private function setMailDriver(): self
    {
        switch ($this->mail_object->settings->email_sending_method) {
            case 'default':
                $this->mailer = config('mail.default');
                break;
            case 'gmail':
                $this->mailer = 'gmail';
                $this->setGmailMailer();
                return $this;
            case 'office365':
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

            default:
                break;
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
        $this->client_postmark_secret = false;

        $this->client_mailgun_secret = false;

        $this->client_mailgun_domain = false;

        app('mail.manager')->forgetMailers();
    }

    
    /**
     * Attempts to send the email
     *
     * @return void
     */
    public function trySending(): void
    {
        try {
            $mail = Mail::mailer($this->mailer);
            $mail->send($this->mailable);

            /* Count the amount of emails sent across all the users accounts */
            Cache::increment($this->company->account->key);

            LightLogs::create(new EmailSuccess($this->company->company_key))
                     ->send();
        } catch(\Symfony\Component\Mime\Exception\RfcComplianceException $e) {
            nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
            $this->fail();
            $this->cleanUpMailers();
            // $this->logMailError($e->getMessage(), $this->company->clients()->first());
            return;
        } catch(\Symfony\Component\Mime\Exception\LogicException $e) {
            nlog("Mailer failed with a Logic Exception {$e->getMessage()}");
            $this->fail();
            $this->cleanUpMailers();
            // $this->logMailError($e->getMessage(), $this->company->clients()->first());
            return;
        } catch (\Exception | \Google\Service\Exception $e) {
            nlog("Mailer failed with {$e->getMessage()}");
            $message = $e->getMessage();

            /**
             * Post mark buries the proper message in a a guzzle response
             * this merges a text string with a json object
             * need to harvest the ->Message property using the following
             */
            if (stripos($e->getMessage(), 'code 406') || stripos($e->getMessage(), 'code 300') || stripos($e->getMessage(), 'code 413')) {
                $message = "Either Attachment too large, or recipient has been suppressed.";

                $this->fail();
                // $this->logMailError($e->getMessage(), $this->company->clients()->first());
                $this->cleanUpMailers();

                return;
            }

            //only report once, not on all tries
            if ($this->attempts() == $this->tries) {
                /* If the is an entity attached to the message send a failure mailer */
                if ($this->mail_object->entity_id) {
                    // $this->entityEmailFailed($message);

                    /* Don't send postmark failures to Sentry */
                    if (Ninja::isHosted() && (!$e instanceof ClientException)) {
                        app('sentry')->captureException($e);
                    }
                }
            }
        
            /* Releasing immediately does not add in the backoff */
            $this->release($this->backoff()[$this->attempts()-1]);
        }
    }

   /**
     * On the hosted platform we scan all outbound email for
     * spam. This sequence processes the filters we use on all
     * emails.
     */
    public function preFlightChecksFail(): bool
    {
        /* Handle bad state */
        if (!$this->company) {
            return true;
        }

        /* Handle deactivated company */
        if ($this->company->is_disabled && !$this->override) {
            return true;
        }

        /* To handle spam users we drop all emails from flagged accounts */
        if (Ninja::isHosted() && $this->company->account && $this->company->account->is_flagged) {
            return true;
        }

        /* On the hosted platform we set default contacts a @example.com email address - we shouldn't send emails to these types of addresses */
        if ($this->hasInValidEmails()) {
            return true;
        }

        /* GMail users are uncapped */
        if (in_array($this->mail_object->settings->email_sending_method, ['gmail', 'office365', 'client_postmark', 'client_mailgun'])) {
            return false;
        }

        /* On the hosted platform, if the user is over the email quotas, we do not send the email. */
        if (Ninja::isHosted() && $this->company->account && $this->company->account->emailQuotaExceeded()) {
            return true;
        }

        /* If the account is verified, we allow emails to flow */
        if (Ninja::isHosted() && $this->company->account && $this->company->account->is_verified_account) {
            //11-01-2022

            /* Continue to analyse verified accounts in case they later start sending poor quality emails*/
            // if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            //     (new \Modules\Admin\Jobs\Account\EmailQuality($this->mail_object, $this->company))->run();

            return false;
        }


        /* On the hosted platform if the user has not verified their account we fail here - but still check what they are trying to send! */
        if ($this->company->account && !$this->company->account->account_sms_verified) {
            if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
                (new \Modules\Admin\Jobs\Account\EmailFilter($this->mail_object, $this->company))->run();
            }

            return true;
        }

        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if (class_exists(\Modules\Admin\Jobs\Account\EmailFilter::class)) {
            (new \Modules\Admin\Jobs\Account\EmailFilter($this->mail_object, $this->company))->run();
        }

        return false;
    }

    
    /**
     * Checks if emails are have some illegal / required characters.
     *
     * @return bool
     */
    private function hasInValidEmails(): bool
    {
        if (Ninja::isSelfHost()) {
            return false;
        }

        foreach ($this->mail_object->to as $address_object) {
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
     * Check to ensure no cross account
     * emails can be sent.
     *
     * @param User $user
     */
    private function checkValidSendingUser($user)
    {
        /* Always ensure the user is set on the correct account */
        if ($user->account_id != $this->company->account_id) {
            $this->mail_object->settings->email_sending_method = 'default';
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
        $sending_user = $this->mail_object->settings->gmail_sending_user_id;

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
        if (strlen($this->mail_object->settings->mailgun_secret) > 2 && strlen($this->mail_object->settings->mailgun_domain) > 2) {
            $this->client_mailgun_secret = $this->mail_object->settings->mailgun_secret;
            $this->client_mailgun_domain = $this->mail_object->settings->mailgun_domain;
        } else {
            $this->mail_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->mail_object->settings->custom_sending_email) && stripos($this->mail_object->settings->custom_sending_email, "@")) ? $this->mail_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->mail_object->settings->email_from_name) && strlen($this->mail_object->settings->email_from_name) > 2) ? $this->mail_object->settings->email_from_name : $user->name();

        $this->mailable
         ->from($sending_email, $sending_user);
    }

    /**
     * Configures Postmark using client supplied secret
     * as the Mailer
     */
    private function setPostmarkMailer()
    {
        if (strlen($this->mail_object->settings->postmark_secret) > 2) {
            $this->client_postmark_secret = $this->mail_object->settings->postmark_secret;
        } else {
            $this->mail_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

        $sending_email = (isset($this->mail_object->settings->custom_sending_email) && stripos($this->mail_object->settings->custom_sending_email, "@")) ? $this->mail_object->settings->custom_sending_email : $user->email;
        $sending_user = (isset($this->mail_object->settings->email_from_name) && strlen($this->mail_object->settings->email_from_name) > 2) ? $this->mail_object->settings->email_from_name : $user->name();

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
            $this->mail_object->settings->email_sending_method = 'default';
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
        } catch(\Exception $e) {
            // $this->logMailError('Gmail Token Invalid', $this->company->clients()->first());
            $this->mail_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /**
         * If the user doesn't have a valid token, notify them
         */

        if (!$user->oauth_user_token) {
            $this->company->account->gmailCredentialNotification();
            $this->mail_object->settings->email_sending_method = 'default';
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
            $this->mail_object->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->mailable
             ->from($user->email, $user->name())
             ->withSymfonyMessage(function ($message) use ($token) {
                 $message->getHeaders()->addTextHeader('gmailtoken', $token);
             });
    }

  /**
     * Attempts to refresh the Microsoft refreshToken
     *
     * @param  App\Models\User
     * @return string | boool
     */
    private function refreshOfficeToken($user)
    {
        $expiry = $user->oauth_user_token_expiry ?: now()->subDay();

        if ($expiry->lt(now())) {
            $guzzle = new \GuzzleHttp\Client();
            $url = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';

            $token = json_decode($guzzle->post($url, [
                'form_params' => [
                    'client_id' => config('ninja.o365.client_id') ,
                    'client_secret' => config('ninja.o365.client_secret') ,
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
     * Backoff time
     *
     * @return void
     */
    public function backoff()
    {
        return [5, 10, 30, 240];
    }
    
    /**
     * Failed handler
     *
     * @param  mixed $exception
     * @return void
     */
    public function failed($exception = null)
    {
        config(['queue.failed.driver' => null]);
    }
}
