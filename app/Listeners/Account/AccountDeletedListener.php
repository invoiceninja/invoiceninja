<?php

namespace App\Listeners\Account;

use App\Utils\Ninja;
use App\Models\Company;
use App\Models\Activity;
use App\Libraries\MultiDB;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Contracts\Queue\ShouldQueue;

class AccountDeletedListener implements ShouldQueue
{
    /**
     * Create the event listener.
     *
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
        
        if (Ninja::isHosted()) {

            MultiDB::setDB('db-ninja-01');
            
            $company = Company::find(config('ninja.ninja_default_company_id'));

            $activity = new Activity();

            $activity->user_id = null;
            $activity->company_id = $company->id;
            $activity->account_id = $company->account_id;
            $activity->activity_type_id = Activity::ACCOUNT_DELETED;
            $activity->notes = "Account {$event->account_key} deleted by {$event->email} from {$event->ip}";
            $activity->ip = $event->ip;
            $activity->is_system = false;
            $activity->save();
            

        }
    }
}
