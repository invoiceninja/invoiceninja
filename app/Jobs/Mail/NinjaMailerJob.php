<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Mail;

use App\DataMapper\Analytics\EmailFailure;
use App\DataMapper\Analytics\EmailSuccess;
use App\Events\Invoice\InvoiceWasEmailedAndFailed;
use App\Events\Payment\PaymentWasEmailedAndFailed;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Mail\TemplateEmail;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\User;
use App\Providers\MailServiceProvider;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Dacastro4\LaravelGmail\Facade\LaravelGmail;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Turbo124\Beacon\Facades\LightLogs;
use Illuminate\Support\Facades\Cache;

/*Multi Mailer implemented*/

class NinjaMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $tries = 3; //number of retries

    public $backoff = 30; //seconds to wait until retry

    public $deleteWhenMissingModels = true;

    public $nmo;

    public $override;

    public $company;

    private $mailer;

    protected $client_postmark_secret = false;

    protected $client_mailgun_secret = false;

    protected $client_mailgun_domain = false;


    public function __construct(NinjaMailerObject $nmo, bool $override = false)
    {

        $this->nmo = $nmo;

        $this->override = $override;

    }

    public function handle()
    {

        /*Set the correct database*/
        MultiDB::setDb($this->nmo->company->db);

        /* Serializing models from other jobs wipes the primary key */
        $this->company = Company::where('company_key', $this->nmo->company->company_key)->first();

        /* If any pre conditions fail, we return early here */
        if($this->preFlightChecksFail())
            return;

        /* Set the email driver */
        $this->setMailDriver();

        /* Run time we set Reply To Email*/
        if (strlen($this->nmo->settings->reply_to_email) > 1) {
            
            if(property_exists($this->nmo->settings, 'reply_to_name'))
                $reply_to_name = strlen($this->nmo->settings->reply_to_name) > 3 ? $this->nmo->settings->reply_to_name : $this->nmo->settings->reply_to_email;
            else
                $reply_to_name = $this->nmo->settings->reply_to_email;

            $this->nmo->mailable->replyTo($this->nmo->settings->reply_to_email, $reply_to_name);

        }
        else {
            $this->nmo->mailable->replyTo($this->company->owner()->email, $this->company->owner()->present()->name());
        }

        /* Run time we set the email tag */
        $this->nmo->mailable->tag($this->company->company_key);

        /* If we have an invitation present, we pass the invitation key into the email headers*/
        if($this->nmo->invitation)
        {

            $this->nmo
                 ->mailable
                 ->withSymfonyMessage(function ($message) {
                    $message->getHeaders()->addTextHeader('x-invitation', $this->nmo->invitation->key);     
                 });

        }

        //send email
        try {
            
            nlog("trying to send to {$this->nmo->to_user->email} ". now()->toDateTimeString());
            nlog("Using mailer => ". $this->mailer);

            $mailer = Mail::mailer($this->mailer);

            if($this->client_postmark_secret){
                nlog("inside postmark config");
                nlog($this->client_postmark_secret);
                $mailer->postmark_config($this->client_postmark_secret);
            }

            if($this->client_mailgun_secret){
                $mailer->mailgun_config($this->client_mailgun_secret, $this->client_mailgun_domain);
            }

                $mailer
                    ->to($this->nmo->to_user->email)
                    ->send($this->nmo->mailable);

            /* Count the amount of emails sent across all the users accounts */
            Cache::increment($this->company->account->key);

            LightLogs::create(new EmailSuccess($this->nmo->company->company_key))
                     ->send();

            $this->nmo = null;
            $this->company = null;
    
        } catch (\Exception | \RuntimeException | \Google\Service\Exception $e) {
            
            nlog("error failed with {$e->getMessage()}");
            
            $this->cleanUpMailers();

            $message = $e->getMessage();

            if($e instanceof \Google\Service\Exception){

                if(($e->getCode() == 429) && ($this->nmo->to_user instanceof ClientContact))
                    $this->logMailError("Google rate limiter hit, we will retry in 30 seconds.", $this->nmo->to_user->client);

            }

            /**
             * Post mark buries the proper message in a a guzzle response
             * this merges a text string with a json object
             * need to harvest the ->Message property using the following
             */
            if($e instanceof ClientException) { //postmark specific failure

                $response = $e->getResponse();
                $message_body = json_decode($response->getBody()->getContents());
                
                if($message_body && property_exists($message_body, 'Message')){
                    $message = $message_body->Message;
                    nlog($message);
                }
                
            }

            /* If the is an entity attached to the message send a failure mailer */
            if($this->nmo->entity)
                $this->entityEmailFailed($message);

            /* Don't send postmark failures to Sentry */
            if(Ninja::isHosted() && (!$e instanceof ClientException)) 
                app('sentry')->captureException($e);

            $message = null;
            $this->nmo = null;
            $this->company = null;
    
        }

        //always dump the drivers to prevent reuse 
        $this->cleanUpMailers();

    }

    /**
     * Entity notification when an email fails to send
     * 
     * @param  string $message
     * @return void
     */
    private function entityEmailFailed($message): void
    {
        $class = get_class($this->nmo->entity);

        switch ($class) {
            case Invoice::class:
                event(new InvoiceWasEmailedAndFailed($this->nmo->invitation, $this->nmo->company, $message, $this->nmo->reminder_template, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            case Payment::class:
                event(new PaymentWasEmailedAndFailed($this->nmo->entity, $this->nmo->company, $message, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
                break;
            default:
                # code...
                break;
        }

        if ($this->nmo->to_user instanceof ClientContact) 
            $this->logMailError($message, $this->nmo->to_user->client);

    }

    /**
     * Initializes the configured Mailer
     */
    private function setMailDriver()
    {
        /* Singletons need to be rebooted each time just in case our Locale is changing*/
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->nmo->settings));

        switch ($this->nmo->settings->email_sending_method) {
            case 'default':
                $this->mailer = config('mail.default');
                break;
            case 'gmail':
                $this->mailer = 'gmail';
                $this->setGmailMailer();
                return;
            case 'office365':
                $this->mailer = 'office365';
                $this->setOfficeMailer();
                return;
            case 'client_postmark':
                $this->mailer = 'postmark';
                $this->setPostmarkMailer();
                return;
            case 'client_mailgun':
                $this->mailer = 'mailgun';
                $this->setMailgunMailer();
                return;

            default:
                break;
        }

        if(Ninja::isSelfHost())
            $this->setSelfHostMultiMailer();

    }

    /**
     * Allows configuration of multiple mailers
     * per company for use by self hosted users
     */
    private function setSelfHostMultiMailer(): void
    {

        if (env($this->company->id . '_MAIL_HOST')) 
        {

            config([
                'mail.mailers.smtp' => [
                    'transport' => 'smtp',
                    'host' => env($this->company->id . '_MAIL_HOST'),
                    'port' => env($this->company->id . '_MAIL_PORT'),
                    'username' => env($this->company->id . '_MAIL_USERNAME'),
                    'password' => env($this->company->id . '_MAIL_PASSWORD'),
                ],
            ]);

            if(env($this->company->id . '_MAIL_FROM_ADDRESS'))
            {
            $this->nmo
                 ->mailable
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
        if($user->account_id != $this->company->account_id){

            $this->nmo->settings->email_sending_method = 'default';
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
        $sending_user = $this->nmo->settings->gmail_sending_user_id;

        $user = User::find($this->decodePrimaryKey($sending_user));

        return $user;
    }

    /**
     * Configures Mailgun using client supplied secret
     * as the Mailer
     */
    private function setMailgunMailer()
    {
        if(strlen($this->nmo->settings->mailgun_secret) > 2 && strlen($this->nmo->settings->mailgun_domain) > 2){
            $this->client_mailgun_secret = $this->nmo->settings->mailgun_secret;
            $this->client_mailgun_domain = $this->nmo->settings->mailgun_domain;
        }
        else{
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }


        $user = $this->resolveSendingUser();

            $this->nmo
             ->mailable
             ->from($user->email, $user->name());
    }

    /**
     * Configures Postmark using client supplied secret
     * as the Mailer
     */
    private function setPostmarkMailer()
    {
        if(strlen($this->nmo->settings->postmark_secret) > 2){
            $this->client_postmark_secret = $this->nmo->settings->postmark_secret;
        }
        else{
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $user = $this->resolveSendingUser();

            $this->nmo
             ->mailable
             ->from($user->email, $user->name());
    }

    /**
     * Configures Microsoft via Oauth
     * as the Mailer
     */
    private function setOfficeMailer()
    {
        $user = $this->resolveSendingUser();
        
        /* Always ensure the user is set on the correct account */
        // if($user->account_id != $this->company->account_id){

        //     $this->nmo->settings->email_sending_method = 'default';
        //     return $this->setMailDriver();

        // }

        $this->checkValidSendingUser($user);
        
        nlog("Sending via {$user->name()}");

        $token = $this->refreshOfficeToken($user);

        if($token)
        {
            $user->oauth_user_token = $token;
            $user->save();

        }
        else {

            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        
        }

        $this->nmo
             ->mailable
             ->from($user->email, $user->name())
             ->withSymfonyMessage(function ($message) use($token) {
                $message->getHeaders()->addTextHeader('gmailtoken', $token);     
             });

        sleep(rand(1,3));
    }

    /**
     * Configures GMail via Oauth
     * as the Mailer
     */
    private function setGmailMailer()
    {

        $user = $this->resolveSendingUser();

        $this->checkValidSendingUser($user);

        /* Always ensure the user is set on the correct account */
        // if($user->account_id != $this->company->account_id){

        //     $this->nmo->settings->email_sending_method = 'default';
        //     return $this->setMailDriver();

        // }
        
        $this->checkValidSendingUser($user);

        nlog("Sending via {$user->name()}");

        $google = (new Google())->init();

        try{

            if ($google->getClient()->isAccessTokenExpired()) {
                $google->refreshToken($user);
                $user = $user->fresh();
            }

            $google->getClient()->setAccessToken(json_encode($user->oauth_user_token));

            sleep(rand(2,4));
        }
        catch(\Exception $e) {
            $this->logMailError('Gmail Token Invalid', $this->company->clients()->first());
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /**
         * If the user doesn't have a valid token, notify them
         */

        if(!$user->oauth_user_token) {
            $this->company->account->gmailCredentialNotification();
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        /*
         *  Now that our token is refreshed and valid we can boot the
         *  mail driver at runtime and also set the token which will persist
         *  just for this request.
        */

        $token = $user->oauth_user_token->access_token;

        if(!$token) {
            $this->company->account->gmailCredentialNotification();
            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();
        }

        $this->nmo
             ->mailable
             ->from($user->email, $user->name())
             ->withSymfonyMessage(function ($message) use($token) {
                $message->getHeaders()->addTextHeader('gmailtoken', $token);     
             });

    }

    /**
     * On the hosted platform we scan all outbound email for 
     * spam. This sequence processes the filters we use on all
     * emails.
     * 
     * @return bool
     */
    private function preFlightChecksFail(): bool
    {

        /* If we are migrating data we don't want to fire any emails */
        if($this->company->is_disabled && !$this->override) 
            return true;

        /* To handle spam users we drop all emails from flagged accounts */
        if(Ninja::isHosted() && $this->company->account && $this->company->account->is_flagged) 
            return true;

        /* On the hosted platform we set default contacts a @example.com email address - we shouldn't send emails to these types of addresses */
        if(Ninja::isHosted() && $this->nmo->to_user && strpos($this->nmo->to_user->email, '@example.com') !== false)
            return true;

        /* GMail users are uncapped */
        if(Ninja::isHosted() && ($this->nmo->settings->email_sending_method == 'gmail' || $this->nmo->settings->email_sending_method == 'office365')) 
            return false;

        /* On the hosted platform, if the user is over the email quotas, we do not send the email. */
        if(Ninja::isHosted() && $this->company->account && $this->company->account->emailQuotaExceeded())
            return true;

        /* If the account is verified, we allow emails to flow */
        if(Ninja::isHosted() && $this->company->account && $this->company->account->is_verified_account) {

            //11-01-2022

            /* Continue to analyse verified accounts in case they later start sending poor quality emails*/
            // if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            //     (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

            return false;
        }

        /* Ensure the user has a valid email address */
        if(!str_contains($this->nmo->to_user->email, "@"))
            return true;
     
        /* On the hosted platform if the user has not verified their account we fail here - but still check what they are trying to send! */
        if(Ninja::isHosted() && $this->company->account && !$this->company->account->account_sms_verified){
            
            if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
                return (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

            return true;
        }

        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            return (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

        return false;
    }

    /**
     * Logs any errors to the SystemLog
     * 
     * @param  string $errors
     * @param  App\Models\User | App\Models\Client $recipient_object
     * @return void
     */
    private function logMailError($errors, $recipient_object) :void
    {

        (new SystemLogger(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $recipient_object,
            $this->nmo->company
        ))->handle();

        $job_failure = new EmailFailure($this->nmo->company->company_key);
        $job_failure->string_metric5 = 'failed_email';
        $job_failure->string_metric6 = substr($errors, 0, 150);

        LightLogs::create($job_failure)
                 ->send();

        $job_failure = null;

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

        if($expiry->lt(now()))
        {
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
            
            if($token){
                
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

    public function failed($exception = null)
    {
        
    }

    /**
     * Is this the cleanest way to requeue a job?
     * 
     * $this->delete();
     *
     * $job = NinjaMailerJob::dispatch($this->nmo, $this->override)->delay(3600);
    */

}