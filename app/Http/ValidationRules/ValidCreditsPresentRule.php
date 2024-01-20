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

namespace App\Http\ValidationRules;

use App\Models\Credit;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidCreditsPresentRule.
 */
class ValidCreditsPresentRule implements Rule
{
    use MakesHash;

    private $input;

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
        return $this->validCreditsPresent();
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.insufficient_credit_balance');
    }

    private function validCreditsPresent(): bool
    {
        //todo need to ensure the clients credits are here not random ones!

        if (array_key_exists('credits', $this->input) && is_array($this->input['credits']) && count($this->input['credits']) > 0) {
            $credit_collection = Credit::query()->whereIn('id', array_column($this->input['credits'], 'credit_id'))->count();

            return $credit_collection == count($this->input['credits']);
        }

        return true;
    }
}
