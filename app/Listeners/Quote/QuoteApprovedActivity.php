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

namespace App\Listeners\Quote;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\ClientContact;
use App\Models\InvoiceInvitation;
use App\Repositories\ActivityRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use stdClass;

class QuoteApprovedActivity implements ShouldQueue
{
    protected $activity_repo;

    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activity_repo
     */
    public function __construct(ActivityRepository $activity_repo)
    {
        $this->activity_repo = $activity_repo;
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

        $fields = new stdClass;

        $fields->quote_id = $event->quote->id;
        $fields->client_id = $event->quote->client_id;
        $fields->user_id = $event->quote->user_id;
        $fields->client_contact_id = $event->contact->id;
        $fields->company_id = $event->payment->company_id;
        $fields->activity_type_id = Activity::RESTORE_PAYMENT;

        $this->activity_repo->save($fields, $event->payment, $event->event_vars);
    }
}
