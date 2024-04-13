<?php

namespace App\Rules\Subscriptions;

use App\Services\Subscription\StepService;
use Closure;
use App\Livewire\BillingPortal\Purchase;
use Illuminate\Contracts\Validation\ValidationRule;

class Steps implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $steps = StepService::mapToClassNames($value);
        $errors = StepService::check($steps);

        if (count($errors) > 0) {
            $fail($errors[0]);
        }
    }
}
