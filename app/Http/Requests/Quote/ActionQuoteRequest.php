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

namespace App\Http\Requests\Quote;

use App\Http\Requests\Request;
use App\Models\Quote;
use Illuminate\Validation\Validator;

class ActionQuoteRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->quote);
    }

    public function rules(): array
    {
        return [
            'action' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->action === 'cancel' && $this->quote->status_id !== Quote::STATUS_SENT) {
                $validator->errors()->add('action', ctrans('texts.quotes_with_status_sent_can_be_cancelled'));
            }
        });
    }

    public function prepareForValidation(): void
    {
        $this->merge(['action' => $this->route('action')]);
    }
}
