<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\User;
use App\Providers\MailServiceProvider;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Dacastro4\LaravelGmail\Facade\LaravelGmail;
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

/*Multi Mailer implemented*/

class NinjaMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $tries = 5; //number of retries

    public $backoff = 5; //seconds to wait until retry

    public $deleteWhenMissingModels = true;

    public $nmo;

    public function __construct(NinjaMailerObject $nmo)
    {

        $this->nmo = $nmo;

    }

    public function handle()
    {
        /*If we are migrating data we don't want to fire any emails*/
        if ($this->nmo->company->is_disabled) 
            return true;
        
        /*Set the correct database*/
        MultiDB::setDb($this->nmo->company->db);

        /* Set the email driver */
        $this->setMailDriver();

        if (strlen($this->nmo->settings->reply_to_email) > 1) {
            
            if(property_exists($this->nmo->settings, 'reply_to_name'))
                $reply_to_name = strlen($this->nmo->settings->reply_to_name) > 3 ? $this->nmo->settings->reply_to_name : $this->nmo->settings->reply_to_email;
            else
                $reply_to_name = $this->nmo->settings->reply_to_email;

            $this->nmo->mailable->replyTo($this->nmo->settings->reply_to_email, $reply_to_name);

        }

        if (strlen($this->nmo->settings->bcc_email) > 1) {
            nlog('bcc list available');
            nlog($this->nmo->settings->bcc_email);
            $this->nmo->mailable->bcc(explode(",", $this->nmo->settings->bcc_email), 'Blind Copy');
        }
        

        //send email
        try {
            nlog("trying to send");
            
            Mail::to($this->nmo->to_user->email)
                ->send($this->nmo->mailable);

            LightLogs::create(new EmailSuccess($this->nmo->company->company_key))
                     ->batch();

        } catch (\Exception $e) {

            nlog("error failed with {$e->getMessage()}");
            // nlog($e);

            if($this->nmo->entity)
                $this->entityEmailFailed($e->getMessage());
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
        App::forgetInstance('mail.manager'); //singletons must be destroyed!
        App::forgetInstance('mailer');
        App::forgetInstance('laravelgmail');

        /* Inject custom translations if any exist */
        Lang::replace(Ninja::transformTranslations($this->nmo->settings));

        switch ($this->nmo->settings->email_sending_method) {
            case 'default':
                //config(['mail.driver' => config('mail.default')]);
                break;
            case 'gmail':
                $this->setGmailMailer();
                break;
            default:
                break;
        }

        (new MailServiceProvider(app()))->register();
    }

    private function setGmailMailer()
    {
        if(LaravelGmail::check())
            LaravelGmail::logout();

        $sending_user = $this->nmo->settings->gmail_sending_user_id;

        $user = User::find($this->decodePrimaryKey($sending_user));

        nlog("Sending via {$user->name()}");

        $google = (new Google())->init();
        $google->getClient()->setAccessToken(json_encode($user->oauth_user_token));

        if ($google->getClient()->isAccessTokenExpired()) {
            $google->refreshToken($user);
        }

        /*
         *  Now that our token is refreshed and valid we can boot the
         *  mail driver at runtime and also set the token which will persist
         *  just for this request.
        */

        config(['mail.driver' => 'gmail']);
        (new MailServiceProvider(app()))->register();

        $token = $user->oauth_user_token->access_token;

        $this->nmo
             ->mailable
             ->from($user->email, $user->name())
             ->withSwiftMessage(function ($message) use($token) {
                $message->getHeaders()->addTextHeader('GmailToken', $token);     
             });

    }

    private function logMailError($errors, $recipient_object)
    {
        SystemLogger::dispatch(
            $errors,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_SEND,
            SystemLog::TYPE_FAILURE,
            $recipient_object
        );
    }

    public function failed($exception = null)
    {
        nlog('mailer job failed');
        nlog($exception->getMessage());
        
        $job_failure = new EmailFailure($this->nmo->company->company_key);
        $job_failure->string_metric5 = 'failed_email';
        $job_failure->string_metric6 = substr($exception->getMessage(), 0, 150);

        LightLogs::create($job_failure)
                 ->batch();
    }
}
