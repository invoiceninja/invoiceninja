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

use App\DataMapper\TransactionEventMetadata;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class TransactionEventMetadataCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null;
        }

        $data = json_decode($value, true);

        if (!is_array($data)) {
            return null;
        }

        return new TransactionEventMetadata($data);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return [$key => null];
        }

        return [
            $key => json_encode($value->toArray()),
        ];
    }
}
