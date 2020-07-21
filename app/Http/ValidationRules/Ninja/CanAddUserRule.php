<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2020. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\Http\ValidationRules\Ninja;

use App\Models\Company;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class CanAddUserRule
 * @package App\Http\ValidationRules
 */
class CanAddUserRule implements Rule
{

    public $account;

    public function __construct($account)
    {
        $this->account = $account;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->account->users->count() < $this->account->num_users;
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.limit_users', ['limit' => $this->account->num_users]);
    }

}
