<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Casts;

use App\DataMapper\InvoiceSync;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class InvoiceSyncCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        $data = json_decode($value, true);

        if (!is_array($data)) {
            return null;
        }

        $is = new InvoiceSync();
        $is->qb_id =  $data['qb_id'];

        return $is;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return [
            $key => json_encode([
                'qb_id' => $value->qb_id,
            ])
        ];
    }
}
