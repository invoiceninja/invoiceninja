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

namespace App\Http\ValidationRules\User;

use App\Models\User;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class RelatedUserRule.
 */
class RelatedUserRule implements Rule
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
        return $this->checkUserIsRelated($value);
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.user_not_associated_with_account');
    }

    /**
     * @param $user_id
     * @return bool
     */
    private function checkUserIsRelated($user_id) : bool
    {
        if (empty($user_id)) {
            return true;
        }

        return User::query()
                    ->where('id', $user_id)
                    ->where('account_id', auth()->user()->company()->account_id)
                    ->exists();
    }
}
