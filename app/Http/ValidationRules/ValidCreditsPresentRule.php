<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules;

use App\Libraries\MultiDB;
use App\Models\Credit;
use App\Models\User;
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
        return 'Insufficient balance on credit.';
    }

    private function validCreditsPresent() :bool
    {
        //todo need to ensure the clients credits are here not random ones!

        if (request()->input('credits') && is_array(request()->input('credits'))) {
            foreach (request()->input('credits') as $credit) {
                $cred = Credit::find($this->decodePrimaryKey($credit['credit_id']));

                if (! $cred || $cred->balance == 0) {
                    return false;
                }
            }
        }

        return  true;
    }
}
