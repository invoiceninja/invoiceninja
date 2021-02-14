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
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\SystemLog;
use App\Models\User;
use App\Providers\MailServiceProvider;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
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
        
        MultiDB::setDb($this->nmo->company->db);

        //if we need to set an email driver do it now
        $this->setMailDriver();

        //send email
        try {
            Mail::to($this->nmo->to_user->email)
                ->send($this->nmo->mailable);
        } catch (\Exception $e) {
            //$this->failed($e);
            
            if ($this->nmo->to_user instanceof ClientContact) {
                $this->logMailError($e->getMessage(), $this->nmo->to_user->client);
            }
        }
    }

    private function setMailDriver()
    {
        /* Singletons need to be rebooted each time just in case our Locale is changing*/
        App::forgetInstance('translator');
        App::forgetInstance('mail.manager'); //singletons must be destroyed!

        /* Inject custom translations if any exist */
        Lang::replace(Ninja::transformTranslations($this->nmo->settings));

        switch ($this->nmo->settings->email_sending_method) {
            case 'default':
                break;
            case 'gmail':
                $this->setGmailMailer();
                break;
            default:
                break;
        }
    }

    private function setGmailMailer()
    {
        $sending_user = $this->settings->gmail_sending_user_id;

        $user = User::find($this->decodePrimaryKey($sending_user));

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
        config(['services.gmail.token' => $user->oauth_user_token->access_token]);
        config(['mail.from.address' => $user->email]);
        config(['mail.from.name' => $user->present()->name()]);

        //(new MailServiceProvider(app()))->register();

        nlog("after registering mail service provider");
        nlog(config('services.gmail.token'));
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

    private function failed($exception = null)
    {
        nlog('mailer job failed');
        nlog($exception->getMessage());
        
        $job_failure = new EmailFailure();
        $job_failure->string_metric5 = get_parent_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->batch();
    }
}
