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
use App\Models\PaymentHash;
use App\PaymentDrivers\Stripe\CardPresent;

/* Finishing a collection: once a card is charged, recording it never depends on the plan or the gateway */
class ActionTerminalPaymentIntentRequest extends TerminalRequest
{
    private ?PaymentHash $payment_hash = null;

    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($this->invoice->company_id != $user->company()->id) {
            return false;
        }

        /* Whoever started it, or anyone who may take payments on the invoice */
        return $this->paymentHash()->data->terminal->user_id == $user->id
            || ($user->can('create', Payment::class) && $user->can('view', $this->invoice));
    }

    /* The invoice's collection for this PaymentIntent */
    public function paymentHash(): PaymentHash
    {
        return $this->payment_hash ??= CardPresent::collections($this->invoice)
            ->where('data->terminal->payment_intent_id', $this->route('payment_intent'))
            ->firstOrFail();
    }
}
