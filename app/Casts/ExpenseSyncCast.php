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

use App\DataMapper\ExpenseSync;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ExpenseSyncCast implements CastsAttributes
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

        $es = new ExpenseSync();
        $es->qb_id = $data['qb_id'];

        return $es;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return [$key => null];
        }

        $data = [
            'qb_id' => $value->qb_id,
        ];

        return [
            $key => json_encode($data),
        ];
    }
}
