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

namespace App\Http\ValidationRules\Scheduler;

use App\Models\Client;
use App\Utils\Traits\MakesHash;
use App\Models\Scheduler;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class InvoiceWithNoExistingSchedule.
 */
class InvoiceWithNoExistingSchedule implements Rule
{
    use MakesHash;
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return Scheduler::where('company_id', $user->company()->id)
                            ->where('template', 'payment_schedule')
                            ->where('parameters->invoice_id', $value)
                            ->count() == 0;

    }

    /**
     * @return string
     */
    public function message()
    {
        return 'Invoice already has a payment schedule';
    }
}
