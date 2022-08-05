<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Notification;

use App\Models\Company;
use App\Services\AbstractService;
use Illuminate\Notifications\Notification as Notifiable;
use Illuminate\Support\Facades\Notification;

class NotificationService extends AbstractService
{
    const ALL = 'all_notifications';

    const ALL_USER = 'all_user_notifications';

    const PAYMENT_SUCCESS = 'payment_success'; //@todo

    const PAYMENT_FAILURE = 'payment_failure'; //@todo

    const INVOICE_SENT = 'invoice_sent'; //@todo

    const QUOTE_SENT = 'quote_sent'; //@todo

    const CREDIT_SENT = 'credit_sent'; //@todo

    const QUOTE_VIEWED = 'quote_viewed';

    const INVOICE_VIEWED = 'invoice_viewed';

    const CREDIT_VIEWED = 'credit_viewed';

    const QUOTE_APPROVED = 'quote_approved'; //@todo

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

        if ($is_system) {
            $this->notification->is_system = true;

            Notification::route('slack', $this->company->slack_webhook_url)
                ->notify($this->notification);
        }
    }

    /**
     * Hosted notifications.
     * @return void
     */
    public function ninja()
    {
        Notification::route('slack', config('ninja.notification.slack'))
            ->notify($this->notification);
    }
}
