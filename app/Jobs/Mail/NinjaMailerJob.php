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

    public $backoff = 10; //seconds to wait until retry

    public $deleteWhenMissingModels = true;

    public $nmo;

    public $override;

    public $company;

    private $mailer;

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

        if($this->preFlightChecksFail())
            return;

        /* Set the email driver */
        $this->setMailDriver();

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

        $this->nmo->mailable->tag($this->company->company_key);

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

            Mail::mailer($this->mailer)
                ->to($this->nmo->to_user->email)
                ->send($this->nmo->mailable);

            LightLogs::create(new EmailSuccess($this->nmo->company->company_key))
                     ->queue();

            /* Count the amount of emails sent across all the users accounts */
            Cache::increment($this->company->account->key);

        } catch (\Exception | \RuntimeException $e) {
            
            nlog("error failed with {$e->getMessage()}");

            $message = $e->getMessage();

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
        }
    }

    /* Switch statement to handle failure notifications */
    private function entityEmailFailed($message)
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
                break;
            case 'office365':
                $this->mailer = 'office365';
                $this->setOfficeMailer();
                break;
            default:
                break;
        }

    }

    private function setOfficeMailer()
    {
        $sending_user = $this->nmo->settings->gmail_sending_user_id;

        $user = User::find($this->decodePrimaryKey($sending_user));
        
        /* Always ensure the user is set on the correct account */
        if($user->account_id != $this->company->account_id){

            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();

        }

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

    private function setGmailMailer()
    {

        $sending_user = $this->nmo->settings->gmail_sending_user_id;

        $user = User::find($this->decodePrimaryKey($sending_user));

        /* Always ensure the user is set on the correct account */
        if($user->account_id != $this->company->account_id){

            $this->nmo->settings->email_sending_method = 'default';
            return $this->setMailDriver();

        }
        
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

    private function preFlightChecksFail()
    {

        /* If we are migrating data we don't want to fire any emails */
        if($this->company->is_disabled && !$this->override) 
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

        /* To handle spam users we drop all emails from flagged accounts */
        if(Ninja::isHosted() && $this->company->account && $this->company->account->is_flagged) 
            return true;

        /* If the account is verified, we allow emails to flow */
        if(Ninja::isHosted() && $this->company->account && $this->company->account->is_verified_account) {

            /* Continue to analyse verified accounts in case they later start sending poor quality emails*/
            if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
                (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

            return false;
        }

        /* Ensure the user has a valid email address */
        if(!str_contains($this->nmo->to_user->email, "@"))
            return true;
     
        /* On the hosted platform we actively scan all outbound emails to ensure outbound email quality remains high */
        if(class_exists(\Modules\Admin\Jobs\Account\EmailQuality::class))
            return (new \Modules\Admin\Jobs\Account\EmailQuality($this->nmo, $this->company))->run();

        /* On the hosted platform if the user has not verified their account we fail here */
        if(Ninja::isHosted() && $this->company->account && !$this->company->account->account_sms_verified)
            return true;

        return false;
    }

    private function logMailError($errors, $recipient_object)
    {

        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $recipient_object,
            $this->nmo->company
        );

        $job_failure = new EmailFailure($this->nmo->company->company_key);
        $job_failure->string_metric5 = 'failed_email';
        $job_failure->string_metric6 = substr($errors, 0, 150);

        LightLogs::create($job_failure)
                 ->queue();
    }

    public function failed($exception = null)
    {
        
    }

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

            nlog($token);
            
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

    /**
     * Is this the cleanest way to requeue a job?
     * 
     * $this->delete();
     *
     * $job = NinjaMailerJob::dispatch($this->nmo, $this->override)->delay(3600);
    */

}