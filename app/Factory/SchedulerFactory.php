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

namespace App\Factory;

use App\Models\Scheduler;

class SchedulerFactory
{
    public static function create($company_id, $user_id): Scheduler
    {
        $scheduler = new Scheduler();

        $scheduler->name = '';
        $scheduler->company_id = $company_id;
        $scheduler->user_id = $user_id;
        $scheduler->parameters = [];
        $scheduler->is_paused = false;
        $scheduler->is_deleted = false;
        $scheduler->template = '';
        $scheduler->next_run = now()->format('Y-m-d');
        $scheduler->next_run_client = now()->format('Y-m-d');

        return $scheduler;
    }
}
