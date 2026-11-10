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

use App\Models\Client;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IsExpiredRule implements ValidationRule
{
    use MakesHash;

    public function __construct(private ?int $client_id){}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if(!$value) {
            return;
        }

        $client = Client::withTrashed()->find($this->client_id);

        if(!$client) {
            return;
        }

        $quote_valid_date = Carbon::parse($value)->toDateString();

        $client_date_now = now()
            ->setTimezone($client->timezone()->name)
            ->toDateString();

        if($quote_valid_date < $client_date_now) {
            $fail(ctrans('texts.quote_due_date_expired'));
        }

    }
}
