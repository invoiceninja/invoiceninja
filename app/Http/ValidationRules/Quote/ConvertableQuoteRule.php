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

namespace App\Http\ValidationRules\Quote;

use App\Utils\Traits\MakesHash;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Models\Quote;

class ConvertableQuoteRule implements ValidationRule
{
    use MakesHash;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        $ids = request()->input('ids');

        $quotes = Quote::withTrashed()
                        ->whereIn('id', $this->transformKeys($ids))
                        ->company()
                        ->get();

        foreach ($quotes as $quote) {
            if (! $quote->service()->isConvertable()) {
                $fail(ctrans('texts.quote_has_expired'));
            }
        }

    }

}
