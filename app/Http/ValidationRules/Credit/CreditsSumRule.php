<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2026. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\Credit;

use App\Utils\BcMath;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class CreditsSumRule.
 */
class CreditsSumRule implements Rule
{
    use MakesHash;

    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        return $this->checkCreditTotals();
    }

    private function checkCreditTotals()
    {
        // Compare the raw decimal amounts with bcmath rather than casting to
        // float first. floatval() + '>' accumulates binary rounding error, which
        // caused credits that exactly equalled the invoice total to be rejected.
        $credit_amounts = array_column($this->input['credits'] ?? [], 'amount');
        $invoice_amounts = array_column($this->input['invoices'] ?? [], 'amount');

        // Non-numeric amounts are rejected by the dedicated `numeric` rules with a
        // 422. Defer to those here so a malformed amount never reaches bcmath and
        // triggers a ValueError (500).
        foreach (array_merge($credit_amounts, $invoice_amounts) as $amount) {
            if (!is_numeric($amount)) {
                return true;
            }
        }

        $credits = BcMath::sum($credit_amounts);
        $invoices = BcMath::sum($invoice_amounts);

        return BcMath::lessThanOrEqual($credits, $invoices);
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.credits_applied_validation');
    }
}
