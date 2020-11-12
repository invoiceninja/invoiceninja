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

namespace App\Jobs\User;

use App\Jobs\Mail\BaseMailerJob;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\User\UserNotificationMailer;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use stdClass;

class UserEmailChanged extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $new_email;

    protected $old_email;

    protected $company;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param string $new_email
     * @param string $old_email
     * @param Company $company
     */
    public function __construct(string $new_email, string $old_email, Company $company)
    {
        $this->new_email = $new_email;
        $this->old_email = $old_email;
        $this->company = $company;
        $this->settings = $this->company->settings;
    }

    public function handle()
    {
        if($this->company->is_disabled)
            return true;
        
        //Set DB
        MultiDB::setDb($this->company->db);

        //If we need to set an email driver do it now
        $this->setMailDriver();

        /*Build the object*/
        $mail_obj = new stdClass;
        $mail_obj->subject = ctrans('texts.email_address_changed');
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->from = [$this->company->owner()->email, $this->company->owner()->present()->name()];
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->data = $this->getData();

        //Send email via a Mailable class
        //
        try {
        Mail::to($this->old_email)
            ->send(new UserNotificationMailer($mail_obj));

        Mail::to($this->new_email)
            ->send(new UserNotificationMailer($mail_obj));
        }
        catch (\Exception $e) {

            $this->failed($e);
            $this->logMailError($e->getMessage(), $this->company->owner());

        }

    }

    private function getData()
    {
        return [
            'title' => ctrans('texts.email_address_changed'),
            'message' => ctrans(
                'texts.email_address_changed_message',
                ['old_email' => $this->old_email,
                'new_email' => $this->new_email,
            ]
            ),
            'url' => config('ninja.app_url'),
            'button' => ctrans('texts.account_login'),
            'signature' => $this->company->owner()->signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
        ];
    }
}
