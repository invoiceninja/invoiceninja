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

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer Router implemented*/

class MailRouter extends BaseMailerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $mailable;

    public $company;

    public $to_user; //User or ClientContact

    public $sending_method; //not sure if we even need this

    public $settings;

    public function __construct(Mailable $mailable, Company $company, $to_user, $sending_method = null)
    {
        $this->mailable = $mailable;

        $this->company = $company;

        $this->to_user = $to_user;

        $this->sending_method = $sending_method;

        if ($to_user instanceof ClientContact) {
            $this->settings = $to_user->client->getMergedSettings();
        } else {
            $this->settings = $this->company->settings;
        }
    }

    public function handle()
    {
        /*If we are migrating data we don't want to fire these notification*/
        if ($this->company->is_disabled) {
            return true;
        }
        
        MultiDB::setDb($this->company->db);

        //if we need to set an email driver do it now
        $this->setMailDriver();

        //send email
        try {
            Mail::to($this->to_user->email)
                ->send($this->mailable);
        } catch (\Exception $e) {
            $this->failed($e);
            
            if ($this->to_user instanceof ClientContact) {
                $this->logMailError($e->getMessage(), $this->to_user->client);
            }
        }
    }
}
