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

namespace App\Listeners\Misc;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntityViewedObject;
use App\Utils\Ninja;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\App;

class InvitationViewedListener implements ShouldQueue
{
    use UserNotifies;

    /**
     * Create the event listener.
     *
     * @return void
     */
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

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($event->company->settings));
        App::setLocale($event->company->getLocale());

        $entity_name = lcfirst(class_basename($event->entity));
        $invitation = $event->invitation;

        if ($entity_name == 'recurringInvoice') {
            return;
        } elseif ($entity_name == 'purchaseOrder') {
            $entity_name = 'purchase_order';
        }

        foreach ($invitation->company->company_users as $company_user) {
            $entity_viewed = "{$entity_name}_viewed";
            $entity_viewed_all = "{$entity_name}_viewed_all";
            $entity_viewed_user = "{$entity_name}_viewed_user";

            $methods = $this->findUserNotificationTypes($invitation, $company_user, $entity_name, ['all_notifications', $entity_viewed, $entity_viewed_all, $entity_viewed_user]);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);


                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer((new EntityViewedObject($invitation, $entity_name, $company_user->portalType()))->build());
                $nmo->company = $invitation->company;
                $nmo->settings = $invitation->company->settings;

                $nmo->to_user = $company_user->user;
                (new NinjaMailerJob($nmo))->handle();

                $nmo = null;
            }
        }
    }
}
