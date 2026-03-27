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

use App\Casts\ClientSyncCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * ClientSync.
 */
class ClientSync implements Castable
{
    public string $qb_id;

    public bool $dn_dirty = false;

    public function __construct(array $attributes = [])
    {
        $this->qb_id = $attributes['qb_id'] ?? '';
        $this->dn_dirty = $attributes['dn_dirty'] ?? false;
    }
    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return ClientSyncCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
