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
use App\Services\EDocument\Standards\France\FrancePaymentReportingMutationGuard;
use Illuminate\Validation\Validator;

class DestroyPaymentRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->payment) && $this->payment->is_deleted === false;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $violation = app(FrancePaymentReportingMutationGuard::class)
                ->paymentDeletionViolation($this->payment);

            if ($violation) {
                $validator->errors()->add('id', $violation);
            }
        });
    }

}
