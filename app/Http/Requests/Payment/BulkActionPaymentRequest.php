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

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Models\Payment;

class BulkActionPaymentRequest extends Request
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

    public function rules(): array
    {
        return [
            'action' => 'required|string',
            'ids' => 'required|array',
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {

            if($this->action === 'delete') {
                $deleted_invoices_exist = Payment::with('invoices')
                                                            ->whereIn('id', $this->transformKeys($this->ids))
                                                            ->whereHas('invoices', function ($query) {
                                                                $query->where('is_deleted', true);
                                                            })
                                                            ->company()
                                                            ->withTrashed()
                                                            ->exists();

                if ($deleted_invoices_exist) {
                    $validator->errors()->add(
                        'ids',
                        ctrans('texts.deleted_invoices_exist')
                    );
                }

            }
        });
    }
}
