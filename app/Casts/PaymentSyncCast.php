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

use App\DataMapper\PaymentSync;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class PaymentSyncCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {

        if (is_null($value)) {
            return null; // Return null if the value is null
        }

        $data = json_decode($value, true);

        if (!is_array($data)) {
            return null;
        }

        return new PaymentSync($data);
    }

    public function set($model, string $key, $value, array $attributes)
    {

        if (is_null($value)) {
            return [$key => null];
        }

        return [
            $key => json_encode([
                'qb_id' => $value->qb_id,
                'qb_sync_token' => $value->qb_sync_token ?? '',
                'qb_immutable' => $value->qb_immutable ?? false,
                'qb_void_failed' => $value->qb_void_failed ?? false,
                'qb_void_error' => $value->qb_void_error ?? '',
                'last_synced_at' => $value->last_synced_at ?? '',
            ]),
        ];
    }
}
