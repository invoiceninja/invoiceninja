<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Jobs\Mail;

use App\Jobs\Mail\BaseMailerJob;
use App\Jobs\Util\SystemLogger;
use App\Libraries\Google\Google;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityNotificationMailer;
use App\Mail\Admin\EntitySentObject;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\SystemLog;
use App\Models\User;
use App\Providers\MailServiceProvider;
use Dacastro4\LaravelGmail\Services\Message\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer Router implemented*/

class MailRouter extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mailable;

    public $company;

    public $to_user; //User or ClientContact

    public $sending_method;

    public $settings;

	public function __construct(Mailable $mailable, Company $company, $to_user, string $sending_method)
    {
        $this->mailable = $mailable;

        $this->company = $company;

        $this->to_user = $to_user;

        $this->sending_method = $sending_method;

        if($to_user instanceof ClientContact)
            $this->settings = $to_user->client->getMergedSettings();
        else
            $this->settings = $this->company->settings;
    }

    public function handle()
    {
    	MultiDB::setDb($this->company->db);
 
        //if we need to set an email driver do it now
        $this->setMailDriver();
        
        //send email
        Mail::to($this->to_user->email)
            ->send($this->mailable);

        //catch errors
        if (count(Mail::failures()) > 0) {
            $this->logMailError(Mail::failures(), $this->to_user);
        }
    }
}