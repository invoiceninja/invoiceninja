<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
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

    private function validCreditsPresent() :bool
    {
        //todo need to ensure the clients credits are here not random ones!

        if (request()->input('credits') && is_array(request()->input('credits')) && count(request()->input('credits')) > 0) {
            $credit_collection = Credit::whereIn('id', $this->transformKeys(array_column(request()->input('credits'), 'credit_id')))
                                       ->count();

            return $credit_collection == count(request()->input('credits'));
        }

        return true;
    }
}
