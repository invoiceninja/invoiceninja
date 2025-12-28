<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Company;

use App\Libraries\MultiDB;
use App\Utils\Ninja;
use Illuminate\Contracts\Validation\Rule;
use Symfony\Component\Validator\Constraints\EmailValidator;

/**
 * Class ValidCompanyQuantity.
 */
class ValidExpenseMailbox implements Rule
{
    private array $endings = [];

    public function __construct()
    {
        $this->endings = explode(",", config('ninja.inbound_mailbox.expense_mailbox_endings'));
    }

    public function passes($attribute, $value)
    {
        if (empty($value) || !config('ninja.inbound_mailbox.expense_mailbox_endings')) {
            return true;
        }

        foreach ($this->endings as $ending) {
            if (str_ends_with($value, $ending)) {
                return true;
            }
        }

        return false;

    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.expense_mailbox_invalid');
    }
}
