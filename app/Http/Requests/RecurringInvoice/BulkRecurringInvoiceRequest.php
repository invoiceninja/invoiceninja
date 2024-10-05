<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\RecurringInvoice;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class BulkRecurringInvoiceRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'ids' => ['required','bail','array', Rule::exists('recurring_invoices', 'id')->where('company_id', $user->company()->id)],
            'action' => 'in:archive,restore,delete,increase_prices,update_prices,start,stop,send_now,set_payment_link,bulk_update',
            'percentage_increase' => 'required_if:action,increase_prices|numeric|min:0|max:100',
            'subscription_id' => 'sometimes|string',
            'column' => ['required_if:action,bulk_update', 'string', Rule::in(\App\Models\RecurringInvoice::$bulk_update_columns)],
            'new_value' => ['required_if:action,bulk_update|string'],
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        $this->replace($input);
    }
}
