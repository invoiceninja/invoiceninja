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

namespace App\Services\Recurring;

use App\Models\RecurringInvoice;

class RecurringService
{
    protected $recurring_entity;

    public function __construct($recurring_entity)
    {
        $this->recurring_entity = $recurring_entity;
    }

    //set schedules - update next_send_dates
}
