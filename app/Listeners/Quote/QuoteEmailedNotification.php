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

namespace App\Listeners\Quote;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntitySentObject;
use App\Notifications\Admin\EntitySentNotification;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class QuoteEmailedNotification implements ShouldQueue
{
    public $delay = 5;

    use UserNotifies;

    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        MultiDB::setDb($event->company->db);

        // $first_notification_sent = true;

        $quote = $event->invitation->quote;
        $quote->last_sent_date = now();
        $quote->saveQuietly();

        $nmo = new NinjaMailerObject;
        $nmo->mailable = new NinjaMailer((new EntitySentObject($event->invitation, 'quote', $event->template))->build());
        $nmo->company = $quote->company;
        $nmo->settings = $quote->company->settings;

        foreach ($event->invitation->company->company_users as $company_user) {
            $user = $company_user->user;

            // $notification = new EntitySentNotification($event->invitation, 'quote');

            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'quote', ['all_notifications', 'quote_sent', 'quote_sent_all']);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $user;

                NinjaMailerJob::dispatch($nmo);

                // $first_notification_sent = false;
            }

            // $notification->method = $methods;

            // $user->notify($notification);
        }
    }
}
