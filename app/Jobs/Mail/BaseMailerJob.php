<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Mail;

use App\DataMapper\Analytics\EmailFailure;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Models\User;
use App\Providers\MailServiceProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Turbo124\Beacon\Facades\LightLogs;

/*Multi Mailer implemented*/

class BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function setMailDriver()
    {
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

        $user = User::find($sending_user);

        $google = (new Google())->init();
        $google->getClient()->setAccessToken(json_encode($user->oauth_user_token));

        if ($google->getClient()->isAccessTokenExpired()) {
            $google->refreshToken($user);
        }

        /*
         *  Now that our token is refresh and valid we can boot the
         *  mail driver at runtime and also set the token which will persist
         *  just for this request.
        */

        Config::set('mail.driver', 'gmail');
        Config::set('services.gmail.token', $user->oauth_user_token->access_token);

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

        info('the job failed');

        $job_failure = new EmailFailure();
        $job_failure->string_metric5 = get_parent_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->batch();

    }
}
