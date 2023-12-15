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
    private array $endings;
    private bool $hasCompanyKey;
    private array $enterprise_endings;

    public function __construct(string $company_key, bool $isEnterprise = false)
    {
        $this->company_key = $company_key;
        $this->isEnterprise = $isEnterprise;
        $this->endings = explode(",", config('ninja.inbound_expense.webhook.mailbox_endings'));
        $this->hasCompanyKey = config('ninja.inbound_expense.webhook.mailbox_hascompanykey');
        $this->enterprise_endings = explode(",", config('ninja.inbound_expense.webhook.mailbox_enterprise_endings'));
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
        $validated_hasCompanyKey = !$this->hasCompanyKey || str_contains($value, $this->company_key);
        $validated = false;
        if ($validated_hasCompanyKey)
            foreach ($this->endings as $ending) {
                if (str_ends_with($ending, $value)) {
                    $validated = true;
                    break;
                }
            }

        $validated_enterprise = false;
        if (Ninja::isHosted() && $this->isEnterprise)
            foreach ($this->endings as $ending) {
                if (str_ends_with($ending, $value)) {
                    $validated_enterprise = true;
                    break;
                }
            }

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
