<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
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

        if (request()->input('credits') && is_array(request()->input('credits'))) {
            $credit_collection = Credit::whereIn('id', $this->transformKeys(array_column(request()->input('credits'), 'credit_id')))
                                       ->where('balance', '>', 0)
                                       ->get();

            return $credit_collection->count() == count(request()->input('credits'));
        }

        return true;
    }
}
