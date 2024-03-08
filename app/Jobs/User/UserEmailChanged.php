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

namespace App\Jobs\User;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\User\UserNotificationMailer;
use App\Models\Company;
use App\Models\User;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use stdClass;

class UserEmailChanged implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\User $new_user
     * @param \stdClass $old_user
     * @param \App\Models\Company $company
     */
    public function __construct(protected User $new_user, protected \stdClass $old_user, protected Company $company, protected bool $is_react = false)
    {
        $this->settings = $this->company->settings;
    }

    public function handle()
    {
        //Set DB
        MultiDB::setDb($this->company->db);

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));
        App::setLocale($this->company->getLocale());

        /*Build the object*/
        $mail_obj = new stdClass();
        $mail_obj->subject = ctrans('texts.email_address_changed');
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->from = [$this->company->owner()->email, $this->company->owner()->present()->name()];
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->data = $this->getData();

        //Send email via a Mailable class

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new UserNotificationMailer($mail_obj);
        $nmo->settings = $this->settings;
        $nmo->company = $this->company;
        $nmo->to_user = $this->old_user;

        NinjaMailerJob::dispatch($nmo, true);

        $this->new_user->service()->invite($this->company, $this->is_react);
    }

    private function getData()
    {
        return [
            'title' => ctrans('texts.email_address_changed'),
            'message' => ctrans(
                'texts.email_address_changed_message',
                ['old_email' => $this->old_user->email,
                    'new_email' => $this->new_user->email,
                ]
            ),
            'url' => config('ninja.app_url'),
            'button' => ctrans('texts.account_login'),
            'signature' => $this->company->owner()->signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->settings,
            'whitelabel' => $this->company->account->isPaid() ? true : false,
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];
    }
}
