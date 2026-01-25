<?php

/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2025. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Invoice;

use App\Utils\Ninja;
use App\Models\Invoice;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Invoice\ActionsInvoice;
use App\Exceptions\DuplicatePaymentException;
use App\Helpers\Cache\Atomic;

class BulkInvoiceRequest extends Request
{
    use ActionsInvoice;
    use MakesHash;
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'action' => ['required', 'bail', 'string'],
            'ids' => ['required', 'bail', 'array'],
            'email_type' => 'sometimes|in:reminder1,reminder2,reminder3,reminder_endless,custom1,custom2,custom3,invoice,quote,credit,payment,payment_partial,statement,purchase_order',
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool',
            'subscription_id' => 'sometimes|string',
        ];
    }

    public function withValidator($validator)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $action = $this->input('action');
        
        $validator->after(function ($validator) use ($user, $action) {
            Invoice::withTrashed()
                ->whereIn('id', $this->transformKeys($this->input('ids', [])))
                ->where('company_id', $user->company()->id)
                ->cursor()
                ->each(function ($invoice) use ($validator, $action) {
                    
                    if ($action ==  'delete' &&! $this->invoiceDeletable($invoice)) {
                        $validator->errors()->add('action', 'This invoice cannot be deleted');
                    } elseif ($action == 'cancel' && ! $this->invoiceCancellable($invoice)) {
                        $validator->errors()->add('action', 'This invoice cannot be cancelled');
                    } elseif ($action == 'reverse' && ! $this->invoiceReversable($invoice)) {
                        $validator->errors()->add('action', 'This invoice cannot be reversed');
                    } elseif($action == 'restore' && ! $this->invoiceRestorable($invoice)) {
                        $validator->errors()->add('action', 'This invoice cannot be restored');
                    } elseif($action == 'mark_paid' && ! $this->invoicePayable($invoice)) {
                        $validator->errors()->add('action', 'This invoice cannot be marked as paid');
                    }
                });
        });
    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $key = ($this->ip()."|".$this->input('action', 0)."|".$user->company()->company_key);

        // Calculate TTL: 1 second base, or up to 3 seconds for delete actions
        $delay = $this->input('action', 'delete') == 'delete' ? (min(count($this->input('ids', [])), 3)) : 1;

        // Atomic lock: returns false if key already exists (request in progress)
        if (!Atomic::set($key, true, $delay)) {
            throw new DuplicatePaymentException('Action still processing, please wait. ', 429);
        }

        $this->merge(['lock_key' => $key]);
    }

}
