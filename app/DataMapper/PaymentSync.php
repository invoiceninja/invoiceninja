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

use App\Casts\PaymentSyncCast;
use Illuminate\Contracts\Database\Eloquent\Castable;

/**
 * PaymentSync.
 */
class PaymentSync implements Castable
{
    public string $qb_id;

    public string $qb_sync_token;

    public bool $qb_immutable;

    public bool $qb_void_failed;

    public string $qb_void_error;

    public string $last_synced_at;

    public function __construct(array $attributes = [])
    {
        $this->qb_id = $attributes['qb_id'] ?? '';
        $this->qb_sync_token = $attributes['qb_sync_token'] ?? '';
        $this->qb_immutable = $attributes['qb_immutable'] ?? false;
        $this->qb_void_failed = $attributes['qb_void_failed'] ?? false;
        $this->qb_void_error = $attributes['qb_void_error'] ?? '';
        $this->last_synced_at = $attributes['last_synced_at'] ?? '';
    }
    /**
     * Get the name of the caster class to use when casting from / to this cast target.
     *
     * @param  array<string, mixed>  $arguments
     */
    public static function castUsing(array $arguments): string
    {
        return PaymentSyncCast::class;
    }

    public static function fromArray(array $data): self
    {
        return new self($data);
    }
}
