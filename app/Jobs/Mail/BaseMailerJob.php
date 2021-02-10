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
use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
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
use Turbo124\Beacon\Facades\LightLogs;

/*Multi Mailer implemented*/

class BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, MakesHash;

    public $tries = 5; //number of retries

    public $backoff = 5; //seconds to wait until retry

    public $deleteWhenMissingModels = true;

    public function setMailDriver()
    {
        /* Singletons need to be rebooted each time just in case our Locale is changing*/
        App::forgetInstance('translator');
        App::forgetInstance('mail.manager'); //singletons must be destroyed!

        /* Inject custom translations if any exist */
        Lang::replace(Ninja::transformTranslations($this->settings));

        switch ($this->settings->email_sending_method) {
            case 'default':
                break;
            case 'gmail':
                $this->setGmailMailer();
                break;
            default:
                break;
        }
    }

    public function setGmailMailer()
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

        Config::set('mail.driver', 'gmail');
        Config::set('services.gmail.token', $user->oauth_user_token->access_token);
        Config::set('mail.from.address', $user->email);
        Config::set('mail.from.name', $user->present()->name());

        (new MailServiceProvider(app()))->register();
    }

    public function logMailError($errors, $recipient_object)
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
        
        $job_failure = new EmailFailure();
        $job_failure->string_metric5 = get_parent_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->batch();
    }
}
