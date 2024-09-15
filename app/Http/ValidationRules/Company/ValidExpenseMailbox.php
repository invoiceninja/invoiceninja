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

    private $validated_schema = false;
    private array $endings;

    public function __construct()
    {
        $this->endings = explode(",", config('ninja.inbound_mailbox.expense_mailbox_endings'));
    }

    public function passes($attribute, $value)
    {
        if (empty($value) || !config('ninja.inbound_mailbox.expense_mailbox_endings')) {
            return true;
        }


        // Validate Schema
        $validated = false;
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
        if (!$this->validated_schema)
            return ctrans('texts.expense_mailbox_invalid');

        return ctrans('texts.expense_mailbox_taken');
    }
}
