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

namespace App\Casts;

use App\DataMapper\Billing\BillingContext;
use App\Enum\BillingState;
use Carbon\Carbon;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class BillingContextCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        $data = json_decode($value, true);

        if (! is_array($data) || empty($data)) {
            return null;
        }

        if (! isset($data['billing_state'])) {
            $data['version'] = BillingContext::VERSION;
            $data['billing_state'] = $this->legacyBillingState($model)->value;
        }

        $context = BillingContext::fromArray($data);

        return $context->client_id > 0 ? $context : null;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value) || ! $value instanceof BillingContext || $value->client_id <= 0) {
            return [$key => null];
        }

        $pricing = [
            'plan_price' => round((float) ($value->pricing['plan_price'] ?? 0), 2),
            'docuninja_price' => round((float) ($value->pricing['docuninja_price'] ?? 0), 2),
        ];

        $data = array_filter([
            'version' => $value->version,
            'client_id' => $value->client_id,
            'recurring_invoice_id' => $value->recurring_invoice_id,
            'pricing' => $pricing === ['plan_price' => 0.0, 'docuninja_price' => 0.0] ? null : $pricing,
            'docuninja_pending_prune' => $value->docuninja_pending_prune ?: null,
            'billing_state' => $value->billing_state->value,
        ], static fn ($value): bool => $value !== null);

        return [$key => json_encode($data)];
    }

    private function legacyBillingState($model): BillingState
    {
        $plan = $model->plan ?? null;
        $is_trial = (bool) ($model->is_trial ?? false);

        if (
            ($is_trial || in_array($plan, ['pro', 'enterprise'], true))
            && ($model->plan_expires ?? null)
            && Carbon::parse($model->plan_expires)->lt(now()->subHours(23))
        ) {
            return BillingState::Expired;
        }

        if ($is_trial) {
            return BillingState::Trial;
        }

        if (in_array($plan, ['pro', 'enterprise'], true)) {
            return BillingState::Paid;
        }

        return BillingState::Free;
    }
}
