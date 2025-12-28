<?php

/**
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\DataMapper;

use App\Casts\QuoteSyncCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * QuoteSync.
 */
class QuoteSync implements Castable
{
    public string $qb_id;

    public function __construct(array $attributes = [])
    {

        $this->qb_id = $attributes['qb_id'] ?? '';

    }

    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return QuoteSyncCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
