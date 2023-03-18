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

namespace App\Services\Scheduler;

use App\Models\Scheduler;
use App\Utils\Traits\MakesHash;

class ScheduleEntity
{
    use MakesHash;

    public function __construct(public Scheduler $scheduler)
    {
    }

    public function run()
    {

    }
}
