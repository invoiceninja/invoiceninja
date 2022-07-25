<?php
/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Quote;

use App\Models\Quote;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ConvertableQuoteRule.
 */
class ConvertableQuoteRule implements Rule
{
    use MakesHash;

    public function __construct()
    {
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkQuoteIsConvertable(); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.quote_has_expired');
    }

    /**
     * @return bool
     */
    private function checkQuoteIsConvertable() : bool
    {
        $ids = request()->input('ids');

        $quotes = Quote::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        foreach ($quotes as $quote) {
            if (! $quote->service()->isConvertable()) {
                return false;
            }
        }

        return true;
    }
}
