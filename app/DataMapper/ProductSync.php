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

namespace App\DataMapper;

use App\Casts\ProductSyncCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * ProductSync.
 */
class ProductSync implements Castable
{
    public string $qb_id;

    public string $qb_status_message;

    public function __construct(array $attributes = [])
    {
        $this->qb_id = $attributes['qb_id'] ?? '';
        $this->qb_status_message = $attributes['qb_status_message'] ?? '';
    }

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return ProductSyncCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
