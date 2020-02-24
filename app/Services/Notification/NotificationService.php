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

    public function run()
    {

        $this->company->owner()->notify($this->notification);
    
    }

}
