<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\BankTransactionRule;

use App\Http\Requests\Request;
use App\Models\BankTransactionRule;

class CreateBankTransactionRuleRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create', BankTransactionRule::class);
    }
}
