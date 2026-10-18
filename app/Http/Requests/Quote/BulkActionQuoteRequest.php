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
use App\Http\ValidationRules\Quote\ConvertableQuoteRule;
use App\Models\Quote;
use Illuminate\Validation\Validator;

class BulkActionQuoteRequest extends Request
{
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
        $input = $this->all();

        $rules = [
            'action' => 'required|in:template,convert,convert_to_invoice,convert_to_project,email,bulk_download,bulk_print,clone_to_quote,convert_to_purchase_order,approve,download,restore,archive,delete,send_email,mark_sent,cancel',
            'ids' => 'required|array',
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool',
            'email_type' => 'sometimes|in:quote,reminder1,custom1,custom2,custom3',
        ];

        if (in_array(($input['action'] ?? ''), ['convert', 'convert_to_invoice'])) {
            $rules['action'] = ['required', 'in:convert,convert_to_invoice', new ConvertableQuoteRule()];
        }

        if (($input['action'] ?? '') === 'convert_to_purchase_order') {
            $rules['ids'] = ['required', 'array', 'size:1'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
        
            $quotes = Quote::withTrashed()
                        ->with('client')
                        ->whereIn('id', $this->transformKeys($this->input('ids')))
                        ->company()
                        ->get();
                
            if (in_array($this->input('action'), ['mark_sent', 'send_email', 'email'])) {
                if ($quotes->contains(fn (Quote $quote) => $quote->hasLapsedValidUntil())) {
                    $validator->errors()->add(
                        'ids',
                        ctrans('texts.expired_quote_validation_error'),
                    );
                }
            }

            if ($this->input('action') === 'cancel' && $quotes->contains(fn(Quote $quote) => $quote->status_id !== Quote::STATUS_SENT)) {
                $validator->errors()->add('ids', ctrans('texts.quotes_with_status_sent_can_be_cancelled'));
            }

        });
    }
}
