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

use App\DataMapper\QuoteSync;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class QuoteSyncCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return null; // Return null if the value is null
        }

        $data = json_decode($value, true);
   
        if (!is_array($data) || empty($data)) {
            return null; // Return null if decoded data is not an array or is empty
        }

        return QuoteSync::fromArray($data);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return [$key => null];
        }

        return [
            $key => json_encode([
                'qb_id' => $value->qb_id,
                'invitations' => $value->invitations,
                'dn_completed' => $value->dn_completed,
                'dn_document_hashed_id' => $value->dn_document_hashed_id,
            ])
        ];
    }
}
