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

use App\DataMapper\Tax\TaxModel;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class TaxModelCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): TaxModel
    {
        $data = is_null($value) ? null : json_decode($value);

        $taxModel = new TaxModel($data);

        if ($data && $taxModel->version !== ($data->version ?? 'delta')) {
            $model->updateQuietly([$key => $taxModel]);
        }

        return $taxModel;
    }

    public function set($model, string $key, $value, array $attributes): ?string
    {
        if ($value instanceof TaxModel) {
            return json_encode($value);
        }

        if (is_object($value) || is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }
}