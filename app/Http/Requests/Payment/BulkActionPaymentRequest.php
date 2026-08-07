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
use App\Services\EDocument\Standards\France\FrancePaymentReportingMutationGuard;
use Illuminate\Validation\Validator;

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

    public function rules()
    {

        return [
            'action' => 'required|string',
            'ids' => 'required|array',
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool',
        ];

    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty() || $this->input('action') !== 'delete') {
                return;
            }

            /** @var \App\Models\User $user */
            $user = auth()->user();

            Payment::withTrashed()
                ->whereIn('id', $this->transformKeys($this->input('ids', [])))
                ->where('company_id', $user->company()->id)
                ->cursor()
                ->each(function (Payment $payment) use ($user, $validator): void {
                    if ($user->can('edit', $payment)) {
                        $violation = app(FrancePaymentReportingMutationGuard::class)
                            ->paymentDeletionViolation($payment);

                        if ($violation) {
                            $validator->errors()->add('id', $violation);
                        }
                    }
                });
        });
    }
}
