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

use App\Models\Scheduler;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class InvoiceWithNoExistingSchedule.
 */
class InvoiceWithNoExistingSchedule implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $exists = Scheduler::where('company_id', $user->company()->id)
                            ->where('template', 'payment_schedule')
                            ->where('parameters->invoice_id', $value)
                            ->exists();

        if ($exists) {
            $fail('Invoice already has a payment schedule');
        }
    }
}
