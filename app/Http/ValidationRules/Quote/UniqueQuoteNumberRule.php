<?php
/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2020. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules\Quote;

use App\Models\Quote;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class UniqueQuoteNumberRule.
 */
class UniqueQuoteNumberRule implements Rule
{
    public $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkIfQuoteNumberUnique(); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return 'Quote number already taken';
    }

    /**
     * @return bool
     */
    private function checkIfQuoteNumberUnique() : bool
    {
        $quote = Quote::where('client_id', $this->input['client_id'])
                        ->where('number', $this->input['number'])
                        ->withTrashed()
                        ->exists();

        if ($quote) {
            return false;
        }

        return true;
    }
}
