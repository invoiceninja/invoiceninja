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
        ], static fn ($value): bool => $value !== null);

        return [$key => json_encode($data)];
    }
}
