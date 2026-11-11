<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Quote;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class QuoteCancelledActivity implements ShouldQueue
{
    public $delay = 5;

    public function __construct(protected ActivityRepository $activity_repo) {}

    public function handle($event): void
    {
        MultiDB::setDb($event->company->db);

        $fields = new stdClass();
        $fields->user_id = $event->event_vars['user_id'] ?? $event->quote->user_id;
        $fields->quote_id = $event->quote->id;
        $fields->client_id = $event->quote->client_id;
        $fields->company_id = $event->quote->company_id;
        $fields->activity_type_id = Activity::CANCELLED_QUOTE;

        $this->activity_repo->save($fields, $event->quote, $event->event_vars);
    }
}
