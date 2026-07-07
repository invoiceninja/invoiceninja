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
        $credits = BcMath::sum(array_column($this->input['credits'], 'amount'));
        $invoices = BcMath::sum(array_column($this->input['invoices'], 'amount'));

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
