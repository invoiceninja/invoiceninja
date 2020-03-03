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

namespace App\Services\Notification;

use App\Models\Company;
use App\Services\AbstractService;
use Illuminate\Notifications\Notification as Notifiable;
use Illuminate\Support\Facades\Notification;

class NotificationService extends AbstractService
{

    public $company;

    public $notification;

    public function __construct(Company $company, Notifiable $notification)
    {

        $this->company = $company;

        $this->notification = $notification;
    
    }

    public function run($is_system = false)
    {

        $this->company->owner()->notify($this->notification);
    
        if($is_system)
        {
            $this->notification->is_system = true;

            Notification::route('slack', $this->company->slack_webhook_url)
                ->notify($this->notification);
        }

    }

    /**
     * Hosted notifications
     * @return void
     */
    public function ninja()
    {

        Notification::route('slack', config('ninja.notification.slack'))
            ->route('mail', config('ninja.notification.mail'))
            ->notify($this->notification);
        
    }

}
