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

namespace App\Http\Requests\Quote;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Quote\UniqueQuoteNumberRule;
use App\Models\Quote;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('create', Quote::class);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        $rules['client_id'] = ['required', 'bail', Rule::exists('clients', 'id')->where('company_id', $user->company()->id)];

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        $rules['number'] = ['nullable', Rule::unique('quotes')->where('company_id', $user->company()->id)];

        $rules['discount'] = 'sometimes|numeric|max:99999999999999';
        $rules['is_amount_discount'] = ['boolean'];
        $rules['exchange_rate'] = 'bail|sometimes|numeric';
        $rules['line_items'] = 'array';
        $rules['date'] = 'bail|sometimes|date:Y-m-d';
        $rules['partial_due_date'] = ['bail', 'sometimes', 'exclude_if:partial,0', Rule::requiredIf(fn () => $this->partial > 0), 'date', 'before:due_date', 'after_or_equal:date'];
        $rules['due_date'] = ['bail', 'sometimes', 'nullable', 'after:partial_due_date', Rule::requiredIf(fn () => strlen($this->partial_due_date) > 1), 'date'];
        $rules['amount'] = ['sometimes', 'bail', 'numeric', 'max:99999999999999'];

        return $rules;
    }

    public function prepareForValidation()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['amount'] = 0;
        $input['balance'] = 0;

        if (isset($input['line_items']) && is_array($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
            $input['amount'] = $this->entityTotalAmount($input['line_items']);
        }

        if (array_key_exists('exchange_rate', $input) && is_null($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        if(isset($input['partial']) && $input['partial'] == 0) {
            $input['partial_due_date'] = null;
        }

        if(!isset($input['date'])) {
            $input['date'] = now()->addSeconds($user->company()->utc_offset())->format('Y-m-d');
        }

        if(isset($input['partial_due_date']) && (!isset($input['due_date']) || strlen($input['due_date']) <= 1)) {
            $client = \App\Models\Client::withTrashed()->find($input['client_id']);
            $valid_days = ($client && strlen($client->getSetting('valid_until')) >= 1) ? $client->getSetting('valid_until') : 7;
            $input['due_date'] = \Carbon\Carbon::parse($input['date'])->addDays((int)$valid_days)->format('Y-m-d');
        }

        $this->replace($input);
    }
}
