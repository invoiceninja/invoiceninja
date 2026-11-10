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

namespace App\Http\Requests\Terminal;

use App\Models\Payment;
use App\Models\TerminalPaymentIntent;

/* Finishing a collection: once a card is charged, recording it never depends on the plan or the gateway */
class ActionTerminalPaymentIntentRequest extends TerminalRequest
{
    private ?TerminalPaymentIntent $collection = null;

    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $collection = $this->collection();

        /* Whoever started it, or anyone who may take payments on an invoice they can see */
        return $collection->user_id == $user->id
            || ($user->can('create', Payment::class) && $collection->invoice && $user->can('view', $collection->invoice));
    }

    public function collection(): TerminalPaymentIntent
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->collection ??= TerminalPaymentIntent::query()
            ->where('company_id', $user->company()->id)
            ->where('payment_intent_id', $this->route('payment_intent'))
            ->firstOrFail();
    }
}
