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

/**
 * Class ValidCompanyQuantity.
 */
class ValidExpenseMailbox implements Rule
{

    private $validated_schema = false;
    private $company_key = false;
    private $isEnterprise = false;

    public function __construct(string $company_key, bool $isEnterprise = false)
    {
        $this->company_key = $company_key;
        $this->isEnterprise = $isEnterprise;
    }

    public function passes($attribute, $value)
    {
        if (empty($value)) {
            return true;
        }

        // early return, if we dont have any additional validation
        if (!config('ninja.inbound_expense.webhook.mailbox_schema') && !(Ninja::isHosted() && config('ninja.inbound_expense.webhook.mailbox_schema_enterprise'))) {
            $this->validated_schema = true;
            return MultiDB::checkExpenseMailboxAvailable($value);
        }

        // Validate Schema
        $validated = !config('ninja.inbound_expense.webhook.mailbox_schema') || (preg_match(config('ninja.inbound_expense.webhook.mailbox_schema'), $value) && (!config('ninja.inbound_expense.webhook.mailbox_schema_hascompanykey') || str_contains($value, $this->company_key))) ? true : false;
        $validated_enterprise = !config('ninja.inbound_expense.webhook.mailbox_schema_enterprise') || (Ninja::isHosted() && $this->isEnterprise && preg_match(config('ninja.inbound_expense.webhook.mailbox_schema_enterprise'), $value));

        if (!$validated && !$validated_enterprise)
            return false;

        $this->validated_schema = true;
        return MultiDB::checkExpenseMailboxAvailable($value);
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->validated_schema ? ctrans('texts.expense_mailbox_taken') : ctrans('texts.expense_mailbox_invalid');
    }
}
